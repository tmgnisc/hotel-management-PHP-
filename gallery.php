<?php
session_start();
require_once 'config/database.php';

$items = [];
$galleryError = '';

try {
    $conn = getDBConnection();
    $query = "SELECT title, subtitle, category, image_url FROM gallery_images WHERE is_active = 1 ORDER BY sort_order ASC, id DESC";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'cat' => $row['category'] ?: 'other',
                'title' => $row['title'] ?: 'Gallery Image',
                'sub' => $row['subtitle'] ?: 'Hamro Thakali Bhancha Ghar',
                'image_url' => $row['image_url'] ?: '',
            ];
        }
    }
    $conn->close();
} catch (Throwable $e) {
    $galleryError = 'Gallery is temporarily unavailable.';
}

if (count($items) === 0) {
    $items = [
        ['cat' => 'food', 'title' => 'Thakali Khana Set', 'sub' => 'Signature Dish', 'image_url' => ''],
        ['cat' => 'interior', 'title' => 'Main Dining Hall', 'sub' => 'Interior', 'image_url' => ''],
        ['cat' => 'team', 'title' => 'Welcoming Team', 'sub' => 'Our Team', 'image_url' => ''],
        ['cat' => 'events', 'title' => 'Festival Celebration', 'sub' => 'Special Event', 'image_url' => ''],
    ];
}

$categoryLabelMap = [
    'food' => 'Food & Dishes',
    'interior' => 'Interior',
    'team' => 'Our Team',
    'events' => 'Events',
    'other' => 'Other',
];

$availableCategories = [];
foreach ($items as $it) {
    $key = $it['cat'] ?? 'other';
    $availableCategories[$key] = $categoryLabelMap[$key] ?? ucfirst($key);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery — थकाली भान्छा घर</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@300;400;600;700;800&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/gallery.css">
</head>
<body>

<!-- NAVBAR -->
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
            <li><a href="gallery.php" class="nav-link active">Gallery</a></li>
            <li><a href="contact.php" class="nav-link">Contact</a></li>
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
            <li><a href="gallery.php" class="mobile-link active">Gallery</a></li>
            <li><a href="contact.php" class="mobile-link">Contact</a></li>
            <li class="mobile-reserve-item"><a href="admin/bookings.php" class="reserve-btn mobile-reserve-link">Reserve Table</a></li>
        </ul>
    </div>
</nav>

<!-- PAGE HERO -->
<section class="page-hero">
    <span class="page-hero-label">थकाली भान्छा घर</span>
    <h1 class="page-hero-title">Our <em>Gallery</em></h1>
    <p class="page-hero-sub">A visual feast — food, atmosphere & memories</p>
    <div class="hero-rule"><span>✦</span></div>
</section>

<!-- FILTER -->
<div class="filter-bar">
    <div class="filter-inner">
        <button class="filter-tab active" data-filter="all">All</button>
        <?php foreach ($availableCategories as $key => $label): ?>
            <button class="filter-tab" data-filter="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></button>
        <?php endforeach; ?>
    </div>
</div>

<!-- GALLERY -->
<div class="gallery-wrap">
    <div class="masonry" id="masonryGrid">

        <?php foreach ($items as $i => $item): ?>
        <div class="gallery-item" data-cat="<?= $item['cat'] ?>" data-index="<?= $i ?>" onclick="openLightbox(<?= $i ?>)">
            <div class="gallery-thumb">
                <?php if (!empty($item['image_url'])): ?>
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="gallery-real-image" loading="lazy">
                <?php else: ?>
                    <div class="gallery-thumb-inner">
                        <svg width="100%" height="260" viewBox="0 0 400 260" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                            <defs>
                                <radialGradient id="rg<?= $i ?>" cx="50%" cy="40%" r="70%">
                                    <stop offset="0%" stop-color="#C8860A"/>
                                    <stop offset="100%" stop-color="#3D1A06"/>
                                </radialGradient>
                            </defs>
                            <rect width="400" height="260" fill="url(#rg<?= $i ?>)"/>
                            <text x="200" y="138" text-anchor="middle" font-family="'Cormorant Garamond', serif" font-size="28" fill="#F5DFA0" fill-opacity="0.5">Gallery</text>
                        </svg>
                    </div>
                <?php endif; ?>
                <div class="gallery-overlay">
                    <div class="overlay-title"><?= $item['title'] ?></div>
                    <div class="overlay-sub"><?= $item['sub'] ?></div>
                </div>
                <div class="expand-icon">⤢</div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox">
    <div class="lightbox-inner">
        <button class="lightbox-close" id="lbClose">✕</button>
        <button class="lightbox-nav lb-prev" id="lbPrev">‹</button>
        <button class="lightbox-nav lb-next" id="lbNext">›</button>
        <div class="lightbox-img-wrap">
            <div class="lightbox-placeholder" id="lbImage"></div>
        </div>
        <div class="lightbox-caption">
            <h3 id="lbTitle"></h3>
            <p id="lbSub"></p>
        </div>
    </div>
</div>

<!-- FOOTER -->
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

<script id="gallery-data" type="application/json"><?= json_encode($items, JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/js/gallery.js"></script>
</body>
</html>
