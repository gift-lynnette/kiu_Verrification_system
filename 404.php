<?php
require_once __DIR__ . '/config/init.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<main class="container" style="max-width:720px;margin:40px auto;">
    <div class="card">
        <div class="card-body">
            <h1>Page Not Found (404)</h1>
            <p>The page URL is invalid or no longer exists.</p>
            <p>Try one of these links:</p>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>login.php">Login</a></li>
            </ul>
        </div>
    </div>
</main>
</body>
</html>
