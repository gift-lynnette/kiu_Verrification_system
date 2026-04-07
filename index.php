<?php
require_once 'config/init.php';

// Redirect based on login status and role
if (is_logged_in()) {
    $role = $_SESSION['role'];
    
    if ($role === ROLE_STUDENT) {
        redirect('modules/student/dashboard.php');
    } elseif ($role === ROLE_FINANCE) {
        redirect('modules/finance/dashboard.php');
    } elseif ($role === ROLE_REGISTRAR) {
        redirect('modules/admissions/dashboard.php');
    } elseif ($role === ROLE_ADMIN) {
        redirect('modules/admin/dashboard.php');
    }
} else {
    redirect('login.php');
}
