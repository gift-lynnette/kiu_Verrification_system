<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_FINANCE, ROLE_ADMIN]);

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $per_page;
$schema_error = '';

// Build query
$where_clauses = [];
$params = [];

if ($status_filter !== 'all') {
    $where_clauses[] = "ds.status = :status";
    $params['status'] = $status_filter;
}

if ($search) {
    $where_clauses[] = "(u.admission_number LIKE :search OR ds.full_name LIKE :search OR u.email LIKE :search OR ds.registration_number LIKE :search)";
    $params['search'] = "%$search%";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$total_records = 0;
$total_pages = 0;
$submissions = [];
$stats = [
    'pending_count' => 0,
    'under_review_count' => 0,
    'verified_count' => 0,
    'rejected_count' => 0
];

if (!table_exists($db, 'document_submissions')) {
    $schema_error = "Required table 'document_submissions' is missing. Run database_migration_regulation_workflow.sql and refresh this page.";
} else {
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM document_submissions ds
        INNER JOIN users u ON ds.user_id = u.user_id
        $where_sql
    ";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);

    // Get submissions
    $query = "
        SELECT ds.*, u.admission_number, u.email,
               TIMESTAMPDIFF(HOUR, ds.submitted_at, NOW()) as hours_pending
        FROM document_submissions ds
        INNER JOIN users u ON ds.user_id = u.user_id
        $where_sql
        ORDER BY ds.submitted_at ASC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $submissions = $stmt->fetchAll();

    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(CASE WHEN status IN ('admissions_approved', 'pending_finance', 'finance_pending') THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'under_finance_review' THEN 1 END) as under_review_count,
            COUNT(CASE WHEN status IN ('finance_approved', 'pending_greencard') THEN 1 END) as verified_count,
            COUNT(CASE WHEN status = 'finance_rejected' THEN 1 END) as rejected_count
        FROM document_submissions
        WHERE DATE(submitted_at) = CURDATE()
    ";
    $stats = $db->query($stats_query)->fetch();
}

$page_title = 'Finance Clearance Queue';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Finance Clearance Queue</h1>
        <p>Confirm student payment after admissions approval</p>
    </div>

    <?php if (!empty($schema_error)): ?>
        <div class="alert alert-danger">
            <strong>Database migration required.</strong><br>
            <?php echo htmlspecialchars($schema_error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
                <h3><?php echo $stats['pending_count']; ?></h3>
                <p>Pending Finance</p>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">🔍</div>
            <div class="stat-content">
                <h3><?php echo $stats['under_review_count']; ?></h3>
                <p>Under Review</p>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <h3><?php echo $stats['verified_count']; ?></h3>
                <p>Finance Approved</p>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">❌</div>
            <div class="stat-content">
                <h3><?php echo $stats['rejected_count']; ?></h3>
                <p>Finance Rejected</p>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="status">Status Filter</label>
                        <select id="status" name="status" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="admissions_approved" <?php echo $status_filter === 'admissions_approved' ? 'selected' : ''; ?>>Admissions Approved</option>
                            <option value="pending_finance" <?php echo $status_filter === 'pending_finance' ? 'selected' : ''; ?>>Pending Finance</option>
                            <option value="finance_pending" <?php echo $status_filter === 'finance_pending' ? 'selected' : ''; ?>>Finance Pending</option>
                            <option value="under_finance_review" <?php echo $status_filter === 'under_finance_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="finance_approved" <?php echo $status_filter === 'finance_approved' ? 'selected' : ''; ?>>Finance Approved</option>
                            <option value="pending_greencard" <?php echo $status_filter === 'pending_greencard' ? 'selected' : ''; ?>>Pending Green Card</option>
                            <option value="finance_rejected" <?php echo $status_filter === 'finance_rejected' ? 'selected' : ''; ?>>Finance Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by admission number, registration number, name, or email">
                    </div>
                    
                    <div class="form-group col-md-3">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Submissions Table -->
    <div class="card">
        <div class="card-header">
            <h3>Submissions (<?php echo $total_records; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($submissions)): ?>
                <p class="text-muted text-center">No submissions found</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Admission No.</th>
                                <th>Reg No.</th>
                                <th>Student Name</th>
                                <th>Program</th>
                                <th>Amount Paid</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Flag</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($submission['admission_number']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['registration_number'] ?? 'Pending'); ?></td>
                                    <td><?php echo htmlspecialchars($submission['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($submission['program'] ?? 'N/A'); ?></td>
                                    <td><?php echo format_currency($submission['payment_amount'] ?? 0, $submission['payment_currency'] ?? PAYMENT_CURRENCY); ?></td>
                                    <td>
                                        <?php echo format_date($submission['submitted_at']); ?><br>
                                        <small class="text-muted"><?php echo $submission['hours_pending']; ?> hours ago</small>
                                    </td>
                                    <td><?php echo get_status_badge($submission['status']); ?></td>
                                    <td>
                                        <?php if (($submission['finance_flag_status'] ?? 'none') === 'none'): ?>
                                            <span class="text-muted">-</span>
                                        <?php else: ?>
                                            <?php echo get_status_badge($submission['finance_flag_status']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (in_array($submission['status'], ['admissions_approved', 'pending_finance', 'under_finance_review', 'finance_pending'])): ?>
                                            <a href="verify_payment.php?id=<?php echo $submission['submission_id']; ?>" 
                                               class="btn btn-sm btn-primary">Verify</a>
                                        <?php else: ?>
                                            <span class="text-muted">No action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
