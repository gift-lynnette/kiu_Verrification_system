<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);

$hasSettingsTable = table_exists($db, 'system_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        Session::setFlash('danger', 'Invalid CSRF token.');
        header('Location: ' . BASE_URL . 'modules/admin/system_settings.php');
        exit;
    }

    if (!$hasSettingsTable) {
        Session::setFlash('warning', 'system_settings table is not available in this database.');
        header('Location: ' . BASE_URL . 'modules/admin/system_settings.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_setting') {
            $settingId = (int)($_POST['setting_id'] ?? 0);
            $settingValue = trim((string)($_POST['setting_value'] ?? ''));

            $stmt = $db->prepare(
                'UPDATE system_settings
                 SET setting_value = :setting_value, last_modified_by = :user_id
                 WHERE setting_id = :setting_id AND is_editable = 1'
            );
            $stmt->execute([
                'setting_value' => $settingValue,
                'user_id' => (int)($_SESSION['user_id'] ?? 0),
                'setting_id' => $settingId
            ]);

            Session::setFlash('success', 'Setting updated.');
            log_activity('ADMIN_SETTING_UPDATE', 'Updated setting_id=' . $settingId);
        } elseif ($action === 'create_setting') {
            $settingKey = trim((string)($_POST['setting_key'] ?? ''));
            $settingValue = trim((string)($_POST['setting_value'] ?? ''));
            $settingType = trim((string)($_POST['setting_type'] ?? 'string'));
            $settingCategory = trim((string)($_POST['setting_category'] ?? 'general'));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($settingKey === '') {
                throw new Exception('Setting key is required.');
            }

            $allowedTypes = ['string', 'integer', 'boolean', 'json', 'date', 'email'];
            if (!in_array($settingType, $allowedTypes, true)) {
                $settingType = 'string';
            }

            $stmt = $db->prepare(
                'INSERT INTO system_settings
                    (setting_key, setting_value, setting_type, setting_category, description, is_editable, is_sensitive, last_modified_by)
                 VALUES
                    (:setting_key, :setting_value, :setting_type, :setting_category, :description, 1, 0, :user_id)'
            );
            $stmt->execute([
                'setting_key' => $settingKey,
                'setting_value' => $settingValue,
                'setting_type' => $settingType,
                'setting_category' => $settingCategory,
                'description' => $description,
                'user_id' => (int)($_SESSION['user_id'] ?? 0)
            ]);

            Session::setFlash('success', 'Setting created.');
            log_activity('ADMIN_SETTING_CREATE', 'Created setting_key=' . $settingKey);
        }
    } catch (Exception $e) {
        Session::setFlash('danger', $e->getMessage());
    }

    header('Location: ' . BASE_URL . 'modules/admin/system_settings.php');
    exit;
}

$settings = [];
if ($hasSettingsTable) {
    $settings = $db->query('SELECT * FROM system_settings ORDER BY setting_category, setting_key')->fetchAll();
}

$page_title = 'System Settings';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>System Settings</h1>
        <p>Configure editable runtime settings.</p>
    </div>

    <?php if (!$hasSettingsTable): ?>
        <div class="alert alert-warning">The <strong>system_settings</strong> table is missing. Run the full schema/migration to enable this page.</div>
    <?php else: ?>
        <div class="card" style="margin-bottom: 18px;">
            <div class="card-header"><h3>Create Setting</h3></div>
            <div class="card-body">
                <form method="POST" class="form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px;" onsubmit="return confirm('Create this setting?');">
                    <?php echo csrf_token_field(); ?>
                    <input type="hidden" name="action" value="create_setting">
                    <div class="form-group">
                        <label>Setting Key</label>
                        <input class="form-control" type="text" name="setting_key" required>
                    </div>
                    <div class="form-group">
                        <label>Value</label>
                        <input class="form-control" type="text" name="setting_value" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control" name="setting_type">
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="boolean">boolean</option>
                            <option value="json">json</option>
                            <option value="date">date</option>
                            <option value="email">email</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <input class="form-control" type="text" name="setting_category" value="general" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Description</label>
                        <input class="form-control" type="text" name="description">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <button class="btn btn-primary" type="submit">Create Setting</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>Editable Settings</h3></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settings as $setting): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$setting['setting_key']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$setting['setting_category']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$setting['setting_type']); ?></td>
                                    <td style="min-width:280px;">
                                        <?php if ((int)$setting['is_editable'] === 1): ?>
                                            <form method="POST" style="display:flex; gap:8px;" onsubmit="return confirm('Save this setting value?');">
                                                <?php echo csrf_token_field(); ?>
                                                <input type="hidden" name="action" value="update_setting">
                                                <input type="hidden" name="setting_id" value="<?php echo (int)$setting['setting_id']; ?>">
                                                <input class="form-control" type="text" name="setting_value" value="<?php echo htmlspecialchars((string)$setting['setting_value']); ?>">
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars((string)$setting['setting_value']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($setting['description'] ?? '')); ?></td>
                                    <td>
                                        <?php if ((int)$setting['is_editable'] === 1): ?>
                                                <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-info">Read only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
