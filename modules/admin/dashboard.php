<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);

// Get system statistics
$users_count = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$students_count = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch()['count'];
$schema_notice = '';

if (table_exists($db, 'document_submissions')) {
    $submissions_count = $db->query("SELECT COUNT(*) as count FROM document_submissions")->fetch()['count'];
} elseif (table_exists($db, 'payment_submissions')) {
    // Legacy fallback so dashboard remains available before/without migration.
    $submissions_count = $db->query("SELECT COUNT(*) as count FROM payment_submissions")->fetch()['count'];
    $schema_notice = "Using legacy submissions table (payment_submissions). Run database_migration_regulation_workflow.sql for the regulation workflow.";
} else {
    $submissions_count = 0;
    $schema_notice = "No submissions table found. Run database_migration_regulation_workflow.sql.";
}

$greencards_count = $db->query("SELECT COUNT(*) as count FROM green_cards")->fetch()['count'];

$performance = [
    'db_time_ms' => null,
    'db_threads_connected' => null,
    'db_uptime_seconds' => null
];
try {
    $start = microtime(true);
    $db->query('SELECT 1')->fetchColumn();
    $performance['db_time_ms'] = round((microtime(true) - $start) * 1000, 2);

    $statusRows = $db->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Uptime')")->fetchAll();
    foreach ($statusRows as $row) {
        if (($row['Variable_name'] ?? '') === 'Threads_connected') {
            $performance['db_threads_connected'] = (int)$row['Value'];
        }
        if (($row['Variable_name'] ?? '') === 'Uptime') {
            $performance['db_uptime_seconds'] = (int)$row['Value'];
        }
    }
} catch (Exception $e) {
    // Keep dashboard available even when status metrics are restricted.
}

// Get recent activity
$recent_activity = $db->query("
    SELECT al.*, u.email, u.admission_number
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.timestamp DESC
    LIMIT 15
")->fetchAll();

$page_title = 'Admin Dashboard';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>System Administration</h1>
        <p>Manage system configuration and user access</p>
    </div>

    <?php if (!empty($schema_notice)): ?>
        <div class="alert alert-warning">
            <?php echo htmlspecialchars($schema_notice); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <h3><?php echo $users_count; ?></h3>
                <p>Total Users</p>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🎓</div>
            <div class="stat-content">
                <h3><?php echo $students_count; ?></h3>
                <p>Students</p>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📄</div>
            <div class="stat-content">
                <h3><?php echo $submissions_count; ?></h3>
                <p>Submissions</p>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🟢</div>
            <div class="stat-content">
                <h3><?php echo $greencards_count; ?></h3>
                <p>Green Cards</p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>Administrative Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="users.php" class="action-btn">
                    <span class="action-icon">👥</span>
                    <span>Manage Users</span>
                </a>
                <a href="data_management.php" class="action-btn">
                    <span class="action-icon">🗂️</span>
                    <span>Data Management</span>
                </a>
                <a href="system_settings.php" class="action-btn">
                    <span class="action-icon">⚙️</span>
                    <span>System Settings</span>
                </a>
                <a href="reports.php" class="action-btn">
                    <span class="action-icon">📊</span>
                    <span>Generate Reports</span>
                </a>
                <a href="system_health.php" class="action-btn">
                    <span class="action-icon">🩺</span>
                    <span>System Health</span>
                </a>
                <a href="audit_logs.php" class="action-btn">
                    <span class="action-icon">📜</span>
                    <span>Audit Logs</span>
                </a>
                <a href="backup.php" class="action-btn">
                    <span class="action-icon">💾</span>
                    <span>Backup System</span>
                </a>
                <a href="notifications.php" class="action-btn">
                    <span class="action-icon">📧</span>
                    <span>Notifications</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent System Activity -->
    <div class="card">
        <div class="card-header">
            <h3>Recent System Activity</h3>
            <a href="audit_logs.php">View All</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activity as $activity): ?>
                            <tr>
                                <td><?php echo format_date($activity['timestamp'], DISPLAY_DATETIME_FORMAT); ?></td>
                                <td><?php echo htmlspecialchars($activity['email'] ?? 'System'); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                                <td><?php echo htmlspecialchars($activity['changes_summary'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>System Performance</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card stat-info">
                    <div class="stat-content">
                        <h3><?php echo $performance['db_time_ms'] !== null ? htmlspecialchars((string)$performance['db_time_ms']) . ' ms' : 'N/A'; ?></h3>
                        <p>DB Ping</p>
                    </div>
                </div>
                <div class="stat-card stat-primary">
                    <div class="stat-content">
                        <h3><?php echo $performance['db_threads_connected'] !== null ? (int)$performance['db_threads_connected'] : 'N/A'; ?></h3>
                        <p>DB Threads Connected</p>
                    </div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-content">
                        <h3><?php echo $performance['db_uptime_seconds'] !== null ? (int)$performance['db_uptime_seconds'] : 'N/A'; ?></h3>
                        <p>DB Uptime (seconds)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
