===========================================
QUICK DEPLOYMENT GUIDE FOR CPANEL
===========================================

STEP 1: PREPARE FILES
---------------------
1. Update config/database.php with your cPanel database credentials:
   - DB_HOST: usually 'localhost'
   - DB_USER: your cPanel database username
   - DB_PASS: your cPanel database password  
   - DB_NAME: your cPanel database name

2. Remove these files before creating ZIP:
   - migrate_tables.php
   - fix_capacity_column.php
   - DEPLOYMENT_GUIDE.md
   - DEPLOYMENT_CHECKLIST.txt
   - README_DEPLOYMENT.txt
   - config/database.production.example.php

STEP 2: CREATE ZIP FILE
------------------------
1. Select all remaining files and folders
2. Create ZIP: hotel_management_system.zip
3. Make sure .htaccess is included

STEP 3: UPLOAD TO CPANEL
-------------------------
1. Login to cPanel
2. Go to File Manager
3. Navigate to public_html (or your domain folder)
4. Click Upload
5. Upload hotel_management_system.zip
6. Right-click ZIP file → Extract
7. Delete ZIP file after extraction

STEP 4: SET UP DATABASE
------------------------
1. In cPanel, go to MySQL Databases
2. Create new database (e.g., username_hotel)
3. Create new database user
4. Add user to database with ALL PRIVILEGES
5. Note down: database name, username, password

STEP 5: UPDATE CONFIG
----------------------
1. In File Manager, edit config/database.php
2. Update with your cPanel database credentials:
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_cpanel_db_user');
   define('DB_PASS', 'your_cpanel_db_password');
   define('DB_NAME', 'your_cpanel_db_name');

STEP 6: SET PERMISSIONS
------------------------
1. Folders: 755
2. PHP files: 644
3. .htaccess: 644

STEP 7: TEST
------------
1. Visit: https://yourdomain.com/
2. Database tables will auto-create on first visit
3. Login and test all features

TROUBLESHOOTING:
----------------
- White page? Check PHP error logs in cPanel
- Database error? Verify credentials in config/database.php
- CSS not loading? Check file paths and permissions

===========================================

