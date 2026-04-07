<?php
require_once 'config/init.php';

function redirect_by_role($role) {
    if ($role === ROLE_STUDENT) {
        redirect('modules/student/dashboard.php');
    } elseif ($role === ROLE_FINANCE) {
        redirect('modules/finance/dashboard.php');
    } elseif ($role === ROLE_REGISTRAR) {
        redirect('modules/admissions/dashboard.php');
    } elseif ($role === ROLE_ADMIN) {
        redirect('modules/admin/dashboard.php');
    } else {
        redirect('index.php');
    }
}

// Redirect if already logged in
if (is_logged_in()) {
    redirect_by_role($_SESSION['role'] ?? '');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator();
    $auth = new Auth($db);
    
    $admission_number = sanitize_input($_POST['admission_number'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    $validator->required('admission_number', $admission_number, 'Admission Number');
    $validator->admissionNumber('admission_number', $admission_number, 'Admission Number');
    $validator->required('email', $email, 'Email');
    $validator->email('email', $email, 'Email');
    $validator->required('password', $password, 'Password');
    $validator->password('password', $password, 'Password');
    $validator->match('confirm_password', $confirm_password, $password, 'Confirm Password', 'Password');
    
    if (!$validator->hasErrors()) {
        $result = $auth->register($admission_number, $email, $password);
        
        if ($result['success']) {
            $success = $result['message'];
            // Redirect to login after 2 seconds
            header("refresh:2;url=login.php");
        } else {
            $error = $result['message'];
        }
    } else {
        $error = $validator->getFirstError();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h2>Student Registration</h2>
                <p>KIU Automated Verification System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="admission_number">Admission Number</label>
                    <input type="text" id="admission_number" name="admission_number" 
                           value="<?php echo htmlspecialchars($_POST['admission_number'] ?? ''); ?>" 
                           required placeholder="e.g., KIU/2024/001">
                    <small>Enter your university admission number</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           required placeholder="your.email@student.kiu.ac.ug">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Minimum 8 characters">
                    <small>Must contain uppercase, lowercase, and numbers</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           required placeholder="Re-enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
