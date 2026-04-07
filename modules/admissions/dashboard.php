<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_REGISTRAR, ROLE_ADMIN]);

$schema_error = '';
$stats = [
    'pending_admissions_count' => 0,
    'pending_greencard_count' => 0,
    'issued_count' => 0,
    'rejected_count' => 0
];
$recent_cards = [];
$pending_admissions = [];
$pending_cards = [];

if (!table_exists($db, 'document_submissions')) {
    $schema_error = "Required table 'document_submissions' is missing. Run database_migration_regulation_workflow.sql and refresh this page.";
} else {
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(CASE WHEN status IN ('pending_admissions', 'under_admissions_review') THEN 1 END) as pending_admissions_count,
            COUNT(CASE WHEN status IN ('finance_approved', 'pending_greencard') THEN 1 END) as pending_greencard_count,
            COUNT(CASE WHEN status = 'greencard_issued' THEN 1 END) as issued_count,
            COUNT(CASE WHEN status IN ('admissions_rejected', 'finance_rejected', 'resubmission_requested') THEN 1 END) as rejected_count
        FROM document_submissions
    ";
    $stats = $db->query($stats_query)->fetch();

    // Get recent green cards
    $recent_cards_query = "
        SELECT gc.*, u.admission_number
        FROM green_cards gc
        INNER JOIN users u ON gc.user_id = u.user_id
        ORDER BY gc.issued_at DESC
        LIMIT 10
    ";
    $recent_cards = $db->query($recent_cards_query)->fetchAll();

    // Get pending admissions document verification queue
    $pending_admissions_query = "
        SELECT ds.submission_id, u.admission_number, ds.full_name, ds.program, ds.submitted_at, ds.status,
               ds.admissions_flag_status
        FROM document_submissions ds
        INNER JOIN users u ON ds.user_id = u.user_id
        WHERE ds.status IN ('pending_admissions', 'under_admissions_review')
        ORDER BY ds.submitted_at ASC
    ";
    $pending_admissions = $db->query($pending_admissions_query)->fetchAll();

    // Get finance-cleared submissions awaiting green card issuance
    $pending_cards_query = "
        SELECT ds.submission_id, u.admission_number, ds.full_name, ds.program, ds.registration_number, ds.last_updated_at
        FROM document_submissions ds
        INNER JOIN users u ON ds.user_id = u.user_id
        LEFT JOIN green_cards gc ON ds.submission_id = gc.submission_id
        WHERE ds.status IN ('finance_approved', 'pending_greencard') AND gc.card_id IS NULL
        ORDER BY ds.last_updated_at DESC
    ";
    $pending_cards = $db->query($pending_cards_query)->fetchAll();
}

$page_title = 'Admissions Dashboard';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Admissions Office Dashboard</h1>
        <p>Verify academic documents and issue green cards</p>
    </div>

    <?php if (!empty($schema_error)): ?>
        <div class="alert alert-danger">
            <strong>Database migration required.</strong><br>
            <?php echo htmlspecialchars($schema_error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🟢</div>
            <div class="stat-content">
                <h3><?php echo $stats['pending_admissions_count']; ?></h3>
                <p>Pending Admissions</p>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <h3><?php echo $stats['pending_greencard_count']; ?></h3>
                <p>Pending Green Card</p>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <h3><?php echo $stats['issued_count']; ?></h3>
                <p>Green Cards Issued</p>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⏰</div>
            <div class="stat-content">
                <h3><?php echo $stats['rejected_count']; ?></h3>
                <p>Rejected Cases</p>
            </div>
        </div>
    </div>

    <!-- Pending Admissions Verification -->
    <?php if (!empty($pending_admissions)): ?>
        <div class="card" id="pending-admissions">
            <div class="card-header">
                <h3>Pending Admissions Verification (<?php echo count($pending_admissions); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Admission No.</th>
                                <th>Student Name</th>
                                <th>Program</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Flag</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_admissions as $pending): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pending['admission_number']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['program']); ?></td>
                                    <td><?php echo format_date($pending['submitted_at']); ?></td>
                                    <td><?php echo get_status_badge($pending['status']); ?></td>
                                    <td>
                                        <?php if ($pending['admissions_flag_status'] === 'none'): ?>
                                            <span class="text-muted">-</span>
                                        <?php else: ?>
                                            <?php echo get_status_badge($pending['admissions_flag_status']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="verify_documents.php?id=<?php echo $pending['submission_id']; ?>" 
                                           class="btn btn-sm btn-primary">Review</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Pending Cards Generation -->
    <?php if (!empty($pending_cards)): ?>
        <div class="card" id="pending-cards">
            <div class="card-header">
                <h3>Pending Green Card Generation (<?php echo count($pending_cards); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    These students are cleared by Finance and waiting for final issuance.
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Admission No.</th>
                                <th>Registration No.</th>
                                <th>Student Name</th>
                                <th>Program</th>
                                <th>Cleared On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_cards as $pending): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pending['admission_number']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($pending['registration_number'] ?: 'To be generated'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pending['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['program']); ?></td>
                                    <td><?php echo format_date($pending['last_updated_at']); ?></td>
                                    <td>
                                        <a href="verify_documents.php?id=<?php echo $pending['submission_id']; ?>" 
                                           class="btn btn-sm btn-success">Issue Card</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="#pending-admissions" class="action-btn">
                    <span class="action-icon">🔍</span>
                    <span>Review Documents</span>
                </a>
                <a href="#pending-cards" class="action-btn">
                    <span class="action-icon">🪪</span>
                    <span>Issue Green Cards</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent Green Cards -->
    <div class="card">
        <div class="card-header">
            <h3>Recently Issued Green Cards</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Registration No.</th>
                            <th>Admission No.</th>
                            <th>Student Name</th>
                            <th>Program</th>
                            <th>Issued Date</th>
                            <th>Expires</th>
                            <th>Downloads</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_cards as $card): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($card['registration_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($card['admission_number']); ?></td>
                                <td><?php echo htmlspecialchars($card['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($card['program']); ?></td>
                                <td><?php echo format_date($card['issued_at']); ?></td>
                                <td><?php echo format_date($card['expiry_date']); ?></td>
                                <td><?php echo $card['download_count']; ?></td>
                                <td>
                                    <?php if (!empty($card['pdf_path'])): ?>
                                        <a href="<?php echo BASE_URL; ?>download_green_card.php?id=<?php echo (int)$card['card_id']; ?>&mode=view&ts=<?php echo time(); ?>" 
                                           class="btn btn-sm btn-info" target="_blank">View</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
