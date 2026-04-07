<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        Session::setFlash('danger', 'Invalid CSRF token.');
        header('Location: ' . BASE_URL . 'modules/admin/notifications.php');
        exit;
    }

    $target = (string)($_POST['target'] ?? 'single');
    $userId = (int)($_POST['user_id'] ?? 0);
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($subject === '' || $message === '') {
        Session::setFlash('danger', 'Subject and message are required.');
        header('Location: ' . BASE_URL . 'modules/admin/notifications.php');
        exit;
    }

    $service = new NotificationService($db);
    $sentCount = 0;

    try {
        if ($target === 'all') {
            $users = $db->query('SELECT user_id FROM users WHERE is_active = 1')->fetchAll();
            foreach ($users as $u) {
                $uid = (int)$u['user_id'];
                $result = $service->notify($uid, 'admin_announcement', $subject, $message, 'normal', [NOTIFY_IN_APP]);
                if (!empty($result['success'])) {
                    $sentCount++;
                }
            }
        } else {
            if ($userId <= 0) {
                throw new Exception('Select a valid target user.');
            }
            $result = $service->notify($userId, 'admin_announcement', $subject, $message, 'normal', [NOTIFY_IN_APP]);
            if (!empty($result['success'])) {
                $sentCount++;
            }
        }

        log_activity('ADMIN_NOTIFICATION_SEND', 'Sent announcement to ' . $sentCount . ' recipient(s).');
        Session::setFlash('success', 'Notification sent to ' . $sentCount . ' recipient(s).');
    } catch (Exception $e) {
        Session::setFlash('danger', 'Failed to send notification: ' . $e->getMessage());
    }

    header('Location: ' . BASE_URL . 'modules/admin/notifications.php');
    exit;
}

$users = $db->query("SELECT user_id, admission_number, email, role FROM users WHERE is_active = 1 ORDER BY role, admission_number")->fetchAll();
$recent = $db->query("SELECT n.notification_id, n.subject, n.message_body, n.notification_type, n.delivery_status, n.created_at,
                             u.email, u.admission_number
                      FROM notifications n
                      INNER JOIN users u ON u.user_id = n.user_id
                      ORDER BY n.created_at DESC
                      LIMIT 100")->fetchAll();

$page_title = 'Notifications';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Notifications</h1>
        <p>Send announcements and review recent notification activity.</p>
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <div class="card-header"><h3>Send Notification</h3></div>
        <div class="card-body">
            <form method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;" onsubmit="return confirm('Send this notification now?');">
                <?php echo csrf_token_field(); ?>
                <div class="form-group">
                    <label>Target</label>
                    <select class="form-control" name="target" id="target-selector" onchange="document.getElementById('single-user-wrap').style.display = (this.value === 'single') ? 'block' : 'none';">
                        <option value="single">Single User</option>
                        <option value="all">All Active Users</option>
                    </select>
                </div>
                <div class="form-group" id="single-user-wrap">
                    <label>User</label>
                    <select class="form-control" name="user_id">
                        <option value="">Select user</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo (int)$u['user_id']; ?>"><?php echo htmlspecialchars((string)$u['admission_number'] . ' - ' . (string)$u['email'] . ' (' . (string)$u['role'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Subject</label>
                    <input class="form-control" type="text" name="subject" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Message</label>
                    <textarea class="form-control" name="message" rows="4" required></textarea>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <button class="btn btn-primary" type="submit">Send</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Recent Notifications</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $n): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(format_date((string)$n['created_at'], DISPLAY_DATETIME_FORMAT)); ?></td>
                                <td><?php echo htmlspecialchars((string)$n['admission_number'] . ' / ' . (string)$n['email']); ?></td>
                                <td><?php echo htmlspecialchars((string)($n['subject'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)$n['notification_type']); ?></td>
                                <td><?php echo htmlspecialchars((string)$n['delivery_status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
