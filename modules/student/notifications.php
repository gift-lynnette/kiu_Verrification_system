<?php
require_once '../../config/init.php';
require_login();
require_role(ROLE_STUDENT);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$filter = strtolower(trim((string)($_GET['filter'] ?? 'approvals')));
if (!in_array($filter, ['approvals', 'all', 'unread'], true)) {
    $filter = 'approvals';
}

$approvalEvents = ['admissions_approved', 'finance_approved', 'greencard_issued'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $markSql = "
            UPDATE notifications
            SET read_at = COALESCE(read_at, NOW())
            WHERE user_id = :user_id
              AND notification_type = 'in_app'
              AND read_at IS NULL
        ";

        if ($filter === 'approvals') {
            $markSql .= " AND event_type IN ('admissions_approved', 'finance_approved', 'greencard_issued')";
            $stmtMark = $db->prepare($markSql);
            $stmtMark->execute(['user_id' => $user_id]);
        } else {
            $stmtMark = $db->prepare($markSql);
            $stmtMark->execute(['user_id' => $user_id]);
        }
    }
    redirect('modules/student/notifications.php?filter=' . rawurlencode($filter));
}

$sql = "
    SELECT notification_id, event_type, subject, message_body, priority, read_at, created_at
    FROM notifications
    WHERE user_id = :user_id
      AND notification_type = 'in_app'
";
$params = ['user_id' => $user_id];

if ($filter === 'approvals') {
    $sql .= " AND event_type IN ('admissions_approved', 'finance_approved', 'greencard_issued')";
} elseif ($filter === 'unread') {
    $sql .= " AND read_at IS NULL";
}

$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$unreadCountStmt = $db->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = :user_id
      AND notification_type = 'in_app'
      AND read_at IS NULL
");
$unreadCountStmt->execute(['user_id' => $user_id]);
$unreadCount = (int)$unreadCountStmt->fetchColumn();

$page_title = 'My Notifications';
include '../../includes/header.php';
?>

<div class="container" style="max-width:1000px;margin:24px auto;">
    <div class="page-header">
        <h1>My Notifications</h1>
        <p>Review approval updates from Admissions and Finance.</p>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <a class="btn <?php echo $filter === 'approvals' ? 'btn-primary' : 'btn-secondary'; ?>" href="notifications.php?filter=approvals">Approval Notifications</a>
            <a class="btn <?php echo $filter === 'unread' ? 'btn-primary' : 'btn-secondary'; ?>" href="notifications.php?filter=unread">Unread (<?php echo $unreadCount; ?>)</a>
            <a class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>" href="notifications.php?filter=all">All In-App</a>

            <form method="POST" style="margin-left:auto;">
                <?php echo csrf_token_field(); ?>
                <button type="submit" name="mark_read" value="1" class="btn btn-success">Mark Visible As Read</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <p class="text-muted">No notifications found for this filter.</p>
            <?php else: ?>
                <ul class="notification-list">
                    <?php foreach ($notifications as $n): ?>
                        <?php
                            $itemClass = empty($n['read_at']) ? 'unread' : '';
                            $badge = get_status_badge((string)$n['event_type']);
                        ?>
                        <li class="notification-item <?php echo $itemClass; ?>">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                <strong><?php echo htmlspecialchars((string)($n['subject'] ?? 'Notification')); ?></strong>
                                <small><?php echo time_ago((string)$n['created_at']); ?></small>
                            </div>
                            <div style="margin:6px 0;"><?php echo $badge; ?></div>
                            <p style="margin:0;"><?php echo htmlspecialchars((string)$n['message_body']); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
