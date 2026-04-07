<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        Session::setFlash('danger', 'Invalid CSRF token.');
        header('Location: ' . BASE_URL . 'modules/admin/users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);

    try {
        if ($action === 'create_user') {
            $admissionNumber = trim((string)($_POST['admission_number'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = trim((string)($_POST['role'] ?? ROLE_STUDENT));
            $fullName = trim((string)($_POST['full_name'] ?? ''));

            $allowedRoles = [ROLE_STUDENT, ROLE_FINANCE, ROLE_REGISTRAR, ROLE_ADMIN];
            if ($admissionNumber === '' || $email === '' || $password === '') {
                throw new Exception('Admission number, email and password are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Enter a valid email address.');
            }
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                throw new Exception('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
            }
            if (!in_array($role, $allowedRoles, true)) {
                throw new Exception('Invalid role selected.');
            }

            $stmt = $db->prepare('INSERT INTO users (admission_number, email, password_hash, role, is_active) VALUES (:admission_number, :email, :password_hash, :role, 1)');
            $stmt->execute([
                'admission_number' => $admissionNumber,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role
            ]);

            $newUserId = (int)$db->lastInsertId();
            if ($fullName !== '' && table_exists($db, 'student_profiles')) {
                $profileStmt = $db->prepare(
                    "INSERT INTO student_profiles (
                        user_id, full_name, date_of_birth, gender, nationality, phone_number,
                        address, city, country, program, faculty, department,
                        intake_year, intake_semester, student_type, study_mode
                    ) VALUES (
                        :user_id, :full_name, '2000-01-01', 'prefer_not_to_say', 'Unknown', '0000000000',
                        'N/A', 'N/A', 'Uganda', 'N/A', 'N/A', 'N/A',
                        :intake_year, 'semester_1', 'undergraduate', 'full_time'
                    )"
                );
                $profileStmt->execute([
                    'user_id' => $newUserId,
                    'full_name' => $fullName,
                    'intake_year' => (int)date('Y')
                ]);
            }

            log_activity('ADMIN_USER_CREATE', 'Created user_id=' . $newUserId . ', role=' . $role);
            Session::setFlash('success', 'User created successfully.');
        } elseif ($action === 'edit_user' && $targetUserId > 0) {
            $admissionNumber = trim((string)($_POST['admission_number'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $fullName = trim((string)($_POST['full_name'] ?? ''));

            if ($admissionNumber === '' || $email === '') {
                throw new Exception('Admission number and email are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Enter a valid email address.');
            }

            $updateUser = $db->prepare('UPDATE users SET admission_number = :admission_number, email = :email WHERE user_id = :user_id');
            $updateUser->execute([
                'admission_number' => $admissionNumber,
                'email' => $email,
                'user_id' => $targetUserId
            ]);

            if (table_exists($db, 'student_profiles')) {
                $profileExists = $db->prepare('SELECT profile_id FROM student_profiles WHERE user_id = :user_id LIMIT 1');
                $profileExists->execute(['user_id' => $targetUserId]);
                $profile = $profileExists->fetch();
                if ($profile && $fullName !== '') {
                    $updateProfile = $db->prepare('UPDATE student_profiles SET full_name = :full_name WHERE user_id = :user_id');
                    $updateProfile->execute(['full_name' => $fullName, 'user_id' => $targetUserId]);
                }
            }

            log_activity('ADMIN_USER_EDIT', 'Edited user_id=' . $targetUserId);
            Session::setFlash('success', 'User details updated successfully.');
        } elseif ($action === 'delete_user' && $targetUserId > 0) {
            if ($targetUserId === $currentUserId) {
                throw new Exception('You cannot delete your own account.');
            }

            $delete = $db->prepare('DELETE FROM users WHERE user_id = :user_id');
            $delete->execute(['user_id' => $targetUserId]);

            log_activity('ADMIN_USER_DELETE', 'Deleted user_id=' . $targetUserId);
            Session::setFlash('success', 'User deleted successfully.');
        } elseif ($action === 'unlock_account' && $targetUserId > 0) {
            $unlock = $db->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL WHERE user_id = :user_id');
            $unlock->execute(['user_id' => $targetUserId]);

            log_activity('ADMIN_USER_UNLOCK', 'Unlocked account for user_id=' . $targetUserId);
            Session::setFlash('success', 'User account unlocked.');
        } elseif ($action === 'toggle_active' && $targetUserId > 0) {
            if ($targetUserId === $currentUserId) {
                throw new Exception('You cannot deactivate your own account.');
            }

            $stmt = $db->prepare('SELECT is_active FROM users WHERE user_id = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $targetUserId]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new Exception('User not found.');
            }

            $newValue = ((int)$row['is_active'] === 1) ? 0 : 1;
            $update = $db->prepare('UPDATE users SET is_active = :is_active WHERE user_id = :user_id');
            $update->execute(['is_active' => $newValue, 'user_id' => $targetUserId]);

            log_activity('ADMIN_USER_STATUS_UPDATE', 'Changed is_active for user_id=' . $targetUserId . ' to ' . $newValue);
            Session::setFlash('success', 'User status updated successfully.');
        } elseif ($action === 'change_role' && $targetUserId > 0) {
            $allowedRoles = [ROLE_STUDENT, ROLE_FINANCE, ROLE_REGISTRAR, ROLE_ADMIN];
            $newRole = (string)($_POST['new_role'] ?? '');
            if (!in_array($newRole, $allowedRoles, true)) {
                throw new Exception('Invalid role selected.');
            }
            if ($targetUserId === $currentUserId && $newRole !== ROLE_ADMIN) {
                throw new Exception('You cannot remove your own admin role.');
            }

            $update = $db->prepare('UPDATE users SET role = :role WHERE user_id = :user_id');
            $update->execute(['role' => $newRole, 'user_id' => $targetUserId]);

            log_activity('ADMIN_USER_ROLE_UPDATE', 'Changed role for user_id=' . $targetUserId . ' to ' . $newRole);
            Session::setFlash('success', 'User role updated successfully.');
        }
    } catch (Exception $e) {
        Session::setFlash('danger', $e->getMessage());
    }

    header('Location: ' . BASE_URL . 'modules/admin/users.php');
    exit;
}

$users = $db->query(
    "SELECT u.user_id, u.admission_number, u.email, u.role, u.is_active, u.last_login_at, u.created_at,
            u.login_attempts, u.locked_until,
            sp.full_name
     FROM users u
     LEFT JOIN student_profiles sp ON sp.user_id = u.user_id
     ORDER BY u.created_at DESC"
)->fetchAll();

$page_title = 'Manage Users';
include '../../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1>Manage Users</h1>
        <p>Add, edit, remove users and manage role/security controls.</p>
    </div>

    <div class="card" style="margin-bottom: 18px;">
        <div class="card-header">
            <h3>Add User</h3>
        </div>
        <div class="card-body">
            <form method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;" onsubmit="return confirm('Create this user account?');">
                <?php echo csrf_token_field(); ?>
                <input type="hidden" name="action" value="create_user">
                <div class="form-group">
                    <label>Full Name (optional)</label>
                    <input type="text" class="form-control" name="full_name" placeholder="e.g. Jane Doe">
                </div>
                <div class="form-group">
                    <label>Admission Number</label>
                    <input type="text" class="form-control" name="admission_number" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select class="form-control" name="role">
                        <option value="student">Student</option>
                        <option value="finance_officer">Finance</option>
                        <option value="registrar">Admissions</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" style="display:flex; align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>All Users</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Admission</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Security</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo (int)$user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars((string)($user['full_name'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars((string)$user['admission_number']); ?></td>
                                <td><?php echo htmlspecialchars((string)$user['email']); ?></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:8px; align-items:center;" onsubmit="return confirm('Apply role change for this user?');">
                                        <?php echo csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                        <select name="new_role" class="form-control" style="min-width: 150px;">
                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="finance_officer" <?php echo $user['role'] === 'finance_officer' ? 'selected' : ''; ?>>Finance</option>
                                            <option value="registrar" <?php echo $user['role'] === 'registrar' ? 'selected' : ''; ?>>Admissions</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ((int)$user['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($user['locked_until']) || (int)$user['login_attempts'] > 0): ?>
                                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                            <span class="badge badge-warning">Attempts: <?php echo (int)$user['login_attempts']; ?></span>
                                            <?php if (!empty($user['locked_until'])): ?>
                                                <span class="badge badge-danger">Locked</span>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Unlock this account?');">
                                                <?php echo csrf_token_field(); ?>
                                                <input type="hidden" name="action" value="unlock_account">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Unlock</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-success">Secure</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['last_login_at'] ? htmlspecialchars(format_date((string)$user['last_login_at'], DISPLAY_DATETIME_FORMAT)) : 'Never'; ?></td>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form method="POST" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;" onsubmit="return confirm('Save user profile changes?');">
                                        <?php echo csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="edit_user">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                        <input type="text" class="form-control" style="width:130px;" name="admission_number" value="<?php echo htmlspecialchars((string)$user['admission_number']); ?>" required>
                                        <input type="email" class="form-control" style="width:180px;" name="email" value="<?php echo htmlspecialchars((string)$user['email']); ?>" required>
                                        <input type="text" class="form-control" style="width:150px;" name="full_name" value="<?php echo htmlspecialchars((string)($user['full_name'] ?? '')); ?>" placeholder="Full name">
                                        <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Change account status for this user?');">
                                        <?php echo csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <?php echo ((int)$user['is_active'] === 1) ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
                                        <?php echo csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                    </div>
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
