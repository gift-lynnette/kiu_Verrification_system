<?php
/**
 * Student Document Submission - Regulation Workflow
 * Step 1: Student submits academic documents to Admissions Office
 * 
 * Required Documents:
 * - Supporting Academic Document(s)
 * - National ID or Passport
 * - Former School ID (if applicable)
 * - Passport Photo
 * - Bank Slip (payment proof)
 */

require_once '../../config/init.php';
require_login();
require_role(ROLE_STUDENT);

if (!table_exists($db, 'document_submissions')) {
    $_SESSION['error'] = "Database migration required: table 'document_submissions' not found. Run database_migration_regulation_workflow.sql.";
    redirect('modules/student/dashboard.php');
}

if (!table_exists($db, 'workflow_history')) {
    $_SESSION['error'] = "Database migration required: table 'workflow_history' not found. Run database_migration_regulation_workflow.sql.";
    redirect('modules/student/dashboard.php');
}

// Ensure required columns exist for current document submission workflow.
try {
    $requiredColumns = [
        'payment_currency' => "payment_currency ENUM('UGX','USD') NOT NULL DEFAULT 'UGX' AFTER payment_amount",
        'admission_letter_path' => "admission_letter_path VARCHAR(500) NULL AFTER user_id",
        'is_bursary' => "is_bursary BOOLEAN NOT NULL DEFAULT FALSE AFTER admission_letter_path",
        'bursary_award_letter_path' => "bursary_award_letter_path VARCHAR(500) NULL AFTER is_bursary"
    ];

    foreach ($requiredColumns as $columnName => $definition) {
        $columnCheck = $db->prepare("SHOW COLUMNS FROM document_submissions LIKE :column_name");
        $columnCheck->execute(['column_name' => $columnName]);
        if (!$columnCheck->fetch()) {
            $db->exec("ALTER TABLE document_submissions ADD COLUMN {$definition}");
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Database update required: unable to prepare submission workflow columns. Please run latest migration.";
    redirect('modules/student/dashboard.php');
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];
$validator = new Validator();

// Check if student profile exists
$stmt = $db->prepare("SELECT * FROM student_profiles WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$profile = $stmt->fetch();
if (!$profile) {
    // Profile page may not exist in all deployments; allow direct submission using form fields.
    $profile = [];
}

$main_campus_units = [
    'College of Economics and Management (Main Campus)',
    'College of Education, Open and Distance Learning (Main Campus)',
    'College of Humanities and Social Sciences (Main Campus)',
    'School of Agriculture Sciences (Main Campus)',
    'School of Digital, Distance and E-Learning (Main Campus)',
    'School of Law (Main Campus)',
    'School of Mathematics and Computing (Main Campus)',
    'School of Natural and Applied Sciences (Main Campus)',
    'School of Professional Studies (Main Campus)',
    'School of Public Health (Main Campus)'
];

$program_catalog = [
    'certificate' => [
        'Certificate in Business Management',
        'Certificate in Information Technology',
        'Certificate in Public Administration',
        'Certificate in Journalism and Mass Communication'
    ],
    'bachelors' => [
        'Bachelor of Business Administration',
        'Bachelor of Science in Computer Science',
        'Bachelor of Information Technology',
        'Bachelor of Laws (LLB)',
        'Bachelor of Science in Accounting',
        'Bachelor of Education'
    ],
    'pgd' => [
        'Postgraduate Diploma in Monitoring and Evaluation',
        'Postgraduate Diploma in Project Planning and Management',
        'Postgraduate Diploma in Education',
        'Postgraduate Diploma in Public Administration'
    ],
    'masters' => [
        'Master of Business Administration (MBA)',
        'Master of Information Technology',
        'Master of Public Health',
        'Master of Education Management'
    ],
    'phd' => [
        'PhD in Business Administration',
        'PhD in Education',
        'PhD in Information Technology',
        'PhD in Public Health'
    ]
];

$allowed_payment_currencies = ['UGX', 'USD'];

// Check if student already has a pending submission
$stmt = $db->prepare("
    SELECT * FROM document_submissions 
    WHERE user_id = :user_id 
    AND status NOT IN ('admissions_rejected', 'finance_rejected', 'resubmission_requested', 'cancelled', 'greencard_issued')
    ORDER BY submitted_at DESC LIMIT 1
");
$stmt->execute(['user_id' => $user_id]);
$existing_submission = $stmt->fetch();

// Last submission for resubmission tracking.
$stmt = $db->prepare("
    SELECT submission_id, status, resubmission_count
    FROM document_submissions
    WHERE user_id = :user_id
    ORDER BY submitted_at DESC
    LIMIT 1
");
$stmt->execute(['user_id' => $user_id]);
$last_submission = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_documents'])) {
    $audit = new AuditLog($db);
    
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Validate form inputs
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $program_level = sanitize_input($_POST['program_level'] ?? '');
        $program = sanitize_input($_POST['program'] ?? '');
        $faculty = sanitize_input($_POST['faculty'] ?? '');
        $intake_year = $_POST['intake_year'] ?? '';
        $intake_semester = $_POST['intake_semester'] ?? '';
        
        // Payment information
        $payment_amount = $_POST['payment_amount'] ?? '';
        $payment_currency = strtoupper(sanitize_input($_POST['payment_currency'] ?? 'UGX'));
        $payment_reference = null;
        $payment_date = null;
        $is_bursary = isset($_POST['is_bursary']) ? 1 : 0;
        
        // Validate required fields
        $validator->required('full_name', $full_name, 'Full Name');
        $validator->required('date_of_birth', $date_of_birth, 'Date of Birth');
        $validator->date('date_of_birth', $date_of_birth, 'Date of Birth');
        $validator->required('program_level', $program_level, 'Program Level');
        $validator->required('program', $program, 'Program');
        $validator->required('faculty', $faculty, 'Faculty');
        $validator->required('intake_year', $intake_year, 'Intake Year');
        $validator->required('intake_semester', $intake_semester, 'Intake Semester');
        $validator->required('payment_amount', $payment_amount, 'Payment Amount');
        $validator->amount('payment_amount', $payment_amount, 'Payment Amount');
        $validator->required('payment_currency', $payment_currency, 'Payment Currency');

        if (!in_array($payment_currency, $allowed_payment_currencies, true)) {
            $validator->errors['payment_currency'] = 'Payment currency must be either UGX or USD';
        }

        if (!empty($program_level)) {
            if (!isset($program_catalog[$program_level])) {
                $validator->errors['program_level'] = 'Selected program level is invalid';
            } elseif (!in_array($program, $program_catalog[$program_level], true)) {
                $validator->errors['program'] = 'Selected program does not match the chosen program level';
            }
        }
        
        // Validate file uploads
        $s6_cert = $_FILES['s6_certificate'] ?? null;
        $admission_letter = $_FILES['admission_letter'] ?? null;
        $national_id = $_FILES['national_id'] ?? null;
        $school_id = $_FILES['school_id'] ?? null;
        $passport_photo = $_FILES['passport_photo'] ?? null;
        $bank_slip = $_FILES['bank_slip'] ?? null;
        $award_letter = $_FILES['award_letter'] ?? null;

        if (!$admission_letter || $admission_letter['error'] === UPLOAD_ERR_NO_FILE) {
            $validator->errors['admission_letter'] = 'Admission letter is required';
        }
        
        // Supporting document is required (PDF only)
        if (!$s6_cert || $s6_cert['error'] === UPLOAD_ERR_NO_FILE) {
            $validator->errors['s6_certificate'] = 'Document (PDF) is required';
        }
        
        // At least one ID is required (National ID or School ID)
        $has_national_id = $national_id && $national_id['error'] !== UPLOAD_ERR_NO_FILE;
        $has_school_id = $school_id && $school_id['error'] !== UPLOAD_ERR_NO_FILE;
        
        if (!$has_national_id && !$has_school_id) {
            $validator->errors['identification'] = 'Please upload either National ID or Former School ID';
        }
        
        // Passport photo is required
        if (!$passport_photo || $passport_photo['error'] === UPLOAD_ERR_NO_FILE) {
            $validator->errors['passport_photo'] = 'Passport photo is required';
        }
        
        // Bank slip is required
        if (!$bank_slip || $bank_slip['error'] === UPLOAD_ERR_NO_FILE) {
            $validator->errors['bank_slip'] = 'Bank slip (payment proof) is required';
        }

        if ($is_bursary && (!$award_letter || $award_letter['error'] === UPLOAD_ERR_NO_FILE)) {
            $validator->errors['award_letter'] = 'Bursary award letter is required for bursary students';
        }
        
        if (!$validator->hasErrors()) {
            try {
                $db->beginTransaction();
                
                // Create upload handlers
                $s6_uploader = new FileUpload(S6_CERTIFICATE_DIR, ['application/pdf']);
                $admission_uploader = new FileUpload(ADMISSION_LETTER_DIR, ['application/pdf']);
                $national_id_uploader = new FileUpload(NATIONAL_ID_DIR, ['application/pdf']);
                $school_id_uploader = new FileUpload(SCHOOL_ID_DIR, ['application/pdf']);
                $photo_uploader = new FileUpload(PASSPORT_PHOTO_DIR, ALLOWED_IMAGE_TYPES);
                $bank_uploader = new FileUpload(BANK_SLIP_DIR, ['application/pdf']);
                $award_uploader = new FileUpload(AWARD_LETTER_DIR, ['application/pdf']);
                
                // Upload files
                $s6_result = $s6_uploader->upload($s6_cert);
                $admission_result = $admission_uploader->upload($admission_letter);
                $national_id_result = $has_national_id ? $national_id_uploader->upload($national_id) : null;
                $school_id_result = $has_school_id ? $school_id_uploader->upload($school_id) : null;
                $photo_result = $photo_uploader->upload($passport_photo);
                $bank_result = $bank_uploader->upload($bank_slip);
                $award_result = ($is_bursary && $award_letter && $award_letter['error'] !== UPLOAD_ERR_NO_FILE)
                    ? $award_uploader->upload($award_letter)
                    : null;
                
                if ($s6_result && $admission_result && $photo_result && $bank_result) {
                    $toRelativePath = static function (?string $path): ?string {
                        if (!$path) {
                            return null;
                        }

                        $normalizedPath = str_replace('\\', '/', $path);
                        $normalizedRoot = str_replace('\\', '/', SITE_ROOT);

                        if (strpos($normalizedPath, $normalizedRoot) === 0) {
                            return ltrim(substr($normalizedPath, strlen($normalizedRoot)), '/');
                        }

                        return $normalizedPath;
                    };

                    $extractStoredPath = static function ($uploadResult) use ($toRelativePath): ?string {
                        if (!$uploadResult || !is_array($uploadResult)) {
                            return null;
                        }

                        $rawPath = $uploadResult['path']
                            ?? $uploadResult['relative_path']
                            ?? $uploadResult['encrypted_path']
                            ?? null;

                        return $toRelativePath($rawPath);
                    };

                    $admissionPath = $extractStoredPath($admission_result);
                    $awardPath = $extractStoredPath($award_result);
                    $s6Path = $extractStoredPath($s6_result);
                    $nationalIdPath = $extractStoredPath($national_id_result);
                    $schoolIdPath = $extractStoredPath($school_id_result);
                    $photoPath = $extractStoredPath($photo_result);
                    $bankPath = $extractStoredPath($bank_result);

                    if (!$admissionPath || !$s6Path || !$photoPath || !$bankPath) {
                        throw new Exception('File upload failed. Please try again.');
                    }

                    // Insert document submission
                    $stmt = $db->prepare("
                        INSERT INTO document_submissions (
                            user_id, admission_letter_path, is_bursary, bursary_award_letter_path,
                            s6_certificate_path, national_id_path, school_id_path,
                            passport_photo_path, bank_slip_path, full_name, date_of_birth,
                            program, faculty, intake_year, intake_semester,
                            payment_reference, payment_amount, payment_currency, payment_date, status, resubmission_count
                        ) VALUES (
                            :user_id, :admission_letter, :is_bursary, :award_letter,
                            :s6_cert, :national_id, :school_id,
                            :photo, :bank_slip, :full_name, :dob,
                            :program, :faculty, :intake_year, :intake_semester,
                            :payment_ref, :payment_amount, :payment_currency, :payment_date, 'pending_admissions', :resubmission_count
                        )
                    ");
                    
                    $stmt->execute([
                        'user_id' => $user_id,
                        'admission_letter' => $admissionPath,
                        'is_bursary' => $is_bursary,
                        'award_letter' => $awardPath,
                        's6_cert' => $s6Path,
                        'national_id' => $nationalIdPath,
                        'school_id' => $schoolIdPath,
                        'photo' => $photoPath,
                        'bank_slip' => $bankPath,
                        'full_name' => $full_name,
                        'dob' => $date_of_birth,
                        'program' => $program,
                        'faculty' => $faculty,
                        'intake_year' => $intake_year,
                        'intake_semester' => $intake_semester,
                        'payment_ref' => $payment_reference,
                        'payment_amount' => $payment_amount,
                        'payment_currency' => $payment_currency,
                        'payment_date' => $payment_date,
                        'resubmission_count' => (($last_submission && in_array($last_submission['status'], ['admissions_rejected', 'finance_rejected', 'resubmission_requested'], true))
                            ? ((int)$last_submission['resubmission_count'] + 1)
                            : 0)
                    ]);
                    
                    $submission_id = $db->lastInsertId();
                    
                    // Log workflow history
                    $stmt = $db->prepare("
                        INSERT INTO workflow_history (submission_id, from_status, to_status, changed_by_user_id, department, notes)
                        VALUES (:submission_id, :from_status, 'pending_admissions', :user_id, 'student', :notes)
                    ");
                    $stmt->execute([
                        'submission_id' => $submission_id,
                        'from_status' => ($last_submission && in_array($last_submission['status'], ['admissions_rejected', 'finance_rejected', 'resubmission_requested'], true)) ? $last_submission['status'] : null,
                        'user_id' => $user_id,
                        'notes' => ($last_submission && in_array($last_submission['status'], ['admissions_rejected', 'finance_rejected', 'resubmission_requested'], true))
                            ? 'Student resubmitted documents'
                            : 'Initial document submission'
                    ]);
                    
                    // Send notification to admissions office
                    $notification = new NotificationService($db);
                    $notification->notify(
                        $user_id,
                        'submission_received',
                        'Your documents have been submitted successfully',
                        'Your academic documents have been received and are now pending review by the Admissions Office. You will be notified once the review is complete.'
                    );
                    
                    // Notify admissions staff
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE role = 'registrar' AND is_active = TRUE");
                    $stmt->execute();
                    $registrars = $stmt->fetchAll();
                    
                    foreach ($registrars as $registrar) {
                        $notification->notify(
                            $registrar['user_id'],
                            'new_submission',
                            'New document submission pending review',
                            "Student {$full_name} has submitted documents for verification."
                        );
                    }
                    
                    // Log activity
                    $audit->log($user_id, 'DOCUMENT_SUBMIT', 'document_submission', $submission_id, 
                        "Student submitted documents for {$program}");
                    
                    $db->commit();
                    
                    $_SESSION['success'] = 'Your documents have been submitted successfully! They will be reviewed by the Admissions Office.';
                    redirect('modules/student/dashboard.php');
                    
                } else {
                    throw new Exception('File upload failed. Please try again.');
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
                error_log("Document submission error: " . $e->getMessage());
            }
        } else {
            $error = 'Please correct the errors below';
        }
    }
}

$page_title = 'Submit Documents';
include '../../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>📄 Submit Academic Documents</h1>
        <p>Submit your documents to the Admissions Office for verification</p>
    </div>
    
    <?php if ($existing_submission): ?>
    <div class="alert alert-info">
        <strong>📋 Existing Submission Found</strong>
        <p>You already have a submission in progress. Current status: <strong><?php echo ucwords(str_replace('_', ' ', $existing_submission['status'])); ?></strong></p>
        <p>Submitted on: <?php echo date('d/m/Y', strtotime($existing_submission['submitted_at'])); ?></p>
        <a href="dashboard.php" class="btn btn-primary">View Status</a>
    </div>
    <?php else: ?>

    <?php if ($last_submission && $last_submission['status'] === 'resubmission_requested'): ?>
    <div class="alert alert-warning">
        <strong>Resubmission Requested</strong>
        <p>Please address Admissions feedback and submit updated documents.</p>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($validator->errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($validator->errors as $field_error): ?>
            <li><?php echo htmlspecialchars($field_error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="workflow-info">
        <h3>📋 Document Submission Process</h3>
        <ol>
            <li><strong>You are here:</strong> Submit documents to Admissions Office</li>
            <li>Admissions verifies documents and generates Registration Number</li>
            <li>Admissions forwards to Finance for payment confirmation</li>
            <li>Finance confirms payment and sends clearance back</li>
            <li>Admissions issues your Green Card</li>
        </ol>
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data" class="document-form">
        <?php echo csrf_token_field(); ?>
        <?php $selected_faculty = $_POST['faculty'] ?? ($profile['faculty'] ?? ''); ?>
        <?php
            $selected_program = $_POST['program'] ?? ($profile['program'] ?? '');
            $selected_program_level = $_POST['program_level'] ?? '';
            if ($selected_program_level === '' && !empty($selected_program)) {
                foreach ($program_catalog as $level_key => $level_programs) {
                    if (in_array($selected_program, $level_programs, true)) {
                        $selected_program_level = $level_key;
                        break;
                    }
                }
            }
        ?>
        
        <div class="form-section">
            <h2>Personal Information</h2>
            
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" name="full_name" id="full_name" 
                       value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" 
                       class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="date_of_birth">Date of Birth *</label>
                <input type="date" name="date_of_birth" id="date_of_birth" 
                       value="<?php echo htmlspecialchars($profile['date_of_birth'] ?? ''); ?>" 
                       class="form-control" required>
            </div>
        </div>
        
        <div class="form-section">
            <h2>Academic Information</h2>
            
            <div class="form-group">
                <label for="program_level">What program have you enrolled in? *</label>
                <select name="program_level" id="program_level" class="form-control" required>
                    <option value="">--- Select Program Level ---</option>
                    <option value="certificate" <?php echo $selected_program_level === 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                    <option value="bachelors" <?php echo $selected_program_level === 'bachelors' ? 'selected' : ''; ?>>Bachelors</option>
                    <option value="pgd" <?php echo $selected_program_level === 'pgd' ? 'selected' : ''; ?>>PGD</option>
                    <option value="masters" <?php echo $selected_program_level === 'masters' ? 'selected' : ''; ?>>Masters</option>
                    <option value="phd" <?php echo $selected_program_level === 'phd' ? 'selected' : ''; ?>>PhD</option>
                </select>
            </div>

            <div class="form-group">
                <label for="program">Program Choice *</label>
                <select name="program" id="program" class="form-control" required>
                    <option value="">--- Select Program Choice ---</option>
                    <?php if (!empty($selected_program_level) && isset($program_catalog[$selected_program_level])): ?>
                        <?php foreach ($program_catalog[$selected_program_level] as $course): ?>
                        <option value="<?php echo htmlspecialchars($course); ?>" <?php echo $selected_program === $course ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course); ?>
                        </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="faculty">College/School/Faculty *</label>
                <select name="faculty" id="faculty" class="form-control" required>
                    <option value="">--- Select College/School/Faculty ---</option>
                    <?php foreach ($main_campus_units as $unit): ?>
                    <option value="<?php echo htmlspecialchars($unit); ?>" <?php echo $selected_faculty === $unit ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($unit); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="intake_year">Intake Year *</label>
                    <select name="intake_year" id="intake_year" class="form-control" required>
                        <option value="">Select Year</option>
                        <?php for ($year = date('Y'); $year >= date('Y') - 5; $year--): ?>
                        <option value="<?php echo $year; ?>" <?php echo ($profile['intake_year'] ?? '') == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="intake_semester">Intake Semester *</label>
                    <select name="intake_semester" id="intake_semester" class="form-control" required>
                        <option value="">Select Semester</option>
                        <option value="semester_1">Semester 1</option>
                        <option value="semester_2">Semester 2</option>
                        <option value="semester_3">Semester 3</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h2>Academic Documents</h2>
            <p class="form-help">These documents will be verified by the Admissions Office</p>

            <div class="form-group">
                  <label for="admission_letter">Admission Letter * (PDF)</label>
                <input type="file" name="admission_letter" id="admission_letter"
                      accept=".pdf,application/pdf" class="form-control" required>
                <small>Upload your official KIU admission letter</small>
            </div>
            
            <div class="form-group">
                  <label for="s6_certificate">Documents * (PDF)</label>
                <input type="file" name="s6_certificate" id="s6_certificate" 
                      accept=".pdf,application/pdf" class="form-control" required>
                  <small>Upload your required academic supporting document in PDF format</small>
            </div>
            
            <div class="form-group">
                  <label for="national_id">National ID * (PDF)</label>
                <input type="file" name="national_id" id="national_id" 
                      accept=".pdf,application/pdf" class="form-control">
                <small>Upload a copy of your National ID or Passport</small>
            </div>
            
            <div class="form-group">
                  <label for="school_id">Former School ID (PDF)</label>
                <input type="file" name="school_id" id="school_id" 
                      accept=".pdf,application/pdf" class="form-control">
                <small>Upload your former school ID (if you don't have National ID)</small>
            </div>
            
            <div class="form-group">
                <label for="passport_photo">Passport Photo * (JPG or PNG)</label>
                <input type="file" name="passport_photo" id="passport_photo" 
                       accept=".jpg,.jpeg,.png" class="form-control" required>
                <small>Passport-size photo (will appear on your Green Card)</small>
            </div>
            
            <p class="alert alert-info">
                <strong>Note:</strong> You must upload either National ID OR Former School ID (or both)
            </p>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_bursary" id="is_bursary" value="1">
                    I am a bursary-sponsored student
                </label>
            </div>

            <div class="form-group" id="award_letter_group" style="display:none;">
                  <label for="award_letter">Bursary Award Letter * (PDF)</label>
                <input type="file" name="award_letter" id="award_letter"
                      accept=".pdf,application/pdf" class="form-control">
                <small>Required for bursary-sponsored students</small>
            </div>
        </div>
        
        <div class="form-section">
            <h2>Payment Information</h2>
            <p class="form-help">This will be verified by the Finance Department after document approval</p>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="payment_amount">Amount Paid *</label>
                    <input type="number" name="payment_amount" id="payment_amount" 
                           step="0.01" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="payment_currency">Currency *</label>
                    <select name="payment_currency" id="payment_currency" class="form-control" required>
                        <option value="UGX" <?php echo (($_POST['payment_currency'] ?? 'UGX') === 'UGX') ? 'selected' : ''; ?>>UGX</option>
                        <option value="USD" <?php echo (($_POST['payment_currency'] ?? '') === 'USD') ? 'selected' : ''; ?>>USD</option>
                    </select>
                </div>
                
            </div>
            
            <div class="form-group">
                  <label for="bank_slip">Bank Slip/Receipt * (PDF)</label>
                <input type="file" name="bank_slip" id="bank_slip" 
                      accept=".pdf,application/pdf" class="form-control" required>
                <small>Upload your payment receipt/bank slip</small>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="submit_documents" class="btn btn-primary btn-lg">
                📤 Submit Documents to Admissions Office
            </button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    
    <?php endif; ?>
</div>

<style>
.workflow-info {
    background: #e8f4f8;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.workflow-info ol {
    margin: 10px 0 0 20px;
    line-height: 1.8;
}

.form-section {
    background: white;
    padding: 25px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-section h2 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

.form-help {
    color: #7f8c8d;
    font-style: italic;
    margin-bottom: 15px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-actions {
    text-align: center;
    padding: 30px 0;
}

.btn-lg {
    padding: 15px 30px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
const programCatalog = <?php echo json_encode($program_catalog, JSON_UNESCAPED_UNICODE); ?>;

function updateProgramChoices(selectedValue = '') {
    const levelSelect = document.getElementById('program_level');
    const programSelect = document.getElementById('program');
    if (!levelSelect || !programSelect) return;

    const level = levelSelect.value;
    const options = programCatalog[level] || [];

    programSelect.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '--- Select Program Choice ---';
    programSelect.appendChild(placeholder);

    options.forEach((course) => {
        const option = document.createElement('option');
        option.value = course;
        option.textContent = course;
        if (selectedValue && selectedValue === course) {
            option.selected = true;
        }
        programSelect.appendChild(option);
    });
}

document.getElementById('program_level')?.addEventListener('change', function() {
    updateProgramChoices('');
});

document.addEventListener('DOMContentLoaded', function() {
    const currentProgram = <?php echo json_encode($selected_program, JSON_UNESCAPED_UNICODE); ?>;
    updateProgramChoices(currentProgram);
});

document.getElementById('is_bursary')?.addEventListener('change', function() {
    const group = document.getElementById('award_letter_group');
    const input = document.getElementById('award_letter');
    if (!group || !input) return;
    if (this.checked) {
        group.style.display = 'block';
        input.required = true;
    } else {
        group.style.display = 'none';
        input.required = false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
