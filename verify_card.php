<?php
require_once __DIR__ . '/config/init.php';

$card = trim($_GET['card'] ?? '');
$reg = trim($_GET['reg'] ?? '');

$record = null;
if ($card !== '' || $reg !== '') {
    try {
        $stmt = $db->prepare("
            SELECT gc.card_id, gc.card_number, gc.registration_number, 
                   gc.full_name, gc.program, gc.faculty, gc.student_photo_path,
                   gc.issue_date, gc.expiry_date, gc.academic_year, gc.semester,
                   gc.is_active,
                   u.user_id, u.admission_number, u.email
            FROM green_cards gc
            LEFT JOIN users u ON gc.user_id = u.user_id
            WHERE gc.card_number = :card OR gc.registration_number = :reg
            LIMIT 1
        ");
        $result = $stmt->execute([
            ':card' => $card,
            ':reg' => $reg
        ]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("verify_card query error: " . $e->getMessage());
    }
}

$status = 'not_found';
if ($record && is_array($record)) {
    $today = date('Y-m-d');
    if (!$record['is_active']) {
        $status = 'revoked';
    } elseif ($record['expiry_date'] < $today) {
        $status = 'expired';
    } else {
        $status = 'valid';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Card Verification</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
        .container { max-width: 760px; margin: 20px auto; padding: 0 12px; }
        .verification-card {
            background: linear-gradient(140deg, #eaf8ef 0%, #d8f0df 100%);
            border: 2px solid #0f5132;
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
        }
        .student-header {
            text-align: center;
            border-bottom: 2px solid #6ea784;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .student-name {
            font-size: 24px;
            font-weight: 900;
            color: #0b5d3b;
            text-transform: uppercase;
        }
        .student-reg {
            font-size: 14px;
            color: #1e5a47;
            margin-top: 6px;
            font-weight: 700;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px dotted #7ea98c;
        }
        .detail-label {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            color: #0f5132;
            letter-spacing: 0.3px;
        }
        .detail-value {
            font-size: 15px;
            font-weight: 700;
            color: #003d27;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 12px;
        }
        .status-valid { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .status-expired { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .status-revoked { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-section { display: none; }
        .form-section.visible { display: block; }
        @media (max-width: 640px) {
            .detail-row { grid-template-columns: 1fr; gap: 4px; }
            .detail-label { font-size: 12px; }
            .student-name { font-size: 20px; }
        }
    </style>
</head>
<body>
<main class="container">
    <h1>Green Card Verification</h1>
    <form method="GET" class="card form-section <?php echo ($card === '' && $reg === '') ? 'visible' : ''; ?>" style="padding:20px;margin:20px 0;">
        <div class="form-group">
            <label>Card Number</label>
            <input class="form-control" type="text" name="card" value="<?php echo htmlspecialchars($card); ?>" placeholder="e.g. GC2026000001">
        </div>
        <div class="form-group">
            <label>Registration Number</label>
            <input class="form-control" type="text" name="reg" value="<?php echo htmlspecialchars($reg); ?>" placeholder="e.g. 2026-01-1000">
        </div>
        <button class="btn btn-primary" type="submit">Verify</button>
    </form>

    <?php if ($card !== '' || $reg !== ''): ?>
        <div class="verification-card" style="text-align: center;">
            <span class="status-badge status-<?php echo $status; ?>">
                <?php if ($status === 'valid') echo '✓ VALID'; elseif ($status === 'expired') echo '⏱ EXPIRED'; elseif ($status === 'revoked') echo '✕ REVOKED'; else echo '? NOT FOUND'; ?>
            </span>
        </div>
        <?php if ($status === 'not_found'): ?>
            <div class="verification-card" style="border-color: #fee2e2; background: #fef2f2;">
                <p style="color: #991b1b; margin: 0; font-size: 14px;">
                    <strong>Card not found in database</strong><br>
                    <small>Searched for card: <code style="background: #fff; padding: 2px 4px;"><?php echo htmlspecialchars($card); ?></code></small>
                </p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($record): ?>
    <div class="verification-card">
        <div class="student-header">
            <div class="student-name"><?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?></div>
            <div class="student-reg"><?php echo htmlspecialchars($record['registration_number'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Card Number</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['card_number'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Admission No.</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['admission_number'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Email</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['email'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Program</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['program'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Faculty</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['faculty'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Academic Year</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['academic_year'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Issue Date</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['issue_date'] ?? 'N/A'); ?></div>
        </div>
        <div class="detail-row" style="border-bottom: none;">
            <div class="detail-label">Expiry Date</div>
            <div class="detail-value"><?php echo htmlspecialchars($record['expiry_date'] ?? 'N/A'); ?></div>
        </div>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
