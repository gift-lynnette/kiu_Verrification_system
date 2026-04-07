<?php
require_once 'config/init.php';
require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $validator = new Validator();
        $validator->required('current_password', $current_password, 'Current Password');
        $validator->required('new_password', $new_password, 'New Password');
        $validator->required('confirm_password', $confirm_password, 'Confirm Password');

        if (!empty($new_password) && strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $validator->errors['new_password_len'] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        }

        if ($new_password !== $confirm_password) {
            $validator->errors['password_match'] = 'New password and confirmation do not match.';
        }

        if (!$validator->hasErrors()) {
            $auth = new Auth($db);
            $result = $auth->changePassword((int)$_SESSION['user_id'], $current_password, $new_password);

            if (!empty($result['success'])) {
                $success = 'Password changed successfully.';
            } else {
                $error = $result['message'] ?? 'Failed to change password.';
            }
        } else {
            $error = implode(' ', array_values($validator->errors));
        }
    }
}

$page_title = 'Change Password';
include 'includes/header.php';
?>

<div class="container" style="max-width: 680px;">
    <div class="card">
        <div class="card-header">
            <h3>Change Password</h3>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_token_field(); ?>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
