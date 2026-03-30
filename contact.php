<?php
session_start();
require_once 'config/database.php';

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name)    $errors[] = 'Name is required.';
    if (!$message) $errors[] = 'Message is required.';

    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_name VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $stmt = $conn->prepare("INSERT INTO contact_messages (customer_name, message) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('ss', $name, $message);
                if ($stmt->execute()) {
                    $success = true;
                    $_POST = [];
                } else {
                    $errors[] = 'Unable to save your message. Please try again.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Unable to prepare message query.';
            }
            $conn->close();
        } catch (Throwable $e) {
            $errors[] = 'Something went wrong while saving your message.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact — थकाली भान्छा घर</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@300;400;600;700;800&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/contact.css">
</head>
<body>

<nav id="navbar">
    <div class="nav-inner">
        <a href="index.php" class="logo-wrap">
            
            <div class="logo-text">
                <span class="top">थकाली</span>
                <span class="sub">Bhanchha Ghar</span>
            </div>
        </a>
        <ul class="nav-links">
            <li><a href="index.php" class="nav-link">Home</a></li>
            <li><a href="menu.php" class="nav-link">Menu</a></li>
            <li><a href="gallery.php" class="nav-link">Gallery</a></li>
            <li><a href="contact.php" class="nav-link active">Contact</a></li>
            <li><a href="admin/bookings.php" class="reserve-btn">Reserve Table</a></li>
        </ul>
        <button class="hamburger" id="menuToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
    <div class="mobile-menu" id="mobileMenu">
        <ul class="mobile-menu-list">
            <li><a href="index.php" class="mobile-link">Home</a></li>
            <li><a href="menu.php" class="mobile-link">Menu</a></li>
            <li><a href="gallery.php" class="mobile-link">Gallery</a></li>
            <li><a href="contact.php" class="mobile-link active">Contact</a></li>
            <li class="mobile-reserve-item"><a href="admin/bookings.php" class="reserve-btn mobile-reserve-link">Reserve Table</a></li>
        </ul>
    </div>
</nav>

<section class="page-hero">
    <span class="page-hero-label">थकाली भान्छा घर</span>
    <h1 class="page-hero-title">Get in <em>Touch</em></h1>
    <p class="page-hero-sub">Reserve a table, ask a question, or simply say hello</p>
    <div class="hero-rule"><span>✦</span></div>
</section>

<div class="contact-body">
    <div class="info-panel">
        <span class="info-section-label">Find Us</span>
        <h2 class="info-title">We'd love to<br><em>hear from you</em></h2>
        <p class="info-desc">
            Whether you're planning a family feast, a business lunch, or just want to know today's special —
            we're always happy to hear from you. Visit us, call us, or send a message below.
        </p>

        <div class="info-cards">
            <div class="info-card">
                <div class="info-card-icon">📍</div>
                <div class="info-card-body">
                    <div class="info-card-label">Address · ठेगाना</div>
                    <div class="info-card-value">तुल्सीपुर, दाङ, नेपाल</div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-icon">☎</div>
                <div class="info-card-body">
                    <div class="info-card-label">Phone · फोन</div>
                    <div class="info-card-value">
                        <a href="tel:+97714412345">+977 9866944626 </a><br>
                        <a href="tel:+9779841234567">+977 9766786885</a>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-icon">✉</div>
                <div class="info-card-body">
                    <div class="info-card-label">Email · इमेल</div>
                    <div class="info-card-value"><a href="mailto:info@thakali.com.np">info@thakali.com.np</a></div>
                </div>
            </div>
        </div>


        <div class="map-wrap">
            <div class="map-inner">
                <iframe
                    class="map-iframe"
                    title="Hamro Thakali Bhancha Ghar Location"
                    src="https://maps.google.com/maps?q=Hamro%20Thakali%20Bhancha%20Ghar%2C%20Tulsipur%2C%20Dang%2C%20Nepal&z=15&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen>
                </iframe>
            </div>
            <a href="https://maps.app.goo.gl/JBECC5QUNWHkeo318" target="_blank" class="map-link">Open in Google Maps</a>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-header">
            <span class="form-label-sm">Send a Message</span>
            <h2 class="form-title">Share your<br><em>Message</em></h2>
        </div>

        <?php if ($success): ?>
    <div class="alert alert-success">✓ &nbsp; Thank you! Your message has been sent successfully.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><ul><?php foreach($errors as $e): ?><li>✕ &nbsp; <?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST" action="contact.php" class="contact-form">
            <div class="form-row single">
                <div class="form-group">
                    <label for="name">Your Name *</label>
                    <input type="text" id="name" name="name" placeholder="Hari Bahadur Thapa" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row single">
                <div class="form-group">
                    <label for="message">Your Message *</label>
                    <textarea id="message" name="message" rows="6" placeholder="Tell us about your visit, dietary requirements, or any special occasion..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">Send Message</button>
                <span class="form-note">Admin can review your message</span>
            </div>
        </form>
    </div>
</div>

<footer>
    <div class="footer-logo">थकाली भान्छा घर</div>
    <div class="footer-sub">Authentic Thakali Cuisine</div>
    <div class="footer-links">
        <a href="index.php" class="footer-link">Home</a>
        <a href="menu.php" class="footer-link">Menu</a>
        <a href="gallery.php" class="footer-link">Gallery</a>
        <a href="contact.php" class="footer-link">Contact</a>
    </div>
    <div class="footer-copy">© <?= date('Y') ?> थकाली भान्छा घर. All rights reserved.</div>
    <br>
</footer>

<script src="assets/js/contact.js"></script>
</body>
</html>
