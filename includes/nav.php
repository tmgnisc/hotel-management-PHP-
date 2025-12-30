<?php
// Get current page name for active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    .devanagari-font {
        font-family: 'Noto Sans Devanagari', sans-serif;
    }
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Sidebar for Desktop -->
<aside id="sidebar" class="hidden md:flex fixed left-0 top-0 h-screen w-64 bg-gray-900 text-white flex-col z-50 shadow-2xl">
    <div class="p-6 border-b border-gray-800">
        <h2 class="text-lg font-bold text-white mb-1 devanagari-font">
            हाम्रो थकाली भान्छा घर
        </h2>
        <p class="text-xs text-gray-400">Hotel Management</p>
    </div>
    <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
        <a href="index.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'index.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">📊</span>
            <span class="font-medium">Dashboard</span>
        </a>
        <a href="tables.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'tables.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">🍽️</span>
            <span class="font-medium">Restaurant Tables</span>
        </a>
        <a href="normal_rooms.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'normal_rooms.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">🛏️</span>
            <span class="font-medium">Normal Rooms</span>
        </a>
        <a href="bookings.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'bookings.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">📅</span>
            <span class="font-medium">Bookings</span>
        </a>
        <a href="categories.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'categories.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">📂</span>
            <span class="font-medium">Categories</span>
        </a>
        <a href="subcategories.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'subcategories.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">📁</span>
            <span class="font-medium">Subcategories</span>
        </a>
        <a href="menu.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'menu.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">🍔</span>
            <span class="font-medium">Menu</span>
        </a>
        <a href="order_details.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'order_details.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">📋</span>
            <span class="font-medium">Order Details</span>
        </a>
        <a href="regular_customers.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'regular_customers.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">👤</span>
            <span class="font-medium">Regular Customers</span>
        </a>
        <a href="users.php" class="flex items-center px-4 py-3 rounded-lg transition-all duration-200 <?php echo ($current_page == 'users.php') ? 'bg-indigo-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
            <span class="mr-3">👥</span>
            <span class="font-medium">Users</span>
        </a>
    </nav>
</aside>

<!-- Header -->
<header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-40 md:ml-64">
    <div class="flex items-center justify-between px-4 md:px-6 h-16">
        <div class="flex items-center">
            <button id="mobileMenuToggle" class="md:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h1 class="ml-2 md:ml-0 text-lg md:text-xl font-bold text-gray-900 devanagari-font">
                हाम्रो थकाली भान्छा घर
            </h1>
        </div>
        <div class="flex items-center gap-4">
            <span class="hidden sm:inline-block px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium">
                <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
            </span>
            <a href="logout.php" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors shadow-md hover:shadow-lg">
                Logout
            </a>
        </div>
    </div>
</header>

<!-- Mobile Navigation -->
<nav id="navMenu" class="md:hidden bg-gray-900 text-white hidden">
    <ul class="space-y-1 p-4">
        <li>
            <a href="index.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'index.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Dashboard
            </a>
        </li>
        <li>
            <a href="tables.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'tables.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Restaurant Tables
            </a>
        </li>
        <li>
            <a href="normal_rooms.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'normal_rooms.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Normal Rooms
            </a>
        </li>
        <li>
            <a href="bookings.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'bookings.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Bookings
            </a>
        </li>
        <li>
            <a href="categories.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'categories.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Categories
            </a>
        </li>
        <li>
            <a href="subcategories.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'subcategories.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Subcategories
            </a>
        </li>
        <li>
            <a href="menu.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'menu.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Menu
            </a>
        </li>
        <li>
            <a href="order_details.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'order_details.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Order Details
            </a>
        </li>
        <li>
            <a href="regular_customers.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'regular_customers.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Regular Customers
            </a>
        </li>
        <li>
            <a href="users.php" class="flex items-center px-4 py-3 rounded-lg transition-all <?php echo ($current_page == 'users.php') ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                Users
            </a>
        </li>
    </ul>
</nav>
