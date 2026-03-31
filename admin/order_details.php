<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$message = '';
$messageType = 'success';

function formatOrderSerial($orderNumber, $orderId = null) {
    if (!empty($orderNumber) && preg_match('/^\d{8}$/', (string)$orderNumber)) {
        return (string)$orderNumber;
    }

    if (!empty($orderNumber) && preg_match('/\d+/', (string)$orderNumber, $matches)) {
        return str_pad(substr($matches[0], -8), 8, '0', STR_PAD_LEFT);
    }

    if (!empty($orderId)) {
        return str_pad((string)intval($orderId), 8, '0', STR_PAD_LEFT);
    }

    return '00000000';
}

function normalizeOrderDateTimeInput($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return date('Y-m-d H:i:s');
    }

    // Convert HTML datetime-local format (YYYY-MM-DDTHH:MM[:SS]) to MySQL DATETIME
    $normalized = str_replace('T', ' ', $value);

    // If seconds are missing, append :00
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $normalized)) {
        $normalized .= ':00';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return date('Y-m-d H:i:s');
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function recalculateRegularCustomerAmounts($conn, $customerId) {
    $customerId = intval($customerId);
    if ($customerId <= 0) {
        return;
    }

    $sumStmt = $conn->prepare("SELECT
        COALESCE(SUM(CASE WHEN transaction_type IN ('credit', 'order') THEN amount ELSE 0 END), 0) AS total_generated,
        COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) AS total_paid
        FROM customer_transactions
        WHERE customer_id = ?");

    if (!$sumStmt) {
        return;
    }

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

    if ($orderId <= 0) {
        return;
    }

    $existingStmt = $conn->prepare("SELECT id, customer_id FROM customer_transactions WHERE order_id = ? AND transaction_type = 'order' ORDER BY id DESC LIMIT 1");
    if (!$existingStmt) {
        return;
    }

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
        $txDescription = 'Auto order transaction for Order #' . formatOrderSerial($orderNumber, $orderId);
        $txReference = formatOrderSerial($orderNumber, $orderId);
    $autoPaymentDescription = 'Auto payment for Order #' . formatOrderSerial($orderNumber, $orderId);
    $autoPaymentReference = 'AUTO_PAY_' . formatOrderSerial($orderNumber, $orderId);
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

        if ($oldCustomerId > 0) {
            $affectedCustomerIds[] = $oldCustomerId;
        }
        if ($oldPaymentCustomerId > 0) {
            $affectedCustomerIds[] = $oldPaymentCustomerId;
        }
        $affectedCustomerIds[] = $regularCustomerId;
    } else {
        if ($existingTx) {
            $deleteTxStmt = $conn->prepare("DELETE FROM customer_transactions WHERE id = ?");
            if ($deleteTxStmt) {
                $deleteTxStmt->bind_param("i", $existingTx['id']);
                $deleteTxStmt->execute();
                $deleteTxStmt->close();
            }
            if ($oldCustomerId > 0) {
                $affectedCustomerIds[] = $oldCustomerId;
            }
        }

        if ($existingPaymentTx) {
            $deletePaymentTxStmt = $conn->prepare("DELETE FROM customer_transactions WHERE id = ?");
            if ($deletePaymentTxStmt) {
                $deletePaymentTxStmt->bind_param("i", $existingPaymentTx['id']);
                $deletePaymentTxStmt->execute();
                $deletePaymentTxStmt->close();
            }
            if ($oldPaymentCustomerId > 0) {
                $affectedCustomerIds[] = $oldPaymentCustomerId;
            }
        }
    }

    foreach (array_unique($affectedCustomerIds) as $affectedCustomerId) {
        recalculateRegularCustomerAmounts($conn, $affectedCustomerId);
    }
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create') {
            $order_number = 'TMP' . date('YmdHis') . rand(100, 999);
            $table_id = !empty($_POST['table_id']) ? intval($_POST['table_id']) : null;
            $regular_customer_id = !empty($_POST['regular_customer_id']) ? intval($_POST['regular_customer_id']) : null;
            // Auto-set to current date/time if not provided
            $order_date = normalizeOrderDateTimeInput($_POST['order_date'] ?? '');
            $order_status = trim($_POST['order_status'] ?? 'pending');
            $payment_status = trim($_POST['payment_status'] ?? 'pending');
            $payment_method = trim($_POST['payment_method'] ?? 'cash');
            $customer_given_amount = floatval($_POST['paid_amount'] ?? ($_POST['customer_given_amount'] ?? 0));
            $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
            $discount_amount = floatval($_POST['discount_amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            // Process food items
            $items = [];
            $subtotal = 0;
            
            if (isset($_POST['food_items']) && is_array($_POST['food_items'])) {
                foreach ($_POST['food_items'] as $food_id) {
                    $qty = floatval($_POST['qty_' . $food_id] ?? 0);
                    if ($qty > 0) {
                        // Get food details
                        $foodStmt = $conn->prepare("SELECT food_name, price FROM menu WHERE id = ?");
                        $foodStmt->bind_param("i", $food_id);
                        $foodStmt->execute();
                        $foodResult = $foodStmt->get_result();
                        if ($foodRow = $foodResult->fetch_assoc()) {
                            $basePrice = (float)$foodRow['price'];
                            $itemTotal = $basePrice * $qty;
                            $items[] = [
                                'food_id'   => $food_id,
                                'food_name' => $foodRow['food_name'],
                                'price'     => $basePrice,
                                'qty'       => $qty,
                                'total'     => $itemTotal
                            ];
                            $subtotal += $itemTotal;
                        }
                        $foodStmt->close();
                    }
                }
            }
            
            if (count($items) == 0) {
                $message = "Please select at least one food item!";
                $messageType = 'error';
            } else {
                $itemsJson = json_encode($items);
                
                // Calculate discount
                $discountByPercentage = ($subtotal * $discount_percentage) / 100;
                $totalDiscount = $discountByPercentage + $discount_amount;
                $total_amount = max(0, $subtotal - $totalDiscount); // Ensure total doesn't go negative
                if (strtolower($payment_status) === 'paid' && $customer_given_amount <= 0) {
                    $customer_given_amount = $total_amount;
                }
                $return_amount = max(0, $customer_given_amount - $total_amount);
                
                $stmt = $conn->prepare("INSERT INTO order_details (order_number, table_id, regular_customer_id, order_date, items, subtotal, discount_percentage, discount_amount, total_amount, customer_given_amount, return_amount, order_status, payment_status, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("siissddddddssss", $order_number, $table_id, $regular_customer_id, $order_date, $itemsJson, $subtotal, $discount_percentage, $discount_amount, $total_amount, $customer_given_amount, $return_amount, $order_status, $payment_status, $payment_method, $notes);
                    if ($stmt->execute()) {
                        $newOrderId = $conn->insert_id;
                        if ($newOrderId > 0) {
                            $serialOrderNumber = str_pad((string)$newOrderId, 8, '0', STR_PAD_LEFT);
                            $updateOrderNoStmt = $conn->prepare("UPDATE order_details SET order_number = ? WHERE id = ?");
                            if ($updateOrderNoStmt) {
                                $updateOrderNoStmt->bind_param("si", $serialOrderNumber, $newOrderId);
                                $updateOrderNoStmt->execute();
                                $updateOrderNoStmt->close();
                                                $order_number = $serialOrderNumber;
                            }

                            // If payment was marked paid on creation, set paid_date
                            $paid_date = (strtolower($payment_status) === 'paid') ? date('Y-m-d H:i:s') : null;
                            if ($paid_date !== null) {
                                $setPaidStmt = $conn->prepare("UPDATE order_details SET paid_date = ? WHERE id = ?");
                                if ($setPaidStmt) {
                                    $setPaidStmt->bind_param("si", $paid_date, $newOrderId);
                                    $setPaidStmt->execute();
                                    $setPaidStmt->close();
                                }
                            }

                            syncOrderTransactionForRegularCustomer($conn, $newOrderId, $regular_customer_id, $total_amount, $order_number, $payment_status);
                        }
                        $shouldPromptPrint = (strtolower($order_status) === 'completed' && strtolower($payment_status) === 'paid' && $newOrderId > 0);
                        $message = "Order created successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        $redirectUrl = 'order_details.php?msg=' . urlencode($message) . '&type=' . $messageType;
                        if ($shouldPromptPrint) {
                            $redirectUrl .= '&print_bill_prompt=1&bill_id=' . intval($newOrderId);
                        }
                        header('Location: ' . $redirectUrl);
                        exit;
                    } else {
                        $message = "Error executing query: " . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = "Error preparing statement: " . $conn->error;
                    $messageType = 'error';
                }
            }
        } elseif ($_POST['action'] == 'update') {
            $id = intval($_POST['id'] ?? 0);
            $table_id = !empty($_POST['table_id']) ? intval($_POST['table_id']) : null;
            $regular_customer_id = !empty($_POST['regular_customer_id']) ? intval($_POST['regular_customer_id']) : null;
            // Auto-set to current date/time if not provided
            $order_date = normalizeOrderDateTimeInput($_POST['order_date'] ?? '');
            $order_status = trim($_POST['order_status'] ?? 'pending');
            $payment_status = trim($_POST['payment_status'] ?? 'pending');
            $payment_method = trim($_POST['payment_method'] ?? 'cash');
            $customer_given_amount = floatval($_POST['paid_amount'] ?? ($_POST['customer_given_amount'] ?? 0));
            $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
            $discount_amount = floatval($_POST['discount_amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            $isOrderStatusOnlyUpdate = false;
            $isLinkedStatusOnlyUpdate = false;
            $canProceedFullUpdate = false;

            if ($id <= 0) {
                $message = "Invalid order ID!";
                $messageType = 'error';
            } else {
                $existingOrderStmt = $conn->prepare("SELECT order_status, payment_status, regular_customer_id, total_amount, order_number FROM order_details WHERE id = ?");
                if ($existingOrderStmt) {
                    $existingOrderStmt->bind_param("i", $id);
                    $existingOrderStmt->execute();
                    $existingOrderResult = $existingOrderStmt->get_result();
                    $existingOrder = $existingOrderResult ? $existingOrderResult->fetch_assoc() : null;
                    $existingOrderStmt->close();

                    if (!$existingOrder) {
                        $message = "Order not found!";
                        $messageType = 'error';
                    } else {
                        $existingOrderStatus = strtolower($existingOrder['order_status'] ?? '');
                        $existingPaymentStatus = strtolower($existingOrder['payment_status'] ?? '');
                        $existingRegularCustomerId = intval($existingOrder['regular_customer_id'] ?? 0);
                        $existingTotalAmount = (float)($existingOrder['total_amount'] ?? 0);
                        $existingOrderNumber = $existingOrder['order_number'] ?? '';

                        if ($existingOrderStatus === 'completed') {
                            $allowedPaymentStatuses = ['pending', 'paid', 'cancelled'];
                            $allowedPaymentMethods = ['cash', 'card', 'online'];

                            if (!in_array($payment_status, $allowedPaymentStatuses, true)) {
                                $payment_status = 'pending';
                            }
                            if (!in_array($payment_method, $allowedPaymentMethods, true)) {
                                $payment_method = 'cash';
                            }

                            // Update payment status/method, paid_date, and order_date in completed-flow edits
                            $paid_date_val = (strtolower($payment_status) === 'paid') ? date('Y-m-d H:i:s') : null;
                            $completedPaymentStmt = $conn->prepare("UPDATE order_details SET payment_status = ?, payment_method = ?, paid_date = ?, order_date = ? WHERE id = ?");
                            if ($completedPaymentStmt) {
                                $completedPaymentStmt->bind_param("ssssi", $payment_status, $payment_method, $paid_date_val, $order_date, $id);
                                if ($completedPaymentStmt->execute()) {
                                    syncOrderTransactionForRegularCustomer($conn, $id, $existingRegularCustomerId, $existingTotalAmount, $existingOrderNumber, $payment_status);

                                    $message = "Completed order details updated successfully!";
                                    $messageType = 'success';
                                    $completedPaymentStmt->close();
                                    $conn->close();
                                    header('Location: order_details.php?msg=' . urlencode($message) . '&type=' . $messageType);
                                    exit;
                                } else {
                                    $message = "Error updating completed order payment details: " . $completedPaymentStmt->error;
                                    $messageType = 'error';
                                }
                                $completedPaymentStmt->close();
                            } else {
                                $message = "Error preparing completed order payment update: " . $conn->error;
                                $messageType = 'error';
                            }
                        } else {
                            $canProceedFullUpdate = true;
                        }
                    }
                } else {
                    $message = "Error preparing order fetch: " . $conn->error;
                    $messageType = 'error';
                }
            }

            if ($messageType === 'error' && !empty($message)) {
                // Stop further full update processing when validation or order-status-only update prep failed
            } elseif ($canProceedFullUpdate) {
            
            // Process food items
            $items = [];
            $subtotal = 0;
            
            if (isset($_POST['food_items']) && is_array($_POST['food_items'])) {
                foreach ($_POST['food_items'] as $food_id) {
                    $qty = floatval($_POST['qty_' . $food_id] ?? 0);
                    if ($qty > 0) {
                        // Get food details
                        $foodStmt = $conn->prepare("SELECT food_name, price FROM menu WHERE id = ?");
                        $foodStmt->bind_param("i", $food_id);
                        $foodStmt->execute();
                        $foodResult = $foodStmt->get_result();
                        if ($foodRow = $foodResult->fetch_assoc()) {
                            $basePrice = (float)$foodRow['price'];
                            $itemTotal = $basePrice * $qty;
                            $items[] = [
                                'food_id'   => $food_id,
                                'food_name' => $foodRow['food_name'],
                                'price'     => $basePrice,
                                'qty'       => $qty,
                                'total'     => $itemTotal
                            ];
                            $subtotal += $itemTotal;
                        }
                        $foodStmt->close();
                    }
                }
            }
            
            if (count($items) == 0 || $id <= 0) {
                $message = "Please select at least one food item!";
                $messageType = 'error';
            } else {
                $itemsJson = json_encode($items);
                
                // Calculate discount
                $discountByPercentage = ($subtotal * $discount_percentage) / 100;
                $totalDiscount = $discountByPercentage + $discount_amount;
                $total_amount = max(0, $subtotal - $totalDiscount); // Ensure total doesn't go negative
                if (strtolower($payment_status) === 'paid' && $customer_given_amount <= 0) {
                    $customer_given_amount = $total_amount;
                }
                $return_amount = max(0, $customer_given_amount - $total_amount);
                
                // Set paid_date based on new payment_status
                $paid_date_val = (strtolower($payment_status) === 'paid') ? date('Y-m-d H:i:s') : null;

                $stmt = $conn->prepare("UPDATE order_details SET table_id=?, regular_customer_id=?, order_date=?, items=?, subtotal=?, discount_percentage=?, discount_amount=?, total_amount=?, customer_given_amount=?, return_amount=?, order_status=?, payment_status=?, payment_method=?, paid_date = ?, notes=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("iissddddddsssssi", $table_id, $regular_customer_id, $order_date, $itemsJson, $subtotal, $discount_percentage, $discount_amount, $total_amount, $customer_given_amount, $return_amount, $order_status, $payment_status, $payment_method, $paid_date_val, $notes, $id);
                    if ($stmt->execute()) {
                        $orderNoStmt = $conn->prepare("SELECT order_number FROM order_details WHERE id = ?");
                        $currentOrderNumber = '';
                        if ($orderNoStmt) {
                            $orderNoStmt->bind_param("i", $id);
                            $orderNoStmt->execute();
                            $orderNoRow = $orderNoStmt->get_result()->fetch_assoc();
                            $currentOrderNumber = $orderNoRow['order_number'] ?? '';
                            $orderNoStmt->close();
                        }

                        syncOrderTransactionForRegularCustomer($conn, $id, $regular_customer_id, $total_amount, $currentOrderNumber, $payment_status);

                        $message = "Order updated successfully!";
                        $messageType = 'success';
                        $stmt->close();
                        $conn->close();
                        header('Location: order_details.php?msg=' . urlencode($message) . '&type=' . $messageType);
                        exit;
                    } else {
                        $message = "Error executing update: " . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = "Error preparing update statement: " . $conn->error;
                    $messageType = 'error';
                }
            }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $checkDeleteStmt = $conn->prepare("SELECT order_status, payment_status FROM order_details WHERE id = ?");
                if ($checkDeleteStmt) {
                    $checkDeleteStmt->bind_param("i", $id);
                    $checkDeleteStmt->execute();
                    $checkDeleteResult = $checkDeleteStmt->get_result();
                    $deleteRow = $checkDeleteResult ? $checkDeleteResult->fetch_assoc() : null;
                    $checkDeleteStmt->close();

                    if (!$deleteRow) {
                        $message = "Order not found!";
                        $messageType = 'error';
                    } else {
                        $isPaidCompleted = (strtolower($deleteRow['order_status'] ?? '') === 'completed' && strtolower($deleteRow['payment_status'] ?? '') === 'paid');
                        if ($isPaidCompleted) {
                            $message = "Completed and paid orders cannot be deleted!";
                            $messageType = 'error';
                        } else {
                            syncOrderTransactionForRegularCustomer($conn, $id, 0, 0, '', 'pending');

                            $stmt = $conn->prepare("DELETE FROM order_details WHERE id=?");
                            if ($stmt) {
                                $stmt->bind_param("i", $id);
                                if ($stmt->execute()) {
                                    $message = "Order deleted successfully!";
                                    $messageType = 'success';
                                    $stmt->close();
                                    $conn->close();
                                    header('Location: order_details.php?msg=' . urlencode($message) . '&type=' . $messageType);
                                    exit;
                                } else {
                                    $message = "Error deleting: " . $stmt->error;
                                    $messageType = 'error';
                                }
                                $stmt->close();
                            } else {
                                $message = "Error preparing delete statement: " . $conn->error;
                                $messageType = 'error';
                            }
                        }
                    }
                } else {
                    $message = "Error preparing delete check: " . $conn->error;
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
    $messageType = $_GET['type'] ?? 'success';
}

// View mode: 'today' (default), 'yesterday', or 'all'
$requestedView = $_GET['view'] ?? 'today';
$allowedViews = ['today', 'yesterday', 'all'];
$viewMode = in_array($requestedView, $allowedViews, true) ? $requestedView : 'today';

$pendingDateFilters = [
    'today' => "AND DATE(o.order_date) = CURDATE()",
    'yesterday' => "AND DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
    'all' => ''
];
$dateWhereClause = $pendingDateFilters[$viewMode] ?? $pendingDateFilters['today'];

$allDateFilters = [
    'today' => "WHERE DATE(o.order_date) = CURDATE()",
    'yesterday' => "WHERE DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
    'all' => ''
];
$allDateFilter = $allDateFilters[$viewMode] ?? $allDateFilters['today'];

$viewLabelMap = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'all' => 'All Time'
];
$viewLabel = $viewLabelMap[$viewMode] ?? 'Today';

// Fetch pending orders separately
// Show orders as pending if: order_status is pending OR (order_status is completed AND payment_status is pending)
$pendingOrders = $conn->query("
    SELECT o.*, t.table_number, rc.customer_name AS reg_customer_name, rc.phone AS reg_customer_phone
    FROM order_details o 
    LEFT JOIN tables t ON o.table_id = t.id 
    LEFT JOIN regular_customers rc ON o.regular_customer_id = rc.id
    WHERE (o.order_status = 'pending' OR (o.order_status = 'completed' AND o.payment_status = 'pending'))
    $dateWhereClause
    ORDER BY o.id DESC
");
if (!$pendingOrders) {
    die("Error fetching pending orders: " . $conn->error);
}

// Fetch all orders with table information
$orders = $conn->query("
    SELECT o.*, t.table_number, rc.customer_name AS reg_customer_name, rc.phone AS reg_customer_phone
    FROM order_details o 
    LEFT JOIN tables t ON o.table_id = t.id 
    LEFT JOIN regular_customers rc ON o.regular_customer_id = rc.id
    $allDateFilter
    ORDER BY o.id DESC
");
if (!$orders) {
    die("Error fetching orders: " . $conn->error);
}

// Fetch tables for dropdown
$tables = $conn->query("SELECT id, table_number FROM tables ORDER BY table_number");
if (!$tables) {
    die("Error fetching tables: " . $conn->error);
}

// Fetch regular customers for dropdown
$regularCustomers = $conn->query("SELECT id, customer_name, phone, discount_percentage, discount_amount FROM regular_customers WHERE status = 'active' ORDER BY customer_name");
if (!$regularCustomers) {
    die("Error fetching regular customers: " . $conn->error);
}

// Fetch menu items for dropdown
$menuItems = $conn->query("SELECT id, food_name, price FROM menu WHERE status = 'available' ORDER BY food_name");
if (!$menuItems) {
    die("Error fetching menu items: " . $conn->error);
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        var openModal, closeModal, deleteRecord, calculateTotal, addFoodItem, removeFoodItem, initFoodSearch;
        var selectedFoods = [];
        var menuData = {};
        var regularCustomersData = {};
        
        (function() {
            // Load menu data
            <?php
            $menuItems->data_seek(0);
            while ($menu = $menuItems->fetch_assoc()): ?>
                menuData[<?php echo $menu['id']; ?>] = {
                    id: <?php echo $menu['id']; ?>,
                    name: <?php echo json_encode($menu['food_name']); ?>,
                    price: <?php echo $menu['price']; ?>
                };
            <?php endwhile; ?>
            
            // Load regular customers data
            <?php
            $regularCustomers->data_seek(0);
            while ($customer = $regularCustomers->fetch_assoc()): ?>
                regularCustomersData[<?php echo $customer['id']; ?>] = {
                    id: <?php echo $customer['id']; ?>,
                    name: <?php echo json_encode($customer['customer_name']); ?>,
                    phone: <?php echo json_encode($customer['phone']); ?>,
                    discount_percentage: <?php echo $customer['discount_percentage'] ?? 0; ?>,
                    discount_amount: <?php echo $customer['discount_amount'] ?? 0; ?>
                };
            <?php endwhile; ?>

            // Helper to set order_date input to client's current local time (and optionally start a live update)
            function setOrderDateNow(startInterval) {
                startInterval = !!startInterval;
                var od = document.getElementById('order_date');
                if (!od) return;

                function pad(n){return n<10? '0'+n : n}
                var now = new Date();
                var localValue = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                // datetime-local sometimes ignores seconds; set a safe length
                od.value = localValue.slice(0, 19);

                // If requested, keep updating every second
                if (startInterval) {
                    if (!od._nowInterval) {
                        od._nowInterval = setInterval(function(){
                            try {
                                var dnow = new Date();
                                var v = dnow.getFullYear() + '-' + pad(dnow.getMonth()+1) + '-' + pad(dnow.getDate()) + 'T' + pad(dnow.getHours()) + ':' + pad(dnow.getMinutes()) + ':' + pad(dnow.getSeconds());
                                od.value = v.slice(0,19);
                            } catch (e) { }
                        }, 1000);
                    }
                }
            }

            function setOrderStatusOnlyEditMode(isOrderStatusOnly, allowPaymentStatus, noticeText) {
                var orderStatusOnlyInput = document.getElementById('order_status_only_update');
                var orderStatusOnlyNotice = document.getElementById('orderStatusOnlyNotice');
                allowPaymentStatus = !!allowPaymentStatus;
                noticeText = noticeText || 'This order is pending. Only <strong>Order Status</strong> can be edited.';

                if (orderStatusOnlyInput) {
                    orderStatusOnlyInput.value = isOrderStatusOnly ? '1' : '0';
                }

                if (orderStatusOnlyNotice) {
                    if (isOrderStatusOnly) {
                        orderStatusOnlyNotice.innerHTML = noticeText;
                        orderStatusOnlyNotice.classList.remove('hidden');
                    } else {
                        orderStatusOnlyNotice.classList.add('hidden');
                    }
                }

                var fieldIds = [
                    'table_id',
                    'regular_customer_search',
                    'foodSearch',
                    'discount_percentage',
                    'discount_amount',
                    'paid_amount',
                    'return_amount',
                    'payment_status',
                    'payment_method',
                    'notes'
                ];

                fieldIds.forEach(function(id) {
                    var field = document.getElementById(id);
                    if (field) {
                        field.disabled = isOrderStatusOnly;
                    }
                });

                var orderStatusField = document.getElementById('order_status');
                if (orderStatusField) {
                    orderStatusField.disabled = false;
                }

                var paymentStatusField = document.getElementById('payment_status');
                if (paymentStatusField) {
                    paymentStatusField.disabled = isOrderStatusOnly ? !allowPaymentStatus : false;
                }

                var foodItemsContainer = document.getElementById('foodItemsContainer');
                if (foodItemsContainer) {
                    var itemControls = foodItemsContainer.querySelectorAll('input, button, select, textarea');
                    itemControls.forEach(function(ctrl) {
                        ctrl.disabled = isOrderStatusOnly;
                    });
                    foodItemsContainer.classList.toggle('opacity-60', isOrderStatusOnly);
                }
            }

            function setCompletedPaymentEditMode(isCompletedPaymentOnly, noticeText) {
                var orderStatusOnlyNotice = document.getElementById('orderStatusOnlyNotice');
                noticeText = noticeText || 'This order is completed. Only <strong>Payment Status</strong>, <strong>Payment Method</strong>, and <strong>Order Date &amp; Time</strong> can be edited.<br><strong>Action Required:</strong> Please update your order date and time.';

                if (orderStatusOnlyNotice) {
                    if (isCompletedPaymentOnly) {
                        orderStatusOnlyNotice.innerHTML = noticeText;
                        orderStatusOnlyNotice.classList.remove('hidden');
                    } else {
                        orderStatusOnlyNotice.classList.add('hidden');
                    }
                }

                var fieldIds = [
                    'table_id',
                    'regular_customer_search',
                    'foodSearch',
                    'discount_percentage',
                    'discount_amount',
                    'paid_amount',
                    'return_amount',
                    'order_status',
                    'notes'
                ];

                fieldIds.forEach(function(id) {
                    var field = document.getElementById(id);
                    if (field) {
                        field.disabled = isCompletedPaymentOnly;
                    }
                });

                var paymentStatusField = document.getElementById('payment_status');
                if (paymentStatusField) {
                    paymentStatusField.disabled = false;
                }

                var paymentMethodField = document.getElementById('payment_method');
                if (paymentMethodField) {
                    paymentMethodField.disabled = false;
                }

                var orderDateField = document.getElementById('order_date');
                if (orderDateField) {
                    orderDateField.disabled = false;
                    if (isCompletedPaymentOnly) {
                        orderDateField.readOnly = false;
                    }
                }

                var foodItemsContainer = document.getElementById('foodItemsContainer');
                if (foodItemsContainer) {
                    var itemControls = foodItemsContainer.querySelectorAll('input, button, select, textarea');
                    itemControls.forEach(function(ctrl) {
                        ctrl.disabled = isCompletedPaymentOnly;
                    });
                    foodItemsContainer.classList.toggle('opacity-60', isCompletedPaymentOnly);
                }
            }
            
            openModal = function(action, data) {
                var modal = document.getElementById('modal');
                var form = document.getElementById('orderForm');
                var orderDateField = document.getElementById('order_date');
                var paymentStatusFieldEl = document.getElementById('payment_status');
                var orderStatusFieldEl = document.getElementById('order_status');

                // Single source of truth for order_date editability
                function syncOrderDateEditability() {
                    var od = orderDateField || document.getElementById('order_date');
                    if (!od) return;

                    // Keep Order Date & Time editable for both pending/normal and completed flows
                    // (Do not lock by payment status)
                    od.disabled = false;
                    od.readOnly = false;
                }
                
                if (!modal || !form) {
                    console.error('Modal or form not found');
                    return;
                }

                var isPaidStatus = String((data && data.payment_status) || '').toLowerCase() === 'paid';
                var isCompletedStatus = String((data && data.order_status) || '').toLowerCase() === 'completed';
                
                // Set form action: 'create' for new, 'update' for edit
                var formAction = action === 'edit' ? 'update' : 'create';
                document.getElementById('formAction').value = formAction;
                document.getElementById('modalTitle').textContent = action === 'create' ? 'Add New Order' : 'Edit Order';
                setOrderStatusOnlyEditMode(false, false, '');
                setCompletedPaymentEditMode(false, '');
                
                // Clear food items container
                var foodItemsContainer = document.getElementById('foodItemsContainer');
                foodItemsContainer.innerHTML = '';
                selectedFoods = [];

                if (action === 'create') {
                    // Auto-fill order_date with client's local datetime and keep it updated in real-time
                    var odField = document.getElementById('order_date');
                    if (odField) {
                        // Use the shared helper which also supports starting a live update interval
                        setOrderDateNow(true);
                        odField.disabled = false;
                        odField.readOnly = false;
                    }
                }

                if (action === 'edit' && data) {
                    // For edit, keep order_date editable in all statuses
                    var odField = document.getElementById('order_date');
                    if (odField) {
                        odField.disabled = false;
                        odField.readOnly = false;
                    }
                    document.getElementById('formId').value = data.id || '';
                    document.getElementById('table_id').value = data.table_id || '';
                    document.getElementById('regular_customer_id').value = data.regular_customer_id || '';
                    
                    // Set customer search input if customer is selected

                    if (data.regular_customer_id && regularCustomersData[data.regular_customer_id]) {
                        var customer = regularCustomersData[data.regular_customer_id];
                        document.getElementById('regular_customer_search').value = customer.name + ' - ' + customer.phone;
                    } else {
                        document.getElementById('regular_customer_search').value = '';
                    }
                    
                    // Format datetime for input
                    var orderDate = data.order_date ? String(data.order_date).replace(' ', 'T').slice(0, 16) : '';
                    document.getElementById('order_date').value = orderDate;
                    
                    document.getElementById('order_status').value = data.order_status || 'pending';
                    document.getElementById('payment_status').value = data.payment_status || 'pending';
                    document.getElementById('payment_method').value = data.payment_method || 'cash';
                    document.getElementById('paid_amount').value = data.paid_amount || data.customer_given_amount || '0';
                    document.getElementById('return_amount').value = data.return_amount || '0';
                    document.getElementById('discount_percentage').value = data.discount_percentage || '0';
                    document.getElementById('discount_amount').value = data.discount_amount || '0';
                    document.getElementById('notes').value = data.notes || '';

                    var currentOrderStatus = String(data.order_status || '').toLowerCase();
                    if (currentOrderStatus === 'completed') {
                        setCompletedPaymentEditMode(true, 'This order is completed. Only <strong>Payment Status</strong>, <strong>Payment Method</strong>, and <strong>Order Date &amp; Time</strong> can be edited.<br><strong>Action Required:</strong> Please update your order date and time.');
                        document.getElementById('modalTitle').textContent = 'Edit Completed Order';
                    } else {
                        setOrderStatusOnlyEditMode(false, false, '');
                        setCompletedPaymentEditMode(false, '');
                    }
                    
                    // Load existing items
                    if (data.items) {
                        try {
                            var items = typeof data.items === 'string' ? JSON.parse(data.items) : data.items;
                            items.forEach(function(item, index) {
                                var foodId = item.food_id || item.id;
                                var qty = item.qty || 1;
                                var portion = item.portion || 'full';
                                var uniqueId = foodId + '_' + index + '_' + Date.now();
                                addFoodItem(foodId, qty, uniqueId, portion);
                            });
                        } catch(e) {
                            console.error('Error parsing items:', e);
                        }
                    }
                } else {
                    form.reset();
                    document.getElementById('formId').value = '';
                    document.getElementById('regular_customer_id').value = '';
                    document.getElementById('regular_customer_search').value = '';
                    document.getElementById('discount_percentage').value = '0';
                    document.getElementById('discount_amount').value = '0';
                    document.getElementById('paid_amount').value = '0';
                    document.getElementById('return_amount').value = '0';
                    // Always set current date/time for new orders
                    var now = new Date();
                    var year = now.getFullYear();
                    var month = String(now.getMonth() + 1).padStart(2, '0');
                    var day = String(now.getDate()).padStart(2, '0');
                    var hours = String(now.getHours()).padStart(2, '0');
                    var minutes = String(now.getMinutes()).padStart(2, '0');
                    document.getElementById('order_date').value = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
                }

                if (paymentStatusFieldEl && !paymentStatusFieldEl._orderDateSyncAttached) {
                    paymentStatusFieldEl.addEventListener('change', syncOrderDateEditability);
                    paymentStatusFieldEl._orderDateSyncAttached = true;
                }
                if (orderStatusFieldEl && !orderStatusFieldEl._orderDateSyncAttached) {
                    orderStatusFieldEl.addEventListener('change', syncOrderDateEditability);
                    orderStatusFieldEl._orderDateSyncAttached = true;
                }
                syncOrderDateEditability();

                // Ensure order form uses client local time if order_date left empty on submit
                var orderForm = document.getElementById('orderForm');
                if (orderForm && !orderForm._localSubmitHandlerAttached) {
                    orderForm.addEventListener('submit', function(ev){
                        var od = document.getElementById('order_date');
                        if (od && (!od.value || String(od.value).trim() === '')) {
                            var now = new Date();
                            function pad(n){return n<10? '0'+n : n}
                            var localMysql = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                            od.value = localMysql;
                        }
                    });
                    orderForm._localSubmitHandlerAttached = true;
                }
                // Attach listener to payment_status change for real-time update
                var paymentStatusField = document.getElementById('payment_status');
                if (paymentStatusField) {
                    paymentStatusField.onchange = function(e) {
                        var newStatus = String(paymentStatusField.value || '').toLowerCase();
                        syncOrderDateEditability();

                        var orderId = document.getElementById('formId').value || '';
                        if (!orderId) return;

                        // Only call endpoint if status actually changed to paid or from paid (to clear)
                        var payload = new FormData();
                        payload.append('id', orderId);
                        payload.append('payment_status', newStatus);
                        var pm = document.getElementById('payment_method');
                        payload.append('payment_method', pm ? pm.value : 'cash');
                        // Append client local order_date so server can use local time if desired
                        try {
                            var now = new Date();
                            function pad(n){return n<10? '0'+n : n}
                            var localMysql = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                            payload.append('order_date', localMysql);
                        } catch (e) {
                            // ignore
                        }

                        fetch('api/update_payment_status.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: payload
                        }).then(function(resp) {
                            return resp.json();
                        }).then(function(json) {
                            if (json && json.success) {
                                // Update badge in table row
                                var row = document.querySelector('tr[data-order-id="' + orderId + '"]');
                                if (row) {
                                    var badge = row.querySelector('td:nth-child(6) span');
                                    if (badge) {
                                        // Update text
                                        badge.textContent = newStatus;
                                        // Update classes
                                        badge.className = 'px-3 py-1 rounded-full text-xs font-semibold capitalize ' + (newStatus === 'paid' ? 'bg-green-100 text-green-800' : (newStatus === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'));
                                    }

                                    // Update payment method text (second small span)
                                    var methodSpan = row.querySelector('td:nth-child(6) .text-xs');
                                    if (methodSpan) {
                                        methodSpan.textContent = (pm ? pm.value : 'N/A');
                                    }

                                    // Update order date cell (4th column) if server returned order_date
                                    if (json.order_date) {
                                        var dateCell = row.querySelector('td:nth-child(4)');
                                        if (dateCell) {
                                            try {
                                                var d = new Date(json.order_date.replace(' ', 'T'));
                                                var opts = { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };
                                                dateCell.textContent = d.toLocaleString('en-US', opts).replace(',', '');
                                            } catch (e) {
                                                // Fallback to raw string
                                                dateCell.textContent = json.order_date;
                                            }
                                        }
                                    }
                                }
                                // Optionally show tiny notice
                                var notice = document.getElementById('ajaxNotice');
                                if (!notice) {
                                    notice = document.createElement('div');
                                    notice.id = 'ajaxNotice';
                                    notice.className = 'fixed top-20 right-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-3 rounded-lg';
                                    notice.textContent = 'Payment status updated';
                                    document.body.appendChild(notice);
                                    setTimeout(function(){ notice.remove(); }, 2500);
                                }
                            } else {
                                alert('Unable to update payment status: ' + (json.message || 'unknown'));
                            }
                        }).catch(function(err){
                            console.error(err);
                            alert('Network error while updating payment status');
                        });
                    };
                }
                
                calculateTotal();
                modal.classList.remove('hidden');
                
                // Initialize food search after modal opens
                setTimeout(function() {
                    if (typeof initFoodSearch === 'function') {
                        initFoodSearch();
                    }
                    // Clear search input
                    var searchInput = document.getElementById('foodSearch');
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    var searchResults = document.getElementById('foodSearchResults');
                    if (searchResults) {
                        searchResults.classList.add('hidden');
                    }
                }, 100);
            };
            
            closeModal = function() {
                var modal = document.getElementById('modal');
                if (modal) {
                    modal.classList.add('hidden');
                }
                // Clear any running auto-now interval for order_date
                try {
                    var od = document.getElementById('order_date');
                    if (od && od._nowInterval) {
                        clearInterval(od._nowInterval);
                        od._nowInterval = null;
                    }
                } catch (e) {
                    // ignore
                }
            };
            
            deleteRecord = function(id) {
                if (confirm('Are you sure you want to delete this order?')) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            };
            
            addFoodItem = function(foodId, qty, existingFoodId, portion) {
                if (!menuData[foodId]) return;
                
                var food = menuData[foodId];
                var foodIdToUse = existingFoodId || foodId;

                // Determine initial qty: support legacy portion (half=0.5, full=1) or direct float qty
                var initQty = 1;
                if (qty && parseFloat(qty) > 0) {
                    initQty = parseFloat(qty);
                } else if (portion === 'half') {
                    initQty = 0.5;
                }
                
                if (selectedFoods.indexOf(foodIdToUse) !== -1) return;
                selectedFoods.push(foodIdToUse);
                
                var container = document.getElementById('foodItemsContainer');
                var itemDiv = document.createElement('div');
                itemDiv.className = 'food-item flex flex-wrap items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200';
                itemDiv.id = 'foodItem_' + foodIdToUse;
                itemDiv.innerHTML = `
                    <input type="hidden" name="food_items[]" value="${foodId}">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900">${food.name}</div>
                        <div class="text-xs text-gray-500">Rs ${food.price.toFixed(2)} / unit</div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <label class="text-sm font-medium text-gray-700">Qty:</label>
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="stepQty('${foodIdToUse}', -0.5)" class="w-7 h-7 rounded bg-gray-200 hover:bg-gray-300 font-bold text-gray-700 flex items-center justify-center text-sm">−</button>
                            <input type="number"
                                name="qty_${foodId}"
                                id="qty_input_${foodIdToUse}"
                                value="${initQty}"
                                min="0.5"
                                step="0.5"
                                class="w-20 px-2 py-1 border border-gray-300 rounded text-center focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm"
                                oninput="calculateTotal()"
                                onchange="calculateTotal()"
                            >
                            <button type="button" onclick="stepQty('${foodIdToUse}', 0.5)" class="w-7 h-7 rounded bg-gray-200 hover:bg-gray-300 font-bold text-gray-700 flex items-center justify-center text-sm">+</button>
                        </div>
                        <div class="flex gap-1">
                            <button type="button" onclick="setQty('${foodIdToUse}', 0.5)"  class="px-2 py-1 text-xs rounded bg-orange-100 hover:bg-orange-200 text-orange-800 font-semibold">½</button>
                            <button type="button" onclick="setQty('${foodIdToUse}', 1)"    class="px-2 py-1 text-xs rounded bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-semibold">1</button>
                            <button type="button" onclick="setQty('${foodIdToUse}', 1.5)"  class="px-2 py-1 text-xs rounded bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-semibold">1½</button>
                            <button type="button" onclick="setQty('${foodIdToUse}', 2)"    class="px-2 py-1 text-xs rounded bg-indigo-100 hover:bg-indigo-200 text-indigo-800 font-semibold">2</button>
                        </div>
                        <div id="item_total_${foodIdToUse}" class="text-sm font-bold text-green-700 min-w-[70px] text-right">Rs ${(food.price * initQty).toFixed(2)}</div>
                    </div>
                    <button type="button" onclick="removeFoodItem('${foodIdToUse}')" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors text-sm">
                        ✕
                    </button>
                `;
                container.appendChild(itemDiv);
                calculateTotal();
            };
            
            removeFoodItem = function(foodId) {
                var itemDiv = document.getElementById('foodItem_' + foodId);
                if (itemDiv) {
                    itemDiv.remove();
                    selectedFoods = selectedFoods.filter(id => id !== foodId);
                    calculateTotal();
                }
            };

            // Step qty by delta (±0.5) using the unique foodIdToUse
            window.stepQty = function(foodIdToUse, delta) {
                var input = document.getElementById('qty_input_' + foodIdToUse);
                if (!input) return;
                var newVal = Math.max(0.5, Math.round((parseFloat(input.value) + delta) * 10) / 10);
                input.value = newVal;
                calculateTotal();
            };

            // Set qty to exact value using the unique foodIdToUse
            window.setQty = function(foodIdToUse, val) {
                var input = document.getElementById('qty_input_' + foodIdToUse);
                if (!input) return;
                input.value = val;
                calculateTotal();
            };
            
            calculateTotal = function() {
                var subtotal = 0;
                var foodItems = document.querySelectorAll('.food-item');
                var billItems = [];
                
                foodItems.forEach(function(item) {
                    var qtyInput = item.querySelector('input[type="number"]');
                    if (!qtyInput) return;
                    
                    var foodId = qtyInput.name.replace('qty_', '');
                    var qty = parseFloat(qtyInput.value) || 0;
                    
                    if (menuData[foodId] && qty > 0) {
                        var basePrice = menuData[foodId].price;
                        var itemTotal = basePrice * qty;
                        subtotal += itemTotal;

                        // Update per-item total display
                        var foodIdToUse = qtyInput.id.replace('qty_input_', '');
                        var itemTotalEl = document.getElementById('item_total_' + foodIdToUse);
                        if (itemTotalEl) itemTotalEl.textContent = 'Rs ' + itemTotal.toFixed(2);
                        
                        billItems.push({
                            name: menuData[foodId].name,
                            qty: qty,
                            unitPrice: basePrice,
                            total: itemTotal
                        });
                    }
                });
                
                // Calculate discount
                var discountPercentage = parseFloat(document.getElementById('discount_percentage').value) || 0;
                var discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
                var discountByPercentage = (subtotal * discountPercentage) / 100;
                var totalDiscount = discountByPercentage + discountAmount;
                var total = Math.max(0, subtotal - totalDiscount);
                
                // Update numeric fields
                document.getElementById('subtotal').value = subtotal.toFixed(2);
                document.getElementById('total_amount').value = total.toFixed(2);

                // Calculate return amount from paid amount
                var paidAmountInput = document.getElementById('paid_amount');
                var returnAmountInput = document.getElementById('return_amount');
                if (paidAmountInput && returnAmountInput) {
                    var paidAmount = parseFloat(paidAmountInput.value) || 0;
                    var returnAmount = Math.max(0, paidAmount - total);
                    returnAmountInput.value = returnAmount.toFixed(2);
                }
                
                // Update visual bill summary
                var billSummaryItemsEl = document.getElementById('billSummaryItems');
                var billSummaryEmptyEl = document.getElementById('billSummaryEmpty');
                var billGrandTotalEl = document.getElementById('billGrandTotal');
                
                if (billSummaryItemsEl && billGrandTotalEl) {
                    billSummaryItemsEl.innerHTML = '';
                    
                    if (billItems.length === 0) {
                        if (billSummaryEmptyEl) {
                            billSummaryEmptyEl.classList.remove('hidden');
                        }
                        billGrandTotalEl.textContent = 'Rs 0.00';
                    } else {
                        if (billSummaryEmptyEl) {
                            billSummaryEmptyEl.classList.add('hidden');
                        }
                        
                        billItems.forEach(function(item) {
                            var row = document.createElement('div');
                            row.className = 'flex items-center justify-between py-1 text-sm';
                            
                            var label = item.name + ' × ' + item.qty;
                            
                            row.innerHTML = `
                                <div class="text-gray-800">${label}</div>
                                <div class="font-semibold text-gray-900">Rs ${item.total.toFixed(2)}</div>
                            `;
                            
                            billSummaryItemsEl.appendChild(row);
                        });
                        
                        billGrandTotalEl.textContent = 'Rs ' + total.toFixed(2);
                    }
                }
            };
            
            // Food search functionality
            var foodSearchInput = null;
            var foodSearchResults = null;
            
            initFoodSearch = function() {
                foodSearchInput = document.getElementById('foodSearch');
                foodSearchResults = document.getElementById('foodSearchResults');
                
                if (!foodSearchInput || !foodSearchResults) return;

                var highlightedFoodIndex = -1;

                function getFoodItems() {
                    return foodSearchResults.querySelectorAll('.search-result-item');
                }

                function setFoodHighlight(index) {
                    var items = getFoodItems();
                    items.forEach(function(el, i) {
                        if (i === index) {
                            el.classList.add('bg-indigo-100');
                            el.scrollIntoView({ block: 'nearest' });
                        } else {
                            el.classList.remove('bg-indigo-100');
                        }
                    });
                    highlightedFoodIndex = index;
                }

                function selectFoodItem(food) {
                    addFoodItem(food.id, 1, null, 'full');
                    foodSearchInput.value = '';
                    foodSearchResults.classList.add('hidden');
                    highlightedFoodIndex = -1;
                    // Keep focus on search so user can keep adding items
                    foodSearchInput.focus();
                }
                
                foodSearchInput.addEventListener('input', function() {
                    highlightedFoodIndex = -1;
                    var searchTerm = this.value.toLowerCase().trim();
                    
                    if (searchTerm.length === 0) {
                        foodSearchResults.classList.add('hidden');
                        return;
                    }
                    
                    var filteredItems = [];
                    for (var foodId in menuData) {
                        var food = menuData[foodId];
                        if (food.name.toLowerCase().includes(searchTerm)) {
                            filteredItems.push(food);
                        }
                    }
                    
                    if (filteredItems.length > 0) {
                        foodSearchResults.innerHTML = '';
                        filteredItems.forEach(function(food) {
                            var itemDiv = document.createElement('div');
                            itemDiv.className = 'search-result-item px-4 py-3 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 transition-colors flex justify-between items-center';
                            itemDiv.innerHTML = '<div><div class="font-medium text-gray-900">' + food.name + '</div><div class="text-sm text-gray-600">Rs ' + food.price.toFixed(2) + '</div></div><div class="text-indigo-600 font-semibold text-sm">↵ Add</div>';
                            itemDiv.addEventListener('mousedown', function(e) {
                                e.preventDefault(); // prevent blur before click
                                selectFoodItem(food);
                            });
                            foodSearchResults.appendChild(itemDiv);
                        });
                        foodSearchResults.classList.remove('hidden');
                    } else {
                        foodSearchResults.innerHTML = '<div class="px-4 py-3 text-gray-500 text-center text-sm">No items found</div>';
                        foodSearchResults.classList.remove('hidden');
                    }
                });

                foodSearchInput.addEventListener('keydown', function(e) {
                    var items = getFoodItems();
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (foodSearchResults.classList.contains('hidden') && this.value.trim()) {
                            this.dispatchEvent(new Event('input'));
                        }
                        var next = Math.min(highlightedFoodIndex + 1, items.length - 1);
                        setFoodHighlight(next);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        var prev = Math.max(highlightedFoodIndex - 1, 0);
                        setFoodHighlight(prev);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (highlightedFoodIndex >= 0 && items[highlightedFoodIndex]) {
                            items[highlightedFoodIndex].dispatchEvent(new MouseEvent('mousedown'));
                        } else if (items.length === 1) {
                            // If only one result, auto-select on Enter
                            items[0].dispatchEvent(new MouseEvent('mousedown'));
                        }
                    } else if (e.key === 'Escape') {
                        foodSearchResults.classList.add('hidden');
                        highlightedFoodIndex = -1;
                    }
                });
                
                document.addEventListener('click', function(e) {
                    if (foodSearchInput && foodSearchResults && 
                        !foodSearchInput.contains(e.target) && 
                        !foodSearchResults.contains(e.target)) {
                        foodSearchResults.classList.add('hidden');
                        highlightedFoodIndex = -1;
                    }
                });
            };
            
            // Function to apply customer discount when regular customer is selected            }
            
            // Function to apply customer discount when regular customer is selected
            function applyCustomerDiscount(customerId) {
                if (customerId && regularCustomersData[customerId]) {
                    var customer = regularCustomersData[customerId];
                    var discountPercentage = parseFloat(customer.discount_percentage) || 0;
                    var discountAmount = parseFloat(customer.discount_amount) || 0;
                    
                    // Only auto-fill if discount fields are currently 0 (to avoid overwriting manual entries)
                    var currentPercentage = parseFloat(document.getElementById('discount_percentage').value) || 0;
                    var currentAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
                    
                    if (currentPercentage === 0 && currentAmount === 0) {
                        document.getElementById('discount_percentage').value = discountPercentage;
                        document.getElementById('discount_amount').value = discountAmount;
                    } else {
                        // Ask user if they want to apply customer discount
                        if (confirm('Do you want to apply the regular customer discount? This will replace your current discount values.')) {
                            document.getElementById('discount_percentage').value = discountPercentage;
                            document.getElementById('discount_amount').value = discountAmount;
                        }
                    }
                    calculateTotal();
                }
            }
            
            // Regular customer search functionality
            var regularCustomerSearchInput = null;
            var regularCustomerSearchResults = null;
            
            function initRegularCustomerSearch() {
                regularCustomerSearchInput = document.getElementById('regular_customer_search');
                regularCustomerSearchResults = document.getElementById('regularCustomerSearchResults');
                
                if (!regularCustomerSearchInput || !regularCustomerSearchResults) return;

                var highlightedCustomerIndex = -1;

                function getCustomerItems() {
                    return regularCustomerSearchResults.querySelectorAll('.customer-result-item');
                }

                function setCustomerHighlight(index) {
                    var items = getCustomerItems();
                    items.forEach(function(el, i) {
                        if (i === index) {
                            el.classList.add('bg-indigo-100');
                            el.scrollIntoView({ block: 'nearest' });
                        } else {
                            el.classList.remove('bg-indigo-100');
                        }
                    });
                    highlightedCustomerIndex = index;
                }

                function selectCustomer(customer) {
                    document.getElementById('regular_customer_id').value = customer.id;
                    regularCustomerSearchInput.value = customer.name + ' - ' + customer.phone;
                    regularCustomerSearchResults.classList.add('hidden');
                    highlightedCustomerIndex = -1;
                    applyCustomerDiscount(customer.id);
                }
                
                regularCustomerSearchInput.addEventListener('input', function() {
                    highlightedCustomerIndex = -1;
                    var query = this.value.trim().toLowerCase();
                    
                    if (query.length === 0) {
                        regularCustomerSearchResults.classList.add('hidden');
                        // Clear selection if user clears the field
                        document.getElementById('regular_customer_id').value = '';
                        return;
                    }
                    
                    var results = [];
                    for (var customerId in regularCustomersData) {
                        var customer = regularCustomersData[customerId];
                        if (customer.name.toLowerCase().includes(query) || customer.phone.toLowerCase().includes(query)) {
                            results.push(customer);
                        }
                    }
                    
                    regularCustomerSearchResults.innerHTML = '';
                    if (results.length === 0) {
                        regularCustomerSearchResults.innerHTML = '<div class="p-3 text-sm text-gray-500 text-center">No customers found</div>';
                    } else {
                        results.forEach(function(customer) {
                            var discountText = '';
                            if (customer.discount_percentage > 0 || customer.discount_amount > 0) {
                                discountText = '<div class="text-xs text-indigo-600 mt-1">Discount: ' +
                                    (customer.discount_percentage > 0 ? customer.discount_percentage + '%' : '') +
                                    (customer.discount_percentage > 0 && customer.discount_amount > 0 ? ' + ' : '') +
                                    (customer.discount_amount > 0 ? 'Rs ' + parseFloat(customer.discount_amount).toFixed(2) : '') +
                                    '</div>';
                            }
                            var itemDiv = document.createElement('div');
                            itemDiv.className = 'customer-result-item p-3 hover:bg-indigo-50 cursor-pointer border-b border-gray-200 flex justify-between items-start';
                            itemDiv.innerHTML = '<div><div class="font-medium text-gray-900">' + customer.name + '</div><div class="text-xs text-gray-600">' + customer.phone + '</div>' + discountText + '</div><div class="text-indigo-600 text-sm font-semibold ml-2">↵ Select</div>';
                            itemDiv.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                selectCustomer(customer);
                            });
                            regularCustomerSearchResults.appendChild(itemDiv);
                        });
                    }
                    regularCustomerSearchResults.classList.remove('hidden');
                });

                regularCustomerSearchInput.addEventListener('keydown', function(e) {
                    var items = getCustomerItems();
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (regularCustomerSearchResults.classList.contains('hidden') && this.value.trim()) {
                            this.dispatchEvent(new Event('input'));
                        }
                        setCustomerHighlight(Math.min(highlightedCustomerIndex + 1, items.length - 1));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setCustomerHighlight(Math.max(highlightedCustomerIndex - 1, 0));
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (highlightedCustomerIndex >= 0 && items[highlightedCustomerIndex]) {
                            items[highlightedCustomerIndex].dispatchEvent(new MouseEvent('mousedown'));
                        } else if (items.length === 1) {
                            items[0].dispatchEvent(new MouseEvent('mousedown'));
                        }
                    } else if (e.key === 'Escape') {
                        regularCustomerSearchResults.classList.add('hidden');
                        highlightedCustomerIndex = -1;
                    }
                });
                
                document.addEventListener('click', function(e) {
                    if (!regularCustomerSearchInput.contains(e.target) && !regularCustomerSearchResults.contains(e.target)) {
                        regularCustomerSearchResults.classList.add('hidden');
                        highlightedCustomerIndex = -1;
                    }
                });
            }
            
            // Make functions globally accessible
            window.applyCustomerDiscount = applyCustomerDiscount;

            function handleAutoBillPrintPrompt() {
                var urlParams = new URLSearchParams(window.location.search);
                var shouldPrompt = urlParams.get('print_bill_prompt') === '1';
                var billId = parseInt(urlParams.get('bill_id') || '0', 10);

                if (!shouldPrompt || billId <= 0) {
                    return;
                }

                var shouldPrint = confirm('Order is completed and paid. Print this bill now?');
                if (shouldPrint) {
                    window.open('order_bill.php?id=' + billId, '_blank');
                }

                var cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('print_bill_prompt');
                cleanUrl.searchParams.delete('bill_id');
                window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search);
            }
            
            // Initialize search when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        initFoodSearch();
                        initRegularCustomerSearch();
                        handleAutoBillPrintPrompt();
                        // Bind "Now" button for order_date if present
                        var nowBtn = document.getElementById('order_date_now_btn');
                        if (nowBtn) {
                            nowBtn.addEventListener('click', function() {
                                try { setOrderDateNow(true); } catch(e) { }
                            });
                        }
                    }, 200);
                });
            } else {
                setTimeout(function() {
                    initFoodSearch();
                    initRegularCustomerSearch();
                    handleAutoBillPrintPrompt();
                    // Bind "Now" button for order_date if present
                    var nowBtn = document.getElementById('order_date_now_btn');
                    if (nowBtn) {
                        nowBtn.addEventListener('click', function() {
                            try { setOrderDateNow(true); } catch(e) { }
                        });
                    }
                }, 200);
            }
        })();
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <?php include 'includes/nav.php'; ?>

        <main class="md:ml-64 p-4 md:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Order Details Management</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        <?php if ($viewMode === 'today'): ?>
                            📅 Showing <strong>today's</strong> orders
                        <?php elseif ($viewMode === 'yesterday'): ?>
                            🕘 Showing <strong>yesterday's</strong> orders
                        <?php else: ?>
                            📋 Showing <strong>all</strong> orders
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <?php if ($viewMode !== 'yesterday'): ?>
                        <a href="?view=yesterday" class="px-5 py-2.5 bg-amber-600 text-white rounded-lg font-semibold hover:bg-amber-700 transition-all shadow-md text-sm flex items-center gap-2">
                             Yesterday Orders
                        </a>
                    <?php else: ?>
                        <span class="px-5 py-2.5 bg-amber-100 text-amber-700 rounded-lg font-semibold shadow-sm text-sm flex items-center gap-2 cursor-default">
                             Yesterday Orders
                        </span>
                    <?php endif; ?>
                    <?php if ($viewMode !== 'today'): ?>
                        <a href="?view=today" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-all shadow-md text-sm flex items-center gap-2">
                             Today Orders
                        </a>
                    <?php else: ?>
                        <span class="px-5 py-2.5 bg-blue-100 text-blue-700 rounded-lg font-semibold shadow-sm text-sm flex items-center gap-2 cursor-default">
                             Today Orders
                        </span>
                    <?php endif; ?>

                    <?php if ($viewMode !== 'all'): ?>
                        <a href="?view=all" class="px-5 py-2.5 bg-gray-700 text-white rounded-lg font-semibold hover:bg-gray-800 transition-all shadow-md text-sm flex items-center gap-2">
                             View All Orders
                        </a>
                    <?php else: ?>
                        <span class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-lg font-semibold shadow-sm text-sm flex items-center gap-2 cursor-default">
                             View All Orders
                        </span>
                    <?php endif; ?>
                    <button onclick="openModal('create')" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all shadow-md hover:shadow-lg">
                        + Add New Order
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 <?php echo $messageType === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-700' : 'bg-red-50 border-l-4 border-red-500 text-red-700'; ?> rounded-lg animate-slide-up">
                    <?php echo htmlspecialchars($message); ?>
                    <button onclick="this.parentElement.remove()" class="float-right text-gray-500 hover:text-gray-700">×</button>
                </div>
            <?php endif; ?>

            <!-- Pending Orders Section -->
            <div class="mb-8">
                <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></span>
                    Pending Orders
                    <span class="text-sm font-normal text-gray-400">(<?php echo htmlspecialchars($viewLabel); ?>)</span>
                </h3>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Customer / Order No</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Table</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $pendingOrders->data_seek(0);
                                $hasPendingOrders = false;
                                while ($row = $pendingOrders->fetch_assoc()): 
                                    $hasPendingOrders = true;
                                ?>
                                <tr data-order-id="<?php echo $row['id']; ?>" class="hover:bg-yellow-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php if (!empty($row['reg_customer_name'])): ?>
                                            <div class="flex items-center gap-1">
                                                <span class="text-indigo-600">👤</span>
                                                <span class="font-semibold text-indigo-700"><?php echo htmlspecialchars($row['reg_customer_name']); ?></span>
                                            </div>
                                            <div class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($row['reg_customer_phone'] ?? ''); ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-700"><?php echo htmlspecialchars(formatOrderSerial($row['order_number'] ?? '', $row['id'] ?? null)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['table_number'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php echo $row['order_date'] ? date('M d, Y H:i', strtotime($row['order_date'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                        Rs <?php echo number_format($row['total_amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $paymentStatusClass = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'paid' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                        $paymentStatus = $row['payment_status'];
                                        $paymentClass = $paymentStatusClass[$paymentStatus] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <div class="flex flex-col gap-1">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $paymentClass; ?>">
                                                <?php echo $paymentStatus; ?>
                                            </span>
                                            <span class="text-xs text-gray-600 capitalize">
                                                <?php echo $row['payment_method'] ?? 'N/A'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex flex-wrap gap-2">
                                        <?php $isPaid = (($row['payment_status'] ?? '') === 'paid'); ?>
                                        <?php $isRegularCustomerLinked = !empty($row['regular_customer_id']); ?>
                                        <?php $isCompleted = (($row['order_status'] ?? '') === 'completed'); ?>
                                        <?php $isLinkedEditLocked = false; ?>
                                        <?php $isDeleteLocked = ($isPaid && $isCompleted); ?>
                                        <?php if ($isLinkedEditLocked): ?>
                                            <button type="button" disabled class="px-3 py-1 bg-gray-300 text-gray-600 rounded-md cursor-not-allowed opacity-70 text-xs" title="Paid non-completed orders cannot be edited">
                                                Edit
                                            </button>
                                        <?php else: ?>
                                            <button onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors text-xs">
                                                Edit
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($isDeleteLocked): ?>
                                            <button type="button" disabled class="px-3 py-1 bg-gray-300 text-gray-600 rounded-md cursor-not-allowed opacity-70 text-xs" title="Completed and paid orders cannot be deleted">
                                                Delete
                                            </button>
                                        <?php else: ?>
                                            <button onclick="deleteRecord(<?php echo $row['id']; ?>)" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors text-xs">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                        <a href="order_bill.php?id=<?php echo $row['id']; ?>" target="_blank" class="px-3 py-1 bg-emerald-500 text-white rounded-md hover:bg-emerald-600 transition-colors text-xs inline-flex items-center gap-1 <?php echo $isPaid ? 'ring-2 ring-emerald-300' : ''; ?>">
                                            <?php echo $isPaid ? '🧾 Print Bill' : '🧾 Bill'; ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if (!$hasPendingOrders): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                        No pending orders at the moment.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- All Orders Section -->
            <div>
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    All Orders
                    <span class="text-sm font-normal text-gray-400">(<?php echo htmlspecialchars($viewLabel); ?>)</span>
                </h3>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Customer / Order No</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Table</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Date</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Total</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $orders->fetch_assoc()): ?>
                            <tr data-order-id="<?php echo $row['id']; ?>" class="hover:bg-indigo-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $row['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php if (!empty($row['reg_customer_name'])): ?>
                                        <div class="flex items-center gap-1">
                                            <span class="text-indigo-600">👤</span>
                                            <span class="font-semibold text-indigo-700"><?php echo htmlspecialchars($row['reg_customer_name']); ?></span>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars($row['reg_customer_phone'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <span class="text-gray-700"><?php echo htmlspecialchars(formatOrderSerial($row['order_number'] ?? '', $row['id'] ?? null)); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['table_number'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo $row['order_date'] ? date('M d, Y H:i', strtotime($row['order_date'])) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    Rs <?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $orderStatusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800'
                                    ];
                                    $orderStatus = $row['order_status'] ?? 'pending';
                                    $orderClass = $orderStatusClass[$orderStatus] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $orderClass; ?>">
                                        <?php echo $orderStatus; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $paymentStatusClass = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $paymentStatus = $row['payment_status'];
                                    $paymentClass = $paymentStatusClass[$paymentStatus] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold capitalize <?php echo $paymentClass; ?>">
                                            <?php echo $paymentStatus; ?>
                                        </span>
                                        <span class="text-xs text-gray-600 capitalize">
                                            <?php echo $row['payment_method'] ?? 'N/A'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex flex-wrap gap-2">
                                    <?php $isPaid = (($row['payment_status'] ?? '') === 'paid'); ?>
                                    <?php $isRegularCustomerLinked = !empty($row['regular_customer_id']); ?>
                                    <?php $isCompleted = (($row['order_status'] ?? '') === 'completed'); ?>
                                    <?php $isLinkedEditLocked = false; ?>
                                    <?php $isDeleteLocked = ($isPaid && $isCompleted); ?>
                                    <?php if ($isLinkedEditLocked): ?>
                                        <button type="button" disabled class="px-3 py-1 bg-gray-300 text-gray-600 rounded-md cursor-not-allowed opacity-70 text-xs" title="Paid non-completed orders cannot be edited">
                                            Edit
                                        </button>
                                    <?php else: ?>
                                        <button onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-3 py-1 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors text-xs">
                                            Edit
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($isDeleteLocked): ?>
                                        <button type="button" disabled class="px-3 py-1 bg-gray-300 text-gray-600 rounded-md cursor-not-allowed opacity-70 text-xs" title="Completed and paid orders cannot be deleted">
                                            Delete
                                        </button>
                                    <?php else: ?>
                                        <button onclick="deleteRecord(<?php echo $row['id']; ?>)" class="px-3 py-1 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors text-xs">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                    <a href="order_bill.php?id=<?php echo $row['id']; ?>" target="_blank" class="px-3 py-1 bg-emerald-500 text-white rounded-md hover:bg-emerald-600 transition-colors text-xs inline-flex items-center gap-1 <?php echo $isPaid ? 'ring-2 ring-emerald-300' : ''; ?>">
                                        <?php echo $isPaid ? '🧾 Print Bill' : '🧾 Bill'; ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 md:p-8 animate-slide-up max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-900">Add New Order</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <form method="POST" id="orderForm" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                <input type="hidden" name="order_status_only_update" id="order_status_only_update" value="0">

                <div id="orderStatusOnlyNotice" class="hidden p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                    This order is pending. Only <strong>Order Status</strong> can be edited.
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="table_id" class="block text-sm font-semibold text-gray-700 mb-2">Table Number</label>
                        <select id="table_id" name="table_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <option value="">Select Table (Optional)</option>
                            <?php
                            $tables->data_seek(0);
                            while ($table = $tables->fetch_assoc()): ?>
                                <option value="<?php echo $table['id']; ?>"><?php echo htmlspecialchars($table['table_number']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="relative">
                        <label for="regular_customer_search" class="block text-sm font-semibold text-gray-700 mb-2">
                            Regular Customer <span class="text-gray-500 text-xs">(Optional - Auto-fills discount if selected)</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="regular_customer_search" 
                                placeholder="Type name or phone… ↑↓ navigate · Enter select · Esc close" 
                                class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                                autocomplete="off"
                            >
                            <input type="hidden" id="regular_customer_id" name="regular_customer_id" value="">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <div id="regularCustomerSearchResults" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <!-- Search results will appear here -->
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Search and select a regular customer to auto-fill their discount, but you can still modify it manually below.</p>
                    </div>
                </div>
                
                <div>
                    <label for="order_date" class="block text-sm font-semibold text-gray-700 mb-2">Order Date & Time <span class="text-gray-500 text-xs">(Auto-filled with current date/time)</span></label>
                    <div class="flex items-center gap-2">
                        <input type="datetime-local" id="order_date" name="order_date" required class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                        <button type="button" id="order_date_now_btn" class="px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">Now</button>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Food Items *</label>
                    <div class="relative mb-3">
                        <div class="relative">
                            <input 
                                type="text" 
                                id="foodSearch" 
                                placeholder="Type food name… ↑↓ navigate · Enter add · Esc close" 
                                class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                                autocomplete="off"
                            >
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <div id="foodSearchResults" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <!-- Search results will appear here -->
                        </div>
                    </div>
                    <div id="foodItemsContainer" class="space-y-2 min-h-[100px] p-3 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-sm text-gray-500 text-center py-4">No food items selected. Search and select items to add them.</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="subtotal" class="block text-sm font-semibold text-gray-700 mb-2">Subtotal</label>
                        <input type="number" id="subtotal" name="subtotal" step="0.01" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 outline-none" value="0.00">
                    </div>
                    
                    <div>
                        <label for="total_amount" class="block text-sm font-semibold text-gray-700 mb-2">Total Amount</label>
                        <input type="number" id="total_amount" name="total_amount" step="0.01" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 outline-none" value="0.00">
                    </div>
                </div>

                <div class="border border-gray-200 rounded-lg mt-4">
                    <div class="px-4 py-2 bg-gray-100 border-b border-gray-200 rounded-t-lg">
                        <h4 class="text-sm font-semibold text-gray-800">Current Bill</h4>
                    </div>
                    <div class="p-4 space-y-2">
                        <div id="billSummaryEmpty" class="text-sm text-gray-500 text-center">
                            No items added yet. Add food items to see the bill details here.
                        </div>
                        <div id="billSummaryItems" class="space-y-1"></div>
                        <div class="border-t border-dashed border-gray-300 mt-2 pt-2 flex items-center justify-between text-sm font-semibold text-gray-900">
                            <span>Grand Total</span>
                            <span id="billGrandTotal">Rs 0.00</span>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">Discount (Available for All Customers)</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="discount_percentage" class="block text-sm font-semibold text-gray-700 mb-2">
                                Discount Percentage <span class="text-gray-500 text-xs">(0-100%)</span>
                            </label>
                            <input 
                                type="number" 
                                id="discount_percentage" 
                                name="discount_percentage" 
                                min="0" 
                                max="100" 
                                step="0.01" 
                                value="0" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" 
                                onchange="calculateTotal()" 
                                placeholder="Enter discount percentage (e.g., 10 for 10%)"
                            >
                            <p class="text-xs text-gray-500 mt-1">Apply percentage discount on subtotal</p>
                        </div>
                        
                        <div>
                            <label for="discount_amount" class="block text-sm font-semibold text-gray-700 mb-2">
                                Discount Amount <span class="text-gray-500 text-xs">(Fixed amount in Rs)</span>
                            </label>
                            <input 
                                type="number" 
                                id="discount_amount" 
                                name="discount_amount" 
                                min="0" 
                                step="0.01" 
                                value="0" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" 
                                onchange="calculateTotal()" 
                                placeholder="Enter fixed discount amount (e.g., 50 for Rs 50)"
                            >
                            <p class="text-xs text-gray-500 mt-1">Apply fixed amount discount</p>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-800">
                            <strong>Note:</strong> You can use both percentage and fixed amount discounts together. 
                            If a regular customer is selected, their discount will be auto-filled, but you can modify it manually for any customer.
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="order_status" class="block text-sm font-semibold text-gray-700 mb-2">Order Status *</label>
                        <select id="order_status" name="order_status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="payment_status" class="block text-sm font-semibold text-gray-700 mb-2">Payment Status *</label>
                        <select id="payment_status" name="payment_status" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="payment_method" class="block text-sm font-semibold text-gray-700 mb-2">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="paid_amount" class="block text-sm font-semibold text-gray-700 mb-2">Paid Amount</label>
                        <input type="number" id="paid_amount" name="paid_amount" min="0" step="0.01" value="0" oninput="calculateTotal()" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" placeholder="Enter paid amount">
                    </div>
                    <div>
                        <label for="return_amount" class="block text-sm font-semibold text-gray-700 mb-2">Return Amount</label>
                        <input type="number" id="return_amount" name="return_amount" step="0.01" readonly value="0" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 outline-none text-green-700 font-semibold">
                    </div>
                </div>
                
                <div>
                    <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Additional notes or special instructions..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"></textarea>
                </div>
                
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all">
                        Save
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-all">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="assets/js/main.js"></script>
    <script>
        // Modal can only be closed via the close button (X) or Cancel button
        // Clicking outside the modal will NOT close it
    </script>
</body>
</html>
