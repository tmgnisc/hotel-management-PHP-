<?php
session_start();

require_once 'config/database.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: admin/index.php');
    exit;
}

$homepageMenuItems = [];

try {
    $conn = getDBConnection();
    $menuQuery = "
        SELECT
            food_name,
            price,
            COALESCE(NULLIF(TRIM(description), ''), 'Authentic Nepali flavor prepared with fresh ingredients.') AS description
        FROM menu
        WHERE status = 'available'
        ORDER BY id DESC
        LIMIT 10
    ";

    $menuResult = $conn->query($menuQuery);
    if ($menuResult) {
        while ($row = $menuResult->fetch_assoc()) {
            $homepageMenuItems[] = $row;
        }
    }
    $conn->close();
} catch (Throwable $e) {
    $homepageMenuItems = [];
}

if (count($homepageMenuItems) === 0) {
    $homepageMenuItems = [
        ['food_name' => 'थकाली सेट (Thakali Set)', 'description' => 'Traditional rice, lentil, gundruk, pickle & local greens.', 'price' => 650],
        ['food_name' => 'कुखुरा सेकुवा (Chicken Sekuwa)', 'description' => 'Smoky charcoal-grilled chicken with timur and mustard oil.', 'price' => 520],
        ['food_name' => 'मासु भात (Mutton Curry Rice)', 'description' => 'Slow-cooked mutton curry with steamed aromatic rice.', 'price' => 740],
        ['food_name' => 'मोमो अचार सेट (Momo Achar Set)', 'description' => 'Hand-folded momo served with roasted tomato-sesame achar.', 'price' => 390],
        ['food_name' => 'ढिंडो गुन्द्रुक (Dhido & Gundruk)', 'description' => 'Buckwheat dhido with hearty fermented leafy stew.', 'price' => 480],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>थकाली भान्छा घर — Authentic Thakali Cuisine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@300;400;500;600;700;800&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/home.css">
</head>
<body>

<!-- NAV -->
<?php include 'includes/nav.php'; ?>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg">
        <div class="hero-photo" id="heroParallax" aria-hidden="true"></div>
        <!-- SVG decorative lines -->
        <svg class="deco-lines" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice">
            <line x1="0" y1="200" x2="400" y2="600" stroke="rgba(200,134,10,0.05)" stroke-width="1"/>
            <line x1="1440" y1="100" x2="900" y2="700" stroke="rgba(200,134,10,0.06)" stroke-width="1"/>
            <circle cx="200" cy="700" r="180" fill="none" stroke="rgba(200,134,10,0.04)" stroke-width="1"/>
            <circle cx="1200" cy="200" r="140" fill="none" stroke="rgba(200,134,10,0.04)" stroke-width="1"/>
            <path d="M0,450 Q360,380 720,450 Q1080,520 1440,450" fill="none" stroke="rgba(200,134,10,0.05)" stroke-width="1"/>
        </svg>
        <!-- Spice dots -->
        <div class="spice-dot" style="width:4px;height:4px;left:15%;top:70%;--dur:7s;--delay:0s;"></div>
        <div class="spice-dot" style="width:3px;height:3px;left:25%;top:80%;--dur:9s;--delay:1.5s;"></div>
        <div class="spice-dot" style="width:5px;height:5px;left:70%;top:75%;--dur:8s;--delay:0.8s;"></div>
        <div class="spice-dot" style="width:3px;height:3px;left:80%;top:65%;--dur:6.5s;--delay:2s;"></div>
        <div class="spice-dot" style="width:4px;height:4px;left:50%;top:85%;--dur:10s;--delay:0.3s;"></div>
    </div>

    <!-- Large decorative Devanagari -->
    <div class="hero-devanagari" aria-hidden="true">थ</div>

    <div class="hero-content">
        <div class="hero-steam" aria-hidden="true">
            <span></span><span></span><span></span><span></span>
        </div>
    <div class="hero-badge">Hamro Thakali Bhancha Ghar · Tulsipur, Dang</div>

        <h1 class="hero-title-ne">
            हाम्रो <span>थकाली</span><br>भान्छा घर
        </h1>

        <div class="hero-divider">
            <div class="hero-divider-icon">✦</div>
        </div>

        <p class="hero-subtitle">
            “Hamro Thakali Bhancha Ghar” तपाईंलाई साँचिकै नेपाली परिकारको स्वाद दिलाउने एक उत्कृष्ट स्थान हो।<br>
            दाङको तुल्सीपुरमा अवस्थित हाम्रो <span>थकाली</span><br>भान्छा घरमा आत्मीय सेवाको अनुभव लिन सकिन्छ।
        </p>

        <div class="hero-actions">
            <a href="menu.php" class="btn-primary">
                <span>Explore Menu</span>
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M8 3l4 4-4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <a href="contact.php" class="btn-secondary">Reserve a Table</a>
        </div>
    </div>

    <div class="scroll-hint">
        <div class="scroll-line"></div>
        <span>Scroll</span>
    </div>
</section>

<!-- MENU SHOWCASE -->
<section class="menu-showcase">
    <div class="menu-showcase-head">
        <span class="section-label">Signature Plates</span>
        <h2 class="section-title">From Himalayan kitchens to your table</h2>
    </div>

    <div class="menu-slider" aria-label="Scrolling menu highlights">
        <div class="menu-track">
            <?php foreach ($homepageMenuItems as $item): ?>
                <article class="menu-item-card">
                    <span class="plate-icon" aria-hidden="true"></span>
                    <h3><?php echo htmlspecialchars($item['food_name']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                    <span class="menu-price">Rs. <?php echo number_format((float)$item['price'], 0); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="menu-track" aria-hidden="true">
            <?php foreach ($homepageMenuItems as $item): ?>
                <article class="menu-item-card">
                    <span class="plate-icon" aria-hidden="true"></span>
                    <h3><?php echo htmlspecialchars($item['food_name']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                    <span class="menu-price">Rs. <?php echo number_format((float)$item['price'], 0); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- TESTIMONIALS -->
<section class="testimonials">
    <div class="testimonials-head">
        <span class="section-label">Guest Stories</span>
        <h2 class="section-title">Voices from our tables</h2>
    </div>
    <div class="testimonial-grid">
        <article class="testimonial-card">
            <div class="stars" aria-label="5 out of 5">★★★★★</div>
            <p>“Best Thakali set in town. The ghee, the achar, and the hospitality are all perfect.”</p>
            <p class="testimonial-ne">“साँचिकै मिठो थकाली खाना र घरजस्तै आतिथ्य अनुभव भयो।”</p>
            <div class="testimonial-name">— Ramesh K.</div>
        </article>
        <article class="testimonial-card">
            <div class="stars" aria-label="5 out of 5">★★★★★</div>
            <p>“Authentic taste, clean ambiance, and fast service. I keep bringing my family here.”</p>
            <p class="testimonial-ne">“स्वाद, सफाइ र सेवा — तीनै कुरा उत्कृष्ट छन्।”</p>
            <div class="testimonial-name">— Anjali T.</div>
        </article>
        <article class="testimonial-card">
            <div class="stars" aria-label="5 out of 5">★★★★★</div>
            <p>“The sekuwa and momo are unforgettable. It feels like a mountain feast every visit.”</p>
            <p class="testimonial-ne">“सेकुवा र मोमो दुवै अद्भुत! प्रत्येक पटक हिमाली स्वादको अनुभूति हुन्छ।”</p>
            <div class="testimonial-name">— Bikash L.</div>
        </article>
    </div>
</section>

<!-- CONTACT & MAP -->
<section class="contact-map-section" id="contact">
    <div class="contact-grid">
        <article class="contact-info-card">
            <span class="section-label">Visit Us</span>
            <h2 class="section-title">Contact & Opening Hours</h2>

            <div class="contact-row">
                <strong>Phone:</strong>
                <a href="tel:+9779800000000">+977 9866944626</a>
            </div>
            <div class="contact-row">
                <strong>Email:</strong>
                <a href="mailto:info@thakalibhanchaghar.com">info@thakalibhanchaghar.com</a>
            </div>

            <div class="hours-box">
                <h3>Opening Hours</h3>
                <p>Sunday – Friday: 7:00 AM – 10:00 PM</p>
                <p>Saturday: 8:00 AM – 11:00 PM</p>
            </div>

            <div class="address-box">
                <h3>Address</h3>
                <p>Tulsipur, Dang, Nepal</p>
                <p class="address-ne">तुल्सीपुर, दाङ, नेपाल</p>
            </div>
              </article>

        <article class="map-placeholder" aria-label="Map location">
            <iframe
                class="map-iframe-home"
                title="Hamro Thakali Bhancha Ghar Location"
                src="https://maps.google.com/maps?q=Hamro%20Thakali%20Bhancha%20Ghar%2C%20Tulsipur%2C%20Dang%2C%20Nepal&z=15&output=embed"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen>
            </iframe>
        </article>

        
    </div>
</section>

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

<script src="assets/js/home.js"></script>
</body>
</html>