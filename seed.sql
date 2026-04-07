-- ============================================================================
-- KIU GREENCARD SYSTEM - SEED DATA
-- Comprehensive Test Data for Development and Testing
-- ============================================================================

USE Greencard_system;

-- Disable foreign key checks for clean insertion
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- CLEAR EXISTING DATA (Optional - comment out if you want to keep existing data)
-- ============================================================================
-- 
-- Using DELETE instead of TRUNCATE to avoid foreign key constraint errors
-- Tables must be deleted in reverse order of dependencies (children first)
-- 

-- Independent tables (no foreign key dependencies)
DELETE FROM audit_logs;
DELETE FROM email_verification_tokens;
DELETE FROM password_resets;
DELETE FROM sessions;

-- Reports (depends on users)
DELETE FROM reports;

-- Notifications (depends on users)
DELETE FROM notifications;

-- Green cards (depends on payment_submissions and users)
DELETE FROM green_cards;

-- Document uploads (depends on payment_submissions)
DELETE FROM document_uploads;

-- Payment verifications (depends on payment_submissions)
DELETE FROM payment_verifications;

-- Payment submissions (depends on users and fee_structures)
DELETE FROM payment_submissions;

-- Fee structures (depends on users)
DELETE FROM fee_structures;

-- Student profiles (depends on users)
DELETE FROM student_profiles;

-- Users (parent table)
DELETE FROM users;

-- Reset auto-increment counters
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE student_profiles AUTO_INCREMENT = 1;
ALTER TABLE fee_structures AUTO_INCREMENT = 1;
ALTER TABLE payment_submissions AUTO_INCREMENT = 1;
ALTER TABLE payment_verifications AUTO_INCREMENT = 1;
ALTER TABLE green_cards AUTO_INCREMENT = 1;
ALTER TABLE document_uploads AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE audit_logs AUTO_INCREMENT = 1;
ALTER TABLE sessions AUTO_INCREMENT = 1;
ALTER TABLE reports AUTO_INCREMENT = 1;
ALTER TABLE password_resets AUTO_INCREMENT = 1;
ALTER TABLE email_verification_tokens AUTO_INCREMENT = 1;

-- system_settings table is preserved

-- ============================================================================
-- TABLE 1: USERS (20 users: 1 admin, 2 finance, 1 registrar, 16 students)
-- ============================================================================

INSERT INTO users (user_id, admission_number, email, password_hash, role, mfa_enabled, email_verified_at, last_login_at, is_active) VALUES
-- Admin
(1, 'ADMIN001', 'admin@kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, NOW(), NOW(), TRUE),

-- Finance Officers
(2, 'FIN001', 'finance.manager@kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'finance_officer', TRUE, NOW(), NOW(), TRUE),
(3, 'FIN002', 'finance.officer@kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'finance_officer', FALSE, NOW(), NOW(), TRUE),

-- Registrar
(4, 'REG001', 'registrar@kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'registrar', TRUE, NOW(), NOW(), TRUE),

-- Students (16 students with varied profiles)
(5, 'KIU/2024/001', 'john.doe@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(6, 'KIU/2024/002', 'jane.smith@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(7, 'KIU/2024/003', 'david.williams@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(8, 'KIU/2024/004', 'sarah.johnson@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', TRUE, NOW(), NOW(), TRUE),
(9, 'KIU/2024/005', 'michael.brown@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(10, 'KIU/2024/006', 'emily.davis@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(11, 'KIU/2023/101', 'robert.taylor@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(12, 'KIU/2023/102', 'linda.anderson@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(13, 'KIU/2025/001', 'james.wilson@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), '2025-02-10 09:15:00', TRUE),
(14, 'KIU/2025/002', 'mary.moore@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), '2025-02-12 14:30:00', TRUE),
(15, 'KIU/2024/007', 'peter.martin@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(16, 'KIU/2024/008', 'grace.lee@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(17, 'KIU/2025/003', 'thomas.white@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, '2025-02-13 10:00:00', NULL, TRUE),
(18, 'KIU/2024/009', 'angela.harris@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(19, 'KIU/2024/010', 'daniel.clark@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NOW(), NOW(), TRUE),
(20, 'KIU/2025/004', 'susan.lewis@student.kiu.ac.ug', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', FALSE, NULL, NULL, TRUE);

-- ============================================================================
-- TABLE 2: STUDENT_PROFILES
-- ============================================================================

INSERT INTO student_profiles (user_id, full_name, date_of_birth, gender, nationality, phone_number, alternative_phone, address, city, country, postal_code, program, faculty, department, intake_year, intake_semester, student_type, study_mode, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship) VALUES
(5, 'John Muwanguzi Doe', '2002-05-15', 'male', 'Ugandan', '+256700123456', '+256750123456', 'Plot 123, Kansanga', 'Kampala', 'Uganda', '00256', 'Bachelor of Science in Computer Science', 'Faculty of Science and Technology', 'Computer Science', 2024, 'semester_1', 'undergraduate', 'full_time', 'Margaret Doe', '+256701234567', 'Mother'),
(6, 'Jane Nakato Smith', '2001-08-22', 'female', 'Ugandan', '+256702234567', NULL, 'Plot 45, Ntinda', 'Kampala', 'Uganda', '00256', 'Bachelor of Business Administration', 'Faculty of Business and Management', 'Business Administration', 2024, 'semester_1', 'undergraduate', 'full_time', 'Peter Smith', '+256703345678', 'Father'),
(7, 'David Okello Williams', '2003-03-10', 'male', 'Ugandan', '+256704456789', '+256755456789', 'Plot 78, Muyenga', 'Kampala', 'Uganda', '00256', 'Bachelor of Engineering (Civil)', 'Faculty of Engineering', 'Civil Engineering', 2024, 'semester_1', 'undergraduate', 'full_time', 'Grace Williams', '+256705567890', 'Mother'),
(8, 'Sarah Achieng Johnson', '2000-11-30', 'female', 'Kenyan', '+254701123456', NULL, 'Westlands Avenue', 'Nairobi', 'Kenya', '00100', 'Master of Business Administration', 'Faculty of Business and Management', 'Business Administration', 2024, 'semester_1', 'postgraduate', 'part_time', 'John Johnson', '+254702234567', 'Spouse'),
(9, 'Michael Wasswa Brown', '2002-07-18', 'male', 'Ugandan', '+256706678901', '+256751678901', 'Plot 234, Bugolobi', 'Kampala', 'Uganda', '00256', 'Bachelor of Medicine and Surgery', 'Faculty of Health Sciences', 'Medicine', 2024, 'semester_1', 'undergraduate', 'full_time', 'Rose Brown', '+256707789012', 'Mother'),
(10, 'Emily Nambi Davis', '2003-01-25', 'female', 'Ugandan', '+256708890123', NULL, 'Plot 56, Kololo', 'Kampala', 'Uganda', '00256', 'Bachelor of Arts in Economics', 'Faculty of Social Sciences', 'Economics', 2024, 'semester_1', 'undergraduate', 'full_time', 'David Davis', '+256709901234', 'Father'),
(11, 'Robert Ochieng Taylor', '2001-04-12', 'male', 'Ugandan', '+256710012345', '+256752012345', 'Plot 89, Makindye', 'Kampala', 'Uganda', '00256', 'Bachelor of Laws (LLB)', 'Faculty of Law', 'Law', 2023, 'semester_1', 'undergraduate', 'full_time', 'Alice Taylor', '+256711123456', 'Sister'),
(12, 'Linda Atim Anderson', '2000-09-05', 'female', 'Ugandan', '+256712234567', NULL, 'Plot 12, Naguru', 'Kampala', 'Uganda', '00256', 'Bachelor of Pharmacy', 'Faculty of Health Sciences', 'Pharmacy', 2023, 'semester_1', 'undergraduate', 'full_time', 'Patrick Anderson', '+256713345678', 'Father'),
(13, 'James Mugisha Wilson', '2004-06-20', 'male', 'Ugandan', '+256714456789', '+256753456789', 'Plot 167, Nakawa', 'Kampala', 'Uganda', '00256', 'Bachelor of Information Technology', 'Faculty of Science and Technology', 'Information Technology', 2025, 'semester_1', 'undergraduate', 'full_time', 'Susan Wilson', '+256715567890', 'Mother'),
(14, 'Mary Nalubega Moore', '2003-12-08', 'female', 'Ugandan', '+256716678901', NULL, 'Plot 201, Lubowa', 'Kampala', 'Uganda', '00256', 'Diploma in Nursing', 'Faculty of Health Sciences', 'Nursing', 2025, 'semester_1', 'diploma', 'full_time', 'Joseph Moore', '+256717789012', 'Father'),
(15, 'Peter Ssemakula Martin', '2002-02-14', 'male', 'Ugandan', '+256718890123', '+256754890123', 'Plot 45, Kyambogo', 'Kampala', 'Uganda', '00256', 'Bachelor of Architecture', 'Faculty of Engineering', 'Architecture', 2024, 'semester_2', 'undergraduate', 'full_time', 'Rebecca Martin', '+256719901234', 'Mother'),
(16, 'Grace Akello Lee', '2001-10-03', 'female', 'Ugandan', '+256720012345', NULL, 'Plot 78, Bukoto', 'Kampala', 'Uganda', '00256', 'Master of Public Health', 'Faculty of Health Sciences', 'Public Health', 2024, 'semester_2', 'postgraduate', 'evening', 'Michael Lee', '+256721123456', 'Spouse'),
(17, 'Thomas Kiprotich White', '2004-08-17', 'male', 'Kenyan', '+254703345678', '+254754345678', 'Riverside Drive', 'Nairobi', 'Kenya', '00100', 'Bachelor of Science in Mathematics', 'Faculty of Science and Technology', 'Mathematics', 2025, 'semester_1', 'undergraduate', 'full_time', 'Jane White', '+254704456789', 'Mother'),
(18, 'Angela Namuddu Harris', '2002-11-22', 'female', 'Ugandan', '+256722234567', NULL, 'Plot 134, Mbuya', 'Kampala', 'Uganda', '00256', 'Bachelor of Education', 'Faculty of Education', 'Education', 2024, 'semester_2', 'undergraduate', 'distance', 'George Harris', '+256723345678', 'Father'),
(19, 'Daniel Okoth Clark', '2003-05-30', 'male', 'Ugandan', '+256724456789', '+256755456789', 'Plot 90, Kisaasi', 'Kampala', 'Uganda', '00256', 'Bachelor of Science in Accounting', 'Faculty of Business and Management', 'Accounting', 2024, 'semester_2', 'undergraduate', 'full_time', 'Betty Clark', '+256725567890', 'Mother'),
(20, 'Susan Nabirye Lewis', '2004-01-11', 'female', 'Ugandan', '+256726678901', NULL, 'Plot 56, Naalya', 'Kampala', 'Uganda', '00256', 'Certificate in Business Management', 'Faculty of Business and Management', 'Business Management', 2025, 'semester_1', 'certificate', 'part_time', 'Francis Lewis', '+256727789012', 'Father');

-- ============================================================================
-- TABLE 3: FEE_STRUCTURES
-- ============================================================================

INSERT INTO fee_structures (program_name, faculty, student_type, study_mode, academic_year, semester, tuition_amount, functional_fees, other_fees, minimum_payment, currency, payment_deadline, late_payment_penalty, is_active, effective_from, effective_to, created_by) VALUES
-- Computer Science & IT Programs
('Bachelor of Science in Computer Science', 'Faculty of Science and Technology', 'undergraduate', 'full_time', '2024/2025', 'semester_1', 3500000.00, 500000.00, 200000.00, 2100000.00, 'UGX', '2024-10-31', 100000.00, TRUE, '2024-08-01', NULL, 1),
('Bachelor of Information Technology', 'Faculty of Science and Technology', 'undergraduate', 'full_time', '2025/2026', 'semester_1', 3600000.00, 500000.00, 200000.00, 2150000.00, 'UGX', '2025-10-31', 100000.00, TRUE, '2025-08-01', NULL, 1),

-- Business Programs
('Bachelor of Business Administration', 'Faculty of Business and Management', 'undergraduate', 'full_time', '2024/2025', 'semester_1', 3200000.00, 500000.00, 200000.00, 1950000.00, 'UGX', '2024-10-31', 100000.00, TRUE, '2024-08-01', NULL, 1),
('Master of Business Administration', 'Faculty of Business and Management', 'postgraduate', 'part_time', '2024/2025', 'semester_1', 5500000.00, 800000.00, 300000.00, 3300000.00, 'UGX', '2024-10-31', 150000.00, TRUE, '2024-08-01', NULL, 1),
('Bachelor of Science in Accounting', 'Faculty of Business and Management', 'undergraduate', 'full_time', '2024/2025', 'semester_2', 3200000.00, 500000.00, 200000.00, 1950000.00, 'UGX', '2025-03-31', 100000.00, TRUE, '2025-01-01', NULL, 1),
('Certificate in Business Management', 'Faculty of Business and Management', 'certificate', 'part_time', '2025/2026', 'semester_1', 1800000.00, 300000.00, 100000.00, 1100000.00, 'UGX', '2025-10-31', 50000.00, TRUE, '2025-08-01', NULL, 1),

-- Engineering Programs
('Bachelor of Engineering (Civil)', 'Faculty of Engineering', 'undergraduate', 'full_time', '2024/2025', 'semester_1', 4000000.00, 600000.00, 250000.00, 2425000.00, 'UGX', '2024-10-31', 120000.00, TRUE, '2024-08-01', NULL, 1),
('Bachelor of Architecture', 'Faculty of Engineering', 'undergraduate', 'full_time', '2024/2025', 'semester_2', 4200000.00, 600000.00, 250000.00, 2525000.00, 'UGX', '2025-03-31', 120000.00, TRUE, '2025-01-01', NULL, 1),

-- Health Sciences Programs
('Bachelor of Medicine and Surgery', 'Faculty of Health Sciences', 'undergraduate', 'full_time', '2024/2025', 'semester_1', 6000000.00, 800000.00, 400000.00, 3600000.00, 'UGX', '2024-10-31', 200000.00, TRUE, '2024-08-01', NULL, 1),
('Bachelor of Pharmacy', 'Faculty of Health Sciences', 'undergraduate', 'full_time', '2023/2024', 'semester_1', 5500000.00, 700000.00, 350000.00, 3275000.00, 'UGX', '2023-10-31', 180000.00, TRUE, '2023-08-01', NULL, 1),
('Diploma in Nursing', 'Faculty of Health Sciences', 'diploma', 'full_time', '2025/2026', 'semester_1', 2800000.00, 400000.00, 150000.00, 1675000.00, 'UGX', '2025-10-31', 80000.00, TRUE, '2025-08-01', NULL, 1),
('Master of Public Health', 'Faculty of Health Sciences', 'postgraduate', 'evening', '2024/2025', 'semester_2', 5000000.00, 700000.00, 300000.00, 3000000.00, 'UGX', '2025-03-31', 150000.00, TRUE, '2025-01-01', NULL, 1),

-- Law & Social Sciences
('Bachelor of Laws (LLB)', 'Faculty of Law', 'undergraduate', 'full_time', '2023/2024', 'semester_1', 3800000.00, 550000.00, 250000.00, 2300000.00, 'UGX', '2023-10-31', 110000.00, TRUE, '2023-08-01', NULL, 1),
('Bachelor of Arts in Economics', 'Faculty of Social Sciences', 'undergraduate', 'full_time', '2024/2025', 'semester_1', 3000000.00, 500000.00, 200000.00, 1850000.00, 'UGX', '2024-10-31', 90000.00, TRUE, '2024-08-01', NULL, 1),

-- Education
('Bachelor of Education', 'Faculty of Education', 'undergraduate', 'distance', '2024/2025', 'semester_2', 2500000.00, 400000.00, 150000.00, 1525000.00, 'UGX', '2025-03-31', 75000.00, TRUE, '2025-01-01', NULL, 1),

-- Mathematics
('Bachelor of Science in Mathematics', 'Faculty of Science and Technology', 'undergraduate', 'full_time', '2025/2026', 'semester_1', 3400000.00, 500000.00, 200000.00, 2050000.00, 'UGX', '2025-10-31', 100000.00, TRUE, '2025-08-01', NULL, 1);

-- ============================================================================
-- TABLE 4: PAYMENT_SUBMISSIONS
-- ============================================================================

INSERT INTO payment_submissions (user_id, fee_structure_id, admission_letter_path, bank_slip_path, id_photo_path, submitted_amount, required_amount, payment_reference, payment_date, bank_name, branch_name, status, rejection_reason, resubmission_count, priority_level, submitted_at, reviewed_at) VALUES
-- Verified submissions (8)
(5, 1, 'encrypted/docs/5_admission.pdf', 'encrypted/docs/5_bankslip.pdf', 'encrypted/docs/5_photo.jpg', 2100000.00, 2100000.00, 'KIU-PAY-2024-001', '2024-09-15', 'Stanbic Bank', 'Kampala Road', 'verified', NULL, 0, 'normal', '2024-09-16 10:30:00', '2024-09-16 14:45:00'),
(6, 3, 'encrypted/docs/6_admission.pdf', 'encrypted/docs/6_bankslip.pdf', 'encrypted/docs/6_photo.jpg', 1950000.00, 1950000.00, 'KIU-PAY-2024-002', '2024-09-18', 'Centenary Bank', 'Entebbe Road', 'verified', NULL, 0, 'normal', '2024-09-19 09:15:00', '2024-09-19 16:20:00'),
(7, 7, 'encrypted/docs/7_admission.pdf', 'encrypted/docs/7_bankslip.pdf', 'encrypted/docs/7_photo.jpg', 2425000.00, 2425000.00, 'KIU-PAY-2024-003', '2024-09-20', 'DFCU Bank', 'Nakasero', 'verified', NULL, 0, 'normal', '2024-09-21 11:00:00', '2024-09-21 15:30:00'),
(9, 9, 'encrypted/docs/9_admission.pdf', 'encrypted/docs/9_bankslip.pdf', 'encrypted/docs/9_photo.jpg', 3600000.00, 3600000.00, 'KIU-PAY-2024-005', '2024-09-22', 'Standard Chartered', 'Speke Road', 'verified', NULL, 0, 'urgent', '2024-09-23 08:45:00', '2024-09-23 13:00:00'),
(10, 14, 'encrypted/docs/10_admission.pdf', 'encrypted/docs/10_bankslip.pdf', 'encrypted/docs/10_photo.jpg', 1850000.00, 1850000.00, 'KIU-PAY-2024-006', '2024-09-25', 'Equity Bank', 'Kampala Road', 'verified', NULL, 0, 'normal', '2024-09-26 10:00:00', '2024-09-26 14:15:00'),
(11, 13, 'encrypted/docs/11_admission.pdf', 'encrypted/docs/11_bankslip.pdf', 'encrypted/docs/11_photo.jpg', 2300000.00, 2300000.00, 'KIU-PAY-2023-101', '2023-09-10', 'Bank of Africa', 'Kampala', 'verified', NULL, 0, 'normal', '2023-09-11 09:30:00', '2023-09-11 16:00:00'),
(12, 10, 'encrypted/docs/12_admission.pdf', 'encrypted/docs/12_bankslip.pdf', 'encrypted/docs/12_photo.jpg', 3275000.00, 3275000.00, 'KIU-PAY-2023-102', '2023-09-12', 'Absa Bank', 'Nakawa', 'verified', NULL, 0, 'normal', '2023-09-13 10:15:00', '2023-09-13 15:45:00'),
(15, 8, 'encrypted/docs/15_admission.pdf', 'encrypted/docs/15_bankslip.pdf', 'encrypted/docs/15_photo.jpg', 2525000.00, 2525000.00, 'KIU-PAY-2024-007', '2024-02-20', 'Stanbic Bank', 'Kololo', 'verified', NULL, 0, 'normal', '2024-02-21 11:30:00', '2024-02-21 16:00:00'),

-- Rejected submission (1)
(8, 4, 'encrypted/docs/8_admission.pdf', 'encrypted/docs/8_bankslip.pdf', 'encrypted/docs/8_photo.jpg', 3000000.00, 3300000.00, 'KIU-PAY-2024-004', '2024-09-21', 'Centenary Bank', 'Jinja Road', 'rejected', 'Amount paid is insufficient. Required minimum: UGX 3,300,000. Please top up the difference and resubmit.', 0, 'normal', '2024-09-22 10:30:00', '2024-09-22 14:00:00'),

-- Under review submissions (2)
(13, 2, 'encrypted/docs/13_admission.pdf', 'encrypted/docs/13_bankslip.pdf', 'encrypted/docs/13_photo.jpg', 2150000.00, 2150000.00, 'KIU-PAY-2025-001', '2025-02-10', 'DFCU Bank', 'Mbarara', 'under_review', NULL, 0, 'normal', '2025-02-11 09:00:00', NULL),
(14, 11, 'encrypted/docs/14_admission.pdf', 'encrypted/docs/14_bankslip.pdf', 'encrypted/docs/14_photo.jpg', 1675000.00, 1675000.00, 'KIU-PAY-2025-002', '2025-02-12', 'Equity Bank', 'Ntinda', 'under_review', NULL, 0, 'urgent', '2025-02-12 14:00:00', NULL),

-- Pending submissions (4)
(16, 12, 'encrypted/docs/16_admission.pdf', 'encrypted/docs/16_bankslip.pdf', 'encrypted/docs/16_photo.jpg', 3000000.00, 3000000.00, 'KIU-PAY-2025-003', '2025-02-13', 'Stanbic Bank', 'Kampala Road', 'pending', NULL, 0, 'very_urgent', '2025-02-13 08:30:00', NULL),
(17, 16, 'encrypted/docs/17_admission.pdf', 'encrypted/docs/17_bankslip.pdf', 'encrypted/docs/17_photo.jpg', 2050000.00, 2050000.00, 'KIU-PAY-2025-004', '2025-02-13', 'Bank of Africa', 'Nairobi Road', 'pending', NULL, 0, 'normal', '2025-02-13 11:45:00', NULL),
(18, 15, 'encrypted/docs/18_admission.pdf', 'encrypted/docs/18_bankslip.pdf', 'encrypted/docs/18_photo.jpg', 1525000.00, 1525000.00, 'KIU-PAY-2025-005', '2025-02-14', 'Centenary Bank', 'Kampala', 'pending', NULL, 0, 'normal', '2025-02-14 10:00:00', NULL),
(19, 5, 'encrypted/docs/19_admission.pdf', 'encrypted/docs/19_bankslip.pdf', 'encrypted/docs/19_photo.jpg', 1950000.00, 1950000.00, 'KIU-PAY-2025-006', '2025-02-14', 'Absa Bank', 'Jinja', 'pending', NULL, 0, 'normal', '2025-02-14 13:20:00', NULL);

-- ============================================================================
-- TABLE 5: PAYMENT_VERIFICATIONS
-- ============================================================================

INSERT INTO payment_verifications (submission_id, verified_by_user_id, finance_api_reference, finance_api_response, is_approved, verification_notes, amount_verified, manual_override, override_reason, payment_date, verification_duration_seconds, verified_at) VALUES
-- Approved verifications
(1, 2, 'FIN-API-2024-001', '{"status":"success","transaction_id":"TXN123456","amount":2100000,"verified":true}', TRUE, 'Payment verified successfully. All documents in order.', 2100000.00, FALSE, NULL, '2024-09-15', 245, '2024-09-16 14:45:00'),
(2, 3, 'FIN-API-2024-002', '{"status":"success","transaction_id":"TXN123457","amount":1950000,"verified":true}', TRUE, 'Verified. Student has paid the minimum required amount.', 1950000.00, FALSE, NULL, '2024-09-18', 180, '2024-09-19 16:20:00'),
(3, 2, 'FIN-API-2024-003', '{"status":"success","transaction_id":"TXN123458","amount":2425000,"verified":true}', TRUE, 'Payment confirmed with finance system.', 2425000.00, FALSE, NULL, '2024-09-20', 210, '2024-09-21 15:30:00'),
(4, 2, 'FIN-API-2024-005', '{"status":"success","transaction_id":"TXN123460","amount":3600000,"verified":true}', TRUE, 'Full payment received and verified.', 3600000.00, FALSE, NULL, '2024-09-22', 190, '2024-09-23 13:00:00'),
(5, 3, 'FIN-API-2024-006', '{"status":"success","transaction_id":"TXN123461","amount":1850000,"verified":true}', TRUE, 'Minimum payment verified successfully.', 1850000.00, FALSE, NULL, '2024-09-25', 165, '2024-09-26 14:15:00'),
(6, 2, 'FIN-API-2023-101', '{"status":"success","transaction_id":"TXN123350","amount":2300000,"verified":true}', TRUE, 'Verified. Payment matches requirement.', 2300000.00, FALSE, NULL, '2023-09-10', 220, '2023-09-11 16:00:00'),
(7, 3, 'FIN-API-2023-102', '{"status":"success","transaction_id":"TXN123351","amount":3275000,"verified":true}', TRUE, 'Full amount verified. Documents authentic.', 3275000.00, FALSE, NULL, '2023-09-12', 195, '2023-09-13 15:45:00'),
(8, 2, 'FIN-API-2024-007', '{"status":"success","transaction_id":"TXN123462","amount":2525000,"verified":true}', TRUE, 'Semester 2 payment verified successfully.', 2525000.00, FALSE, NULL, '2024-02-20', 175, '2024-02-21 16:00:00'),

-- Rejected verification
(9, 3, 'FIN-API-2024-004', '{"status":"partial","transaction_id":"TXN123459","amount":3000000,"required":3300000,"verified":false}', FALSE, 'Payment amount insufficient for MBA program. Student needs to pay additional UGX 300,000.', 3000000.00, FALSE, NULL, '2024-09-21', 150, '2024-09-22 14:00:00');

-- ============================================================================
-- TABLE 6: GREEN_CARDS
-- ============================================================================

INSERT INTO green_cards (user_id, submission_id, registration_number, pdf_path, qr_code_data, qr_code_hash, digital_signature, card_version, issued_at, expires_at, is_active, download_count, last_downloaded_at) VALUES
(5, 1, 'KIU/2024/000001', 'encrypted/greencards/KIU_2024_000001.pdf', '{"user_id":5,"reg":"KIU/2024/000001","name":"John Muwanguzi Doe","program":"BSc Computer Science","valid_until":"2025-09-16"}', 'a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3e5f7a9b1c3d5e7f9a1b3c5d7e9f1a3b5', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2024-09-16 15:00:00', '2025-09-16 23:59:59', TRUE, 3, '2024-12-10 09:30:00'),
(6, 2, 'KIU/2024/000002', 'encrypted/greencards/KIU_2024_000002.pdf', '{"user_id":6,"reg":"KIU/2024/000002","name":"Jane Nakato Smith","program":"BBA","valid_until":"2025-09-19"}', 'b4c6d8e0f2a4b6c8d0e2f4a6b8c0d2e4f6a8b0c2d4e6f8a0b2c4d6e8f0a2b4', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2024-09-19 16:30:00', '2025-09-19 23:59:59', TRUE, 2, '2024-11-15 14:20:00'),
(7, 3, 'KIU/2024/000003', 'encrypted/greencards/KIU_2024_000003.pdf', '{"user_id":7,"reg":"KIU/2024/000003","name":"David Okello Williams","program":"BEng Civil","valid_until":"2025-09-21"}', 'c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3e5f7a9b1c3d5e7f9a1b3c5', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2024-09-21 15:45:00', '2025-09-21 23:59:59', TRUE, 1, '2024-09-21 16:00:00'),
(9, 4, 'KIU/2024/000004', 'encrypted/greencards/KIU_2024_000004.pdf', '{"user_id":9,"reg":"KIU/2024/000004","name":"Michael Wasswa Brown","program":"MBChB","valid_until":"2025-09-23"}', 'd6e8f0a2b4c6d8e0f2a4b6c8d0e2f4a6b8c0d2e4f6a8b0c2d4e6f8a0b2c4d6', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2024-09-23 13:15:00', '2025-09-23 23:59:59', TRUE, 4, '2025-01-20 11:45:00'),
(10, 5, 'KIU/2024/000005', 'encrypted/greencards/KIU_2024_000005.pdf', '{"user_id":10,"reg":"KIU/2024/000005","name":"Emily Nambi Davis","program":"BA Economics","valid_until":"2025-09-26"}', 'e7f9a1b3c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3e5f7a9b1c3d5e7', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2024-09-26 14:30:00', '2025-09-26 23:59:59', TRUE, 2, '2024-10-05 10:15:00'),
(11, 6, 'KIU/2023/000101', 'encrypted/greencards/KIU_2023_000101.pdf', '{"user_id":11,"reg":"KIU/2023/000101","name":"Robert Ochieng Taylor","program":"LLB","valid_until":"2024-09-11"}', 'f8a0b2c4d6e8f0a2b4c6d8e0f2a4b6c8d0e2f4a6b8c0d2e4f6a8b0c2d4e6f8', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2023-09-11 16:15:00', '2024-09-11 23:59:59', TRUE, 5, '2024-08-30 13:00:00'),
(12, 7, 'KIU/2023/000102', 'encrypted/greencards/KIU_2023_000102.pdf', '{"user_id":12,"reg":"KIU/2023/000102","name":"Linda Atim Anderson","program":"B.Pharm","valid_until":"2024-09-13"}', 'a9b1c3d5e7f9a1b3c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3e5f7a9', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2023-09-13 16:00:00', '2024-09-13 23:59:59', TRUE, 6, '2024-09-01 09:20:00'),
(15, 8, 'KIU/2024/000006', 'encrypted/greencards/KIU_2024_000006.pdf', '{"user_id":15,"reg":"KIU/2024/000006","name":"Peter Ssemakula Martin","program":"B.Arch","valid_until":"2025-02-21"}', 'b0c2d4e6f8a0b2c4d6e8f0a2b4c6d8e0f2a4b6c8d0e2f4a6b8c0d2e4f6f8a0', 'SIGNATURE_DATA_ENCRYPTED', '1.0', '2024-02-21 16:15:00', '2025-02-21 23:59:59', TRUE, 1, '2024-02-22 08:45:00');

-- ============================================================================
-- TABLE 7: DOCUMENT_UPLOADS
-- ============================================================================

INSERT INTO document_uploads (submission_id, document_type, file_path, file_name, file_size, mime_type, file_hash, encryption_key_id, is_encrypted, ocr_extracted_text, virus_scan_status, virus_scan_date, uploaded_at) VALUES
-- Documents for submission 1 (John Doe)
(1, 'admission_letter', 'encrypted/docs/5_admission.pdf', 'admission_letter_john_doe.pdf', 245680, 'application/pdf', '1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2', 'KEY-2024-001', TRUE, 'KAMPALA INTERNATIONAL UNIVERSITY ADMISSION LETTER Student Name: John Muwanguzi Doe...', 'clean', '2024-09-16 10:35:00', '2024-09-16 10:30:00'),
(1, 'bank_slip', 'encrypted/docs/5_bankslip.pdf', 'bank_slip_john_doe.pdf', 189320, 'application/pdf', '2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3', 'KEY-2024-002', TRUE, 'STANBIC BANK UGANDA Receipt No: KIU-PAY-2024-001 Amount: UGX 2,100,000...', 'clean', '2024-09-16 10:35:00', '2024-09-16 10:30:00'),
(1, 'id_photo', 'encrypted/docs/5_photo.jpg', 'passport_photo_john_doe.jpg', 87450, 'image/jpeg', '3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4', 'KEY-2024-003', TRUE, NULL, 'clean', '2024-09-16 10:35:00', '2024-09-16 10:30:00'),

-- Documents for submission 2 (Jane Smith)
(2, 'admission_letter', 'encrypted/docs/6_admission.pdf', 'admission_letter_jane_smith.pdf', 256780, 'application/pdf', '4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5', 'KEY-2024-004', TRUE, 'KAMPALA INTERNATIONAL UNIVERSITY ADMISSION LETTER Student Name: Jane Nakato Smith...', 'clean', '2024-09-19 09:20:00', '2024-09-19 09:15:00'),
(2, 'bank_slip', 'encrypted/docs/6_bankslip.pdf', 'bank_slip_jane_smith.pdf', 195420, 'application/pdf', '5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6', 'KEY-2024-005', TRUE, 'CENTENARY BANK Receipt No: KIU-PAY-2024-002 Amount: UGX 1,950,000...', 'clean', '2024-09-19 09:20:00', '2024-09-19 09:15:00'),
(2, 'id_photo', 'encrypted/docs/6_photo.jpg', 'passport_photo_jane_smith.jpg', 92340, 'image/jpeg', '6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7', 'KEY-2024-006', TRUE, NULL, 'clean', '2024-09-19 09:20:00', '2024-09-19 09:15:00'),

-- Documents for pending submission 11 (James Wilson)
(11, 'admission_letter', 'encrypted/docs/13_admission.pdf', 'admission_letter_james_wilson.pdf', 248900, 'application/pdf', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2', 'KEY-2025-001', TRUE, 'KAMPALA INTERNATIONAL UNIVERSITY ADMISSION LETTER Student Name: James Mugisha Wilson...', 'clean', '2025-02-11 09:05:00', '2025-02-11 09:00:00'),
(11, 'bank_slip', 'encrypted/docs/13_bankslip.pdf', 'bank_slip_james_wilson.pdf', 187650, 'application/pdf', 'b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3', 'KEY-2025-002', TRUE, 'DFCU BANK Receipt No: KIU-PAY-2025-001 Amount: UGX 2,150,000...', 'pending', NULL, '2025-02-11 09:00:00'),
(11, 'id_photo', 'encrypted/docs/13_photo.jpg', 'passport_photo_james_wilson.jpg', 89200, 'image/jpeg', 'c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4', 'KEY-2025-003', TRUE, NULL, 'pending', NULL, '2025-02-11 09:00:00');

-- ============================================================================
-- TABLE 8: NOTIFICATIONS
-- ============================================================================

INSERT INTO notifications (user_id, notification_type, event_type, subject, message_body, template_name, delivery_status, delivery_attempts, sent_at, delivered_at, read_at, priority) VALUES
-- Welcome notifications
(5, 'email', 'account_created', 'Welcome to KIU Greencard System', 'Dear John Doe, your account has been successfully created. You can now submit your payment documents.', 'welcome_email', 'sent', 1, '2024-09-15 08:00:00', '2024-09-15 08:01:00', '2024-09-15 08:30:00', 'normal'),
(6, 'email', 'account_created', 'Welcome to KIU Greencard System', 'Dear Jane Smith, your account has been successfully created. You can now submit your payment documents.', 'welcome_email', 'sent', 1, '2024-09-17 10:00:00', '2024-09-17 10:01:00', '2024-09-17 11:00:00', 'normal'),

-- Submission received notifications
(5, 'email', 'submission_received', 'Payment Documents Received', 'Your payment submission has been received and is being processed. Reference: KIU-PAY-2024-001', 'submission_received', 'sent', 1, '2024-09-16 10:35:00', '2024-09-16 10:36:00', '2024-09-16 11:00:00', 'high'),
(6, 'sms', 'submission_received', NULL, 'KIU: Your payment documents have been received. Ref: KIU-PAY-2024-002', 'submission_sms', 'sent', 1, '2024-09-19 09:20:00', '2024-09-19 09:21:00', NULL, 'high'),

-- Verification complete notifications
(5, 'email', 'payment_verified', 'Payment Verified Successfully', 'Congratulations! Your payment has been verified. You can now download your green card.', 'verification_success', 'sent', 1, '2024-09-16 14:50:00', '2024-09-16 14:51:00', '2024-09-16 15:00:00', 'urgent'),
(5, 'in_app', 'greencard_issued', 'Green Card Available', 'Your KIU Green Card is now ready for download!', 'greencard_ready', 'sent', 1, '2024-09-16 15:00:00', '2024-09-16 15:00:00', '2024-09-16 15:05:00', 'urgent'),
(6, 'email', 'payment_verified', 'Payment Verified Successfully', 'Congratulations! Your payment has been verified. You can now download your green card.', 'verification_success', 'sent', 1, '2024-09-19 16:25:00', '2024-09-19 16:26:00', '2024-09-19 17:00:00', 'urgent'),

-- Rejection notification
(8, 'email', 'payment_rejected', 'Payment Verification Issue', 'Your payment submission requires attention. Please review the rejection reason and resubmit.', 'verification_rejected', 'sent', 1, '2024-09-22 14:05:00', '2024-09-22 14:06:00', '2024-09-22 15:30:00', 'urgent'),
(8, 'sms', 'payment_rejected', NULL, 'KIU: Your payment needs attention. Check your email for details. Ref: KIU-PAY-2024-004', 'rejection_sms', 'sent', 1, '2024-09-22 14:05:00', '2024-09-22 14:06:00', NULL, 'urgent'),

-- Pending submission notifications
(13, 'in_app', 'submission_under_review', 'Documents Under Review', 'Your payment documents are currently being reviewed by our finance team.', 'under_review', 'sent', 1, '2025-02-11 09:05:00', '2025-02-11 09:05:00', '2025-02-11 10:00:00', 'normal'),
(16, 'email', 'submission_received', 'Payment Documents Received', 'Your payment submission has been received. Reference: KIU-PAY-2025-003', 'submission_received', 'sent', 1, '2025-02-13 08:35:00', '2025-02-13 08:36:00', NULL, 'high');

-- ============================================================================
-- TABLE 9: AUDIT_LOGS
-- ============================================================================

INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value, changes_summary, ip_address, user_agent, severity, timestamp) VALUES
(1, 'CREATE', 'users', 5, NULL, '{"email":"john.doe@student.kiu.ac.ug","role":"student"}', 'New student account created', '41.210.145.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'info', '2024-09-15 08:00:00'),
(2, 'UPDATE', 'payment_submissions', 1, '{"status":"pending"}', '{"status":"verified"}', 'Payment submission status updated to verified', '102.134.147.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'info', '2024-09-16 14:45:00'),
(2, 'CREATE', 'payment_verifications', 1, NULL, '{"submission_id":1,"is_approved":true}', 'Payment verification created and approved', '102.134.147.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'info', '2024-09-16 14:45:00'),
(2, 'CREATE', 'green_cards', 1, NULL, '{"user_id":5,"registration_number":"KIU/2024/000001"}', 'Green card generated for student', '102.134.147.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'info', '2024-09-16 15:00:00'),
(5, 'LOGIN', 'users', 5, NULL, NULL, 'Successful login', '41.210.145.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'info', '2024-09-16 09:00:00'),
(3, 'UPDATE', 'payment_submissions', 9, '{"status":"pending"}', '{"status":"rejected"}', 'Payment submission rejected due to insufficient amount', '197.239.5.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'warning', '2024-09-22 14:00:00'),
(1, 'UPDATE', 'system_settings', 1, '{"setting_value":"false"}', '{"setting_value":"true"}', 'Maintenance mode enabled', '102.134.147.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'warning', '2024-12-25 02:00:00'),
(1, 'UPDATE', 'system_settings', 1, '{"setting_value":"true"}', '{"setting_value":"false"}', 'Maintenance mode disabled', '102.134.147.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'info', '2024-12-25 08:00:00');

-- ============================================================================
-- TABLE 10: SESSIONS
-- ============================================================================

INSERT INTO sessions (user_id, session_token, refresh_token, ip_address, user_agent, device_type, browser_name, operating_system, location_country, location_city, is_active, last_activity, expires_at) VALUES
(1, 'sess_admin_7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c', 'ref_admin_1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d', '102.134.147.89', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'desktop', 'Chrome', 'Windows 10', 'Uganda', 'Kampala', TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
(2, 'sess_fin_8g9h0i1j2k3l4m5n6o7p8q9r0s1t2u3v', 'ref_fin_2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e', '102.134.147.90', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'desktop', 'Edge', 'Windows 10', 'Uganda', 'Kampala', TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
(5, 'sess_std_9h0i1j2k3l4m5n6o7p8q9r0s1t2u3v4w', 'ref_std_3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f', '41.210.145.23', 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36', 'mobile', 'Chrome Mobile', 'Android 12', 'Uganda', 'Kampala', TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
(6, 'sess_std_0i1j2k3l4m5n6o7p8q9r0s1t2u3v4w5x', 'ref_std_4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a', '197.239.5.100', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)', 'mobile', 'Safari', 'iOS 16', 'Uganda', 'Kampala', TRUE, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY));

-- ============================================================================
-- TABLE 11: REPORTS
-- ============================================================================

INSERT INTO reports (generated_by_user_id, report_type, report_title, report_description, file_path, file_format, file_size, parameters, record_count, generation_duration_seconds, is_scheduled, schedule_frequency, generated_at, download_count, last_downloaded_at) VALUES
(2, 'daily_verifications', 'Daily Verification Report - 2024-09-16', 'Summary of payment verifications processed on 2024-09-16', 'reports/daily_verification_20240916.pdf', 'pdf', 458920, '{"date":"2024-09-16","status":"all"}', 3, 12, TRUE, 'daily', '2024-09-16 23:00:00', 2, '2024-09-17 08:30:00'),
(2, 'monthly_submissions', 'Monthly Submissions Report - September 2024', 'All payment submissions received in September 2024', 'reports/monthly_submissions_202409.xlsx', 'excel', 892450, '{"month":"2024-09","include_rejected":true}', 45, 25, TRUE, 'monthly', '2024-10-01 00:30:00', 5, '2024-10-05 09:15:00'),
(1, 'system_audit', 'System Audit Report - Q3 2024', 'Comprehensive audit trail for Q3 2024', 'reports/audit_Q3_2024.pdf', 'pdf', 1245680, '{"quarter":3,"year":2024}', 1247, 45, FALSE, NULL, '2024-10-15 14:00:00', 1, '2024-10-15 14:30:00'),
(4, 'active_students', 'Active Students Report - 2025-02-14', 'List of all active students as of February 14, 2025', 'reports/active_students_20250214.csv', 'csv', 125340, '{"as_of_date":"2025-02-14"}', 156, 8, FALSE, NULL, '2025-02-14 10:00:00', 0, NULL);

-- ============================================================================
-- TABLE 12: PASSWORD_RESETS
-- ============================================================================

INSERT INTO password_resets (user_id, email, token, ip_address, user_agent, is_used, used_at, expires_at) VALUES
(8, 'sarah.johnson@student.kiu.ac.ug', 'reset_token_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', '197.239.5.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', TRUE, '2024-10-05 14:30:00', '2024-10-05 16:00:00'),
(13, 'james.wilson@student.kiu.ac.ug', 'reset_token_q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2', '41.210.145.67', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', FALSE, NULL, '2025-02-15 12:00:00');

-- ============================================================================
-- TABLE 13: EMAIL_VERIFICATION_TOKENS
-- ============================================================================

INSERT INTO email_verification_tokens (user_id, email, token, is_verified, verified_at, expires_at) VALUES
(5, 'john.doe@student.kiu.ac.ug', 'verify_g3h4i5j6k7l8m9n0o1p2q3r4s5t6u7v8', TRUE, '2024-09-15 08:15:00', '2024-09-16 08:00:00'),
(6, 'jane.smith@student.kiu.ac.ug', 'verify_w9x0y1z2a3b4c5d6e7f8g9h0i1j2k3l4', TRUE, '2024-09-17 10:30:00', '2024-09-18 10:00:00'),
(20, 'susan.lewis@student.kiu.ac.ug', 'verify_m5n6o7p8q9r0s1t2u3v4w5x6y7z8a9b0', FALSE, NULL, '2025-02-15 14:00:00');

-- ============================================================================
-- ADDITIONAL SYSTEM SETTINGS
-- ============================================================================

INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_category, description, is_editable, is_sensitive) VALUES
('smtp_host', 'smtp.kiu.ac.ug', 'string', 'email', 'SMTP server hostname', TRUE, FALSE),
('smtp_port', '587', 'integer', 'email', 'SMTP server port', TRUE, FALSE),
('smtp_username', 'noreply@kiu.ac.ug', 'email', 'email', 'SMTP authentication username', TRUE, FALSE),
('smtp_password', 'encrypted_password_here', 'string', 'email', 'SMTP authentication password', TRUE, TRUE),
('sms_api_key', 'encrypted_api_key_here', 'string', 'sms', 'SMS provider API key', TRUE, TRUE),
('sms_sender_id', 'KIU-UG', 'string', 'sms', 'SMS sender identifier', TRUE, FALSE),
('min_password_length', '8', 'integer', 'security', 'Minimum password length requirement', TRUE, FALSE),
('session_timeout_minutes', '60', 'integer', 'security', 'Session timeout in minutes', TRUE, FALSE),
('max_login_attempts', '5', 'integer', 'security', 'Maximum failed login attempts before lockout', TRUE, FALSE),
('lockout_duration_minutes', '30', 'integer', 'security', 'Account lockout duration in minutes', TRUE, FALSE),
('greencard_validity_days', '365', 'integer', 'general', 'Green card validity period in days', TRUE, FALSE),
('max_file_upload_size_mb', '10', 'integer', 'general', 'Maximum file upload size in megabytes', TRUE, FALSE),
('supported_file_types', '["pdf","jpg","jpeg","png"]', 'json', 'general', 'Supported file upload types', TRUE, FALSE);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- DATA VERIFICATION QUERIES (Optional - for testing)
-- ============================================================================

-- SELECT COUNT(*) AS total_users FROM users;
-- SELECT COUNT(*) AS total_students FROM student_profiles;
-- SELECT COUNT(*) AS total_submissions FROM payment_submissions;
-- SELECT COUNT(*) AS verified_submissions FROM payment_submissions WHERE status = 'verified';
-- SELECT COUNT(*) AS pending_submissions FROM payment_submissions WHERE status = 'pending';
-- SELECT COUNT(*) AS active_greencards FROM green_cards WHERE is_active = TRUE;

-- ============================================================================
-- SEED DATA SUMMARY
-- ============================================================================
-- 
-- ✅ 20 Users (1 admin, 2 finance officers, 1 registrar, 16 students)
-- ✅ 16 Student Profiles (complete demographic and academic data)
-- ✅ 16 Fee Structures (covering various programs and semesters)
-- ✅ 15 Payment Submissions (8 verified, 1 rejected, 2 under review, 4 pending)
-- ✅ 9 Payment Verifications (8 approved, 1 rejected)
-- ✅ 8 Green Cards (active and ready for download)
-- ✅ 9 Document Uploads (admission letters, bank slips, photos)
-- ✅ 10 Notifications (emails, SMS, in-app)
-- ✅ 8 Audit Logs (tracking system activities)
-- ✅ 4 Active Sessions
-- ✅ 4 Reports (daily, monthly, audit, student lists)
-- ✅ 2 Password Reset Tokens
-- ✅ 3 Email Verification Tokens
-- ✅ 13 System Settings (email, SMS, security configurations)
-- 
-- Database is now fully seeded with realistic test data!
-- ============================================================================
