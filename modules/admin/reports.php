<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);

$reportType = (string)($_GET['download'] ?? '');
if ($reportType !== '') {
    $rows = [];
    $fileName = 'report_' . $reportType . '_' . date('Ymd_His') . '.csv';

    if ($reportType === 'summary') {
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total Users', (string)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()];
        $rows[] = ['Total Students', (string)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn()];
        $rows[] = ['Total Submissions', (string)(table_exists($db, 'document_submissions') ? $db->query('SELECT COUNT(*) FROM document_submissions')->fetchColumn() : (table_exists($db, 'payment_submissions') ? $db->query('SELECT COUNT(*) FROM payment_submissions')->fetchColumn() : 0))];
        $rows[] = ['Total Green Cards', (string)$db->query('SELECT COUNT(*) FROM green_cards')->fetchColumn()];
    } elseif ($reportType === 'users') {
        $rows[] = ['user_id', 'admission_number', 'email', 'role', 'is_active', 'created_at'];
        $stmt = $db->query('SELECT user_id, admission_number, email, role, is_active, created_at FROM users ORDER BY created_at DESC');
        foreach ($stmt as $row) {
            $rows[] = [
                (string)$row['user_id'],
                (string)$row['admission_number'],
                (string)$row['email'],
                (string)$row['role'],
                (string)$row['is_active'],
                (string)$row['created_at']
            ];
        }
    } elseif ($reportType === 'audit') {
        $rows[] = ['timestamp', 'user', 'action', 'details', 'ip_address'];
        $stmt = $db->query("SELECT al.timestamp, COALESCE(u.email, 'System') as email, al.action, al.changes_summary, al.ip_address
                           FROM audit_logs al
                           LEFT JOIN users u ON u.user_id = al.user_id
                           ORDER BY al.timestamp DESC
                           LIMIT 1000");
        foreach ($stmt as $row) {
            $rows[] = [
                (string)$row['timestamp'],
                (string)$row['email'],
                (string)$row['action'],
                (string)($row['changes_summary'] ?? ''),
                (string)$row['ip_address']
            ];
        }
    }

    if (!empty($rows)) {
        if (table_exists($db, 'reports')) {
            try {
                $db->prepare("INSERT INTO reports (generated_by_user_id, report_type, report_title, file_path, file_format, record_count)
                              VALUES (:uid, :type, :title, :path, 'csv', :count)")
                   ->execute([
                       'uid' => (int)($_SESSION['user_id'] ?? 0),
                       'type' => $reportType,
                       'title' => strtoupper($reportType) . ' report',
                       'path' => 'download://' . $fileName,
                       'count' => max(0, count($rows) - 1)
                   ]);
            } catch (Exception $e) {
                error_log('Failed to store report metadata: ' . $e->getMessage());
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        $out = fopen('php://output', 'w');
        foreach ($rows as $line) {
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    Session::setFlash('warning', 'Invalid report selected.');
    header('Location: ' . BASE_URL . 'modules/admin/reports.php');
    exit;
}

$summary = [
    'users' => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'students' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'green_cards' => (int)$db->query('SELECT COUNT(*) FROM green_cards')->fetchColumn(),
    'submissions' => (int)(table_exists($db, 'document_submissions') ? $db->query('SELECT COUNT(*) FROM document_submissions')->fetchColumn() : (table_exists($db, 'payment_submissions') ? $db->query('SELECT COUNT(*) FROM payment_submissions')->fetchColumn() : 0))
];

$page_title = 'Reports';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Generate Reports</h1>
        <p>Export system data as CSV files.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card stat-primary"><div class="stat-content"><h3><?php echo $summary['users']; ?></h3><p>Total Users</p></div></div>
        <div class="stat-card stat-success"><div class="stat-content"><h3><?php echo $summary['students']; ?></h3><p>Students</p></div></div>
        <div class="stat-card stat-info"><div class="stat-content"><h3><?php echo $summary['submissions']; ?></h3><p>Submissions</p></div></div>
        <div class="stat-card stat-warning"><div class="stat-content"><h3><?php echo $summary['green_cards']; ?></h3><p>Green Cards</p></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Available Exports</h3></div>
        <div class="card-body">
            <div class="quick-actions">
                <a class="action-btn" href="?download=summary"><span class="action-icon">📌</span><span>Download Summary CSV</span></a>
                <a class="action-btn" href="?download=users"><span class="action-icon">👥</span><span>Download Users CSV</span></a>
                <a class="action-btn" href="?download=audit"><span class="action-icon">📜</span><span>Download Audit CSV</span></a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
