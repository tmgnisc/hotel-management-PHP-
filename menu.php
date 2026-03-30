<?php
session_start();
require_once 'config/database.php';

$menuByCategory = [];
$menuLoadError = '';

try {
    $conn = getDBConnection();
    $query = "
        SELECT
            c.id AS category_id,
            c.category_name,
            s.subcategory_name,
            m.food_name,
            m.price,
            COALESCE(NULLIF(TRIM(m.description), ''), 'Authentic Nepali dish prepared fresh daily.') AS description
        FROM menu m
        LEFT JOIN categories c ON m.category_id = c.id
        LEFT JOIN subcategories s ON m.subcategory_id = s.id
        WHERE m.status = 'available'
        ORDER BY c.category_name ASC, s.subcategory_name ASC, m.food_name ASC
    ";

    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categoryId = (int)($row['category_id'] ?? 0);
            $categoryName = trim($row['category_name'] ?? 'Uncategorized');
            if ($categoryName === '') {
                $categoryName = 'Uncategorized';
            }

            $categoryKey = 'cat-' . ($categoryId > 0 ? $categoryId : 0);

            if (!isset($menuByCategory[$categoryKey])) {
                $menuByCategory[$categoryKey] = [
                    'name' => $categoryName,
                    'items' => [],
                ];
            }

            $menuByCategory[$categoryKey]['items'][] = [
                'food_name' => $row['food_name'] ?? 'Menu Item',
                'price' => (float)($row['price'] ?? 0),
                'description' => $row['description'] ?? '',
                'subcategory_name' => trim($row['subcategory_name'] ?? ''),
            ];
        }
    }
    $conn->close();
} catch (Throwable $e) {
    $menuLoadError = 'Unable to load menu right now. Please try again shortly.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — थकाली भान्छा घर</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@300;400;500;600;700;800&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/menu.css">
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
            <li><a href="menu.php" class="nav-link active">Menu</a></li>
            <li><a href="gallery.php" class="nav-link">Gallery</a></li>
            <li><a href="contact.php" class="nav-link">Contact</a></li>
            <li><a href="admin/bookings.php" class="reserve-btn">Reserve Table</a></li>
        </ul>
        <button class="hamburger" id="menuToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
    <div class="mobile-menu" id="mobileMenu">
        <ul style="list-style:none; padding:1.5rem 2rem; display:flex; flex-direction:column; gap:0.5rem;">
            <li><a href="index.php" class="mobile-link">Home</a></li>
            <li><a href="menu.php" class="mobile-link active">Menu</a></li>
            <li><a href="gallery.php" class="mobile-link">Gallery</a></li>
            <li><a href="contact.php" class="mobile-link">Contact</a></li>
            <li style="margin-top:0.75rem;"><a href="admin/bookings.php" class="reserve-btn" style="display:inline-block">Reserve Table</a></li>
        </ul>
    </div>
</nav>

<!-- PAGE HERO -->
<section class="page-hero">
    <span class="page-hero-label">थकाली भान्छा घर</span>
    <h1 class="page-hero-title">Our <em>Menu</em></h1>
    <p class="page-hero-sub">Authentic flavours from the highlands of Mustang</p>
    <div class="hero-rule"><span>✦</span></div>
</section>

<!-- FILTER BAR -->
<div class="filter-bar">
    <div class="filter-inner">
        <button class="filter-tab active" data-filter="all">All Items</button>
        <?php foreach ($menuByCategory as $categoryKey => $categoryData): ?>
            <button class="filter-tab" data-filter="<?php echo htmlspecialchars($categoryKey); ?>">
                <?php echo htmlspecialchars($categoryData['name']); ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- MENU BODY -->
<div class="menu-body">
    <?php if ($menuLoadError !== ''): ?>
        <div class="menu-section show-all" data-cat="all">
            <div class="section-header">
                <div class="section-num">!</div>
                <div class="section-info">
                    <div class="section-label-sm">Menu Status</div>
                    <div class="section-title-main">Unable to load menu</div>
                    <div class="section-title-ne"><?php echo htmlspecialchars($menuLoadError); ?></div>
                </div>
            </div>
        </div>
    <?php elseif (count($menuByCategory) === 0): ?>
        <div class="menu-section show-all" data-cat="all">
            <div class="section-header">
                <div class="section-num">00</div>
                <div class="section-info">
                    <div class="section-label-sm">Menu Status</div>
                    <div class="section-title-main">No menu items available</div>
                    <div class="section-title-ne">Please add available items from admin panel.</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php $sectionIndex = 1; ?>
        <?php foreach ($menuByCategory as $categoryKey => $categoryData): ?>
            <div class="menu-section show-all" data-cat="<?php echo htmlspecialchars($categoryKey); ?>">
                <div class="section-header">
                    <div class="section-num"><?php echo str_pad((string)$sectionIndex, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="section-info">
                        <div class="section-label-sm">Category</div>
                        <div class="section-title-main"><?php echo htmlspecialchars($categoryData['name']); ?></div>
                        <div class="section-title-ne">From our admin menu collection</div>
                    </div>
                </div>

                <div class="dishes-grid">
                    <?php foreach ($categoryData['items'] as $item): ?>
                        <div class="dish-card">
                            <div class="dish-top">
                                <div>
                                    <div class="dish-name">
                                        <?php echo htmlspecialchars($item['food_name']); ?>
                                        <?php if ($item['subcategory_name'] !== ''): ?>
                                            <span class="dish-name-ne"><?php echo htmlspecialchars($item['subcategory_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="dish-price">Rs. <?php echo number_format($item['price'], 0); ?></div>
                            </div>
                            <p class="dish-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="dish-tags">
                                <?php if ($item['subcategory_name'] !== ''): ?>
                                    <span class="tag"><?php echo htmlspecialchars($item['subcategory_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $sectionIndex++; ?>
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /menu-body -->

<!-- FOOTER -->
<footer>
    <div class="footer-logo">थकाली भान्छा घर</div>
    <div class="footer-sub">Authentic Thakali Cuisine · Est. 2010</div>
    <div class="footer-links">
        <a href="index.php" class="footer-link">Home</a>
        <a href="menu.php" class="footer-link">Menu</a>
        <a href="gallery.php" class="footer-link">Gallery</a>
        <a href="contact.php" class="footer-link">Contact</a>
    </div>
    <div class="footer-copy">© <?= date('Y') ?> थकाली भान्छा घर. All rights reserved.</div>
</footer>

<script src="assets/js/menu.js" defer></script>
</body>
</html>
