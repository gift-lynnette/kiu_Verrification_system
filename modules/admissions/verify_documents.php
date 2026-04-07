<?php
/**
 * Admissions Document Verification Module
 * Step 2: Admissions verifies documents and forwards to Finance
 * Step 7: After finance clearance, Admissions issues Green Card
 */

require_once '../../config/init.php';
require_login();
require_role(ROLE_REGISTRAR);

$user_id = $_SESSION['user_id'];
$submission_id = $_GET['id'] ?? null;

if (!$submission_id) {
    $_SESSION['error'] = 'No submission specified';
    redirect('modules/admissions/dashboard.php');
}

if (!table_exists($db, 'document_submissions')) {
    $_SESSION['error'] = "Database migration required: table 'document_submissions' not found. Run database_migration_regulation_workflow.sql.";
    redirect('modules/admissions/dashboard.php');
}

// Get submission details
if (!table_exists($db, 'admissions_verifications') || !table_exists($db, 'finance_clearances') || !table_exists($db, 'green_cards')) {
    $_SESSION['error'] = "Database migration required: one or more regulation tables are missing.";
    redirect('modules/admissions/dashboard.php');
}

$stmt = $db->prepare("
    SELECT ds.*, u.email, u.admission_number,
           av.verification_id, av.is_approved as adm_approved, av.registration_number as adm_reg_num,
           fc.clearance_id, fc.is_cleared,
           gc.card_id
    FROM document_submissions ds
    JOIN users u ON ds.user_id = u.user_id
    LEFT JOIN admissions_verifications av ON ds.submission_id = av.submission_id
    LEFT JOIN finance_clearances fc ON ds.submission_id = fc.submission_id
    LEFT JOIN green_cards gc ON ds.submission_id = gc.submission_id
    WHERE ds.submission_id = :submission_id
");
$stmt->execute(['submission_id' => $submission_id]);
$submission = $stmt->fetch();

if (!$submission) {
    $_SESSION['error'] = 'Submission not found';
    redirect('modules/admissions/dashboard.php');
}

// Mark as under review when admissions starts processing for the first time.
if (!$submission['adm_approved'] && $submission['status'] === 'pending_admissions') {
    try {
        $db->beginTransaction();
        transition_submission_status(
            $db,
            $submission_id,
            STATUS_PENDING_ADMISSIONS,
            STATUS_UNDER_ADMISSIONS_REVIEW,
            $user_id,
            'admissions',
            'Admissions review started'
        );

        $db->commit();
        $submission['status'] = 'under_admissions_review';
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Failed to mark admissions review start: " . $e->getMessage());
    }
}

$error = '';
$success = '';

// Handle document verification (Step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_documents'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $decision = $_POST['decision'] ?? '';
        $is_approved = $decision === 'approve';
        $is_resubmission = $decision === 'resubmit';
        $verification_notes = sanitize_input($_POST['verification_notes'] ?? '');
        $rejection_reason = $is_approved ? null : sanitize_input($_POST['rejection_reason'] ?? '');
        $flag_status = sanitize_input($_POST['flag_status'] ?? 'none');
        $flag_notes = sanitize_input($_POST['flag_notes'] ?? '');
        
        $s6_verified = isset($_POST['s6_verified']);
        $national_id_verified = isset($_POST['national_id_verified']);
        $school_id_verified = isset($_POST['school_id_verified']);
        $documents_authentic = isset($_POST['documents_authentic']);
        
        if (!in_array($flag_status, ['none', 'incomplete', 'suspicious', 'mismatch'], true)) {
            $flag_status = 'none';
        }

        if (!$is_approved && empty($rejection_reason)) {
            $error = $is_resubmission
                ? 'Resubmission reason is required'
                : 'Rejection reason is required when rejecting a submission';
        } else {
            try {
                $db->beginTransaction();

                // Re-read the latest submission state under lock to avoid duplicate processing
                // when the form is submitted twice.
                $stateStmt = $db->prepare("
                    SELECT submission_id, user_id, full_name, status
                    FROM document_submissions
                    WHERE submission_id = :submission_id
                    FOR UPDATE
                ");
                $stateStmt->execute(['submission_id' => $submission_id]);
                $currentSubmission = $stateStmt->fetch();

                if (!$currentSubmission) {
                    throw new Exception('Submission not found');
                }

                $processableStatuses = ['pending_admissions', 'under_admissions_review'];
                if (!in_array($currentSubmission['status'], $processableStatuses, true)) {
                    $db->commit();
                    $_SESSION['success'] = 'This submission was already processed by Admissions.';
                    redirect('modules/admissions/dashboard.php');
                }
                
                $registration_number = null;
                $new_status = $is_approved
                    ? 'pending_finance'
                    : ($is_resubmission ? 'resubmission_requested' : 'admissions_rejected');
                
                // Create or update verification record (submission_id is UNIQUE).
                $stmt = $db->prepare("
                    INSERT INTO admissions_verifications (
                        submission_id, verified_by_user_id, s6_certificate_verified,
                        national_id_verified, school_id_verified, documents_authentic,
                        is_approved, rejection_reason, verification_notes,
                        registration_number, registration_generated_at,
                        forwarded_to_finance, forwarded_at,
                        requested_resubmission, flag_status, flag_notes
                    ) VALUES (
                        :submission_id, :user_id, :s6_verified,
                        :national_id_verified, :school_id_verified, :documents_authentic,
                        :is_approved, :rejection_reason, :notes,
                        :reg_number, :reg_time, :forwarded, :forwarded_at,
                        :requested_resubmission, :flag_status, :flag_notes
                    )
                    ON DUPLICATE KEY UPDATE
                        verified_by_user_id = VALUES(verified_by_user_id),
                        s6_certificate_verified = VALUES(s6_certificate_verified),
                        national_id_verified = VALUES(national_id_verified),
                        school_id_verified = VALUES(school_id_verified),
                        documents_authentic = VALUES(documents_authentic),
                        is_approved = VALUES(is_approved),
                        rejection_reason = VALUES(rejection_reason),
                        verification_notes = VALUES(verification_notes),
                        registration_number = VALUES(registration_number),
                        registration_generated_at = VALUES(registration_generated_at),
                        forwarded_to_finance = VALUES(forwarded_to_finance),
                        forwarded_at = VALUES(forwarded_at),
                        requested_resubmission = VALUES(requested_resubmission),
                        flag_status = VALUES(flag_status),
                        flag_notes = VALUES(flag_notes),
                        verified_at = CURRENT_TIMESTAMP
                ");
                
                $stmt->execute([
                    'submission_id' => $submission_id,
                    'user_id' => $user_id,
                    's6_verified' => $s6_verified,
                    'national_id_verified' => $national_id_verified,
                    'school_id_verified' => $school_id_verified,
                    'documents_authentic' => $documents_authentic,
                    'is_approved' => $is_approved,
                    'rejection_reason' => $rejection_reason,
                    'notes' => $verification_notes,
                    'reg_number' => $registration_number,
                    'reg_time' => null,
                    'forwarded' => $is_approved ? 1 : 0,
                    'forwarded_at' => $is_approved ? date('Y-m-d H:i:s') : null,
                    'requested_resubmission' => $is_resubmission ? 1 : 0,
                    'flag_status' => $flag_status,
                    'flag_notes' => $flag_notes
                ]);
                
                transition_submission_status(
                    $db,
                    $submission_id,
                    $currentSubmission['status'],
                    $new_status,
                    $user_id,
                    'admissions',
                    $is_approved
                        ? 'Documents verified and forwarded to Finance'
                        : ($is_resubmission ? "Resubmission requested: {$rejection_reason}" : "Documents rejected: {$rejection_reason}"),
                    [
                        'admissions_flag_status' => $flag_status,
                        'admissions_flag_notes' => $flag_notes ?: null,
                        'resubmission_reason' => $is_approved ? null : $rejection_reason,
                        'resubmission_requested_at' => $is_resubmission ? date('Y-m-d H:i:s') : null
                    ]
                );
                
                // Send notifications
                $notification = new NotificationService($db);
                
                if ($is_approved) {
                    // Notify student
                    $notification->notify(
                        $currentSubmission['user_id'],
                        'admissions_approved',
                        'Documents Approved - Sent to Finance',
                        "Your documents have been verified by the Admissions Office and forwarded to Finance for tuition confirmation. Registration number will be issued after financial clearance.",
                        'normal',
                        [NOTIFY_IN_APP, NOTIFY_EMAIL]
                    );
                    
                    // Notify finance officers
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE role = 'finance_officer' AND is_active = TRUE");
                    $stmt->execute();
                    $finance_officers = $stmt->fetchAll();
                    
                    foreach ($finance_officers as $officer) {
                        $notification->notify(
                            $officer['user_id'],
                            'pending_finance_verification',
                            'New submission for payment verification',
                            "Student {$currentSubmission['full_name']} requires payment confirmation.",
                            'normal',
                            [NOTIFY_IN_APP, NOTIFY_EMAIL]
                        );
                    }
                } else {
                    // Notify student of rejection / resubmission request
                    $notification->notify(
                        $currentSubmission['user_id'],
                        $is_resubmission ? 'resubmission_requested' : 'admissions_rejected',
                        $is_resubmission ? 'Resubmission Requested by Admissions' : 'Document Verification Rejected',
                        $is_resubmission
                            ? "Admissions has requested document resubmission. Reason: {$rejection_reason}. Please update your documents and submit again."
                            : "Your document submission has been rejected by the Admissions Office. Reason: {$rejection_reason}. Please contact the admissions office or resubmit with correct documents.",
                        'normal',
                        [NOTIFY_IN_APP, NOTIFY_EMAIL]
                    );
                }
                
                // Log activity
                $audit = new AuditLog($db);
                $audit->log($user_id, 'DOCUMENT_VERIFY', 'admissions_verification', $submission_id,
                    $is_approved
                        ? "Approved documents and forwarded to finance"
                        : ($is_resubmission ? "Requested resubmission" : "Rejected documents"));
                
                $db->commit();
                
                $_SESSION['success'] = $is_approved 
                    ? "Documents approved and forwarded to Finance Department."
                    : ($is_resubmission ? 'Resubmission requested. Student has been notified.' : 'Documents rejected. Student has been notified.');
                redirect('modules/admissions/dashboard.php');
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
                error_log("Document verification error: " . $e->getMessage());
            }
        }
    }
}

// Handle green card issuance (Step 7)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_greencard'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } elseif (($_SESSION['role'] ?? '') !== ROLE_REGISTRAR) {
        $error = 'Only Admissions officers can issue green cards.';
    } else {
        try {
            $db->beginTransaction();
            $issuedCard = issue_green_card_for_submission(
                $db,
                (int)$submission_id,
                (int)$user_id,
                'admissions'
            );
            
            // Notify student
            $notification = new NotificationService($db);
            $notification->notify(
                $submission['user_id'],
                'greencard_issued',
                'Green Card Issued - Ready for Download',
                "Congratulations! Your Green Card has been issued. Registration Number: {$issuedCard['registration_number']}. You can now download your green card from your dashboard."
            );
            
            // Log activity
            $audit = new AuditLog($db);
            $audit->log($user_id, 'GREENCARD_ISSUE', 'green_card', $submission_id,
                "Issued green card. Card#: {$issuedCard['card_number']}, Reg#: {$issuedCard['registration_number']}");
            
            $db->commit();
            
            $_SESSION['success'] = $issuedCard['created']
                ? "Green card issued successfully! Card Number: {$issuedCard['card_number']}"
                : "Green card already exists. Card Number: {$issuedCard['card_number']}";
            redirect('modules/admissions/dashboard.php');
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
            error_log("Green card issuance error: " . $e->getMessage());
        }
    }
}

$page_title = 'Verify Documents';
include '../../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔍 Document Verification</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="verification-container">
        <!-- Student Information -->
        <div class="info-card">
            <h2>👤 Student Information</h2>
            <table class="info-table">
                <tr>
                    <th>Full Name:</th>
                    <td><?php echo htmlspecialchars($submission['full_name']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($submission['email']); ?></td>
                </tr>
                <tr>
                    <th>Date of Birth:</th>
                    <td><?php echo date('d/m/Y', strtotime($submission['date_of_birth'])); ?></td>
                </tr>
                <tr>
                    <th>Program:</th>
                    <td><?php echo htmlspecialchars($submission['program']); ?></td>
                </tr>
                <tr>
                    <th>Faculty:</th>
                    <td><?php echo htmlspecialchars($submission['faculty']); ?></td>
                </tr>
                <tr>
                    <th>Intake:</th>
                    <td><?php echo $submission['intake_year'] . ' - ' . ucwords(str_replace('_', ' ', $submission['intake_semester'])); ?></td>
                </tr>
                <tr>
                    <th>Current Status:</th>
                    <td><span class="status-badge status-<?php echo $submission['status']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $submission['status'])); ?>
                    </span></td>
                </tr>
                <?php if ($submission['registration_number']): ?>
                <tr>
                    <th>Registration Number:</th>
                    <td><strong><?php echo htmlspecialchars($submission['registration_number']); ?></strong></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Documents Review -->
        <div class="info-card">
            <h2>📄 Submitted Documents</h2>
            <div class="documents-grid">
                <?php if ($submission['admission_letter_path']): ?>
                <div class="document-item">
                    <h4>Admission Letter</h4>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=admission_letter" 
                       target="_blank" class="btn btn-sm btn-primary">View Document</a>
                </div>
                <?php endif; ?>

                <?php if ($submission['s6_certificate_path']): ?>
                <div class="document-item">
                    <h4>S.6 Certificate</h4>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=s6_certificate" 
                       target="_blank" class="btn btn-sm btn-primary">View Document</a>
                </div>
                <?php endif; ?>
                
                <?php if ($submission['national_id_path']): ?>
                <div class="document-item">
                    <h4>National ID</h4>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=national_id" 
                       target="_blank" class="btn btn-sm btn-primary">View Document</a>
                </div>
                <?php endif; ?>
                
                <?php if ($submission['school_id_path']): ?>
                <div class="document-item">
                    <h4>School ID</h4>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=school_id" 
                       target="_blank" class="btn btn-sm btn-primary">View Document</a>
                </div>
                <?php endif; ?>
                
                <?php if ($submission['passport_photo_path']): ?>
                <div class="document-item">
                    <h4>Passport Photo</h4>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=passport_photo" 
                       target="_blank" class="btn btn-sm btn-primary">View Photo</a>
                </div>
                <?php endif; ?>
                
                <?php if ($submission['bank_slip_path']): ?>
                <div class="document-item">
                    <h4>Bank Slip</h4>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=bank_slip" 
                       target="_blank" class="btn btn-sm btn-primary">View Receipt</a>
                </div>
                <?php endif; ?>

                <?php if (!empty($submission['is_bursary']) && !empty($submission['bursary_award_letter_path'])): ?>
                <div class="document-item">
                    <h4>Bursary Award Letter</h4>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=bursary_award_letter" 
                       target="_blank" class="btn btn-sm btn-primary">View Document</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="info-card">
            <h2>💰 Payment Information</h2>
            <table class="info-table">
                <tr>
                    <th>Amount Paid:</th>
                    <td><?php echo htmlspecialchars($submission['payment_currency'] ?? 'UGX'); ?> <?php echo number_format($submission['payment_amount'] ?? 0, 2); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Verification Form (if not yet verified) -->
        <?php if (!$submission['adm_approved'] && in_array($submission['status'], ['pending_admissions', 'under_admissions_review'], true)): ?>
        <div class="info-card">
            <h2>✅ Verification Decision</h2>
            <form method="POST" action="">
                <?php echo csrf_token_field(); ?>
                
                <div class="form-group">
                    <h4>Document Checklist:</h4>
                    <label class="checkbox-label">
                        <input type="checkbox" name="s6_verified" value="1">
                        S.6 Certificate is authentic and valid
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="national_id_verified" value="1">
                        National ID/Passport is valid
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="school_id_verified" value="1">
                        School ID is valid (if provided)
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="documents_authentic" value="1">
                        All documents appear authentic
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Decision *</label>
                    <select name="decision" class="form-control" required>
                        <option value="">Select Decision</option>
                        <option value="approve">✅ Approve - Forward to Finance</option>
                        <option value="resubmit">📝 Request Resubmission</option>
                        <option value="reject">❌ Reject</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Verification Notes</label>
                    <textarea name="verification_notes" class="form-control" rows="3" 
                              placeholder="Internal notes about this verification"></textarea>
                </div>

                <div class="form-group">
                    <label>Flag Status</label>
                    <select name="flag_status" class="form-control">
                        <option value="none">No Flag</option>
                        <option value="incomplete">Incomplete Documents</option>
                        <option value="suspicious">Suspicious Documents</option>
                        <option value="mismatch">Data Mismatch</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Flag Notes</label>
                    <textarea name="flag_notes" class="form-control" rows="2"
                              placeholder="Optional details for flagged issues"></textarea>
                </div>
                
                <div class="form-group" id="rejection-reason-group" style="display:none;">
                    <label id="decision-reason-label">Reason * (Will be sent to student)</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" 
                              placeholder="Explain the decision"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="verify_documents" class="btn btn-primary btn-lg">
                        📋 Submit Verification Decision
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Green Card Issuance (if finance approved) -->
        <?php if ((int)$submission['is_cleared'] === 1 && (int)$submission['adm_approved'] === 1 && $submission['status'] === 'pending_greencard' && !$submission['card_id']): ?>
        <div class="info-card">
            <h2>🟢 Issue Green Card</h2>
            <p>Finance has confirmed payment clearance. You can now issue the green card to this student.</p>
            
            <div class="alert alert-success">
                <strong>✅ Finance Clearance Confirmed</strong><br>
                Student: <?php echo htmlspecialchars($submission['full_name']); ?><br>
                Registration Number: <?php echo htmlspecialchars($submission['registration_number'] ?: 'Will be generated on issuance'); ?>
            </div>
            
            <form method="POST" action="">
                <?php echo csrf_token_field(); ?>
                <button type="submit" name="issue_greencard" class="btn btn-success btn-lg">
                    🟢 Issue Green Card Now
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Already Processed -->
        <?php if ($submission['card_id']): ?>
        <div class="alert alert-success">
            <strong>✅ Green Card Already Issued</strong><br>
            This student's green card has been issued and is ready for download.
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.verification-container {
    max-width: 1000px;
    margin: 0 auto;
}

.info-card {
    background: white;
    padding: 25px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table th,
.info-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.info-table th {
    width: 200px;
    font-weight: bold;
    color: #555;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.document-item {
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    text-align: center;
}

.document-item h4 {
    margin: 0 0 10px 0;
    color: #2c3e50;
}

.checkbox-label {
    display: block;
    padding: 10px;
    margin: 5px 0;
    background: #f8f9fa;
    border-radius: 5px;
}

.checkbox-label input {
    margin-right: 10px;
}

.status-badge {
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
}

.status-pending_admissions { background: #fff3cd; color: #856404; }
.status-under_admissions_review { background: #d1ecf1; color: #0c5460; }
.status-admissions_approved { background: #d4edda; color: #155724; }
.status-admissions_rejected { background: #f8d7da; color: #721c24; }
.status-resubmission_requested { background: #fff3cd; color: #856404; }
.status-pending_finance { background: #fff3cd; color: #856404; }
.status-under_finance_review { background: #d1ecf1; color: #0c5460; }
.status-finance_approved { background: #d1ecf1; color: #0c5460; }
.status-finance_rejected { background: #f8d7da; color: #721c24; }
.status-finance_pending { background: #fff3cd; color: #856404; }
.status-pending_greencard { background: #cfe2ff; color: #084298; }
.status-greencard_issued { background: #d4edda; color: #155724; }
</style>

<script>
// Show/hide rejection reason field
document.querySelector('select[name="decision"]')?.addEventListener('change', function() {
    const rejectionGroup = document.getElementById('rejection-reason-group');
    const reasonLabel = document.getElementById('decision-reason-label');
    if (this.value === 'reject' || this.value === 'resubmit') {
        rejectionGroup.style.display = 'block';
        rejectionGroup.querySelector('textarea').required = true;
        reasonLabel.textContent = this.value === 'resubmit'
            ? 'Resubmission Reason * (Will be sent to student)'
            : 'Rejection Reason * (Will be sent to student)';
    } else {
        rejectionGroup.style.display = 'none';
        rejectionGroup.querySelector('textarea').required = false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
