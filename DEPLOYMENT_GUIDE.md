# Deployment Guide for cPanel

## Pre-Deployment Checklist

### 1. Files to Include in ZIP
- ✅ All PHP files (`.php`)
- ✅ All CSS files (`assets/css/`)
- ✅ All JavaScript files (`assets/js/`)
- ✅ All includes (`includes/`)
- ✅ All config files (`config/`)

### 2. Files to EXCLUDE from ZIP
- ❌ `migrate_tables.php` (one-time migration script)
- ❌ `fix_capacity_column.php` (one-time fix script)
- ❌ Any `.git` folders
- ❌ Any backup files
- ❌ Any temporary files

### 3. Before Creating ZIP

#### Step 1: Update Database Configuration
1. Open `config/database.php`
2. Update these values for your cPanel:
   ```php
   define('DB_HOST', 'localhost'); // Usually 'localhost' on cPanel
   define('DB_USER', 'your_cpanel_db_user');
   define('DB_PASS', 'your_cpanel_db_password');
   define('DB_NAME', 'your_cpanel_db_name');
   ```

#### Step 2: Security Check
- ✅ Remove any debug code (`error_reporting`, `ini_set('display_errors')`)
- ✅ Ensure all passwords are secure
- ✅ Check file permissions

#### Step 3: Test Locally
- ✅ Test all CRUD operations
- ✅ Test login functionality
- ✅ Verify all pages load correctly

---

## Deployment Steps

### Method 1: ZIP Upload (Recommended)

#### Step 1: Create Deployment Package
1. Select all files EXCEPT:
   - `migrate_tables.php`
   - `fix_capacity_column.php`
   - `DEPLOYMENT_GUIDE.md` (this file)
   - `.git` folder (if exists)

2. Create a ZIP file named: `hotel_management_system.zip`

#### Step 2: Upload to cPanel
1. Login to cPanel
2. Go to **File Manager**
3. Navigate to `public_html` (or your domain folder)
4. Click **Upload**
5. Upload `hotel_management_system.zip`
6. Select the ZIP file and click **Extract**
7. Delete the ZIP file after extraction

#### Step 3: Set Up Database
1. Go to **MySQL Databases** in cPanel
2. Create a new database (e.g., `username_hotel`)
3. Create a new database user
4. Add user to database with **ALL PRIVILEGES**
5. Note down:
   - Database name: `username_hotel`
   - Database user: `username_dbuser`
   - Database password: `your_password`
   - Database host: Usually `localhost`

#### Step 4: Update Database Config
1. Edit `config/database.php` via File Manager
2. Update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'username_dbuser');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'username_hotel');
   ```

#### Step 5: Set File Permissions
1. Set folder permissions: `755`
2. Set PHP file permissions: `644`
3. Set config folder: `755` (but `database.php` should be `644`)

#### Step 6: Test Installation
1. Visit your domain: `https://yourdomain.com/`
2. The database will auto-create tables on first visit
3. Login with default admin credentials (if set)
4. Test all features

---

### Method 2: FTP Upload

1. Use FTP client (FileZilla, WinSCP)
2. Connect to your cPanel FTP
3. Upload all files to `public_html` folder
4. Follow Steps 3-6 from Method 1

---

## Post-Deployment Steps

### 1. Security Hardening
- [ ] Change default admin password
- [ ] Remove or protect migration scripts
- [ ] Set proper file permissions
- [ ] Enable SSL/HTTPS if available

### 2. Testing Checklist
- [ ] Login page works
- [ ] Dashboard loads correctly
- [ ] All CRUD operations work
- [ ] Database connections work
- [ ] Images/assets load correctly

### 3. Performance Optimization
- [ ] Enable caching (if available)
- [ ] Optimize database queries
- [ ] Compress CSS/JS files (optional)

---

## Troubleshooting

### Issue: White Page / 500 Error
**Solution:**
- Check file permissions (should be 644 for PHP files)
- Check PHP error logs in cPanel
- Verify database credentials
- Check PHP version compatibility (requires PHP 7.4+)

### Issue: Database Connection Failed
**Solution:**
- Verify database credentials in `config/database.php`
- Check database user has proper permissions
- Ensure database exists in cPanel
- Check database host (usually `localhost`)

### Issue: CSS/JS Not Loading
**Solution:**
- Check file paths are correct
- Verify file permissions
- Clear browser cache
- Check `.htaccess` file (if exists)

### Issue: Session Not Working
**Solution:**
- Check PHP session settings in cPanel
- Verify `session_start()` is called before output
- Check file permissions on session directory

---

## Important Notes

1. **Database Auto-Creation**: The system will automatically create tables on first page load
2. **Default Admin**: Check `config/database.php` for default admin credentials
3. **Backup**: Always backup before making changes
4. **PHP Version**: Requires PHP 7.4 or higher
5. **MySQL Version**: Requires MySQL 5.7 or higher

---

## Support Files Included

- `config/database.php` - Database configuration
- `includes/nav.php` - Navigation component
- `assets/css/style.css` - Styles
- `assets/js/main.js` - JavaScript functions

---

## Quick Reference

**Default Admin Login** (if configured):
- Username: Check `config/database.php` initialization
- Password: Check `config/database.php` initialization

**Database Tables Created:**
- `superadmin`
- `tables`
- `normal_rooms`
- `bookings`
- `menu`
- `order_details`

---

## Need Help?

If you encounter issues:
1. Check cPanel error logs
2. Verify all file permissions
3. Test database connection separately
4. Check PHP version compatibility












