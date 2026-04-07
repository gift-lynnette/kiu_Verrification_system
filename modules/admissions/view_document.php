<?php
/**
 * Secure document viewer for Admissions users.
 */

require_once '../../config/init.php';
require_login();
require_role(ROLE_REGISTRAR);

$documentMap = [
    'admission_letter' => 'admission_letter_path',
    's6_certificate' => 's6_certificate_path',
    'national_id' => 'national_id_path',
    'school_id' => 'school_id_path',
    'passport_photo' => 'passport_photo_path',
    'bank_slip' => 'bank_slip_path',
    'bursary_award_letter' => 'bursary_award_letter_path'
];

$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$documentType = isset($_GET['doc']) ? trim((string)$_GET['doc']) : '';
$fileParam = isset($_GET['file']) ? trim((string)$_GET['file']) : '';

if ($submissionId > 0 && isset($documentMap[$documentType])) {
    $columnName = $documentMap[$documentType];
    $stmt = $db->prepare("SELECT {$columnName} AS file_path FROM document_submissions WHERE submission_id = :submission_id LIMIT 1");
    $stmt->execute(['submission_id' => $submissionId]);
    $row = $stmt->fetch();
    $fileParam = $row['file_path'] ?? '';
}

if ($fileParam === '') {
    http_response_code(400);
    echo 'Missing or invalid document reference.';
    exit;
}

$uploadsRootReal = realpath(UPLOAD_DIR);
if ($uploadsRootReal === false) {
    http_response_code(500);
    echo 'Uploads directory is unavailable.';
    exit;
}

$uploadsRootNormalized = rtrim(str_replace('\\', '/', $uploadsRootReal), '/');
$requestedPath = preg_replace('#/+#', '/', str_replace('\\', '/', $fileParam));

$candidatePaths = [];

// Absolute paths (legacy records may already store absolute disk paths)
if (preg_match('/^[A-Za-z]:\//', $requestedPath) === 1 || strpos($requestedPath, '/') === 0) {
    $candidatePaths[] = $requestedPath;
}

// Relative paths (preferred: uploads/...)
$relativePath = ltrim($requestedPath, '/');
if (strpos($relativePath, 'uploads/') === 0) {
    $relativePath = substr($relativePath, strlen('uploads/'));
}
if ($relativePath !== '') {
    $candidatePaths[] = $uploadsRootNormalized . '/' . $relativePath;
}

$resolvedFile = null;
foreach ($candidatePaths as $candidatePath) {
    $realCandidate = realpath($candidatePath);
    if ($realCandidate === false || !is_file($realCandidate) || !is_readable($realCandidate)) {
        continue;
    }

    $normalizedCandidate = str_replace('\\', '/', $realCandidate);
    $normalizedCandidateLower = strtolower($normalizedCandidate);
    $uploadsRootLower = strtolower($uploadsRootNormalized);
    if (
        $normalizedCandidateLower === $uploadsRootLower ||
        strpos($normalizedCandidateLower, $uploadsRootLower . '/') === 0
    ) {
        $resolvedFile = $realCandidate;
        break;
    }
}

if ($resolvedFile === null) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? (finfo_file($finfo, $resolvedFile) ?: 'application/octet-stream') : 'application/octet-stream';
if ($finfo) {
    finfo_close($finfo);
}

$previewMimeTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];
$mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : 'view';
$safeFilename = htmlspecialchars(basename($resolvedFile), ENT_QUOTES, 'UTF-8');

if ($mode === 'view' && in_array($mimeType, $previewMimeTypes, true)) {
    $fileBytes = file_get_contents($resolvedFile);
    if ($fileBytes === false) {
        http_response_code(500);
        echo 'Unable to open document.';
        exit;
    }

    $dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode($fileBytes);
    $escapedDataUrl = htmlspecialchars($dataUrl, ENT_QUOTES, 'UTF-8');
    $isPdf = ($mimeType === 'application/pdf');

    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Document Viewer - <?php echo $safeFilename; ?></title>
        <style>
            html, body { height: 100%; margin: 0; background: #f4f6f8; font-family: Arial, sans-serif; }
            .bar { padding: 10px 14px; background: #1f3c2f; color: #fff; font-size: 14px; }
            .wrap { height: calc(100% - 44px); display: flex; justify-content: center; align-items: center; }
            iframe, img { width: 96%; height: 96%; border: 0; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
            img { object-fit: contain; }
        </style>
    </head>
    <body>
        <div class="bar"><?php echo $safeFilename; ?></div>
        <div class="wrap">
            <?php if ($isPdf): ?>
                <iframe src="<?php echo $escapedDataUrl; ?>" title="PDF Preview"></iframe>
            <?php else: ?>
                <img src="<?php echo $escapedDataUrl; ?>" alt="Document Preview">
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$disposition = in_array($mimeType, $previewMimeTypes, true) ? 'inline' : 'attachment';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($resolvedFile));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace(['"', "\r", "\n"], '', basename($resolvedFile)) . '"');
header('X-Content-Type-Options: nosniff');
readfile($resolvedFile);
exit;
