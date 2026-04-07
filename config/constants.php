<?php
/**
 * System Constants
 * KIU Automated Tuition Verification & Green Card System
 */

if (!function_exists('kiu_detect_public_base_url')) {
	function kiu_detect_public_base_url(string $defaultBaseUrl): string
	{
		$envBase = getenv('KIU_PUBLIC_BASE_URL');
		if (is_string($envBase) && trim($envBase) !== '') {
			return rtrim(trim($envBase), '/') . '/';
		}

		$scheme = 'http';
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			$scheme = 'https';
		}

		$host = (string)($_SERVER['HTTP_HOST'] ?? '');
		$hostNameOnly = strtolower(preg_replace('/:\d+$/', '', $host));
		$isLocalHost = ($hostNameOnly === '' || $hostNameOnly === 'localhost' || $hostNameOnly === '127.0.0.1' || $hostNameOnly === '::1');

		if ($isLocalHost) {
			$candidateHost = (string)($_SERVER['SERVER_ADDR'] ?? '');
			if ($candidateHost === '' || $candidateHost === '127.0.0.1' || $candidateHost === '::1') {
				$resolved = @gethostbyname((string)gethostname());
				if (is_string($resolved) && $resolved !== '' && $resolved !== '127.0.0.1') {
					$candidateHost = $resolved;
				}
			}

			if ($candidateHost !== '' && $candidateHost !== '127.0.0.1' && $candidateHost !== '::1') {
				$port = (string)($_SERVER['SERVER_PORT'] ?? '');
				$needsPort = ($port !== '' && $port !== '80' && $port !== '443');
				$host = $candidateHost . ($needsPort ? ':' . $port : '');
			}
		}

		if ($host === '') {
			return $defaultBaseUrl;
		}

		return $scheme . '://' . $host . '/research/';
	}
}

// Application Settings
define('APP_NAME', 'KIU Automated Verification System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/research/');
define('PUBLIC_BASE_URL', kiu_detect_public_base_url(BASE_URL));
define('SITE_ROOT', dirname(__DIR__));

// File Upload Settings
define('UPLOAD_DIR', SITE_ROOT . '/uploads/');
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);

// Upload Subdirectories - Regulation Workflow
define('S6_CERTIFICATE_DIR', UPLOAD_DIR . 's6_certificates/');
define('NATIONAL_ID_DIR', UPLOAD_DIR . 'national_ids/');
define('SCHOOL_ID_DIR', UPLOAD_DIR . 'school_ids/');
define('PASSPORT_PHOTO_DIR', UPLOAD_DIR . 'passport_photos/');
define('BANK_SLIP_DIR', UPLOAD_DIR . 'bank_slips/');
define('ADMISSION_LETTER_DIR', UPLOAD_DIR . 'admission_letters/');
define('AWARD_LETTER_DIR', UPLOAD_DIR . 'award_letters/');
define('GREEN_CARD_DIR', UPLOAD_DIR . 'green_cards/');
define('QR_CODE_DIR', UPLOAD_DIR . 'qr_codes/');

// Legacy alias kept for backward compatibility in initialization.
define('ID_PHOTO_DIR', PASSPORT_PHOTO_DIR);

// Session Settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_NAME', 'KIU_SESSION');

// Security Settings
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes
define('JWT_SECRET', 'your-secret-key-change-in-production');
define('JWT_EXPIRY', 3600); // 1 hour
define('ENCRYPTION_KEY', 'your-encryption-key-change-in-production');

// Email Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'noreply@kiu.ac.ug');
define('SMTP_FROM_NAME', 'KIU Verification System');

// SMS Settings
define('SMS_GATEWAY', 'africas_talking'); // africas_talking or twilio
define('SMS_API_KEY', 'your-api-key');
define('SMS_API_SECRET', 'your-api-secret');
define('SMS_SENDER_ID', 'KIU');

// Payment Settings
define('MINIMUM_PAYMENT_PERCENTAGE', 50); // 50% of total fees
define('PAYMENT_CURRENCY', 'UGX');

// Green Card Settings
define('GREEN_CARD_VALIDITY_YEARS', 3);
define('GREEN_CARD_VALIDITY_DAYS', GREEN_CARD_VALIDITY_YEARS * 365);
define('GREEN_CARD_TEMPLATE', SITE_ROOT . '/templates/greencard_template.php');

// Pagination
define('RECORDS_PER_PAGE', 20);

// Date Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i:s');

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_FINANCE', 'finance_officer');
define('ROLE_REGISTRAR', 'registrar');
define('ROLE_ADMIN', 'admin');

// Payment Status - DEPRECATED (Use Workflow Status below)
define('STATUS_PENDING', 'pending');
define('STATUS_UNDER_REVIEW', 'under_review');
define('STATUS_VERIFIED', 'verified');
define('STATUS_REJECTED', 'rejected');

// Workflow Status - Regulation Compliant
define('STATUS_PENDING_ADMISSIONS', 'pending_admissions');
define('STATUS_UNDER_ADMISSIONS_REVIEW', 'under_admissions_review');
define('STATUS_ADMISSIONS_APPROVED', 'admissions_approved');
define('STATUS_ADMISSIONS_REJECTED', 'admissions_rejected');
define('STATUS_RESUBMISSION_REQUESTED', 'resubmission_requested');
define('STATUS_PENDING_FINANCE', 'pending_finance');
define('STATUS_UNDER_FINANCE_REVIEW', 'under_finance_review');
define('STATUS_FINANCE_APPROVED', 'finance_approved');
define('STATUS_FINANCE_REJECTED', 'finance_rejected');
define('STATUS_FINANCE_PENDING', 'finance_pending');
define('STATUS_PENDING_GREENCARD', 'pending_greencard');
define('STATUS_GREENCARD_ISSUED', 'greencard_issued');
define('STATUS_CANCELLED', 'cancelled');

// Notification Types
define('NOTIFY_EMAIL', 'email');
define('NOTIFY_SMS', 'sms');
define('NOTIFY_IN_APP', 'in_app');

// Error Messages
define('ERROR_UNAUTHORIZED', 'Unauthorized access');
define('ERROR_INVALID_INPUT', 'Invalid input provided');
define('ERROR_DATABASE', 'Database error occurred');
define('ERROR_FILE_UPLOAD', 'File upload failed');
define('ERROR_NOT_FOUND', 'Resource not found');

// Success Messages
define('SUCCESS_LOGIN', 'Login successful');
define('SUCCESS_LOGOUT', 'Logout successful');
define('SUCCESS_REGISTER', 'Registration successful');
define('SUCCESS_UPLOAD', 'File uploaded successfully');
define('SUCCESS_UPDATE', 'Update successful');
define('SUCCESS_DELETE', 'Deletion successful');

// API Settings
define('API_VERSION', 'v1');
define('API_BASE_URL', BASE_URL . 'api/' . API_VERSION . '/');

// Logging
define('LOG_DIR', SITE_ROOT . '/logs/');
define('ERROR_LOG', LOG_DIR . 'error.log');
define('AUDIT_LOG', LOG_DIR . 'audit.log');
define('DEBUG_MODE', true); // Set to false in production
