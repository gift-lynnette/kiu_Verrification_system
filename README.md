# KIU Automated Tuition Verification & Green Card System

A comprehensive web-based system for automating the tuition verification process and digital green card generation for Kampala International University (KIU).

## 📋 Table of Contents
- [Features](#features)
- [System Architecture](#system-architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Application Lifecycle](#application-lifecycle)
- [Status Reference](#status-reference)
- [Verification URLs](#verification-urls)
- [Usage](#usage)
- [Module Overview](#module-overview)
- [API Documentation](#api-documentation)
- [Security](#security)
- [Troubleshooting](#troubleshooting)

## ✨ Features

### Student Portal
- ✅ User registration and authentication
- ✅ Academic document submission (S.6 certificate, National ID or School ID, passport photo, bank slip)
- ✅ Real-time status tracking
- ✅ Green card download
- ✅ Notification center

### Finance Office Module
- ✅ Financial clearance queue from Admissions
- ✅ Approve / Reject / Pending decisions
- ✅ Partial payment and deferral flagging
- ✅ Manual override capability
- ✅ Analytics dashboard

### Admissions Office Module
- ✅ Document verification with flagging (incomplete/suspicious/mismatch)
- ✅ Registration number generation (`YYYYMM + sequence`)
- ✅ Resubmission requests with reason
- ✅ Automatic green card generation after finance clearance
- ✅ QR code generation and verification

### Admin Module
- ✅ User management
- ✅ System configuration
- ✅ Report generation
- ✅ Audit log viewer
- ✅ System backup

### Notification Service
- ✅ Email notifications
- ✅ SMS notifications (queue-based transport)
- ✅ In-app notifications
- ✅ Event-based triggers

## 🏗️ System Architecture

```
research/
├── api/                    # RESTful API endpoints
│   └── v1/
│       ├── submission/
│       └── notifications/
├── assets/                 # Frontend assets
│   ├── css/
│   └── js/
├── config/                 # Configuration files
│   ├── database.php
│   ├── constants.php
│   └── init.php
├── includes/               # Shared classes and utilities
│   ├── Auth.php
│   ├── Session.php
│   ├── Validator.php
│   ├── FileUpload.php
│   ├── Encryption.php
│   ├── AuditLog.php
│   ├── NotificationService.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── modules/                # Application modules
│   ├── student/
│   ├── finance/
│   ├── admissions/
│   └── admin/
├── uploads/                # File storage
│   ├── s6_certificates/
│   ├── national_ids/
│   ├── school_ids/
│   ├── passport_photos/
│   ├── bank_slips/
│   ├── qr_codes/
│   └── green_cards/
├── logs/                   # System logs
├── index.php              # Main entry point
├── login.php
├── register.php
├── logout.php
├── greencard_schema_final.sql
└── seed.sql
```

## 📦 Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Apache/Nginx**: Web server
- **Extensions**: PDO, OpenSSL, GD, mbstring
- **XAMPP/WAMP**: Recommended for local development

## 🚀 Installation

### Step 1: Database Setup

1. Start XAMPP/WAMP and ensure MySQL is running

2. Open phpMyAdmin and create a new database:
```sql
CREATE DATABASE Greencard_system;
```

3. Import the database schema:
```bash
mysql -u root -p Greencard_system < greencard_schema_final.sql
```

4. Apply regulation workflow migration:
```bash
mysql -u root -p Greencard_system < database_migration_regulation_workflow.sql
```

5. Import seed data (optional):
```bash
mysql -u root -p Greencard_system < seed.sql
```

If you import `seed.sql`, run the migration script again so regulation tables/states are active.

### Step 2: Configure Application

1. Edit database connection in `config/database.php`:
```php
private $host = "localhost";
private $db_name = "Greencard_system";
private $username = "root";
private $password = "";  // Your MySQL password
```

2. Update constants in `config/constants.php`:
```php
define('BASE_URL', 'http://localhost/research/');
define('ENCRYPTION_KEY', 'your-unique-encryption-key');
define('JWT_SECRET', 'your-unique-jwt-secret');
```

3. Configure email settings (if using email notifications):
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### Step 3: Set Permissions

Create necessary directories and set permissions:
```bash
mkdir -p uploads/s6_certificates
mkdir -p uploads/national_ids
mkdir -p uploads/school_ids
mkdir -p uploads/passport_photos
mkdir -p uploads/bank_slips
mkdir -p uploads/qr_codes
mkdir -p uploads/green_cards
mkdir -p logs

chmod 755 uploads/
chmod 755 logs/
```

### Step 4: Access the System

Open your web browser and navigate to:
```
http://localhost/research/
```

## 🔧 Configuration

### Default Users (from seed data)

| Role | Admission Number | Email | Password |
|------|-----------------|-------|----------|
| Admin | ADMIN001 | admin@kiu.ac.ug | password |
| Finance | FIN001 | finance.manager@kiu.ac.ug | password |
| Registrar | REG001 | registrar@kiu.ac.ug | password |
| Student | KIU/2024/001 | john.doe@student.kiu.ac.ug | password |

**Note**: Default password hash is for "password". Change these in production!

### Environment Variables

Key settings in `config/constants.php`:
- `DEBUG_MODE`: Set to `false` in production
- `SESSION_TIMEOUT`: Session expiration time (seconds)
- `UPLOAD_MAX_SIZE`: Maximum file upload size (bytes)
- `PASSWORD_MIN_LENGTH`: Minimum password length
- `MAX_LOGIN_ATTEMPTS`: Login attempt limit before lockout

## 🔄 Application Lifecycle
`SUBMITTED` (`pending_admissions`)
→ `DOCUMENTS UNDER REVIEW` (`under_admissions_review`)
→ `DOCUMENTS APPROVED` (`pending_finance`)
→ `SENT TO FINANCE` (`under_finance_review`)
→ `FINANCIALLY CLEARED` (`pending_greencard`)
→ `GREEN CARD ISSUED` (`greencard_issued`)

Alternative paths:
- `resubmission_requested` (Admissions requests corrected documents)
- `admissions_rejected`
- `finance_pending` (awaiting additional finance confirmation)
- `finance_rejected`

## 🏷️ Status Reference
- `pending_admissions`: submitted and waiting for Admissions pickup
- `under_admissions_review`: Admissions has started review
- `pending_finance`: Admissions approved; waiting for Finance
- `under_finance_review`: Finance has started payment review
- `finance_pending`: Finance needs additional confirmation
- `pending_greencard`: Finance cleared; waiting final issuance by Admissions
- `greencard_issued`: process complete
- `resubmission_requested`: student must re-upload corrected documents
- `admissions_rejected` / `finance_rejected`: rejected with reason
- `cancelled`: cancelled by system/admin

## 🔗 Verification URLs
- Public card verification page:
  - `GET /verify_card.php?card={card_number}`
  - `GET /verify_card.php?reg={registration_number}`
- Admissions verification helper UI:
  - `GET /modules/admissions/verify_qr.php`

## 📖 Usage

### For Students

1. **Register**: Navigate to registration page and create account
2. **Login**: Access student dashboard
3. **Submit Documents**: Upload S.6 certificate + National ID or School ID + photo + bank slip
4. **Track Status**: Monitor verification progress in real-time
5. **Download Green Card**: Once approved, download your digital green card

### For Finance Officers

1. **Login**: Access finance dashboard
2. **Review Queue**: View submissions forwarded by Admissions
3. **Review Payment**: Confirm amount/date against finance records
4. **Decide**: Approve / Pending / Reject (with reasons and flags)
5. **Analytics**: View verification statistics

### For Admissions Staff

1. **Login**: Access admissions dashboard
2. **Verify Documents**: Approve / Reject / Request resubmission
3. **Registration Number**: Auto-generated on approval (`YYYYMM + sequence`)
4. **Issue Green Card**: After finance clearance
5. **Verify QR**: Validate card authenticity from verification page

### For Administrators

1. **Login**: Access admin dashboard
2. **Manage Users**: Create, edit, or disable user accounts
3. **System Settings**: Configure system parameters
4. **Generate Reports**: Export system data and analytics
5. **View Audit Logs**: Monitor all system activities

## 📚 Module Overview

### Module 1: Student Portal
- Account registration with email verification
- Secure document upload with encryption
- Real-time status dashboard
- Green card download with QR code
- Notification management

### Module 2: Admissions Verification
- Document review and verification workflow
- Document issue flagging (incomplete/suspicious/mismatch)
- Registration number generation and profile linking
- Resubmission requests with reasons
- Green card issuance after financial clearance

### Module 3: Finance Office
- Verification queue with filtering
- Payment review interface
- Approve / Pending / Reject workflow with audit trail
- Partial payment and deferral indicators
- Manual override for special cases
- Performance analytics

### Module 4: System Administration
- Role-based access control
- User management
- System configuration
- Report generation (PDF, Excel, CSV)
- Comprehensive audit logging

### Module 5: Notification Service
- Email queue management
- SMS gateway integration (placeholder)
- In-app notifications
- Event-driven triggers
- Delivery tracking

### Module 6: Data Management
- Secure database with encryption
- Automated backups (configure separately)
- Data archiving
- File storage management
- Transaction logging

## 🔌 API Documentation

### Authentication
All API endpoints require authentication via session cookie.

### Endpoints

#### Get Submission Status
```
GET /api/v1/submission/status.php?submission_id={id}
```
Response:
```json
{
  "success": true,
  "data": {
    "submission_id": 1,
    "status": "under_finance_review",
    "submitted_at": "2024-01-15 10:30:00",
    "finance_approved": 0,
    "finance_pending": 0,
    "has_greencard": true
  }
}
```

#### Get Notifications
```
GET /api/v1/notifications/list.php?limit=10&unread_only=true
```

#### Mark Notification as Read
```
POST /api/v1/notifications/mark_read.php
Body: { "notification_id": 1 }
```

## 🔒 Security Features

- ✅ Password hashing with bcrypt
- ✅ CSRF token protection
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (input sanitization)
- ✅ File upload validation
- ✅ Session management
- ✅ Login attempt limiting
- ✅ Account lockout mechanism
- ✅ Comprehensive audit logging
- ✅ AES-256 file encryption (optional)

## 🐛 Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check database credentials in `config/database.php`
- Ensure database `Greencard_system` exists

### File Upload Error
- Check upload directory permissions (755)
- Verify `php.ini` settings: `upload_max_filesize` and `post_max_size`
- Ensure sufficient disk space

### Session Issues
- Clear browser cookies
- Check session directory permissions
- Verify `session.save_path` in `php.ini`

### Email Not Sending
- Configure SMTP settings in `config/constants.php`
- Use app-specific password for Gmail
- Check firewall/port settings (587 for TLS)

## 📝 Development Notes

### Password Hash Generation
To generate password hashes for new users:
```php
<?php
echo password_hash('your_password', PASSWORD_BCRYPT);
?>
```

### Adding New Roles
Update the `role` ENUM in the `users` table and add corresponding checks in `includes/functions.php`.

### Customizing Email Templates
Edit the `getEmailTemplate()` method in `includes/NotificationService.php`.

## 🤝 Support

For issues or questions:
- Email: support@kiu.ac.ug
- Documentation: See inline code comments
- Audit Logs: Check `modules/admin/audit_logs.php`

## 📄 License

Copyright © 2024 Kampala International University. All rights reserved.

## 🎯 Future Enhancements

- [ ] Mobile app integration (API-ready)
- [ ] Biometric verification
- [ ] Payment gateway integration
- [ ] SMS gateway integration (Twilio/Africa's Talking)
- [ ] Advanced analytics and reporting
- [ ] Multi-language support
- [ ] Digital signature verification
- [ ] Blockchain-based certificate verification

---

**Version**: 1.0.0  
**Last Updated**: February 2026  
**Developed for**: Kampala International University
