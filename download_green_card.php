<?php
if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . '/config/init.php';
require_login();

$cardIdRaw = $_GET['id'] ?? '';
if (!is_string($cardIdRaw) || !ctype_digit($cardIdRaw)) {
    http_response_code(400);
    exit('Invalid request.');
}
$mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : 'download';
if (!in_array($mode, ['view', 'pdf', 'card', 'download'], true)) {
    $mode = 'download';
}

$cardId = (int)$cardIdRaw;
$stmt = $db->prepare("
    SELECT gc.card_id, gc.submission_id, gc.user_id, gc.card_number, gc.pdf_path,
           gc.registration_number, gc.full_name, gc.program, gc.faculty, gc.student_photo_path,
        gc.issue_date, gc.expiry_date, gc.academic_year, gc.semester, gc.qr_code_image_path,
           u.admission_number
    FROM green_cards gc
    LEFT JOIN users u ON u.user_id = gc.user_id
    WHERE gc.card_id = :card_id
    LIMIT 1
");
$stmt->execute(['card_id' => $cardId]);
$card = $stmt->fetch();

if (!$card) {
    http_response_code(404);
    exit('Green card not found.');
}

$role = $_SESSION['role'] ?? '';
$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$isOwnerStudent = ($role === ROLE_STUDENT) && ($sessionUserId === (int)$card['user_id']);
$isAdminUser = in_array($role, [ROLE_ADMIN, ROLE_REGISTRAR], true);

if (!$isOwnerStudent && !$isAdminUser) {
    http_response_code(403);
    exit('Access denied.');
}

$validityYears = max(1, (int)(defined('GREEN_CARD_VALIDITY_YEARS') ? GREEN_CARD_VALIDITY_YEARS : 1));
$issueTimestamp = strtotime((string)($card['issue_date'] ?? ''));
if ($issueTimestamp === false) {
    $issueTimestamp = time();
}
$expectedExpiryRaw = date('Y-m-d', strtotime('+' . $validityYears . ' years', $issueTimestamp));

$baseAcademicYear = (int)date('Y', $issueTimestamp);
$existingAcademicYear = trim((string)($card['academic_year'] ?? ''));
if (preg_match('/^(\d{4})\/(\d{4})$/', $existingAcademicYear, $parts)) {
    $baseAcademicYear = (int)$parts[1];
}
$expectedAcademicYear = $baseAcademicYear . '/' . ($baseAcademicYear + $validityYears);

$needsCardFieldRefresh =
    ((string)($card['expiry_date'] ?? '') !== $expectedExpiryRaw) ||
    ($existingAcademicYear !== $expectedAcademicYear);

if ($needsCardFieldRefresh) {
    try {
        $refreshStmt = $db->prepare(
            'UPDATE green_cards SET expiry_date = :expiry_date, academic_year = :academic_year WHERE card_id = :card_id'
        );
        $refreshStmt->execute([
            'expiry_date' => $expectedExpiryRaw,
            'academic_year' => $expectedAcademicYear,
            'card_id' => $cardId
        ]);
        $card['expiry_date'] = $expectedExpiryRaw;
        $card['academic_year'] = $expectedAcademicYear;
    } catch (Throwable $e) {
        error_log('Green card field refresh failed: ' . $e->getMessage());
    }
}

$update = $db->prepare("
    UPDATE green_cards
    SET download_count = download_count + 1,
        downloaded_at = COALESCE(downloaded_at, NOW())
    WHERE card_id = :card_id
");
$update->execute(['card_id' => $cardId]);

$semesterLabel = ucwords(str_replace('_', ' ', (string)($card['semester'] ?? '')));
$academicYear = (string)($card['academic_year'] ?? '');
$issueDateLabel = 'N/A';
$issueDateRaw = (string)($card['issue_date'] ?? '');
if ($issueDateRaw !== '' && strtotime($issueDateRaw) !== false) {
    $issueDateLabel = date('d M Y', strtotime($issueDateRaw));
}
$expiryDateLabel = 'N/A';
$expiryDateRaw = (string)($card['expiry_date'] ?? '');
if ($expiryDateRaw !== '' && strtotime($expiryDateRaw) !== false) {
    $expiryDateLabel = date('d M Y', strtotime($expiryDateRaw));
}
$studyYear = '';
if (preg_match('/^(\d{4})\/(\d{4})$/', $academicYear, $m)) {
    $startYear = (int)$m[1];
    $issueYear = (int)date('Y', strtotime((string)$card['issue_date']));
    $studyYear = (string)max(1, $issueYear - $startYear + 1);
}

$fullName = trim((string)($card['full_name'] ?? ''));
$registrationNo = trim((string)($card['registration_number'] ?? ''));
$admissionNumber = trim((string)($card['admission_number'] ?? ''));
$courseName = trim((string)($card['program'] ?? ''));
$departmentName = trim((string)($card['faculty'] ?? ''));

if ($fullName === '') { $fullName = 'N/A'; }
if ($registrationNo === '') { $registrationNo = 'N/A'; }
if ($admissionNumber === '') { $admissionNumber = 'N/A'; }
if ($courseName === '') { $courseName = 'N/A'; }
if ($semesterLabel === '') { $semesterLabel = 'N/A'; }
if ($studyYear === '') { $studyYear = 'N/A'; }
if ($academicYear === '') { $academicYear = 'N/A'; }
if ($departmentName === '') { $departmentName = 'N/A'; }

if ($mode === 'card') {
    if (ob_get_length()) {
        ob_clean();
    }

    $photoSrc = '';
    $photoPath = trim(str_replace('\\', '/', (string)$card['student_photo_path']));
    if ($photoPath !== '' && strpos($photoPath, '..') === false) {
        $photoSrc = BASE_URL . ltrim($photoPath, '/');
    }

    $verificationUrl = PUBLIC_BASE_URL . 'verify_card.php?card=' . rawurlencode((string)$card['card_number']);
    $qrPayload = gc_generate_qr_code_asset($verificationUrl, (string)$card['card_number']);
    $qrSrc = (string)($qrPayload['image_src'] ?? '');
    if ($qrSrc === '') {
        $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($verificationUrl);
    }

    $directorSignatureSrc = '';
    $directorSignatureCandidates = [
        'assets/img/director_signature.png',
        'assets/img/director_signature.jpg',
        'assets/img/director_signature.jpeg',
        'assets/img/director_signature.webp'
    ];
    foreach ($directorSignatureCandidates as $candidate) {
        if (is_file(SITE_ROOT . '/' . $candidate)) {
            $directorSignatureSrc = BASE_URL . $candidate;
            break;
        }
    }

    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Green Card Preview</title>
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: #eef7f0;
                font-family: DejaVu Sans, Arial, sans-serif;
                color: #0b1220;
            }
            .wrap { max-width: 980px; margin: 20px auto; padding: 0 12px; }
            .card-shell {
                width: 176mm;
                margin: 0 auto;
                border-radius: 12px;
                border: 1.3px solid #0f5132;
                background: linear-gradient(180deg, #eaf8ef 0%, #d8f0df 100%);
                overflow: hidden;
                position: relative;
                box-shadow: 0 12px 28px rgba(12, 58, 37, 0.2);
            }
            .card-watermark {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-28deg);
                font-size: 52mm;
                font-weight: 900;
                letter-spacing: 2.5mm;
                color: rgba(11, 93, 59, 0.11);
                text-transform: uppercase;
                pointer-events: none;
                user-select: none;
                z-index: 0;
                line-height: 1;
                white-space: nowrap;
            }
            .head {
                min-height: 22mm;
                padding: 0 8mm;
                background: linear-gradient(140deg, #0b5d3b 0%, #0f7a4d 60%, #3ca56f 100%);
                color: #fff;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                position: relative;
                z-index: 1;
            }
            .university {
                font-size: 17px;
                font-weight: 700;
                letter-spacing: 0.7px;
                text-transform: uppercase;
            }
            .title {
                margin-top: 1.2mm;
                font-size: 13px;
                letter-spacing: 0.4px;
                text-transform: uppercase;
            }
            .content {
                padding: 8mm 10mm 8mm;
                display: table;
                width: 100%;
                position: relative;
                z-index: 1;
                background:
                    radial-gradient(circle at 18% 18%, rgba(15, 122, 77, 0.08) 0, rgba(15, 122, 77, 0.08) 26mm, transparent 27mm),
                    radial-gradient(circle at 84% 78%, rgba(60, 165, 111, 0.08) 0, rgba(60, 165, 111, 0.08) 30mm, transparent 31mm);
            }
            .left, .right { display: table-cell; vertical-align: top; }
            .left { width: 49mm; padding-right: 4.8mm; }
            .right { width: auto; }
            .photo-box {
                width: 40mm;
                height: 49mm;
                border: 1px solid #6ea784;
                border-radius: 6px;
                overflow: hidden;
                background: #fff;
                margin-left: 1.2mm;
            }
            .photo-box img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center top;
                image-orientation: from-image;
            }
            .meta {
                margin-top: 3mm;
                font-size: 8px;
                color: #0f172a;
                line-height: 1.45;
                text-transform: uppercase;
                word-wrap: break-word;
                padding-left: 1.2mm;
            }
            .qr-box {
                margin-top: 3mm;
                margin-left: 1.2mm;
                width: 30mm;
                height: 30mm;
                border: 1px solid #6ea784;
                border-radius: 4px;
                background: #fff;
                padding: 1.8mm;
            }
            .qr-box img { width: 100%; height: 100%; object-fit: contain; }
            .row {
                margin-bottom: 2.0mm;
                border-bottom: 1px dotted #7ea98c;
                padding-bottom: 1.0mm;
            }
            .label {
                font-size: 9px;
                color: #000000;
                text-transform: uppercase;
                letter-spacing: 0.32px;
                margin-bottom: 0.4mm;
                font-weight: 800;
            }
            .value {
                font-size: 12.8px;
                color: #000000;
                font-weight: 900;
                line-height: 1.28;
                word-wrap: break-word;
            }
            .cert {
                margin-top: 3.0mm;
                font-size: 8px;
                color: #000000;
                line-height: 1.52;
                text-transform: uppercase;
                font-weight: 800;
            }
            .sign {
                margin-top: 1.6mm;
                display: table;
                width: 100%;
            }
            .sign .left, .sign .right {
                display: table-cell;
                vertical-align: bottom;
            }
            .sign .left {
                width: 44%;
                font-size: 8.6px;
                font-weight: 800;
                color: #000000;
                text-transform: uppercase;
            }
            .sign .right {
                text-align: left;
                padding-top: 0.3mm;
                padding-bottom: 0.2mm;
            }
            .sig-line {
                display: inline-block;
                min-width: 52mm;
                border-bottom: 1px solid #3f6f52;
                height: 2.6mm;
            }
            .sig-name {
                margin-top: 0;
                margin-bottom: 0.6mm;
                font-size: 10px;
                color: #000000;
                font-family: DejaVu Sans, Arial, sans-serif;
                font-weight: 900;
            }
            .sig-image-wrap {
                margin-top: 0;
                margin-bottom: 0;
                line-height: 0;
            }
            .sig-image {
                max-width: 40mm;
                height: 8.5mm;
                width: auto;
                object-fit: cover;
                object-position: center;
                display: block;
                margin-left: 0;
            }
            .sig-title {
                margin-top: 0.3mm;
                font-size: 8.2px;
                font-weight: 900;
                color: #000000;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .footer {
                border-top: 1px solid #a7c9b3;
                background: #e9f8ee;
                padding: 3.6mm 10mm;
                font-size: 8.3px;
                color: #1e293b;
                display: table;
                width: 100%;
                position: relative;
                z-index: 1;
            }
            .left-f, .right-f {
                display: table-cell;
                vertical-align: middle;
            }
            .right-f {
                text-align: right;
                font-weight: 700;
                color: #0b5d3b;
                text-transform: uppercase;
            }
            .mobile-back {
                display: block;
                padding: 0;
                margin: 0 0 16px 0;
            }
            .mobile-back a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 20px;
                background: #0f5132;
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
                font-size: 15px;
                font-weight: 600;
                transition: all 0.2s ease;
                width: 100%;
                box-shadow: 0 2px 6px rgba(15, 81, 50, 0.3);
            }
            .mobile-back a:active {
                background: #0d4229;
                box-shadow: 0 1px 3px rgba(15, 81, 50, 0.5);
            }
            .mobile-back a::before {
                content: '← ';
                font-weight: 800;
                font-size: 18px;
            }
            @media (min-width: 821px) {
                .mobile-back {
                    display: none;
                }
            }
            @media (max-width: 820px) {
                .content, .left, .right, .footer, .left-f, .right-f, .sign, .sign .left, .sign .right {
                    display: block;
                    width: 100%;
                }
                .left { padding-right: 0; margin-bottom: 12px; }
                .sign .right { margin-top: 8px; text-align: left; }
                .sig-line { min-width: 160px; }
                .right-f { text-align: left; margin-top: 6px; }
                .card-shell { width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="mobile-back">
                <a href="<?php echo htmlspecialchars(BASE_URL); ?>modules/student/dashboard.php">Back to Dashboard</a>
            </div>
            <div class="card-shell">
                <div class="card-watermark" aria-hidden="true">KIU</div>
                <div class="head">
                    <div class="university">Kampala International University</div>
                    <div class="title">Student Green Card</div>
                </div>

                <div class="content">
                    <div class="left">
                        <div class="photo-box">
                            <?php if ($photoSrc !== ''): ?>
                                <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="Student Photo">
                            <?php endif; ?>
                        </div>
                        <div class="meta">ADM NO: <?php echo htmlspecialchars($admissionNumber); ?></div>
                        <div class="qr-box">
                            <img src="<?php echo htmlspecialchars($qrSrc); ?>" alt="QR Code">
                        </div>
                    </div>

                    <div class="right">
                        <div class="row"><div class="label">STUDENT'S NAME</div><div class="value"><?php echo htmlspecialchars($fullName); ?></div></div>
                        <div class="row"><div class="label">REGISTRATION No.</div><div class="value"><?php echo htmlspecialchars($registrationNo); ?></div></div>
                        <div class="row"><div class="label">COURSE</div><div class="value"><?php echo htmlspecialchars($courseName); ?></div></div>
                        <div class="row"><div class="label">TERM / SEMESTER</div><div class="value"><?php echo htmlspecialchars($semesterLabel); ?></div></div>
                        <div class="row"><div class="label">YEAR</div><div class="value"><?php echo htmlspecialchars($studyYear); ?></div></div>
                        <div class="row"><div class="label">ACADEMIC YEAR</div><div class="value"><?php echo htmlspecialchars($academicYear); ?></div></div>
                        <div class="row"><div class="label">DEPARTMENT</div><div class="value"><?php echo htmlspecialchars($departmentName); ?></div></div>

                        <div class="cert">
                            THIS IS TO CERTIFY THAT THE ABOVE NAMED HAS REGISTERED AS A STUDENT
                            OF THE STATED COURSE FOR THE ACADEMIC YEAR INDICATED ABOVE.
                        </div>

                        <div class="sign">
                            <div class="left">CERTIFICATE ISSUED BY</div>
                            <div class="right">
                                <?php if ($directorSignatureSrc !== ''): ?>
                                    <div class="sig-image-wrap">
                                        <img class="sig-image" src="<?php echo htmlspecialchars($directorSignatureSrc); ?>" alt="Director Signature">
                                    </div>
                                <?php else: ?>
                                    <div class="sig-name">DIRECTOR SIGNATURE</div>
                                <?php endif; ?>
                                <span class="sig-line"></span>
                                <div class="sig-title">DIRECTOR OF ADMISSIONS</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    <div class="left-f">Issued: <?php echo htmlspecialchars($issueDateLabel); ?></div>
                    <div class="right-f">Official Green Card</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Regenerate PDF using the latest template so style/content updates are visible on existing cards.
try {
    $verificationUrl = PUBLIC_BASE_URL . 'verify_card.php?card=' . rawurlencode((string)$card['card_number']);
    $qrPayload = gc_generate_qr_code_asset($verificationUrl, (string)$card['card_number']);

    $photoSrc = gc_image_source_from_relative_path((string)($card['student_photo_path'] ?? ''));
    if ($photoSrc === null) {
        $photoSrc = gc_placeholder_photo_data_uri();
    }

    $intakeForStudyYear = (int)date('Y', strtotime((string)$card['issue_date']));
    if (preg_match('/^(\d{4})\/(\d{4})$/', (string)$card['academic_year'], $m)) {
        $intakeForStudyYear = (int)$m[1];
    }
    $studyYearValue = max(1, ((int)date('Y')) - $intakeForStudyYear + 1);

    $templateData = [
        'card_number' => (string)($card['card_number'] ?? ''),
        'full_name' => (string)($card['full_name'] ?? ''),
        'registration_number' => (string)($card['registration_number'] ?? ''),
        'admission_number' => (string)($card['admission_number'] ?? ''),
        'course' => (string)($card['program'] ?? ''),
        'college' => (string)($card['faculty'] ?? ''),
        'department' => (string)($card['faculty'] ?? ''),
        'semester' => (string)($card['semester'] ?? ''),
        'study_year' => (string)$studyYearValue,
        'academic_year' => (string)($card['academic_year'] ?? ''),
        'director_signature' => 'DIRECTOR SIGNATURE',
        'director_signature_image' => gc_resolve_director_signature_image_src(),
        'issue_date' => (string)($card['issue_date'] ?? date('Y-m-d')),
        'expiry_date' => (string)($card['expiry_date'] ?? date('Y-m-d')),
        'verification_url' => $verificationUrl,
        'photo_src' => $photoSrc,
        'qr_src' => (string)($qrPayload['image_src'] ?? '')
    ];

    $newPdfPath = gc_generate_green_card_pdf($templateData);
    if (is_string($newPdfPath) && $newPdfPath !== '') {
        $card['pdf_path'] = $newPdfPath;
        $db->prepare(
            'UPDATE green_cards SET pdf_path = :pdf_path, qr_code_image_path = :qr_code_image_path WHERE card_id = :card_id'
        )->execute([
            'pdf_path' => $newPdfPath,
            'qr_code_image_path' => (string)($qrPayload['relative_path'] ?? ($card['qr_code_image_path'] ?? '')),
            'card_id' => $cardId
        ]);
    }
} catch (Throwable $e) {
    error_log('Green card PDF regeneration failed: ' . $e->getMessage());
}

$relativePath = trim(str_replace('\\', '/', (string)$card['pdf_path']));
if (
    $relativePath === '' ||
    strpos($relativePath, '..') !== false ||
    strpos($relativePath, 'uploads/green_cards/') !== 0
) {
    http_response_code(404);
    exit('Card file path is invalid.');
}

$baseDir = realpath(GREEN_CARD_DIR);
$absolutePath = realpath(SITE_ROOT . '/' . $relativePath);

if (
    $baseDir === false ||
    $absolutePath === false ||
    strpos(strtolower($absolutePath), strtolower($baseDir)) !== 0 ||
    !is_file($absolutePath) ||
    !is_readable($absolutePath)
) {
    http_response_code(404);
    exit('Card file not found.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Cache-Control: private, no-transform, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/pdf');
$disposition = ($mode === 'download') ? 'attachment' : 'inline';
$fileName = basename((string)$card['card_number']) . '.pdf';
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
if ($mode === 'download') {
    // Keep explicit transfer headers for forced download only.
    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
}
header('Content-Length: ' . (string)filesize($absolutePath));
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
