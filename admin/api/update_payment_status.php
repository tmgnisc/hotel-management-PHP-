<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function recalculateRegularCustomerAmounts($conn, $customerId) {
    $customerId = intval($customerId);
    if ($customerId <= 0) return;

    $sumStmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN transaction_type IN ('credit', 'order') THEN amount ELSE 0 END), 0) AS total_generated,
        COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) AS total_paid
        FROM customer_transactions
        WHERE customer_id = ?");

    if (!$sumStmt) return;
    $sumStmt->bind_param("i", $customerId);
    $sumStmt->execute();
    $summary = $sumStmt->get_result()->fetch_assoc();
    $sumStmt->close();

    $totalGenerated = isset($summary['total_generated']) ? (float)$summary['total_generated'] : 0;
    $totalPaid = isset($summary['total_paid']) ? (float)$summary['total_paid'] : 0;
    $dueAmount = max(0, $totalGenerated - $totalPaid);
    $totalAmount = max(0, $totalGenerated);

    $updateStmt = $conn->prepare("UPDATE regular_customers SET due_amount = ?, total_amount = ? WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("ddi", $dueAmount, $totalAmount, $customerId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

function syncOrderTransactionForRegularCustomer($conn, $orderId, $regularCustomerId, $orderAmount, $orderNumber, $paymentStatus = 'pending') {
    $orderId = intval($orderId);
    $regularCustomerId = intval($regularCustomerId);
    $paymentStatus = strtolower(trim((string)$paymentStatus));
    $isPaid = ($paymentStatus === 'paid');

    if ($orderId <= 0) return;

    $existingStmt = $conn->prepare("SELECT id, customer_id FROM customer_transactions WHERE order_id = ? AND transaction_type = 'order' ORDER BY id DESC LIMIT 1");
    if (!$existingStmt) return;

    $existingStmt->bind_param("i", $orderId);
    $existingStmt->execute();
    $existingTx = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    $existingPaymentStmt = $conn->prepare("SELECT id, customer_id FROM customer_transactions WHERE order_id = ? AND transaction_type = 'payment' AND reference_number LIKE 'AUTO_PAY_%' ORDER BY id DESC LIMIT 1");
    $existingPaymentTx = null;
    if ($existingPaymentStmt) {
        $existingPaymentStmt->bind_param("i", $orderId);
        $existingPaymentStmt->execute();
        $existingPaymentTx = $existingPaymentStmt->get_result()->fetch_assoc();
        $existingPaymentStmt->close();
    }

    $oldCustomerId = intval($existingTx['customer_id'] ?? 0);
    $oldPaymentCustomerId = intval($existingPaymentTx['customer_id'] ?? 0);
    $affectedCustomerIds = [];

    if ($regularCustomerId > 0) {
        $txDescription = 'Auto order transaction for Order #' . ($orderNumber ?: str_pad((string)$orderId, 8, '0', STR_PAD_LEFT));
        $txReference = $orderNumber ?: str_pad((string)$orderId, 8, '0', STR_PAD_LEFT);
        $autoPaymentDescription = 'Auto payment for Order #' . ($orderNumber ?: str_pad((string)$orderId, 8, '0', STR_PAD_LEFT));
        $autoPaymentReference = 'AUTO_PAY_' . ($orderNumber ?: str_pad((string)$orderId, 8, '0', STR_PAD_LEFT));
        $orderAmount = (float)$orderAmount;

        if ($existingTx) {
            $updateTxStmt = $conn->prepare("UPDATE customer_transactions SET customer_id = ?, amount = ?, description = ?, reference_number = ? WHERE id = ?");
            if ($updateTxStmt) {
                $updateTxStmt->bind_param("idssi", $regularCustomerId, $orderAmount, $txDescription, $txReference, $existingTx['id']);
                $updateTxStmt->execute();
                $updateTxStmt->close();
            }
        } else {
            $insertTxStmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, transaction_type, amount, description, order_id, reference_number) VALUES (?, 'order', ?, ?, ?, ?)");
            if ($insertTxStmt) {
                $insertTxStmt->bind_param("idsis", $regularCustomerId, $orderAmount, $txDescription, $orderId, $txReference);
                $insertTxStmt->execute();
                $insertTxStmt->close();
            }
        }

        if ($isPaid) {
            if ($existingPaymentTx) {
                $updatePaymentTxStmt = $conn->prepare("UPDATE customer_transactions SET customer_id = ?, amount = ?, description = ?, reference_number = ? WHERE id = ?");
                if ($updatePaymentTxStmt) {
                    $updatePaymentTxStmt->bind_param("idssi", $regularCustomerId, $orderAmount, $autoPaymentDescription, $autoPaymentReference, $existingPaymentTx['id']);
                    $updatePaymentTxStmt->execute();
                    $updatePaymentTxStmt->close();
                }
            } else {
                $insertPaymentTxStmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, transaction_type, amount, description, order_id, reference_number) VALUES (?, 'payment', ?, ?, ?, ?)");
                if ($insertPaymentTxStmt) {
                    $insertPaymentTxStmt->bind_param("idsis", $regularCustomerId, $orderAmount, $autoPaymentDescription, $orderId, $autoPaymentReference);
                    $insertPaymentTxStmt->execute();
                    $insertPaymentTxStmt->close();
                }
            }
        } else {
            if ($existingPaymentTx) {
                $deletePaymentTxStmt = $conn->prepare("DELETE FROM customer_transactions WHERE id = ?");
                if ($deletePaymentTxStmt) {
                    $deletePaymentTxStmt->bind_param("i", $existingPaymentTx['id']);
                    $deletePaymentTxStmt->execute();
                    $deletePaymentTxStmt->close();
                }
            }
        }

        if ($oldCustomerId > 0) $affectedCustomerIds[] = $oldCustomerId;
        if ($oldPaymentCustomerId > 0) $affectedCustomerIds[] = $oldPaymentCustomerId;
        $affectedCustomerIds[] = $regularCustomerId;
    } else {
        if ($existingTx) {
            $deleteTxStmt = $conn->prepare("DELETE FROM customer_transactions WHERE id = ?");
            if ($deleteTxStmt) {
                $deleteTxStmt->bind_param("i", $existingTx['id']);
                $deleteTxStmt->execute();
                $deleteTxStmt->close();
            }
            if ($oldCustomerId > 0) $affectedCustomerIds[] = $oldCustomerId;
        }

        if ($existingPaymentTx) {
            $deletePaymentTxStmt = $conn->prepare("DELETE FROM customer_transactions WHERE id = ?");
            if ($deletePaymentTxStmt) {
                $deletePaymentTxStmt->bind_param("i", $existingPaymentTx['id']);
                $deletePaymentTxStmt->execute();
                $deletePaymentTxStmt->close();
            }
            if ($oldPaymentCustomerId > 0) $affectedCustomerIds[] = $oldPaymentCustomerId;
        }
    }

    foreach (array_unique($affectedCustomerIds) as $affectedCustomerId) {
        recalculateRegularCustomerAmounts($conn, $affectedCustomerId);
    }
}

// Accept POST
$input = $_POST;
$id = intval($input['id'] ?? 0);
$payment_status = trim(strtolower($input['payment_status'] ?? 'pending'));
$payment_method = trim($input['payment_method'] ?? 'cash');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order id']);
    exit;
}

$conn = getDBConnection();

// Fetch existing order to know regular_customer_id, total_amount, order_number
$orderStmt = $conn->prepare("SELECT regular_customer_id, total_amount, order_number FROM order_details WHERE id = ?");
$orderStmt->bind_param("i", $id);
$orderStmt->execute();
$orderRow = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

$regular_customer_id = intval($orderRow['regular_customer_id'] ?? 0);
$total_amount = (float)($orderRow['total_amount'] ?? 0);
$order_number = $orderRow['order_number'] ?? '';

// Determine paid_date: if marking paid use now
$paid_date_val = ($payment_status === 'paid') ? date('Y-m-d H:i:s') : null;

// Order date: prefer client-sent order_date if provided (so admin/local browser time can be used)
$order_date_val = null;
if (!empty($input['order_date'])) {
    // Try to normalize incoming date formats (ISO or MySQL)
    $raw = trim($input['order_date']);
    try {
        // Replace T with space if needed
        $try = str_replace('T', ' ', $raw);
        $dt = new DateTime($try);
        $order_date_val = $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $order_date_val = null;
    }
} else {
    // If client didn't send a date and we're marking paid, default to server now
    if ($payment_status === 'paid') {
        $order_date_val = date('Y-m-d H:i:s');
    }
}

$updateStmt = $conn->prepare("UPDATE order_details SET payment_status = ?, payment_method = ?, paid_date = ?, order_date = COALESCE(?, order_date) WHERE id = ?");
if (!$updateStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    $conn->close();
    exit;
}
$updateStmt->bind_param("ssssi", $payment_status, $payment_method, $paid_date_val, $order_date_val, $id);
$ok = $updateStmt->execute();
$err = $updateStmt->error;
$updateStmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $err]);
    $conn->close();
    exit;
}

// Sync customer transactions for regular customer if linked
syncOrderTransactionForRegularCustomer($conn, $id, $regular_customer_id, $total_amount, $order_number, $payment_status);

$conn->close();

echo json_encode(['success' => true, 'message' => 'Payment status updated', 'paid_date' => $paid_date_val, 'order_date' => $order_date_val]);
exit;
