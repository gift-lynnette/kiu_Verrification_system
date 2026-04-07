<?php
        a {
 * Admin Password Reset Utility
 * Run this file directly in your browser: http://localhost/research/reset_admin_password.php
 * 
            background-color: #3aa76d;
 */

require_once 'config/database.php';

        a:hover { background-color: #2f9f68; }
$new_password = 'admin123'; // Change this to your desired password
$admin_email = 'admin@kiu.ac.ug';

try {
    // Create database connection
    $database = new Database();
    $db = $database->connect();
    
    // Hash the new password
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    
    // Update admin password
    $stmt = $db->prepare("
        UPDATE users 
        SET password_hash = :password_hash,
            login_attempts = 0,
            locked_until = NULL,
            updated_at = NOW()
        WHERE email = :email AND role = 'admin'
    ");
    
    $result = $stmt->execute([
        'password_hash' => $password_hash,
        'email' => $admin_email
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo "<h2 style='color: green;'>✓ Password Reset Successful!</h2>";
        echo "<p><strong>Email:</strong> {$admin_email}</p>";
        echo "<p><strong>New Password:</strong> {$new_password}</p>";
        echo "<hr>";
        echo "<p style='color: red;'><strong>IMPORTANT:</strong> Delete this file (reset_admin_password.php) immediately for security!</p>";
        echo "<p><a href='login.php'>Go to Login Page</a></p>";
        
        // Log the password reset
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, description, ip_address, user_agent)
            SELECT user_id, 'PASSWORD_RESET', 'user', 'Admin password reset via utility script', :ip, :user_agent
            FROM users WHERE email = :email
        ");
        $stmt->execute([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'email' => $admin_email
        ]);
        
    } else {
        echo "<h2 style='color: red;'>✗ Error: Admin user not found</h2>";
        echo "<p>Please check the admin email in the script.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Database Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h2 { margin-top: 0; }
        p { line-height: 1.6; }
        hr { margin: 20px 0; }
        a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3aa76d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        a:hover { background-color: #2f9f68; }
    </style>
</head>
<body>
    <h1>🔐 Admin Password Reset Utility</h1>
    <p>This utility resets the admin password to: <code><?php echo htmlspecialchars($new_password); ?></code></p>
    <hr>
    <?php if (!isset($result)): ?>
    <p style='color: orange;'>⚠️ Page not executed. Refresh to run the password reset.</p>
    <?php endif; ?>
</body>
</html>
