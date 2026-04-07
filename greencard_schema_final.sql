-- ============================================================================
-- KIU GREENCARD SYSTEM - FINAL CORRECTED SCHEMA
-- All Errors Fixed - Ready for Production
-- ============================================================================

USE Greencard_system;

-- ============================================================================
-- TABLE 1: USERS
-- ============================================================================

CREATE TABLE users (
    user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'University admission number',
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'User email address',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
    role ENUM('student', 'finance_officer', 'registrar', 'admin') NOT NULL DEFAULT 'student',
    mfa_enabled BOOLEAN DEFAULT FALSE COMMENT 'Multi-factor authentication status',
    mfa_secret VARCHAR(255) NULL COMMENT 'TOTP secret for MFA',
    email_verified_at TIMESTAMP NULL COMMENT 'Email verification timestamp',
    last_login_at TIMESTAMP NULL COMMENT 'Last successful login',
    login_attempts TINYINT UNSIGNED DEFAULT 0 COMMENT 'Failed login attempt counter',
    locked_until TIMESTAMP NULL COMMENT 'Account lockout expiry time',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Account active status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_admission_number (admission_number),
    INDEX idx_role (role),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='User authentication and authorization';

-- ============================================================================
-- TABLE 2: STUDENT_PROFILES
-- ============================================================================

CREATE TABLE student_profiles (
    profile_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say') NOT NULL,
    nationality VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    alternative_phone VARCHAR(20) NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NULL,
    program VARCHAR(255) NOT NULL COMMENT 'Academic program enrolled',
    faculty VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    intake_year YEAR NOT NULL,
    intake_semester ENUM('semester_1', 'semester_2', 'semester_3') NOT NULL,
    student_type ENUM('undergraduate', 'postgraduate', 'diploma', 'certificate') NOT NULL,
    study_mode ENUM('full_time', 'part_time', 'distance', 'evening') NOT NULL DEFAULT 'full_time',
    photo_path VARCHAR(500) NULL COMMENT 'Encrypted path to student photo',
    emergency_contact_name VARCHAR(255) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    emergency_contact_relationship VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_student_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_program (program),
    INDEX idx_intake (intake_year, intake_semester),
    INDEX idx_full_name (full_name)
) ENGINE=InnoDB COMMENT='Student personal and academic details';

-- ============================================================================
-- TABLE 3: FEE_STRUCTURES
-- ============================================================================

CREATE TABLE fee_structures (
    fee_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(255) NOT NULL,
    faculty VARCHAR(255) NOT NULL,
    student_type ENUM('undergraduate', 'postgraduate', 'diploma', 'certificate') NOT NULL,
    study_mode ENUM('full_time', 'part_time', 'distance', 'evening') NOT NULL,
    academic_year VARCHAR(20) NOT NULL COMMENT 'e.g., 2025/2026',
    semester ENUM('semester_1', 'semester_2', 'semester_3') NOT NULL,
    tuition_amount DECIMAL(10, 2) NOT NULL COMMENT 'Full tuition fee',
    functional_fees DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'University functional fees',
    other_fees DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Additional fees',
    total_amount DECIMAL(10, 2) GENERATED ALWAYS AS (tuition_amount + functional_fees + other_fees) STORED,
    minimum_payment DECIMAL(10, 2) NOT NULL COMMENT 'Minimum required payment (typically 50%)',
    currency VARCHAR(10) DEFAULT 'UGX' COMMENT 'Currency code',
    payment_deadline DATE NULL COMMENT 'Fee payment deadline',
    late_payment_penalty DECIMAL(10, 2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_fee_creator 
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_program_year (program_name, academic_year),
    INDEX idx_active_fees (is_active, effective_from, effective_to),
    UNIQUE KEY unique_fee_structure (program_name, student_type, study_mode, academic_year, semester)
) ENGINE=InnoDB COMMENT='Fee structures for different programs';

-- ============================================================================
-- TABLE 4: PAYMENT_SUBMISSIONS
-- ============================================================================

CREATE TABLE payment_submissions (
    submission_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    fee_structure_id BIGINT UNSIGNED NULL COMMENT 'Reference to applicable fee structure',
    admission_letter_path VARCHAR(500) NULL COMMENT 'Encrypted path to admission letter',
    bank_slip_path VARCHAR(500) NOT NULL COMMENT 'Encrypted path to bank payment slip',
    id_photo_path VARCHAR(500) NULL COMMENT 'Encrypted path to ID photo',
    submitted_amount DECIMAL(10, 2) NOT NULL,
    required_amount DECIMAL(10, 2) NOT NULL,
    payment_reference VARCHAR(100) NULL COMMENT 'Bank transaction reference',
    payment_date DATE NULL COMMENT 'Date of payment as per bank slip',
    bank_name VARCHAR(255) NULL,
    branch_name VARCHAR(255) NULL,
    status ENUM('pending', 'under_review', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL COMMENT 'Required if status is rejected',
    resubmission_count TINYINT UNSIGNED DEFAULT 0 COMMENT 'Number of times resubmitted',
    priority_level ENUM('normal', 'urgent', 'very_urgent') DEFAULT 'normal',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_submission_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    CONSTRAINT fk_submission_fee 
        FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(fee_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    CONSTRAINT chk_amount_positive 
        CHECK (submitted_amount > 0 AND required_amount > 0),
    
    INDEX idx_user_submissions (user_id, submitted_at DESC),
    INDEX idx_status_date (status, submitted_at),
    INDEX idx_priority (priority_level, status)
) ENGINE=InnoDB COMMENT='Student payment document submissions';

-- ============================================================================
-- TABLE 5: PAYMENT_VERIFICATIONS
-- FIX: Removed generated column with subquery (not supported in MySQL)
-- ============================================================================

CREATE TABLE payment_verifications (
    verification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL UNIQUE,
    verified_by_user_id BIGINT UNSIGNED NOT NULL,
    finance_api_reference VARCHAR(255) NULL COMMENT 'External finance system reference',
    finance_api_response JSON NULL COMMENT 'Complete API response for audit',
    is_approved BOOLEAN NOT NULL,
    verification_notes TEXT NULL COMMENT 'Finance officer notes',
    amount_verified DECIMAL(10, 2) NOT NULL,
    manual_override BOOLEAN DEFAULT FALSE COMMENT 'Manual verification flag',
    override_reason TEXT NULL COMMENT 'Required if manual_override is TRUE',
    payment_date DATE NOT NULL COMMENT 'Verified payment date',
    verification_duration_seconds INT UNSIGNED NULL COMMENT 'Time taken to verify',
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_verification_submission 
        FOREIGN KEY (submission_id) REFERENCES payment_submissions(submission_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    CONSTRAINT fk_verification_officer 
        FOREIGN KEY (verified_by_user_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    
    INDEX idx_verifier (verified_by_user_id, verified_at),
    INDEX idx_approval_status (is_approved, verified_at)
) ENGINE=InnoDB COMMENT='Payment verification records';

-- ============================================================================
-- VIEW: Verification Details with Discrepancy Calculation
-- ============================================================================

CREATE OR REPLACE VIEW vw_verification_details AS
SELECT 
    pv.verification_id,
    pv.submission_id,
    pv.verified_by_user_id,
    pv.finance_api_reference,
    pv.is_approved,
    pv.verification_notes,
    pv.amount_verified,
    ps.submitted_amount,
    ABS(pv.amount_verified - ps.submitted_amount) AS discrepancy_amount,
    pv.manual_override,
    pv.override_reason,
    pv.payment_date,
    pv.verification_duration_seconds,
    pv.verified_at,
    u.email AS verified_by_email,
    u.admission_number AS verified_by_officer
FROM payment_verifications pv
INNER JOIN payment_submissions ps ON pv.submission_id = ps.submission_id
INNER JOIN users u ON pv.verified_by_user_id = u.user_id;

-- ============================================================================
-- TABLE 6: GREEN_CARDS
-- FIX: Changed expires_at from TIMESTAMP to DATETIME to avoid error
-- ============================================================================

CREATE TABLE green_cards (
    card_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL UNIQUE,
    registration_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Official university registration number',
    pdf_path VARCHAR(500) NOT NULL COMMENT 'Encrypted path to generated PDF',
    qr_code_data TEXT NOT NULL COMMENT 'Data encoded in QR code',
    qr_code_hash VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of QR data for verification',
    digital_signature TEXT NULL COMMENT 'OpenSSL digital signature',
    card_version VARCHAR(10) DEFAULT '1.0' COMMENT 'Green card template version',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL COMMENT 'Expiry date (typically one academic year)',
    is_active BOOLEAN DEFAULT TRUE,
    revoked_at TIMESTAMP NULL,
    revocation_reason TEXT NULL,
    revoked_by BIGINT UNSIGNED NULL,
    download_count INT UNSIGNED DEFAULT 0 COMMENT 'Track how many times downloaded',
    last_downloaded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_greencard_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    CONSTRAINT fk_greencard_submission 
        FOREIGN KEY (submission_id) REFERENCES payment_submissions(submission_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    CONSTRAINT fk_greencard_revoker 
        FOREIGN KEY (revoked_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_user_cards (user_id, is_active),
    INDEX idx_registration_number (registration_number),
    INDEX idx_expiry (expires_at, is_active)
) ENGINE=InnoDB COMMENT='Digital green card records';

-- ============================================================================
-- TABLE 7: DOCUMENT_UPLOADS
-- ============================================================================

CREATE TABLE document_uploads (
    document_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('admission_letter', 'bank_slip', 'id_photo', 'supporting_document') NOT NULL,
    file_path VARCHAR(500) NOT NULL COMMENT 'Encrypted storage path',
    file_name VARCHAR(255) NOT NULL COMMENT 'Original filename',
    file_size BIGINT UNSIGNED NOT NULL COMMENT 'File size in bytes',
    mime_type VARCHAR(100) NOT NULL,
    file_hash VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash for integrity verification',
    encryption_key_id VARCHAR(100) NULL COMMENT 'Reference to encryption key',
    is_encrypted BOOLEAN DEFAULT TRUE,
    ocr_extracted_text TEXT NULL COMMENT 'OCR extracted text if applicable',
    virus_scan_status ENUM('pending', 'clean', 'infected', 'error') DEFAULT 'pending',
    virus_scan_date TIMESTAMP NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_document_submission 
        FOREIGN KEY (submission_id) REFERENCES payment_submissions(submission_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_submission_docs (submission_id, document_type),
    INDEX idx_upload_date (uploaded_at)
) ENGINE=InnoDB COMMENT='Document upload tracking';

-- ============================================================================
-- TABLE 8: NOTIFICATIONS
-- ============================================================================

CREATE TABLE notifications (
    notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    notification_type ENUM('email', 'sms', 'in_app', 'push') NOT NULL,
    event_type VARCHAR(100) NOT NULL COMMENT 'e.g., submission_received, payment_verified',
    subject VARCHAR(255) NULL,
    message_body TEXT NOT NULL,
    template_name VARCHAR(100) NULL COMMENT 'Notification template used',
    delivery_status ENUM('queued', 'sending', 'sent', 'failed', 'bounced') NOT NULL DEFAULT 'queued',
    delivery_attempts TINYINT UNSIGNED DEFAULT 0,
    error_message TEXT NULL,
    provider_response JSON NULL COMMENT 'Response from SMS/Email provider',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL COMMENT 'For in-app notifications',
    expires_at TIMESTAMP NULL COMMENT 'Notification expiry (for in-app)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_notification_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_user_notifications (user_id, created_at DESC),
    INDEX idx_delivery_status (delivery_status, delivery_attempts),
    INDEX idx_event_type (event_type, created_at)
) ENGINE=InnoDB COMMENT='System notifications tracking';

-- ============================================================================
-- TABLE 9: AUDIT_LOGS
-- ============================================================================

CREATE TABLE audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL COMMENT 'NULL for system-generated actions',
    action VARCHAR(100) NOT NULL COMMENT 'e.g., CREATE, UPDATE, DELETE, LOGIN, LOGOUT',
    table_name VARCHAR(100) NULL COMMENT 'Affected table',
    record_id BIGINT UNSIGNED NULL COMMENT 'Affected record ID',
    old_value JSON NULL COMMENT 'Previous state of record',
    new_value JSON NULL COMMENT 'New state of record',
    changes_summary TEXT NULL COMMENT 'Human-readable summary of changes',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    user_agent TEXT NULL COMMENT 'Browser/client user agent',
    request_url VARCHAR(500) NULL COMMENT 'Request URL if applicable',
    request_method VARCHAR(10) NULL COMMENT 'HTTP method (GET, POST, etc.)',
    session_id VARCHAR(255) NULL COMMENT 'Session identifier',
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    timestamp TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Microsecond precision',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_audit_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_user_actions (user_id, timestamp DESC),
    INDEX idx_table_record (table_name, record_id, timestamp),
    INDEX idx_action_timestamp (action, timestamp),
    INDEX idx_severity (severity, timestamp)
) ENGINE=InnoDB COMMENT='Comprehensive audit trail';

-- ============================================================================
-- TABLE 10: SESSIONS
-- FIX: Changed expires_at from TIMESTAMP to DATETIME
-- ============================================================================

CREATE TABLE sessions (
    session_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE COMMENT 'Secure session token',
    refresh_token VARCHAR(255) NULL UNIQUE COMMENT 'JWT refresh token',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    device_type ENUM('desktop', 'mobile', 'tablet', 'other') DEFAULT 'other',
    browser_name VARCHAR(100) NULL,
    operating_system VARCHAR(100) NULL,
    location_country VARCHAR(100) NULL,
    location_city VARCHAR(100) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    terminated_at TIMESTAMP NULL,
    termination_reason ENUM('logout', 'timeout', 'forced', 'security') NULL,
    
    CONSTRAINT fk_session_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_user_sessions (user_id, is_active, last_activity),
    INDEX idx_session_token (session_token),
    INDEX idx_expiry (expires_at, is_active)
) ENGINE=InnoDB COMMENT='User session management';

-- ============================================================================
-- TABLE 11: REPORTS
-- ============================================================================

CREATE TABLE reports (
    report_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generated_by_user_id BIGINT UNSIGNED NOT NULL,
    report_type VARCHAR(100) NOT NULL COMMENT 'e.g., daily_submissions, monthly_verifications',
    report_title VARCHAR(255) NOT NULL,
    report_description TEXT NULL,
    file_path VARCHAR(500) NOT NULL COMMENT 'Path to generated report file',
    file_format ENUM('pdf', 'excel', 'csv', 'json') NOT NULL,
    file_size BIGINT UNSIGNED NULL COMMENT 'File size in bytes',
    parameters JSON NULL COMMENT 'Report generation parameters (date ranges, filters)',
    record_count INT UNSIGNED NULL COMMENT 'Number of records in report',
    generation_duration_seconds INT UNSIGNED NULL COMMENT 'Time taken to generate',
    is_scheduled BOOLEAN DEFAULT FALSE COMMENT 'Whether report is auto-generated',
    schedule_frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'Auto-delete date for old reports',
    download_count INT UNSIGNED DEFAULT 0,
    last_downloaded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_report_generator 
        FOREIGN KEY (generated_by_user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_generator_reports (generated_by_user_id, generated_at DESC),
    INDEX idx_report_type (report_type, generated_at),
    INDEX idx_scheduled_reports (is_scheduled, schedule_frequency)
) ENGINE=InnoDB COMMENT='Generated reports metadata';

-- ============================================================================
-- TABLE 12: SYSTEM_SETTINGS
-- ============================================================================

CREATE TABLE system_settings (
    setting_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'date', 'email') NOT NULL,
    setting_category VARCHAR(100) NOT NULL COMMENT 'e.g., email, sms, security, general',
    description TEXT NULL,
    is_editable BOOLEAN DEFAULT TRUE COMMENT 'Can be edited via admin panel',
    is_sensitive BOOLEAN DEFAULT FALSE COMMENT 'Contains sensitive data (encrypt)',
    validation_rules JSON NULL COMMENT 'Validation rules for setting value',
    default_value TEXT NULL,
    last_modified_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_setting_modifier 
        FOREIGN KEY (last_modified_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    INDEX idx_setting_category (setting_category),
    INDEX idx_editable_settings (is_editable, setting_category)
) ENGINE=InnoDB COMMENT='System configuration settings';

-- ============================================================================
-- TABLE 13: PASSWORD_RESETS
-- FIX: Changed expires_at from TIMESTAMP to DATETIME
-- ============================================================================

CREATE TABLE password_resets (
    reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_reset_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_user_resets (user_id, created_at DESC),
    INDEX idx_token (token, is_used, expires_at)
) ENGINE=InnoDB COMMENT='Password reset tokens';

-- ============================================================================
-- TABLE 14: EMAIL_VERIFICATION_TOKENS
-- FIX: Changed expires_at from TIMESTAMP to DATETIME
-- ============================================================================

CREATE TABLE email_verification_tokens (
    token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_verification_user 
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX idx_user_verifications (user_id, is_verified),
    INDEX idx_token (token, is_verified, expires_at)
) ENGINE=InnoDB COMMENT='Email verification tokens';

-- ============================================================================
-- VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW vw_active_students AS
SELECT 
    u.user_id,
    u.admission_number,
    u.email,
    sp.full_name,
    sp.program,
    sp.faculty,
    sp.intake_year,
    sp.intake_semester,
    sp.phone_number,
    u.created_at AS registration_date,
    u.last_login_at
FROM users u
INNER JOIN student_profiles sp ON u.user_id = sp.user_id
WHERE u.role = 'student' AND u.is_active = TRUE;

CREATE OR REPLACE VIEW vw_pending_verifications AS
SELECT 
    ps.submission_id,
    ps.user_id,
    u.admission_number,
    sp.full_name,
    sp.program,
    ps.submitted_amount,
    ps.required_amount,
    ps.status,
    ps.submitted_at,
    TIMESTAMPDIFF(HOUR, ps.submitted_at, NOW()) AS hours_pending
FROM payment_submissions ps
INNER JOIN users u ON ps.user_id = u.user_id
INNER JOIN student_profiles sp ON u.user_id = sp.user_id
WHERE ps.status IN ('pending', 'under_review')
ORDER BY ps.priority_level DESC, ps.submitted_at ASC;

CREATE OR REPLACE VIEW vw_verification_statistics AS
SELECT 
    pv.verified_by_user_id,
    u.email AS officer_email,
    COUNT(*) AS total_verifications,
    SUM(CASE WHEN pv.is_approved = TRUE THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN pv.is_approved = FALSE THEN 1 ELSE 0 END) AS rejected_count,
    AVG(pv.verification_duration_seconds) AS avg_verification_time_seconds,
    MIN(pv.verified_at) AS first_verification,
    MAX(pv.verified_at) AS last_verification
FROM payment_verifications pv
INNER JOIN users u ON pv.verified_by_user_id = u.user_id
GROUP BY pv.verified_by_user_id, u.email;

CREATE OR REPLACE VIEW vw_active_green_cards AS
SELECT 
    gc.card_id,
    gc.registration_number,
    u.admission_number,
    sp.full_name,
    sp.program,
    gc.issued_at,
    gc.expires_at,
    gc.download_count,
    CASE 
        WHEN gc.expires_at < NOW() THEN 'expired'
        WHEN gc.expires_at < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'expiring_soon'
        ELSE 'active'
    END AS card_status
FROM green_cards gc
INNER JOIN users u ON gc.user_id = u.user_id
INNER JOIN student_profiles sp ON u.user_id = sp.user_id
WHERE gc.is_active = TRUE;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER //

CREATE PROCEDURE sp_complete_student_registration(
    IN p_user_id BIGINT UNSIGNED,
    IN p_submission_id BIGINT UNSIGNED,
    OUT p_registration_number VARCHAR(50),
    OUT p_card_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_year YEAR;
    DECLARE v_sequence INT;
    DECLARE v_prefix VARCHAR(10);
    
    START TRANSACTION;
    
    SET v_year = YEAR(NOW());
    SET v_prefix = CONCAT('KIU/', v_year, '/');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(registration_number, LENGTH(v_prefix) + 1) AS UNSIGNED)), 0) + 1
    INTO v_sequence
    FROM green_cards
    WHERE registration_number LIKE CONCAT(v_prefix, '%');
    
    SET p_registration_number = CONCAT(v_prefix, LPAD(v_sequence, 6, '0'));
    
    INSERT INTO green_cards (
        user_id,
        submission_id,
        registration_number,
        pdf_path,
        qr_code_data,
        qr_code_hash,
        expires_at
    ) VALUES (
        p_user_id,
        p_submission_id,
        p_registration_number,
        CONCAT('encrypted/greencards/', p_registration_number, '.pdf'),
        JSON_OBJECT('user_id', p_user_id, 'reg_number', p_registration_number),
        SHA2(CONCAT(p_user_id, p_registration_number, NOW()), 256),
        DATE_ADD(NOW(), INTERVAL 1 YEAR)
    );
    
    SET p_card_id = LAST_INSERT_ID();
    
    COMMIT;
END //

CREATE PROCEDURE sp_get_dashboard_statistics(
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT 
        COUNT(DISTINCT CASE WHEN ps.status = 'pending' THEN ps.submission_id END) AS pending_submissions,
        COUNT(DISTINCT CASE WHEN ps.status = 'under_review' THEN ps.submission_id END) AS under_review,
        COUNT(DISTINCT CASE WHEN ps.status = 'verified' THEN ps.submission_id END) AS verified_today,
        COUNT(DISTINCT CASE WHEN ps.status = 'rejected' THEN ps.submission_id END) AS rejected_today,
        COUNT(DISTINCT gc.card_id) AS green_cards_issued,
        AVG(pv.verification_duration_seconds) AS avg_verification_time,
        COUNT(DISTINCT u.user_id) AS new_users_registered
    FROM payment_submissions ps
    LEFT JOIN payment_verifications pv ON ps.submission_id = pv.submission_id
    LEFT JOIN green_cards gc ON ps.submission_id = gc.submission_id
    LEFT JOIN users u ON ps.user_id = u.user_id
    WHERE DATE(ps.submitted_at) BETWEEN p_start_date AND p_end_date
       OR DATE(u.created_at) BETWEEN p_start_date AND p_end_date;
END //

DELIMITER ;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

DELIMITER //

CREATE TRIGGER tr_after_verification_insert
AFTER INSERT ON payment_verifications
FOR EACH ROW
BEGIN
    UPDATE payment_submissions
    SET status = CASE 
        WHEN NEW.is_approved = TRUE THEN 'verified'
        ELSE 'rejected'
    END,
    reviewed_at = NEW.verified_at
    WHERE submission_id = NEW.submission_id;
END //

CREATE TRIGGER tr_after_user_update
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (
        user_id,
        action,
        table_name,
        record_id,
        old_value,
        new_value,
        ip_address,
        changes_summary
    ) VALUES (
        NEW.user_id,
        'UPDATE',
        'users',
        NEW.user_id,
        JSON_OBJECT(
            'email', OLD.email,
            'role', OLD.role,
            'is_active', OLD.is_active
        ),
        JSON_OBJECT(
            'email', NEW.email,
            'role', NEW.role,
            'is_active', NEW.is_active
        ),
        COALESCE(@current_ip_address, '0.0.0.0'),
        CONCAT('User account updated for ', NEW.email)
    );
END //

CREATE TRIGGER tr_after_greencard_download
BEFORE UPDATE ON green_cards
FOR EACH ROW
BEGIN
    IF NEW.last_downloaded_at IS NOT NULL AND (OLD.last_downloaded_at IS NULL OR NEW.last_downloaded_at > OLD.last_downloaded_at) THEN
        SET NEW.download_count = OLD.download_count + 1;
    END IF;
END //

DELIMITER ;

-- ============================================================================
-- INITIAL DATA
-- ============================================================================

INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_category, description, is_editable) VALUES
('app_name', 'KIU Automated Verification System', 'string', 'general', 'Application name', TRUE),
('app_version', '1.0.0', 'string', 'general', 'Current application version', FALSE),
('maintenance_mode', 'false', 'boolean', 'general', 'System maintenance mode flag', TRUE);

INSERT INTO users (admission_number, email, password_hash, role, email_verified_at, is_active) VALUES
('ADMIN001', 'admin@kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW(), TRUE);

-- ============================================================================
-- ALL ERRORS FIXED
-- ============================================================================
-- 
-- ✅ Database name: Greencard_system (as requested)
-- ✅ Removed generated column with subquery (payment_verifications)
-- ✅ Fixed expires_at TIMESTAMP errors (changed to DATETIME in 4 tables)
-- ✅ Created view for discrepancy_amount calculation
-- 
-- Schema is now 100% MySQL compatible and ready for deployment!
-- ============================================================================
