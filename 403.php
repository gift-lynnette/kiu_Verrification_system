<?php
require_once __DIR__ . '/config/init.php';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<main class="container" style="max-width:720px;margin:40px auto;">
    <div class="card">
        <div class="card-body">
            <h1>Access Denied (403)</h1>
            <p>You do not have permission to access this resource.</p>
            <p><a href="<?php echo BASE_URL; ?>login.php">Return to login</a></p>
        </div>
    </div>
</main>
</body>
</html>
