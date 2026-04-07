<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);
require_once '../../includes/BackupService.php';

$checks = [];

function add_health_check(array &$checks, string $name, string $status, string $details, string $value = ''): void
{
    $checks[] = [
        'name' => $name,
        'status' => $status,
        'details' => $details,
        'value' => $value
    ];
}

function status_rank(string $status): int
{
    if ($status === 'fail') {
        return 3;
    }
    if ($status === 'warn') {
        return 2;
    }
    return 1;
}

// Database ping and connectivity
try {
    $start = microtime(true);
    $db->query('SELECT 1')->fetchColumn();
    $dbMs = round((microtime(true) - $start) * 1000, 2);
    $dbStatus = $dbMs > 200 ? 'warn' : 'pass';
    add_health_check($checks, 'Database Connection', $dbStatus, 'Database responded to ping query.', $dbMs . ' ms');
} catch (Exception $e) {
    add_health_check($checks, 'Database Connection', 'fail', 'Database ping failed: ' . $e->getMessage(), 'N/A');
}

// Required table checks
$requiredTables = ['users', 'fee_structures', 'green_cards', 'audit_logs', 'notifications'];
foreach ($requiredTables as $table) {
    $exists = table_exists($db, $table);
    add_health_check(
        $checks,
        'Table: ' . $table,
        $exists ? 'pass' : 'fail',
        $exists ? 'Table is available.' : 'Required table missing.',
        $exists ? 'Present' : 'Missing'
    );
}

$workflowTableExists = table_exists($db, 'document_submissions');
add_health_check(
    $checks,
    'Workflow Table: document_submissions',
    $workflowTableExists ? 'pass' : 'warn',
    $workflowTableExists ? 'Regulation workflow table is present.' : 'Legacy mode may be active. Consider running migration.',
    $workflowTableExists ? 'Present' : 'Missing'
);

// Path writability checks
$pathChecks = [
    'Uploads Directory' => UPLOAD_DIR,
    'Logs Directory' => LOG_DIR,
    'Backups Directory' => kiu_backup_dir()
];

foreach ($pathChecks as $label => $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    $status = (!$exists) ? 'fail' : ($writable ? 'pass' : 'warn');
    $details = !$exists ? 'Directory missing.' : ($writable ? 'Directory writable.' : 'Directory exists but is not writable.');
    add_health_check($checks, $label, $status, $details, $path);
}

// Backup freshness and schedule
$backups = kiu_list_backups();
$schedule = kiu_load_backup_schedule();
if (empty($backups)) {
    add_health_check($checks, 'Latest Backup', 'warn', 'No backup files found.', 'None');
} else {
    $latest = $backups[0];
    $ageSeconds = time() - (int)$latest['mtime'];
    $ageHours = round($ageSeconds / 3600, 1);
    $status = $ageHours > 48 ? 'warn' : 'pass';
    add_health_check($checks, 'Latest Backup', $status, 'Most recent backup age.', $ageHours . ' hours');
}

add_health_check(
    $checks,
    'Auto Backup Schedule',
    !empty($schedule['enabled']) ? 'pass' : 'warn',
    !empty($schedule['enabled']) ? 'Automatic backup is enabled.' : 'Automatic backup is disabled.',
    !empty($schedule['enabled']) ? ('Every ' . (int)$schedule['interval_hours'] . ' hour(s)') : 'Disabled'
);

// Runtime and resource checks
$phpVersion = PHP_VERSION;
add_health_check(
    $checks,
    'PHP Version',
    version_compare($phpVersion, '8.0.0', '>=') ? 'pass' : 'warn',
    'Minimum recommended PHP version is 8.0.',
    $phpVersion
);

$diskFree = @disk_free_space(SITE_ROOT);
$diskTotal = @disk_total_space(SITE_ROOT);
if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
    $usedPct = round((1 - ($diskFree / $diskTotal)) * 100, 1);
    $status = $usedPct >= 90 ? 'fail' : ($usedPct >= 80 ? 'warn' : 'pass');
    add_health_check(
        $checks,
        'Disk Usage (Site Root)',
        $status,
        'Disk usage for server partition containing project.',
        $usedPct . '% used'
    );
}

$extensions = ['pdo', 'pdo_mysql', 'openssl', 'json'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    add_health_check(
        $checks,
        'PHP Extension: ' . $ext,
        $loaded ? 'pass' : 'fail',
        $loaded ? 'Extension loaded.' : 'Required extension not loaded.',
        $loaded ? 'Loaded' : 'Missing'
    );
}

$overall = 'pass';
foreach ($checks as $c) {
    if (status_rank($c['status']) > status_rank($overall)) {
        $overall = $c['status'];
    }
}

$statusCounts = ['pass' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $c) {
    $statusCounts[$c['status']]++;
}

$page_title = 'System Health';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>System Health</h1>
        <p>Live diagnostics for system status, reliability, and operational readiness.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card <?php echo $overall === 'fail' ? 'stat-danger' : ($overall === 'warn' ? 'stat-warning' : 'stat-success'); ?>">
            <div class="stat-content">
                <h3><?php echo strtoupper($overall); ?></h3>
                <p>Overall Health</p>
            </div>
        </div>
        <div class="stat-card stat-success">
            <div class="stat-content">
                <h3><?php echo (int)$statusCounts['pass']; ?></h3>
                <p>Passing Checks</p>
            </div>
        </div>
        <div class="stat-card stat-warning">
            <div class="stat-content">
                <h3><?php echo (int)$statusCounts['warn']; ?></h3>
                <p>Warnings</p>
            </div>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-content">
                <h3><?php echo (int)$statusCounts['fail']; ?></h3>
                <p>Failures</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 18px;">
        <div class="card-header">
            <h3>Health Check Results</h3>
            <a href="system_health.php">Refresh</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Check</th>
                            <th>Status</th>
                            <th>Value</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $check): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$check['name']); ?></td>
                                <td>
                                    <?php if ($check['status'] === 'pass'): ?>
                                        <span class="badge badge-success">PASS</span>
                                    <?php elseif ($check['status'] === 'warn'): ?>
                                        <span class="badge badge-warning">WARN</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">FAIL</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string)$check['value']); ?></td>
                                <td><?php echo htmlspecialchars((string)$check['details']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
