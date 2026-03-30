<?php
// nav.php - Include this in all pages
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="navbar" class="fixed top-0 left-0 right-0 z-50 transition-all duration-500">
    <div class="nav-light nav-light-left" aria-hidden="true"></div>
    <div class="nav-light nav-light-right" aria-hidden="true"></div>
    <div class="nav-inner max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
        <!-- Logo -->
        <a href="index.php" class="logo-wrap flex items-center gap-3 group">
            <div class="logo-text">
                <span class="logo-top">थकाली</span>
                <span class="logo-sub">Bhanchha Ghar</span>
            </div>
        </a>

        <!-- Desktop Menu -->
    <ul class="nav-links">
            <li>
                <a href="index.php" class="nav-link <?= ($current_page === 'index.php') ? 'active' : '' ?>">
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="menu.php" class="nav-link <?= ($current_page === 'menu.php') ? 'active' : '' ?>">
                    <span>Menu</span>
                </a>
            </li>
            <li>
                <a href="gallery.php" class="nav-link <?= ($current_page === 'gallery.php') ? 'active' : '' ?>">
                    <span>Gallery</span>
                </a>
            </li>
            <li>
                <a href="contact.php" class="nav-link <?= ($current_page === 'contact.php') ? 'active' : '' ?>">
                    <span>Contact</span>
                </a>
            </li>
            <li>
                <a href="contact.php" class="reserve-btn">
                    Reserve Table
                </a>
            </li>
        </ul>

        <!-- Mobile Hamburger -->
        <button class="hamburger" id="menuToggle" aria-label="Toggle menu" aria-controls="mobileMenu" aria-expanded="false" type="button">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-menu-list">
            <li><a href="index.php" class="mobile-link <?= ($current_page === 'index.php') ? 'active' : '' ?>">Home</a></li>
            <li><a href="menu.php" class="mobile-link <?= ($current_page === 'menu.php') ? 'active' : '' ?>">Menu</a></li>
            <li><a href="gallery.php" class="mobile-link <?= ($current_page === 'gallery.php') ? 'active' : '' ?>">Gallery</a></li>
            <li><a href="contact.php" class="mobile-link <?= ($current_page === 'contact.php') ? 'active' : '' ?>">Contact</a></li>
            <li><a href="login.php" class="reserve-btn mobile-reserve-btn">Reserve Table</a></li>
        </ul>
    </div>
</nav>