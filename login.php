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

function redirect_to_requested_page_if_available() {
    $requested = $_SESSION['redirect_after_login'] ?? '';
    if (empty($requested)) {
        return false;
    }

    unset($_SESSION['redirect_after_login']);
    $path = ltrim(parse_url($requested, PHP_URL_PATH) ?? '', '/');
    $basePath = trim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');

    if ($basePath !== '' && strpos($path, $basePath . '/') === 0) {
        $path = substr($path, strlen($basePath) + 1);
    }

    if ($path !== '') {
        redirect($path);
        return true;
    }

    return false;
}

if (isset($_GET['switch']) && is_logged_in()) {
    $auth = new Auth($db);
    $auth->logout();
    redirect('login.php');
}

$error = '';
$info = '';

if (is_logged_in()) {
    $currentRole = $_SESSION['role'] ?? 'unknown';
    $info = "You are currently logged in as " . htmlspecialchars($_SESSION['admission_number'] ?? 'user') . " ({$currentRole}). Log in again below to switch account.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth($db);
    
    $login_identifier = sanitize_input($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($login_identifier) && !empty($password)) {
        $result = $auth->login($login_identifier, $password);
        
        if ($result['success']) {
            session_regenerate_id(true);
            if (!redirect_to_requested_page_if_available()) {
                redirect_by_role($result['user']['role'] ?? '');
            }
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Please provide admission/staff number (or email) and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        :root {
            --ink: #1f4f36;
            --blue: #3aa76d;
            --soft-bg: #eefaf2;
            --line: #cfe8d8;
            --danger-bg: #fce9e9;
            --danger-text: #8c1f1f;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", "Trebuchet MS", Tahoma, sans-serif;
            background: linear-gradient(145deg, #eefaf2 0%, #f8fdf9 100%);
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(18, 44, 72, 0.13);
            overflow: hidden;
        }

        .card-top {
            background: linear-gradient(90deg, #5bbf85 0%, #2f9f68 100%);
            color: #fff;
            padding: 20px 24px;
        }

        .card-top h1 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.4px;
            font-weight: 700;
            text-align: center;
        }

        .card-top p {
            margin: 6px 0 0;
            font-size: 13px;
            opacity: 0.9;
            text-align: center;
        }

        .card-body {
            padding: 24px;
        }

        .form-head h2 {
            margin: 0;
            font-size: 24px;
        }

        .form-head p {
            margin: 6px 0 0;
            color: #4f6f5a;
            font-size: 13px;
        }

        .error-box {
            margin-top: 14px;
            border: 1px solid #f3c1c1;
            background: var(--danger-bg);
            color: var(--danger-text);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
        }

        .auth-form { margin-top: 18px; }
        .form-group { margin-bottom: 14px; }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #2f5f46;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            height: 42px;
            border: 1px solid #b9dec9;
            border-radius: 10px;
            padding: 0 12px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(58, 167, 109, 0.18);
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #3e6b55;
        }

        .btn-login {
            width: 100%;
            height: 44px;
            border: 0;
            border-radius: 10px;
            background: var(--blue);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-login:hover { filter: brightness(1.05); }

        .auth-footer {
            margin-top: 14px;
            text-align: center;
            font-size: 13px;
        }

        .auth-footer a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-top">
            <h1>Kampala International University</h1>
            <p>Automated Verification System</p>
        </div>
        <div class="card-body">
            <div class="form-head">
                <h2>Sign In</h2>
                <p>Use your admission/staff number or email and password.</p>
            </div>

            <?php if ($info): ?>
                <div class="alert alert-info" style="margin-top: 14px;">
                    <?php echo $info; ?>
                    <br><a href="<?php echo BASE_URL; ?>login.php?switch=1">Logout current session</a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                          <label for="login_identifier">Admission/Staff Number or Email</label>
                          <input type="text" id="login_identifier" name="login_identifier" 
                              value="<?php echo htmlspecialchars($_POST['login_identifier'] ?? ''); ?>" 
                           required placeholder="e.g., KIU/2024/001">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           required placeholder="Enter your password">
                </div>
                
                <div class="remember-row">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Remember me</label>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="auth-footer">
                <p><a href="forgot_password.php">Forgot password?</a></p>
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
