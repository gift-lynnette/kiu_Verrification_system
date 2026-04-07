<?php
/**
 * Finance Payment Clearance Module
 * Step 4-5: Finance confirms payment and sends clearance back to Admissions
 */

require_once '../../config/init.php';
require_login();
require_role(ROLE_FINANCE);

$user_id = $_SESSION['user_id'];
$submission_id = $_GET['id'] ?? null;

if (!$submission_id) {
    $_SESSION['error'] = 'No submission specified';
    redirect('modules/finance/dashboard.php');
}

if (!table_exists($db, 'document_submissions')) {
    $_SESSION['error'] = "Database migration required: table 'document_submissions' not found. Run database_migration_regulation_workflow.sql.";
    redirect('modules/finance/dashboard.php');
}

if (!table_exists($db, 'admissions_verifications') || !table_exists($db, 'finance_clearances')) {
    $_SESSION['error'] = "Database migration required: one or more regulation tables are missing.";
    redirect('modules/finance/dashboard.php');
}

// Get submission details
$stmt = $db->prepare("
    SELECT ds.*, u.email, u.admission_number,
           av.verification_id, av.is_approved, av.registration_number,
           fc.clearance_id, fc.is_cleared
    FROM document_submissions ds
    JOIN users u ON ds.user_id = u.user_id
    LEFT JOIN admissions_verifications av ON ds.submission_id = av.submission_id
    LEFT JOIN finance_clearances fc ON ds.submission_id = fc.submission_id
    WHERE ds.submission_id = :submission_id
");
$stmt->execute(['submission_id' => $submission_id]);
$submission = $stmt->fetch();

if (!$submission) {
    $_SESSION['error'] = 'Submission not found';
    redirect('modules/finance/dashboard.php');
}

// Check if admissions approved and in a finance-queue state
if (
    !$submission['is_approved'] ||
    !in_array($submission['status'], ['admissions_approved', 'pending_finance', 'under_finance_review', 'finance_pending'], true)
) {
    $_SESSION['error'] = 'This submission has not been approved by Admissions yet';
    redirect('modules/finance/dashboard.php');
}

// Mark as under review when finance starts processing for the first time.
if (!$submission['clearance_id'] && in_array($submission['status'], ['admissions_approved', 'pending_finance'], true)) {
    try {
        $db->beginTransaction();
        transition_submission_status(
            $db,
            $submission_id,
            $submission['status'],
            STATUS_UNDER_FINANCE_REVIEW,
            $user_id,
            'finance',
            'Finance review started'
        );

        $db->commit();
        $submission['status'] = 'under_finance_review';
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Failed to mark finance review start: " . $e->getMessage());
    }
}

$error = '';
$success = '';
$validator = new Validator();

// Get fee structure for this student
$stmt = $db->prepare("
    SELECT * FROM fee_structures 
    WHERE program_name = :program 
    AND semester = :semester
    AND is_active = TRUE
    ORDER BY effective_from DESC, created_at DESC LIMIT 1
");
$stmt->execute([
    'program' => $submission['program'],
    'semester' => $submission['intake_semester']
]);
$fee_structure = $stmt->fetch();

$fee_requirements = calculate_finance_fee_requirements($fee_structure, $submission['program'], $submission['faculty']);
$required_amount = $fee_requirements['total_required_fee'];
$minimum_payment = $fee_requirements['threshold_50_percent'];
$submission_currency = in_array(($submission['payment_currency'] ?? 'UGX'), ['UGX', 'USD'], true)
    ? $submission['payment_currency']
    : 'UGX';
$semester_exchange_rate = get_semester_exchange_rate_ugx($db, $submission['intake_year'], $submission['intake_semester']);
$slip_amount = extract_bank_slip_amount_for_submission($db, $submission_id, $submission['payment_amount'] ?? 0);
$extracted_amount_raw = (float)$slip_amount['amount'];
$amount_paid_ugx = convert_amount_to_ugx($extracted_amount_raw, $submission_currency, $semester_exchange_rate);
$bursary_check = get_bursary_status_for_submission($db, $submission);

$preview_threshold = ($bursary_check['status'] === 'Yes')
    ? $fee_requirements['bursary_tuition_threshold']
    : $fee_requirements['threshold_50_percent'];
$preview_outstanding = max(0, $preview_threshold - $amount_paid_ugx);
$preview_decision = $amount_paid_ugx >= $preview_threshold
    ? 'APPROVED'
    : ('DECLINED - Outstanding: UGX ' . number_format($preview_outstanding, 2));

if ($bursary_check['status'] === 'Pending Confirmation') {
    $preview_decision = 'PENDING - Bursary confirmation required';
}

// Handle payment clearance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $decision = $_POST['decision'] ?? '';
        $payment_amount_verified = $_POST['payment_amount_verified'] ?? '';
        $payment_date_verified = $_POST['payment_date_verified'] ?? '';
        $clearance_notes = sanitize_input($_POST['clearance_notes'] ?? '');
        $rejection_reason = sanitize_input($_POST['rejection_reason'] ?? '');
        $pending_reason = sanitize_input($_POST['pending_reason'] ?? '');
        $manual_override = isset($_POST['manual_override']);
        $override_reason = $manual_override ? sanitize_input($_POST['override_reason'] ?? '') : null;
        $partial_payment = isset($_POST['partial_payment']);
        $deferral_approved = isset($_POST['deferral_approved']);
        $deferral_reason = $deferral_approved ? sanitize_input($_POST['deferral_reason'] ?? '') : null;
        $finance_director_override = isset($_POST['finance_director_override']);
        $finance_director_override_reason = $finance_director_override
            ? sanitize_input($_POST['finance_director_override_reason'] ?? '')
            : null;
        $slip_addressed_to_kiu = isset($_POST['slip_addressed_to_kiu']);
        $slip_has_bank_stamp = isset($_POST['slip_has_bank_stamp']);
        $slip_has_valid_date = isset($_POST['slip_has_valid_date']);
        $slip_legible = isset($_POST['slip_legible']);
        $slip_appears_altered = isset($_POST['slip_appears_altered']);

        $bank_slip_valid = $slip_addressed_to_kiu && $slip_has_bank_stamp && $slip_has_valid_date && $slip_legible && !$slip_appears_altered;
        $fraud_flag = !$bank_slip_valid;
        $amount_verified_ugx = convert_amount_to_ugx((float)$payment_amount_verified, $submission_currency, $semester_exchange_rate);

        $threshold_for_decision = ($bursary_check['status'] === 'Yes')
            ? $fee_requirements['bursary_tuition_threshold']
            : $fee_requirements['threshold_50_percent'];

        $computed_decision = 'reject';
        $computed_reason = '';
        $outstanding_balance = max(0, $threshold_for_decision - $amount_verified_ugx);

        if ($fraud_flag) {
            $computed_decision = 'pending';
            $computed_reason = 'Bank slip is unclear, missing required marks, or appears altered. Manual review required.';
        } elseif ($bursary_check['status'] === 'Pending Confirmation') {
            $computed_decision = 'pending';
            $computed_reason = 'Bursary list confirmation from Bursary Department is still pending.';
        } elseif ($bursary_check['status'] === 'Yes') {
            if ($amount_verified_ugx >= $fee_requirements['bursary_tuition_threshold']) {
                $computed_decision = 'approve';
                $computed_reason = 'Student is on the forwarded bursary list and has paid at least 50% of tuition.';
            } else {
                $computed_decision = 'pending';
                $computed_reason = 'Bursary student payment is below 50% tuition threshold; awaiting payment confirmation.';
            }
        } else {
            if ($amount_verified_ugx >= $fee_requirements['threshold_50_percent']) {
                $computed_decision = 'approve';
                $computed_reason = 'Standard student meets the 50% threshold of total required fee.';
            } else {
                $computed_decision = 'reject';
                $computed_reason = 'Minimum payment threshold not met.';
            }
        }

        if (!$finance_director_override) {
            $decision = $computed_decision;
        }

        $is_cleared = $decision === 'approve';
        $is_pending = $decision === 'pending';
        
        // Validate
        $validator->required('payment_amount_verified', $payment_amount_verified, 'Verified Amount');
        $validator->amount('payment_amount_verified', $payment_amount_verified, 'Verified Amount');
        $validator->required('payment_date_verified', $payment_date_verified, 'Payment Date');
        $validator->date('payment_date_verified', $payment_date_verified, 'Payment Date');

        if (empty($fee_structure)) {
            $validator->errors['fee_structure'] = 'No active fee structure found for this programme and semester.';
        }

        if (!$slip_addressed_to_kiu || !$slip_has_bank_stamp || !$slip_has_valid_date || !$slip_legible) {
            $validator->errors['bank_slip_checks'] = 'Bank slip must be addressed to KIU, stamped, dated, and legible before processing.';
        }

        if ($finance_director_override && empty($finance_director_override_reason)) {
            $validator->errors['finance_director_override_reason'] = 'Finance Director override reason is required.';
        }
        
        if ($manual_override && empty($override_reason)) {
            $validator->errors['override_reason'] = 'Override reason is required';
        }

        if ($deferral_approved && empty($deferral_reason)) {
            $validator->errors['deferral_reason'] = 'Deferral reason is required';
        }

        if (
            $is_cleared &&
            !$manual_override &&
            !$finance_director_override &&
            !$deferral_approved &&
            $threshold_for_decision > 0 &&
            (float)$amount_verified_ugx < (float)$threshold_for_decision
        ) {
            $validator->errors['minimum_payment'] = 'Cannot approve: verified amount is below the required policy threshold.';
        }

        if ($decision === 'reject' && empty($rejection_reason)) {
            $rejection_reason = $computed_decision === 'reject'
                ? 'Minimum payment not met. Outstanding amount to reach threshold: UGX ' . number_format($outstanding_balance, 2)
                : $computed_reason;
        }

        if ($decision === 'pending' && empty($pending_reason)) {
            $pending_reason = $computed_reason;
        }

        if ($decision === 'reject' && empty($rejection_reason)) {
            $validator->errors['rejection_reason'] = 'Rejection reason is required';
        }

        if ($is_pending && empty($pending_reason)) {
            $validator->errors['pending_reason'] = 'Pending reason is required';
        }

        if ($finance_director_override) {
            $manual_override = true;
            $override_reason = trim(implode(' | ', array_filter([
                $override_reason,
                'Finance Director override: ' . $finance_director_override_reason
            ])));
        }

        $verification_summary = [
            'Student Name' => $submission['full_name'],
            'Programme' => $submission['program'],
            'Semester' => ucwords(str_replace('_', ' ', (string)$submission['intake_semester'])),
            'Tuition Fee' => 'UGX ' . number_format($fee_requirements['tuition_fee'], 2),
            'Functional Fee' => 'UGX ' . number_format($fee_requirements['functional_fee'], 2),
            'Total Required Fee' => 'UGX ' . number_format($fee_requirements['total_required_fee'], 2),
            '50% Threshold' => 'UGX ' . number_format($threshold_for_decision, 2),
            'Amount Paid (Bank Slip)' => $submission_currency . ' ' . number_format((float)$payment_amount_verified, 2),
            'Bursary Status' => $bursary_check['status']
        ];

        $decision_label = '❌ DECLINED — Minimum payment not met. Outstanding: UGX ' . number_format($outstanding_balance, 2);
        if ($decision === 'approve') {
            $decision_label = '✅ APPROVED — Forwarded to Admissions for Green Card issuance';
        } elseif ($decision === 'pending') {
            $decision_label = '⏳ PENDING — Bursary confirmation required';
        }

        $formatted_summary = [];
        foreach ($verification_summary as $label => $value) {
            $formatted_summary[] = $label . ': ' . $value;
        }
        $formatted_summary[] = 'DECISION: ' . $decision_label;
        $formatted_summary[] = 'Reason: ' . ($decision === 'approve'
            ? $computed_reason
            : ($decision === 'pending' ? ($pending_reason ?: $computed_reason) : ($rejection_reason ?: $computed_reason)));
        $formatted_summary_text = implode("\n", $formatted_summary);
        
        if (!$validator->hasErrors()) {
            try {
                $db->beginTransaction();
                
                $new_status = $is_cleared ? 'pending_greencard' : ($is_pending ? 'finance_pending' : 'finance_rejected');
                $finance_flag_status = 'none';
                if ($partial_payment) {
                    $finance_flag_status = 'partial_payment';
                } elseif ($deferral_approved) {
                    $finance_flag_status = 'deferral';
                } elseif ($is_pending) {
                    $finance_flag_status = 'pending_confirmation';
                }
                if ($fraud_flag) {
                    $finance_flag_status = 'pending_confirmation';
                }
                $finance_flag_notes = trim(implode(' | ', array_filter([
                    $partial_payment ? 'Partial payment flagged' : '',
                    $deferral_approved ? ('Deferral approved: ' . $deferral_reason) : '',
                    $is_pending ? ('Pending: ' . $pending_reason) : '',
                    $fraud_flag ? 'Fraud flag: bank slip requires manual review' : '',
                    $finance_director_override ? ('Finance Director override: ' . $finance_director_override_reason) : '',
                    'Bank slip check: KIU=' . ($slip_addressed_to_kiu ? 'Y' : 'N') . ', Stamp=' . ($slip_has_bank_stamp ? 'Y' : 'N') . ', Date=' . ($slip_has_valid_date ? 'Y' : 'N') . ', Legible=' . ($slip_legible ? 'Y' : 'N') . ', Altered=' . ($slip_appears_altered ? 'Y' : 'N'),
                    $clearance_notes
                ])));

                $clearance_notes = trim(implode("\n\n", array_filter([
                    $clearance_notes,
                    'Verification Summary',
                    $formatted_summary_text
                ])));
                
                // Insert or update clearance record
                if (!$submission['clearance_id']) {
                    $stmt = $db->prepare("
                        INSERT INTO finance_clearances (
                            submission_id, verified_by_user_id, payment_reference,
                            payment_amount_verified, payment_date_verified, required_amount,
                            is_cleared, clearance_notes, rejection_reason,
                            manual_override, override_reason, forwarded_to_admissions, forwarded_at,
                            is_pending, pending_reason, partial_payment, deferral_approved, deferral_reason
                        ) VALUES (
                            :submission_id, :user_id, :payment_ref,
                            :amount, :payment_date, :required_amount,
                            :is_cleared, :notes, :rejection_reason,
                            :manual_override, :override_reason, :forwarded, :forwarded_at,
                            :is_pending, :pending_reason, :partial_payment, :deferral_approved, :deferral_reason
                        )
                    ");
                } else {
                    $stmt = $db->prepare("
                        UPDATE finance_clearances
                        SET verified_by_user_id = :user_id,
                            payment_reference = :payment_ref,
                            payment_amount_verified = :amount,
                            payment_date_verified = :payment_date,
                            required_amount = :required_amount,
                            is_cleared = :is_cleared,
                            clearance_notes = :notes,
                            rejection_reason = :rejection_reason,
                            manual_override = :manual_override,
                            override_reason = :override_reason,
                            forwarded_to_admissions = :forwarded,
                            forwarded_at = :forwarded_at,
                            is_pending = :is_pending,
                            pending_reason = :pending_reason,
                            partial_payment = :partial_payment,
                            deferral_approved = :deferral_approved,
                            deferral_reason = :deferral_reason,
                            verified_at = NOW()
                        WHERE submission_id = :submission_id
                    ");
                }

                $stmt->execute([
                    'submission_id' => $submission_id,
                    'user_id' => $user_id,
                    'payment_ref' => $submission['payment_reference'],
                    'amount' => $payment_amount_verified,
                    'payment_date' => $payment_date_verified,
                    'required_amount' => $required_amount,
                    'is_cleared' => $is_cleared,
                    'notes' => $clearance_notes,
                    'rejection_reason' => $rejection_reason,
                    'manual_override' => $manual_override,
                    'override_reason' => $override_reason,
                    'forwarded' => $is_cleared ? 1 : 0,
                    'forwarded_at' => $is_cleared ? date('Y-m-d H:i:s') : null,
                    'is_pending' => $is_pending ? 1 : 0,
                    'pending_reason' => $pending_reason,
                    'partial_payment' => $partial_payment ? 1 : 0,
                    'deferral_approved' => $deferral_approved ? 1 : 0,
                    'deferral_reason' => $deferral_reason
                ]);

                transition_submission_status(
                    $db,
                    $submission_id,
                    $submission['status'],
                    $new_status,
                    $user_id,
                    'finance',
                    $is_cleared
                        ? "Payment cleared: {$submission_currency} " . number_format($payment_amount_verified, 2)
                        : ($is_pending ? "Payment marked pending: {$pending_reason}" : "Payment not cleared: {$rejection_reason}"),
                    [
                        'finance_flag_status' => $finance_flag_status,
                        'finance_flag_notes' => $finance_flag_notes ?: null
                    ]
                );

                // Send notifications
                $notification = new NotificationService($db);
                $notifyAdmissions = function($event_type, $subject, $message, $priority = 'normal') use ($db, $notification) {
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE role = :role AND is_active = TRUE");
                    $stmt->execute(['role' => ROLE_REGISTRAR]);
                    $registrars = $stmt->fetchAll();

                    foreach ($registrars as $registrar) {
                        $notification->notify(
                            $registrar['user_id'],
                            $event_type,
                            $subject,
                            $message,
                            $priority,
                            [NOTIFY_IN_APP, NOTIFY_EMAIL]
                        );
                    }
                };

                $notifyBursaryDesk = function($subject, $message) use ($db, $notification) {
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE role = :role AND is_active = TRUE");
                    $stmt->execute(['role' => ROLE_ADMIN]);
                    $admins = $stmt->fetchAll();

                    foreach ($admins as $admin) {
                        $notification->notify(
                            $admin['user_id'],
                            'bursary_pending_confirmation',
                            $subject,
                            $message,
                            'high',
                            [NOTIFY_IN_APP, NOTIFY_EMAIL]
                        );
                    }
                };
                
                if ($is_cleared) {
                    // Notify student
                    $notification->notify(
                        $submission['user_id'],
                        'finance_approved',
                        'Payment Confirmed - Sent to Admissions',
                        "Your payment has been confirmed by Finance and your application has been forwarded to Admissions for registration number and Green Card issuance."
                    );
                    
                    // Notify admissions staff
                    $notifyAdmissions(
                        'pending_greencard',
                        'Finance Cleared - Ready for Green Card Issuance',
                        "Student {$submission['full_name']} has been cleared by Finance and is ready for Admissions Green Card issuance.",
                        'normal'
                    );
                } elseif ($is_pending) {
                    $notification->notify(
                        $submission['user_id'],
                        'finance_pending',
                        'Financial Clearance Pending',
                        "Your payment review is pending additional confirmation. Reason: {$pending_reason}. Please monitor your dashboard for updates."
                    );

                    $notifyAdmissions(
                        'finance_pending',
                        'Finance Review Pending - Admissions Awareness',
                        "Finance has marked {$submission['full_name']}'s payment as pending. Reason: {$pending_reason}.",
                        'normal'
                    );

                    if ($bursary_check['status'] !== 'No') {
                        $notifyBursaryDesk(
                            'Bursary Payment Confirmation Needed',
                            "Student {$submission['full_name']} is pending finance clearance under bursary handling. Reason: {$pending_reason}."
                        );
                    }
                } else {
                    // Notify student of rejection
                    $notification->notify(
                        $submission['user_id'],
                        'finance_rejected',
                        'Payment Clearance Rejected',
                        "Your payment could not be confirmed by the Finance Department. Reason: {$rejection_reason}. Please contact the finance office for clarification."
                    );

                    $notifyAdmissions(
                        'finance_rejected',
                        'Finance Rejected Payment - Admissions Follow-up Needed',
                        "Finance rejected {$submission['full_name']}'s payment clearance. Reason: {$rejection_reason}. Admissions may guide the student on next steps.",
                        'high'
                    );
                }
                
                // Log activity
                $audit = new AuditLog($db);
                $audit->log(
                    'PAYMENT_VERIFY',
                    'finance_clearances',
                    $submission_id,
                    null,
                    [
                        'decision' => $decision,
                        'amount_verified' => (float)$payment_amount_verified,
                        'amount_verified_ugx' => $amount_verified_ugx,
                        'bursary_status' => $bursary_check['status'],
                        'rule_threshold_ugx' => $threshold_for_decision,
                        'fraud_flag' => $fraud_flag,
                        'summary' => $formatted_summary_text
                    ],
                    $is_cleared
                        ? 'Payment cleared under policy rules'
                        : ($is_pending ? 'Payment pending confirmation under policy rules' : 'Payment rejected under policy rules')
                );
                
                $db->commit();
                
                $_SESSION['success'] = $is_cleared
                    ? 'Payment confirmed and forwarded to Admissions for green card issuance'
                    : ($is_pending
                        ? 'Payment marked pending. Student has been notified.'
                        : 'Payment clearance rejected. Student has been notified.');
                redirect('modules/finance/dashboard.php');
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
                error_log("Payment clearance error: " . $e->getMessage());
            }
        } else {
            $error = 'Please correct the errors below';
        }
    }
}

$page_title = 'Verify Payment';
include '../../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>💰 Payment Clearance Verification</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($validator->errors ?? [])): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($validator->errors as $field_error): ?>
            <li><?php echo htmlspecialchars($field_error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
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
                    <th>Registration Number:</th>
                    <td><strong><?php echo htmlspecialchars($submission['registration_number']); ?></strong></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($submission['email']); ?></td>
                </tr>
                <tr>
                    <th>Program:</th>
                    <td><?php echo htmlspecialchars($submission['program']); ?></td>
                </tr>
                <tr>
                    <th>Faculty:</th>
                    <td><?php echo htmlspecialchars($submission['faculty']); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Admissions Verification Status -->
        <div class="info-card">
            <h2>✅ Admissions Office Status</h2>
            <div class="alert alert-success">
                <strong>Approved by Admissions Office</strong><br>
                Documents verified and forwarded to Finance. Registration number will be issued by Admissions after finance clearance.
            </div>
        </div>
        
        <!-- Fee Structure -->
        <?php if ($fee_structure): ?>
        <div class="info-card">
            <h2>📊 Fee Structure</h2>
            <table class="info-table">
                <tr>
                    <th>Tuition Amount:</th>
                    <td>UGX <?php echo number_format($fee_requirements['tuition_fee'], 2); ?></td>
                </tr>
                <tr>
                    <th>Functional Fees:</th>
                    <td>UGX <?php echo number_format($fee_requirements['functional_fee'], 2); ?></td>
                </tr>
                <?php if ($fee_requirements['research_fee'] > 0): ?>
                <tr>
                    <th>Research Fee:</th>
                    <td>UGX <?php echo number_format($fee_requirements['research_fee'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Total Required:</th>
                    <td><strong>UGX <?php echo number_format($fee_requirements['total_required_fee'], 2); ?></strong></td>
                </tr>
                <tr>
                    <th>Minimum Payment (50%):</th>
                    <td><strong>UGX <?php echo number_format($fee_requirements['threshold_50_percent'], 2); ?></strong></td>
                </tr>
                <?php if ($bursary_check['status'] === 'Yes'): ?>
                <tr>
                    <th>Bursary Tuition 50%:</th>
                    <td><strong>UGX <?php echo number_format($fee_requirements['bursary_tuition_threshold'], 2); ?></strong></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Payment Information -->
        <div class="info-card">
            <h2>💳 Payment Information Submitted</h2>
            <table class="info-table">
                <tr>
                    <th>Amount Claimed:</th>
                    <td><?php echo $submission_currency; ?> <?php echo number_format($submission['payment_amount'], 2); ?></td>
                </tr>
                <tr>
                    <th>Bank Slip Amount Source:</th>
                    <td><?php echo htmlspecialchars($slip_amount['source'] === 'ocr_text' ? 'Extracted from OCR' : 'Submitted amount field'); ?></td>
                </tr>
                <?php if ($submission_currency === 'USD'): ?>
                <tr>
                    <th>Semester FX Rate:</th>
                    <td>1 USD = UGX <?php echo number_format($semester_exchange_rate, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Bursary Status:</th>
                    <td><strong><?php echo htmlspecialchars($bursary_check['status']); ?></strong></td>
                </tr>
            </table>
            
            <div class="document-view">
                <?php if (!empty($submission['admission_letter_path'])): ?>
                <h4>Admission Letter:</h4>
                <a href="<?php echo BASE_URL; ?>modules/finance/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=admission_letter" 
                   target="_blank" class="btn btn-primary">📄 View Admission Letter</a>
                <?php endif; ?>

                <h4>Bank Slip/Receipt:</h4>
                <a href="<?php echo BASE_URL; ?>modules/finance/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=bank_slip" 
                   target="_blank" class="btn btn-primary">📄 View Bank Slip</a>

                <?php if (!empty($submission['is_bursary']) && !empty($submission['bursary_award_letter_path'])): ?>
                <h4 class="mt-3">Bursary Award Letter:</h4>
                <a href="<?php echo BASE_URL; ?>modules/finance/view_document.php?id=<?php echo (int)$submission['submission_id']; ?>&doc=bursary_award_letter" 
                   target="_blank" class="btn btn-primary">📄 View Award Letter</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Verification Form -->
        <?php if (!$submission['clearance_id'] || $submission['status'] === 'finance_pending'): ?>
        <div class="info-card">
            <h2>✅ Payment Clearance Decision</h2>
            <div class="payment-analysis">
                <h4>Rule-Based Preview</h4>
                <p><strong>Student Name:</strong> <?php echo htmlspecialchars($submission['full_name']); ?></p>
                <p><strong>Programme:</strong> <?php echo htmlspecialchars($submission['program']); ?></p>
                <p><strong>Semester:</strong> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $submission['intake_semester']))); ?></p>
                <p><strong>Tuition Fee:</strong> UGX <?php echo number_format($fee_requirements['tuition_fee'], 2); ?></p>
                <p><strong>Functional Fee:</strong> UGX <?php echo number_format($fee_requirements['functional_fee'], 2); ?></p>
                <p><strong>Total Required Fee:</strong> UGX <?php echo number_format($fee_requirements['total_required_fee'], 2); ?></p>
                <p><strong>50% Threshold:</strong> UGX <?php echo number_format($preview_threshold, 2); ?></p>
                <p><strong>Amount Paid (Bank Slip):</strong> <?php echo $submission_currency; ?> <?php echo number_format($submission['payment_amount'], 2); ?></p>
                <p><strong>Bursary Status:</strong> <?php echo htmlspecialchars($bursary_check['status']); ?></p>
                <p><strong>DECISION:</strong> <?php echo htmlspecialchars($preview_decision); ?></p>
            </div>

            <form method="POST" action="">
                <?php echo csrf_token_field(); ?>

                <div class="form-group">
                    <label>Bank Slip Integrity Checklist *</label>
                    <label class="checkbox-label"><input type="checkbox" name="slip_addressed_to_kiu" value="1" required> Slip addressed to KIU</label>
                    <label class="checkbox-label"><input type="checkbox" name="slip_has_bank_stamp" value="1" required> Valid bank stamp present</label>
                    <label class="checkbox-label"><input type="checkbox" name="slip_has_valid_date" value="1" required> Valid bank/date stamp</label>
                    <label class="checkbox-label"><input type="checkbox" name="slip_legible" value="1" required> Slip is legible and clear</label>
                    <label class="checkbox-label"><input type="checkbox" name="slip_appears_altered" value="1"> Slip appears altered/suspicious</label>
                    <small>If altered/suspicious is checked, the case is flagged for manual review.</small>
                </div>
                
                <div class="form-group">
                    <label>Verified Payment Amount (<?php echo $submission_currency; ?>) *</label>
                    <input type="number" name="payment_amount_verified" step="0.01" 
                           value="<?php echo htmlspecialchars($submission['payment_amount']); ?>"
                           class="form-control" required>
                    <small>Confirm the actual amount paid as per your finance system</small>
                </div>
                
                <div class="form-group">
                    <label>Verified Payment Date *</label>
                    <input type="date" name="payment_date_verified" 
                           value="<?php echo htmlspecialchars($submission['payment_date']); ?>"
                           class="form-control" required>
                </div>
                
                <?php if ($fee_structure): ?>
                <div class="payment-analysis">
                    <h4>Payment Analysis:</h4>
                    <p>Amount Claimed: <strong><?php echo $submission_currency; ?> <?php echo number_format($submission['payment_amount'], 2); ?></strong></p>
                    <p>Minimum Required: <strong>UGX <?php echo number_format($preview_threshold, 2); ?></strong></p>
                    <?php 
                    $base_amount = $preview_threshold > 0 ? $preview_threshold : 1;
                    $percentage = ($amount_paid_ugx / $base_amount) * 100;
                    $meets_minimum = $amount_paid_ugx >= $preview_threshold;
                    ?>
                    <p>Threshold Coverage: <strong><?php echo number_format($percentage, 2); ?>%</strong></p>
                    <p>Status: 
                        <span class="badge badge-<?php echo $meets_minimum ? 'success' : 'warning'; ?>">
                            <?php echo $meets_minimum ? '✅ Meets Minimum' : '⚠️ Below Minimum'; ?>
                        </span>
                    </p>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Decision *</label>
                    <select name="decision" class="form-control" required>
                        <option value="">Select Decision</option>
                        <option value="approve">✅ Confirm Payment - Forward to Admissions</option>
                        <option value="pending">⏳ Mark as Pending</option>
                        <option value="reject">❌ Reject - Payment Not Confirmed</option>
                    </select>
                    <small>The system applies policy rules automatically unless a Finance Director override is provided.</small>
                </div>
                
                <div class="form-group">
                    <label>Clearance Notes (Internal)</label>
                    <textarea name="clearance_notes" class="form-control" rows="3" 
                              placeholder="Internal notes about this payment verification"></textarea>
                </div>
                
                <div class="form-group" id="rejection-reason-group" style="display:none;">
                    <label>Rejection Reason * (Will be sent to student)</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" 
                              placeholder="Explain why the payment cannot be confirmed"></textarea>
                </div>

                <div class="form-group" id="pending-reason-group" style="display:none;">
                    <label>Pending Reason * (Will be sent to student)</label>
                    <textarea name="pending_reason" class="form-control" rows="3"
                              placeholder="Explain what is still pending for financial clearance"></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="partial_payment" value="1">
                        Flag as Partial Payment
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="deferral_approved" value="1" id="deferral-approved">
                        Approved Deferral Arrangement
                    </label>
                </div>

                <div class="form-group" id="deferral-reason-group" style="display:none;">
                    <label>Deferral Reason *</label>
                    <textarea name="deferral_reason" class="form-control" rows="3"
                              placeholder="Provide approved deferral details for audit"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="manual_override" value="1" id="manual-override">
                        Manual Override (Use for exceptional cases)
                    </label>
                </div>
                
                <div class="form-group" id="override-reason-group" style="display:none;">
                    <label>Override Reason * (Required for audit)</label>
                    <textarea name="override_reason" class="form-control" rows="3" 
                              placeholder="Explain why manual override is necessary"></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="finance_director_override" value="1" id="finance-director-override">
                        Finance Director Written Override Attached
                    </label>
                </div>

                <div class="form-group" id="finance-director-override-reason-group" style="display:none;">
                    <label>Finance Director Override Note *</label>
                    <textarea name="finance_director_override_reason" class="form-control" rows="3"
                              placeholder="Reference the written directive allowing exception"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="verify_payment" class="btn btn-primary btn-lg">
                        💳 Submit Clearance Decision
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <strong>ℹ️ Already Processed</strong><br>
            This payment has already been verified.
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
    width: 250px;
    font-weight: bold;
    color: #555;
}

.document-view {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.payment-analysis {
    padding: 15px;
    background: #e8f4f8;
    border-left: 4px solid #3498db;
    margin: 20px 0;
}

.payment-analysis p {
    margin: 10px 0;
}

.badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-weight: bold;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.checkbox-label {
    display: block;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.checkbox-label input {
    margin-right: 10px;
}
</style>

<script>
// Show/hide rejection reason field
document.querySelector('select[name="decision"]')?.addEventListener('change', function() {
    const rejectionGroup = document.getElementById('rejection-reason-group');
    const pendingGroup = document.getElementById('pending-reason-group');
    if (this.value === 'reject') {
        rejectionGroup.style.display = 'block';
        rejectionGroup.querySelector('textarea').required = true;
        pendingGroup.style.display = 'none';
        pendingGroup.querySelector('textarea').required = false;
    } else if (this.value === 'pending') {
        rejectionGroup.style.display = 'none';
        rejectionGroup.querySelector('textarea').required = false;
        pendingGroup.style.display = 'block';
        pendingGroup.querySelector('textarea').required = true;
    } else {
        rejectionGroup.style.display = 'none';
        rejectionGroup.querySelector('textarea').required = false;
        pendingGroup.style.display = 'none';
        pendingGroup.querySelector('textarea').required = false;
    }
});

// Show/hide override reason field
document.getElementById('manual-override')?.addEventListener('change', function() {
    const overrideGroup = document.getElementById('override-reason-group');
    if (this.checked) {
        overrideGroup.style.display = 'block';
        overrideGroup.querySelector('textarea').required = true;
    } else {
        overrideGroup.style.display = 'none';
        overrideGroup.querySelector('textarea').required = false;
    }
});

document.getElementById('deferral-approved')?.addEventListener('change', function() {
    const deferralGroup = document.getElementById('deferral-reason-group');
    if (this.checked) {
        deferralGroup.style.display = 'block';
        deferralGroup.querySelector('textarea').required = true;
    } else {
        deferralGroup.style.display = 'none';
        deferralGroup.querySelector('textarea').required = false;
    }
});

document.getElementById('finance-director-override')?.addEventListener('change', function() {
    const group = document.getElementById('finance-director-override-reason-group');
    if (this.checked) {
        group.style.display = 'block';
        group.querySelector('textarea').required = true;
    } else {
        group.style.display = 'none';
        group.querySelector('textarea').required = false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
