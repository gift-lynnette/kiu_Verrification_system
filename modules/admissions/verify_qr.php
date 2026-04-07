<?php
require_once '../../config/init.php';
require_login();
require_role([ROLE_REGISTRAR, ROLE_ADMIN]);

$page_title = 'Verify Green Card';
include '../../includes/header.php';
?>
<div class="container" style="max-width:760px;margin:30px auto;">
    <div class="page-header">
        <h1>Verify Green Card</h1>
        <p>Enter card details to verify authenticity.</p>
    </div>

    <form action="<?php echo BASE_URL; ?>verify_card.php" method="GET" class="card" style="padding:20px;">
        <div class="form-group">
            <label>Card Number</label>
            <input type="text" name="card" class="form-control" placeholder="GC2026000001">
        </div>
        <div class="form-group">
            <label>Registration Number</label>
            <input type="text" name="reg" class="form-control" placeholder="2026-01-1000">
        </div>
        <button type="submit" class="btn btn-primary">Verify</button>
    </form>
</div>
<?php include '../../includes/footer.php'; ?>
