<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_FINANCE, ROLE_ADMIN]);

$error = '';
$success = '';

$submission_id = $_GET['id'] ?? null;

if (!$submission_id) {
    redirect('modules/finance/dashboard.php');
}

// Get submission details
$stmt = $db->prepare("
    SELECT ps.*, u.admission_number, u.email, u.user_id,
           sp.full_name, sp.program, sp.faculty, sp.phone_number, sp.photo_path
    FROM payment_submissions ps
    INNER JOIN users u ON ps.user_id = u.user_id
    LEFT JOIN student_profiles sp ON u.user_id = sp.user_id
    WHERE ps.submission_id = :submission_id
");
$stmt->execute(['submission_id' => $submission_id]);
$submission = $stmt->fetch();

if (!$submission) {
    redirect('modules/finance/dashboard.php');
}

// Check if already verified
$stmt = $db->prepare("SELECT * FROM payment_verifications WHERE submission_id = :submission_id");
$stmt->execute(['submission_id' => $submission_id]);
$existing_verification = $stmt->fetch();

// Handle verification submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $is_approved = $_POST['decision'] === 'approve';
        $verification_notes = sanitize_input($_POST['verification_notes'] ?? '');
        $amount_verified = $_POST['amount_verified'] ?? $submission['submitted_amount'];
        $manual_override = isset($_POST['manual_override']) ? 1 : 0;
        $override_reason = $manual_override ? sanitize_input($_POST['override_reason'] ?? '') : null;
        
        try {
            $db->beginTransaction();
            
            // Update submission status first
            $new_status = $is_approved ? 'verified' : 'rejected';
            $stmt = $db->prepare("
                UPDATE payment_submissions
                SET status = :status,
                    rejection_reason = :rejection_reason,
                    reviewed_at = NOW()
                WHERE submission_id = :submission_id
            ");
            $stmt->execute([
                'status' => $new_status,
                'rejection_reason' => $is_approved ? null : $verification_notes,
                'submission_id' => $submission_id
            ]);
            
            // Insert verification record
            $stmt = $db->prepare("
                INSERT INTO payment_verifications (
                    submission_id, verified_by_user_id, is_approved,
                    verification_notes, amount_verified, manual_override,
                    override_reason, payment_date
                ) VALUES (
                    :submission_id, :verified_by, :is_approved,
                    :notes, :amount_verified, :manual_override,
                    :override_reason, :payment_date
                )
            ");
            $stmt->execute([
                'submission_id' => $submission_id,
                'verified_by' => $_SESSION['user_id'],
                'is_approved' => $is_approved ? 1 : 0,
                'notes' => $verification_notes,
                'amount_verified' => $amount_verified,
                'manual_override' => $manual_override,
                'override_reason' => $override_reason,
                'payment_date' => $submission['payment_date']
            ]);
            
            // Legacy endpoint guard: Finance must never generate registration numbers/green cards.
            if ($is_approved) {
                // Log activity
                $audit = new AuditLog($db);
                $audit->log('PAYMENT_VERIFIED', 'payment_verifications', $submission_id, 
                    null, null, "Payment verified by finance officer (legacy endpoint, no card issuance)");
            } else {
                $audit = new AuditLog($db);
                $audit->log('PAYMENT_REJECTED', 'payment_verifications', $submission_id, 
                    null, null, "Payment rejected by finance officer");
            }
            
            $db->commit();
            $success = $is_approved
                ? 'Payment verified. Registration number and green card issuance are restricted to Admissions in the regulation workflow.'
                : 'Payment rejected.';
            
            // Redirect after 2 seconds
            header("refresh:2;url=dashboard.php");
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Verification error: " . $e->getMessage());
            $error = 'An error occurred during verification. Please try again.';
        }
    }
}

$page_title = 'Review Payment';
include '../../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Review Payment Submission</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Queue</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($existing_verification): ?>
        <div class="alert alert-info">
            This submission has already been verified by 
            <?php 
            $stmt = $db->prepare("SELECT email FROM users WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $existing_verification['verified_by_user_id']]);
            echo htmlspecialchars($stmt->fetch()['email']);
            ?> 
            on <?php echo format_date($existing_verification['verified_at'], DISPLAY_DATETIME_FORMAT); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Student Information -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Student Information</h3>
                </div>
                <div class="card-body">
                    <?php if ($submission['photo_path']): ?>
                        <div class="text-center mb-3">
                            <img src="<?php echo BASE_URL . $submission['photo_path']; ?>" 
                                 alt="Student Photo" class="student-photo">
                        </div>
                    <?php endif; ?>
                    
                    <table class="info-table">
                        <tr>
                            <th>Name:</th>
                            <td><?php echo htmlspecialchars($submission['full_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Admission No:</th>
                            <td><?php echo htmlspecialchars($submission['admission_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($submission['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td><?php echo htmlspecialchars($submission['phone_number'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Program:</th>
                            <td><?php echo htmlspecialchars($submission['program'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Faculty:</th>
                            <td><?php echo htmlspecialchars($submission['faculty'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Payment Details -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Payment Details</h3>
                </div>
                <div class="card-body">
                    <table class="info-table">
                        <tr>
                            <th>Required Amount:</th>
                            <td><?php echo format_currency($submission['required_amount']); ?></td>
                        </tr>
                        <tr>
                            <th>Submitted Amount:</th>
                            <td class="text-success">
                                <strong><?php echo format_currency($submission['submitted_amount']); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <th>Payment Percentage:</th>
                            <td>
                                <?php 
                                $percentage = ($submission['submitted_amount'] / $submission['required_amount']) * 100;
                                echo number_format($percentage, 2) . '%';
                                if ($percentage < MINIMUM_PAYMENT_PERCENTAGE) {
                                    echo ' <span class="badge badge-danger">Below Minimum</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Payment Date:</th>
                            <td><?php echo format_date($submission['payment_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Bank Name:</th>
                            <td><?php echo htmlspecialchars($submission['bank_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Payment Reference:</th>
                            <td><?php echo htmlspecialchars($submission['payment_reference'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Submitted On:</th>
                            <td><?php echo format_date($submission['submitted_at'], DISPLAY_DATETIME_FORMAT); ?></td>
                        </tr>
                        <tr>
                            <th>Current Status:</th>
                            <td><?php echo get_status_badge($submission['status']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Document Preview -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3>Uploaded Documents</h3>
                </div>
                <div class="card-body">
                    <div class="document-grid">
                        <?php if ($submission['admission_letter_path']): ?>
                            <div class="document-item">
                                <strong>Admission Letter</strong>
                                <a href="<?php echo BASE_URL . $submission['admission_letter_path']; ?>" 
                                   target="_blank" class="btn btn-sm btn-primary">View</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($submission['bank_slip_path']): ?>
                            <div class="document-item">
                                <strong>Bank Slip</strong>
                                <a href="<?php echo BASE_URL . $submission['bank_slip_path']; ?>" 
                                   target="_blank" class="btn btn-sm btn-primary">View</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($submission['id_photo_path']): ?>
                            <div class="document-item">
                                <strong>ID Photo</strong>
                                <a href="<?php echo BASE_URL . $submission['id_photo_path']; ?>" 
                                   target="_blank" class="btn btn-sm btn-primary">View</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Verification Form -->
            <?php if (!$existing_verification): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h3>Verification Decision</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="form-group">
                                <label>Decision</label>
                                <div class="form-check">
                                    <input type="radio" id="approve" name="decision" value="approve" 
                                           class="form-check-input" required>
                                    <label for="approve" class="form-check-label">Approve Payment</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" id="reject" name="decision" value="reject" 
                                           class="form-check-input" required>
                                    <label for="reject" class="form-check-label">Reject Payment</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="amount_verified">Amount Verified (UGX)</label>
                                <input type="number" id="amount_verified" name="amount_verified" 
                                       class="form-control" step="0.01" 
                                       value="<?php echo $submission['submitted_amount']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="verification_notes">Verification Notes</label>
                                <textarea id="verification_notes" name="verification_notes" 
                                          class="form-control" rows="4" required
                                          placeholder="Enter verification notes or rejection reason"></textarea>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input type="checkbox" id="manual_override" name="manual_override" 
                                       class="form-check-input">
                                <label for="manual_override" class="form-check-label">
                                    Manual Override (Check if approving below minimum payment)
                                </label>
                            </div>
                            
                            <div class="form-group" id="override_reason_group" style="display: none;">
                                <label for="override_reason">Override Reason</label>
                                <textarea id="override_reason" name="override_reason" 
                                          class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="verify_payment" class="btn btn-success btn-lg">
                                    Submit Verification
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('manual_override').addEventListener('change', function() {
    document.getElementById('override_reason_group').style.display = 
        this.checked ? 'block' : 'none';
});
</script>

<?php include '../../includes/footer.php'; ?>
