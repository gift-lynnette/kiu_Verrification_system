<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);

$q = trim((string)($_GET['q'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$limit = 200;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(al.changes_summary LIKE :q OR al.ip_address LIKE :q OR u.email LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($action !== '') {
    $where[] = 'al.action = :action';
    $params['action'] = $action;
}

$sql = "SELECT al.*, COALESCE(u.email, 'System') as user_email
        FROM audit_logs al
        LEFT JOIN users u ON u.user_id = al.user_id";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY al.timestamp DESC LIMIT ' . (int)$limit;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionRows = $db->query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')->fetchAll();

$page_title = 'Audit Logs';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Audit Logs</h1>
        <p>Track admin and system events.</p>
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <div class="card-body">
            <form method="GET" style="display:grid; grid-template-columns: 1fr 220px 120px; gap:12px;">
                <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search details, IP, user email">
                <select class="form-control" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actionRows as $row): ?>
                        <?php $name = (string)$row['action']; ?>
                        <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $name === $action ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Filter</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Recent Logs (max <?php echo $limit; ?>)</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(format_date((string)$log['timestamp'], DISPLAY_DATETIME_FORMAT)); ?></td>
                                <td><?php echo htmlspecialchars((string)$log['user_email']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars((string)$log['action']); ?></span></td>
                                <td><?php echo htmlspecialchars((string)($log['changes_summary'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)$log['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
