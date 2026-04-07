# KIU Green Card System - Installation Guide

## Quick Start Guide

### 1. Prerequisites
- XAMPP/WAMP installed
- Web browser (Chrome, Firefox, Edge)
- Text editor (VS Code, Notepad++, Sublime)

### 2. Installation Steps

#### Step 1: Start XAMPP
1. Open XAMPP Control Panel
2. Start Apache
3. Start MySQL
4. Verify both are running (green indicators)

#### Step 2: Setup Database
1. Open browser: `http://localhost/phpmyadmin`
2. Click "New" to create database
3. Database name: `Greencard_system`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

#### Step 3: Import Database
**Option A - Using phpMyAdmin:**
1. Select `Greencard_system` database
2. Click "Import" tab
3. Click "Choose File"
4. Select `greencard_schema_final.sql`
5. Click "Go"
6. Wait for success message
7. Repeat for `database_migration_regulation_workflow.sql`
8. Optional: Import `seed.sql`, then re-run `database_migration_regulation_workflow.sql`

**Option B - Using Command Line:**
```bash
cd C:\xampp\htdocs\research
mysql -u root -p Greencard_system < greencard_schema_final.sql
mysql -u root -p Greencard_system < database_migration_regulation_workflow.sql
mysql -u root -p Greencard_system < seed.sql
mysql -u root -p Greencard_system < database_migration_regulation_workflow.sql
```

#### Step 4: Configure Application
1. Open `config/database.php`
2. Verify settings:
   ```php
   private $host = "localhost";
   private $db_name = "Greencard_system";
   private $username = "root";
   private $password = "";  // Leave empty if no password
   ```

3. Open `config/constants.php`
4. Update BASE_URL if needed:
   ```php
   define('BASE_URL', 'http://localhost/research/');
   ```

#### Step 5: Test Installation
1. Open browser
2. Navigate to: `http://localhost/research/`
3. You should see login page

### 3. Test Accounts

Use these credentials to test the system:

**Admin Account:**
- Admission Number: `ADMIN001`
- Password: `password`
- Access: Full system administration

**Finance Officer:**
- Admission Number: `FIN001`
- Password: `password`
- Access: Payment verification

**Registrar:**
- Admission Number: `REG001`
- Password: `password`
- Access: Green card generation

**Student:**
- Admission Number: `KIU/2024/001`
- Password: `password`
- Access: Student portal

### 4. First Login

1. Go to login page
2. Enter credentials
3. Click "Login"
4. You'll be redirected to appropriate dashboard

### 5. Testing Student Flow

**As Student (KIU/2024/001):**
1. Login
2. Go to "Submit Documents"
3. Fill personal/academic/payment information
4. Upload S.6 certificate + National ID or School ID + passport photo + bank slip
5. Submit

**As Registrar (REG001) - Admissions Step:**
1. Login
2. Open Admissions dashboard
3. Click "Review" on pending submission
4. Review documents
5. Approve / Reject / Request Resubmission

**As Finance Officer (FIN001):**
1. Login
2. View finance clearance queue
3. Click "Verify" on submission
4. Review payment and choose Approve / Pending / Reject
5. Save decision

**As Registrar (REG001) - Green Card Issuance:**
1. Login
2. Open pending green card queue
3. Issue green card
4. Verify using `/modules/admissions/verify_qr.php` or `/verify_card.php`

### 6. Lifecycle and Status Validation

Expected happy-path statuses:
`pending_admissions -> under_admissions_review -> pending_finance -> under_finance_review -> pending_greencard -> greencard_issued`

Alternative statuses:
- `resubmission_requested`
- `admissions_rejected`
- `finance_pending`
- `finance_rejected`
- `cancelled`

### 7. Verification URLs

- Admissions verification page: `http://localhost/research/modules/admissions/verify_qr.php`
- Public verification endpoint:
  - `http://localhost/research/verify_card.php?card=GC2026000001`
  - `http://localhost/research/verify_card.php?reg=2026030001`

### 8. Common Issues

**Database Connection Error:**
- Check if MySQL is running in XAMPP
- Verify database name is exactly `Greencard_system`
- Check username/password in database.php

**Page Not Found:**
- Verify Apache is running
- Check BASE_URL in constants.php
- Ensure files are in `C:\xampp\htdocs\research\`

**Upload Errors:**
- Check if `uploads` folder exists
- Verify folder permissions
- Check PHP upload settings in php.ini

**Can't Login:**
- Verify seed data was imported
- Try password: `password` (lowercase)
- Check browser console for errors

### 9. File Structure Check

Your `research` folder should contain:
```
research/
├── api/
├── assets/
├── config/
├── includes/
├── modules/
├── uploads/
├── logs/
├── index.php
├── login.php
├── register.php
├── logout.php
├── greencard_schema_final.sql
├── database_migration_regulation_workflow.sql
├── seed.sql
├── README.md
└── .htaccess
```

### 10. Verify Installation

**Check Database:**
```sql
USE Greencard_system;
SHOW TABLES;  -- Should show 14 tables
SELECT COUNT(*) FROM users;  -- Should show 20 users
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='Greencard_system' AND table_name='document_submissions'; -- Should be 1
```

**Check File Permissions:**
- uploads/ folder should be writable
- logs/ folder should be writable

**Check Apache:**
- mod_rewrite should be enabled
- .htaccess file should be present

### 11. Security Checklist

Before going live:
- [ ] Change all default passwords
- [ ] Update ENCRYPTION_KEY in constants.php
- [ ] Update JWT_SECRET in constants.php
- [ ] Set DEBUG_MODE to false
- [ ] Configure proper SMTP settings
- [ ] Enable HTTPS
- [ ] Set proper file permissions
- [ ] Configure automatic backups

### 12. Next Steps

1. **Customize System:**
   - Update university branding
   - Modify email templates
   - Configure fee structures

2. **Test Thoroughly:**
   - Test all user roles
   - Test document uploads
   - Test notifications
   - Test green card generation

3. **Train Users:**
   - Prepare user manuals
   - Conduct training sessions
   - Setup support channels

### 13. Getting Help

**Check Logs:**
- Browser Console (F12)
- PHP Error Log: `C:\xampp\apache\logs\error.log`
- System Log: `logs/error.log`

**Common Solutions:**
```bash
# Restart Apache
- Stop Apache in XAMPP
- Wait 5 seconds
- Start Apache again

# Clear Browser Cache
- Press Ctrl+Shift+Delete
- Clear all cache
- Reload page

# Reset Database
- Drop Greencard_system database
- Recreate and reimport SQL files
```

### 14. Support

For additional help:
- Read README.md for detailed documentation
- Check inline code comments
- Review database schema diagram
- Contact system administrator

---

**Installation Complete!** 

You now have a fully functional KIU Green Card System.

Test all modules and customize as needed for your institution.
