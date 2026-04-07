<?php
require_once '../../config/init.php';
require_login();
require_role(ROLE_STUDENT);
$schema_error = '';

// Get student information
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("
    SELECT u.*, sp.full_name, sp.phone_number, sp.program, sp.faculty
    FROM users u
    LEFT JOIN student_profiles sp ON u.user_id = sp.user_id
    WHERE u.user_id = :user_id
");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch();

$submission = null;
if (!table_exists($db, 'document_submissions')) {
    $schema_error = "Required table 'document_submissions' is missing. Run database_migration_regulation_workflow.sql and refresh this page.";
} else {
    // Get submission status in regulation workflow
    $stmt = $db->prepare("
        SELECT ds.*,
               av.is_approved AS admissions_approved,
               av.rejection_reason AS admissions_rejection_reason,
               fc.is_cleared AS finance_cleared,
               fc.rejection_reason AS finance_rejection_reason,
               gc.card_id,
               gc.registration_number,
               gc.pdf_path
        FROM document_submissions ds
        LEFT JOIN admissions_verifications av ON ds.submission_id = av.submission_id
        LEFT JOIN finance_clearances fc ON ds.submission_id = fc.submission_id
        LEFT JOIN green_cards gc ON ds.submission_id = gc.submission_id
        WHERE ds.user_id = :user_id
        ORDER BY ds.submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $user_id]);
    $submission = $stmt->fetch();
}

// Get recent notifications
$stmt = $db->prepare("
    SELECT * FROM notifications
    WHERE user_id = :user_id
      AND notification_type = 'in_app'
      AND event_type IN ('admissions_approved', 'finance_approved', 'greencard_issued')
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute(['user_id' => $user_id]);
$notifications = $stmt->fetchAll();

$page_title = 'Student Dashboard';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Welcome, <?php echo htmlspecialchars($user['full_name'] ?? $user['admission_number']); ?></h1>
        <p>Track your admissions and finance clearance workflow</p>
    </div>

    <?php if (!empty($schema_error)): ?>
        <div class="alert alert-danger">
            <strong>Database migration required.</strong><br>
            <?php echo htmlspecialchars($schema_error); ?>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-grid">
        <!-- Status Card -->
        <div class="card">
            <div class="card-header">
                <h3>Application Status</h3>
            </div>
            <div class="card-body">
                <?php if (!$submission): ?>
                    <div class="status-empty">
                        <p>No submission yet</p>
                        <a href="submit_documents.php" class="btn btn-primary">Submit Documents</a>
                    </div>
                <?php else: ?>
                    <div class="status-timeline">
                        <div class="status-item completed">
                            <div class="status-icon">📄</div>
                            <div class="status-content">
                                <h4>Document Submission</h4>
                                <p><?php echo format_date($submission['submitted_at']); ?></p>
                            </div>
                        </div>
                        
                        <div class="status-item <?php echo in_array($submission['status'], ['admissions_approved', 'pending_finance', 'under_finance_review', 'finance_approved', 'pending_greencard', 'greencard_issued']) ? 'completed' : (in_array($submission['status'], ['pending_admissions', 'under_admissions_review']) ? 'active' : (in_array($submission['status'], ['admissions_rejected', 'resubmission_requested']) ? 'rejected' : '')); ?>">
                            <div class="status-icon">🔍</div>
                            <div class="status-content">
                                <h4>Admissions Verification</h4>
                                <p>
                                    <?php if (in_array($submission['status'], ['pending_admissions', 'under_admissions_review'])): ?>
                                        In progress
                                    <?php elseif ($submission['status'] === 'admissions_approved'): ?>
                                        Approved - Reg #: <?php echo htmlspecialchars($submission['registration_number']); ?>
                                    <?php elseif ($submission['status'] === 'admissions_rejected'): ?>
                                        Rejected
                                    <?php elseif ($submission['status'] === 'resubmission_requested'): ?>
                                        Resubmission requested
                                    <?php endif; ?>
                                </p>
                                <?php if ($submission['status'] === 'admissions_rejected' && $submission['admissions_rejection_reason']): ?>
                                    <p class="text-danger"><?php echo htmlspecialchars($submission['admissions_rejection_reason']); ?></p>
                                <?php endif; ?>
                                <?php if ($submission['status'] === 'resubmission_requested' && !empty($submission['resubmission_reason'])): ?>
                                    <p class="text-danger"><?php echo htmlspecialchars($submission['resubmission_reason']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="status-item <?php echo in_array($submission['status'], ['finance_approved', 'pending_greencard', 'greencard_issued']) ? 'completed' : (in_array($submission['status'], ['admissions_approved', 'pending_finance', 'under_finance_review', 'finance_pending']) ? 'active' : ($submission['status'] === 'finance_rejected' ? 'rejected' : '')); ?>">
                            <div class="status-icon"><?php echo in_array($submission['status'], ['finance_approved', 'pending_greencard', 'greencard_issued']) ? '✅' : ($submission['status'] === 'finance_rejected' ? '❌' : ($submission['status'] === 'finance_pending' ? '⏳' : '💳')); ?></div>
                            <div class="status-content">
                                <h4>Finance Clearance</h4>
                                <p>
                                    <?php if (in_array($submission['status'], ['admissions_approved', 'pending_finance', 'under_finance_review'])): ?>
                                        Pending finance confirmation
                                    <?php elseif (in_array($submission['status'], ['finance_approved', 'pending_greencard', 'greencard_issued'])): ?>
                                        Approved
                                    <?php elseif ($submission['status'] === 'finance_pending'): ?>
                                        Pending additional confirmation
                                    <?php elseif ($submission['status'] === 'finance_rejected'): ?>
                                        Rejected
                                    <?php endif; ?>
                                </p>
                                <?php if ($submission['status'] === 'finance_pending' && !empty($submission['finance_flag_notes'])): ?>
                                    <p class="text-danger"><?php echo htmlspecialchars($submission['finance_flag_notes']); ?></p>
                                <?php endif; ?>
                                <?php if ($submission['status'] === 'finance_rejected' && $submission['finance_rejection_reason']): ?>
                                    <p class="text-danger"><?php echo htmlspecialchars($submission['finance_rejection_reason']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="status-item <?php echo $submission['status'] === 'greencard_issued' && $submission['card_id'] ? 'completed' : (in_array($submission['status'], ['finance_approved', 'pending_greencard']) ? 'active' : ''); ?>">
                            <div class="status-icon"><?php echo $submission['status'] === 'greencard_issued' && $submission['card_id'] ? '🟢' : '🪪'; ?></div>
                            <div class="status-content">
                                <h4>Green Card</h4>
                                <p>Registration: <?php echo htmlspecialchars($submission['registration_number'] ?? 'Pending'); ?></p>
                                <?php if ($submission['status'] === 'greencard_issued' && $submission['card_id'] && $submission['pdf_path']): ?>
                                    <a href="<?php echo BASE_URL; ?>download_green_card.php?id=<?php echo (int)$submission['card_id']; ?>&mode=card&ts=<?php echo time(); ?>" class="btn btn-success btn-sm" target="_blank">View Green Card</a>
                                <?php else: ?>
                                    <p>Card generation in progress</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (in_array($submission['status'], ['admissions_rejected', 'finance_rejected', 'resubmission_requested'])): ?>
                        <a href="submit_documents.php" class="btn btn-warning mt-3">Resubmit Documents</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="submit_documents.php" class="action-btn">
                        <span class="action-icon">📤</span>
                        <span>Submit Documents</span>
                    </a>
                    <a href="submit_documents.php" class="action-btn">
                        <span class="action-icon">🧾</span>
                        <span>Update Submission</span>
                    </a>
                    <?php if ($submission && $submission['card_id'] && $submission['pdf_path']): ?>
                    <a href="<?php echo BASE_URL; ?>download_green_card.php?id=<?php echo (int)$submission['card_id']; ?>&mode=download&ts=<?php echo time(); ?>" class="action-btn">
                        <span class="action-icon">⬇️</span>
                        <span>Download PDF</span>
                    </a>
                    <?php endif; ?>
                    <a href="notifications.php?filter=approvals" class="action-btn">
                        <span class="action-icon">🔔</span>
                        <span>Notifications</span>
                        <?php if (count($notifications) > 0): ?>
                            <span class="badge"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Notifications -->
        <div class="card">
        <div class="card-header">
            <h3>Recent Notifications</h3>
            <a href="notifications.php?filter=approvals">View All</a>
        </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <p class="text-muted">No notifications</p>
                <?php else: ?>
                    <ul class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <li class="notification-item <?php echo $notification['read_at'] ? '' : 'unread'; ?>">
                                <strong><?php echo htmlspecialchars($notification['subject']); ?></strong>
                                <p><?php echo htmlspecialchars($notification['message_body']); ?></p>
                                <small><?php echo time_ago($notification['created_at']); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="card">
            <div class="card-header">
                <h3>Account Information</h3>
            </div>
            <div class="card-body">
                <table class="info-table">
                    <tr>
                        <th>Admission Number:</th>
                        <td><?php echo htmlspecialchars($user['admission_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                    </tr>
                    <?php if ($user['program']): ?>
                    <tr>
                        <th>Program:</th>
                        <td><?php echo htmlspecialchars($user['program']); ?></td>
                    </tr>
                    <tr>
                        <th>Faculty:</th>
                        <td><?php echo htmlspecialchars($user['faculty']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Last Login:</th>
                        <td><?php echo format_date($user['last_login_at'], DISPLAY_DATETIME_FORMAT); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
