<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);
require_once '../../includes/BackupService.php';

$backupDir = kiu_backup_dir();

$downloadFile = trim((string)($_GET['download'] ?? ''));
if ($downloadFile !== '') {
    $safeName = basename($downloadFile);
    $filePath = $backupDir . '/' . $safeName;
    if (!kiu_is_valid_backup_filename($safeName) || !is_file($filePath)) {
        http_response_code(404);
        exit('Backup file not found.');
    }

    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . (string)filesize($filePath));
    readfile($filePath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        Session::setFlash('danger', 'Invalid CSRF token.');
        header('Location: ' . BASE_URL . 'modules/admin/backup.php');
        exit;
    }

    $action = trim((string)($_POST['action'] ?? 'create'));

    try {
        if ($action === 'create') {
            $backup = kiu_create_backup($db, 'manual-admin');
            log_activity('ADMIN_BACKUP_CREATE', 'Created backup ' . $backup['filename']);
            Session::setFlash('success', 'Backup created and stored successfully: ' . $backup['filename']);
        } elseif ($action === 'restore') {
            $filename = basename((string)($_POST['backup_file'] ?? ''));
            $confirmText = trim((string)($_POST['confirm_restore'] ?? ''));
            $fullPath = $backupDir . '/' . $filename;

            if (!kiu_is_valid_backup_filename($filename) || !is_file($fullPath)) {
                throw new Exception('Selected backup file is invalid.');
            }
            if ($confirmText !== 'RESTORE') {
                throw new Exception('Type RESTORE to confirm recovery action.');
            }

            kiu_execute_sql_backup_file($db, $fullPath);
            log_activity('ADMIN_BACKUP_RESTORE', 'Restored backup ' . $filename);
            Session::setFlash('success', 'Backup restored successfully from ' . $filename);
        } elseif ($action === 'delete') {
            $filename = basename((string)($_POST['backup_file'] ?? ''));
            $fullPath = $backupDir . '/' . $filename;

            if (!kiu_is_valid_backup_filename($filename) || !is_file($fullPath)) {
                throw new Exception('Selected backup file is invalid.');
            }
            if (!@unlink($fullPath)) {
                throw new Exception('Failed to delete backup file.');
            }

            log_activity('ADMIN_BACKUP_DELETE', 'Deleted backup ' . $filename);
            Session::setFlash('success', 'Backup deleted: ' . $filename);
        } elseif ($action === 'save_schedule') {
            $enabled = isset($_POST['auto_enabled']) && (string)$_POST['auto_enabled'] === '1';
            $intervalHours = max(1, (int)($_POST['interval_hours'] ?? 24));
            $keepLast = max(1, (int)($_POST['keep_last'] ?? 15));

            $existing = kiu_load_backup_schedule();
            $config = [
                'enabled' => $enabled,
                'interval_hours' => $intervalHours,
                'keep_last' => $keepLast,
                'last_run_at' => (int)($existing['last_run_at'] ?? 0)
            ];
            kiu_save_backup_schedule($config);

            log_activity('ADMIN_BACKUP_SCHEDULE_UPDATE', 'Updated auto backup schedule settings.');
            Session::setFlash('success', 'Automatic backup settings saved.');
        } elseif ($action === 'run_auto_now') {
            $result = kiu_run_automatic_backup_if_due($db, true, 'manual-auto-run');
            if ($result === null) {
                Session::setFlash('info', 'No auto backup was created.');
            } else {
                log_activity('ADMIN_BACKUP_AUTO_RUN', 'Forced automatic backup run: ' . $result['filename']);
                Session::setFlash('success', 'Automatic backup executed: ' . $result['filename']);
            }
        }
    } catch (Exception $e) {
        Session::setFlash('danger', 'Backup failed: ' . $e->getMessage());
    }

    header('Location: ' . BASE_URL . 'modules/admin/backup.php');
    exit;
}

$backupFiles = kiu_list_backups();
$schedule = kiu_load_backup_schedule();

$page_title = 'Backup System';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Backup System</h1>
        <p>Manage backup creation, restore, and automatic scheduling.</p>
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <div class="card-header"><h3>Create Backup</h3></div>
        <div class="card-body">
            <p>Create a full SQL backup and store it on the server for recovery.</p>
            <form method="POST" onsubmit="return confirm('Create a new full backup now?');">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="action" value="create">
                <button class="btn btn-primary" type="submit">Create Backup</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <div class="card-header"><h3>Automatic Backups</h3></div>
        <div class="card-body">
            <form method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom: 14px;" onsubmit="return confirm('Save automatic backup settings?');">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="action" value="save_schedule">

                <div class="form-group">
                    <label>Enable Auto Backup</label>
                    <select class="form-control" name="auto_enabled">
                        <option value="1" <?php echo !empty($schedule['enabled']) ? 'selected' : ''; ?>>Enabled</option>
                        <option value="0" <?php echo empty($schedule['enabled']) ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Interval (hours)</label>
                    <input class="form-control" type="number" min="1" name="interval_hours" value="<?php echo (int)$schedule['interval_hours']; ?>">
                </div>

                <div class="form-group">
                    <label>Keep Latest (count)</label>
                    <input class="form-control" type="number" min="1" name="keep_last" value="<?php echo (int)$schedule['keep_last']; ?>">
                </div>

                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button class="btn btn-primary" type="submit">Save Schedule</button>
                </div>
            </form>

            <form method="POST" onsubmit="return confirm('Run automatic backup now?');">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="action" value="run_auto_now">
                <button class="btn btn-secondary" type="submit">Run Auto Backup Now</button>
            </form>

            <p style="margin-top: 12px;">
                Last auto run:
                <strong>
                    <?php echo !empty($schedule['last_run_at']) ? htmlspecialchars(date(DISPLAY_DATETIME_FORMAT, (int)$schedule['last_run_at'])) : 'Never'; ?>
                </strong>
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Stored Backups</h3></div>
        <div class="card-body">
            <?php if (empty($backupFiles)): ?>
                <p>No backups found yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Backup File</th>
                                <th>Created</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupFiles as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$backup['name']); ?></td>
                                    <td><?php echo htmlspecialchars(date(DISPLAY_DATETIME_FORMAT, (int)$backup['mtime'])); ?></td>
                                    <td><?php echo htmlspecialchars(format_file_size((int)$backup['size'])); ?></td>
                                    <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a class="btn btn-secondary btn-sm" href="?download=<?php echo rawurlencode((string)$backup['name']); ?>">Download</a>

                                        <form method="POST" onsubmit="return confirm('Delete this backup file?');" style="display:inline;">
                                            <?php echo csrf_token_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars((string)$backup['name']); ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                        </form>

                                        <form method="POST" onsubmit="return confirm('This will overwrite current data with backup data. Continue?');" style="display:inline-flex; gap:6px; align-items:center;">
                                            <?php echo csrf_token_field(); ?>
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars((string)$backup['name']); ?>">
                                            <input class="form-control" style="width:120px;" type="text" name="confirm_restore" placeholder="Type RESTORE" required>
                                            <button class="btn btn-warning btn-sm" type="submit">Restore</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
