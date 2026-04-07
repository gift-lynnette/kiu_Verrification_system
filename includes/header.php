<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>logs/kiu.png">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<?php
    $bodyClasses = [];
    if (is_logged_in()) {
        $bodyClasses[] = 'role-' . ($_SESSION['role'] ?? 'guest');
    } else {
        $bodyClasses[] = 'role-guest';
    }

    $requestPath = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestPath, '/modules/student/') !== false) {
        $bodyClasses[] = 'module-student';
    } elseif (strpos($requestPath, '/modules/finance/') !== false) {
        $bodyClasses[] = 'module-finance';
    } elseif (strpos($requestPath, '/modules/admissions/') !== false) {
        $bodyClasses[] = 'module-admissions';
    } elseif (strpos($requestPath, '/modules/admin/') !== false) {
        $bodyClasses[] = 'module-admin';
    }
?>
<body class="<?php echo htmlspecialchars(implode(' ', $bodyClasses)); ?>">
    <?php if (is_logged_in()): ?>
        <?php
            $userRole = $_SESSION['role'] ?? '';
            $isStudentModule = strpos($_SERVER['REQUEST_URI'] ?? '', '/modules/student/') !== false;
            $profileUrl = null;
            if ($userRole === ROLE_STUDENT) {
                $profileUrl = BASE_URL . 'modules/student/profile.php';
            } elseif ($userRole === ROLE_ADMIN) {
                $profileUrl = BASE_URL . 'modules/admin/dashboard.php';
            } elseif ($userRole === ROLE_FINANCE) {
                $profileUrl = BASE_URL . 'modules/finance/dashboard.php';
            } elseif ($userRole === ROLE_REGISTRAR) {
                $profileUrl = BASE_URL . 'modules/admissions/dashboard.php';
            }
        ?>
        <nav class="navbar <?php echo $isStudentModule ? 'navbar-student' : ''; ?>">
            <div class="navbar-container">
                <div class="navbar-brand">
                    <a href="<?php echo BASE_URL; ?>">
                        <img src="<?php echo BASE_URL; ?>logs/kiu.png" alt="Kampala International University" class="brand-logo">
                    </a>
                </div>
                
                <ul class="navbar-menu">
                    <?php if ($userRole === ROLE_STUDENT): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/student/dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/student/submit_documents.php">Submit Documents</a></li>
                    <?php elseif ($userRole === ROLE_FINANCE): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/finance/dashboard.php">Verification Queue</a></li>
                    <?php elseif ($userRole === ROLE_REGISTRAR): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/admissions/dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/admissions/verify_qr.php">Verify QR</a></li>
                    <?php elseif ($userRole === ROLE_ADMIN): ?>
                        <li><a href="<?php echo BASE_URL; ?>modules/admin/dashboard.php">Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/student/dashboard.php">Student Module</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/admissions/dashboard.php">Admissions Module</a></li>
                        <li><a href="<?php echo BASE_URL; ?>modules/finance/dashboard.php">Finance Module</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="navbar-user">
                    <div class="dropdown">
                        <button class="dropdown-toggle" type="button">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($_SESSION['admission_number']); ?>
                            <i class="fas fa-chevron-down chevron"></i>
                        </button>
                        <div class="dropdown-menu">
                            <?php if ($profileUrl): ?>
                            <a href="<?php echo $profileUrl; ?>">
                                <i class="fas fa-user"></i> Profile
                            </a>
                            <?php endif; ?>
                            <?php if ($userRole === ROLE_ADMIN): ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>modules/admin/users.php">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/system_settings.php">
                                <i class="fas fa-cogs"></i> System Settings
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/data_management.php">
                                <i class="fas fa-database"></i> Data Management
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/system_health.php">
                                <i class="fas fa-heartbeat"></i> System Health
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/audit_logs.php">
                                <i class="fas fa-clipboard-list"></i> Audit Logs
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/backup.php">
                                <i class="fas fa-database"></i> Backups
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admin/notifications.php">
                                <i class="fas fa-bell"></i> Notifications
                            </a>
                            <?php elseif ($userRole === ROLE_STUDENT): ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>modules/student/dashboard.php">
                                <i class="fas fa-home"></i> Student Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/student/submit_documents.php">
                                <i class="fas fa-file-upload"></i> Submit Documents
                            </a>
                            <?php elseif ($userRole === ROLE_FINANCE): ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>modules/finance/dashboard.php">
                                <i class="fas fa-check-circle"></i> Verification Queue
                            </a>
                            <?php elseif ($userRole === ROLE_REGISTRAR): ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>modules/admissions/dashboard.php">
                                <i class="fas fa-home"></i> Admissions Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/admissions/verify_qr.php">
                                <i class="fas fa-qrcode"></i> Verify QR
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>change_password.php">
                                <i class="fas fa-lock"></i> Change Password
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>
    
    <main class="main-content">
        <?php 
        // Display flash messages
        $flash = Session::getFlash();
        if ($flash):
        ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
