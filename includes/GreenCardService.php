<?php
/**
 * Green card issuance service.
 *
 * Handles generation of registration number (if missing), QR metadata,
 * HTML template rendering, Dompdf PDF generation, and workflow transition.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Issue (or return existing) green card for a submission.
 *
 * Must be called inside an existing transaction.
 */
function issue_green_card_for_submission(PDO $db, int $submissionId, int $issuedByUserId, string $actorDepartment = 'system'): array
{
    $stmt = $db->prepare("
        SELECT ds.submission_id, ds.user_id, ds.status, ds.registration_number,
               ds.full_name, ds.program, ds.faculty, ds.passport_photo_path,
               ds.intake_year, ds.intake_semester,
               u.admission_number,
               av.is_approved AS admissions_approved,
               fc.is_cleared, fc.forwarded_to_admissions,
               gc.card_id, gc.card_number, gc.pdf_path
        FROM document_submissions ds
        INNER JOIN users u ON ds.user_id = u.user_id
        LEFT JOIN admissions_verifications av ON ds.submission_id = av.submission_id
        LEFT JOIN finance_clearances fc ON ds.submission_id = fc.submission_id
        LEFT JOIN green_cards gc ON ds.submission_id = gc.submission_id
        WHERE ds.submission_id = :submission_id
        FOR UPDATE
    ");
    $stmt->execute(['submission_id' => $submissionId]);
    $submission = $stmt->fetch();

    if (!$submission) {
        throw new Exception('Submission not found while issuing green card.');
    }

    if ($submission['status'] === STATUS_FINANCE_APPROVED) {
        transition_submission_status(
            $db,
            $submissionId,
            STATUS_FINANCE_APPROVED,
            STATUS_PENDING_GREENCARD,
            $issuedByUserId,
            'system',
            'Normalized legacy state for strict workflow'
        );
        $submission['status'] = STATUS_PENDING_GREENCARD;
    }

    if (!empty($submission['card_id'])) {
        return [
            'created' => false,
            'card_id' => (int)$submission['card_id'],
            'card_number' => (string)$submission['card_number'],
            'registration_number' => (string)$submission['registration_number'],
            'pdf_path' => (string)$submission['pdf_path']
        ];
    }

    if ($submission['status'] !== STATUS_PENDING_GREENCARD) {
        throw new Exception('Invalid state transition. Submission must be in pending_greencard.');
    }

    if (!(int)$submission['admissions_approved']) {
        throw new Exception('Cannot issue green card before Admissions approval.');
    }

    if (!(int)$submission['is_cleared'] || !(int)$submission['forwarded_to_admissions']) {
        throw new Exception('Cannot issue green card before Finance clearance and handoff.');
    }

    $registrationNumber = (string)($submission['registration_number'] ?? '');
    if ($registrationNumber === '') {
        $registrationNumber = gc_generate_registration_number(
            $db,
            (int)$submission['intake_year'],
            (string)$submission['intake_semester']
        );
        gc_persist_registration_number($db, $submissionId, (int)$submission['user_id'], $registrationNumber);
    } elseif (preg_match('/^\d{10}$/', $registrationNumber) === 1) {
        // Legacy format detected (e.g. 2026030001): normalize to YYYY-SEM-####.
        $registrationNumber = gc_generate_registration_number(
            $db,
            (int)$submission['intake_year'],
            (string)$submission['intake_semester']
        );
        gc_persist_registration_number($db, $submissionId, (int)$submission['user_id'], $registrationNumber);
    }

    $cardNumber = gc_generate_card_number($db);
    $verificationUrl = PUBLIC_BASE_URL . 'verify_card.php?card=' . rawurlencode($cardNumber);
    $issueDate = date('Y-m-d');
    $validityYears = max(1, (int)(defined('GREEN_CARD_VALIDITY_YEARS') ? GREEN_CARD_VALIDITY_YEARS : 1));
    $expiryDate = date('Y-m-d', strtotime('+' . $validityYears . ' years', strtotime($issueDate)));
    $intakeYear = (int)$submission['intake_year'];
    if ($intakeYear <= 0) {
        $intakeYear = (int)date('Y');
    }
    $academicYear = $intakeYear . '/' . ($intakeYear + $validityYears);
    $semester = (string)$submission['intake_semester'];
    $studyYear = max(1, ((int)date('Y')) - ((int)$submission['intake_year']) + 1);

    $qr = gc_generate_qr_code_asset($verificationUrl, $cardNumber);
    $photoSrc = gc_image_source_from_relative_path((string)$submission['passport_photo_path']);
    if ($photoSrc === null) {
        $photoSrc = gc_placeholder_photo_data_uri();
    }

    $templateData = [
        'card_number' => $cardNumber,
        'full_name' => (string)$submission['full_name'],
        'registration_number' => $registrationNumber,
        'admission_number' => (string)$submission['admission_number'],
        'course' => (string)$submission['program'],
        'college' => (string)$submission['faculty'],
        'department' => (string)$submission['faculty'],
        'semester' => $semester,
        'study_year' => (string)$studyYear,
        'academic_year' => $academicYear,
        'director_signature' => 'DIRECTOR SIGNATURE',
        'director_signature_image' => gc_resolve_director_signature_image_src(),
        'issue_date' => $issueDate,
        'expiry_date' => $expiryDate,
        'verification_url' => $verificationUrl,
        'photo_src' => $photoSrc,
        'qr_src' => $qr['image_src']
    ];

    $pdfPath = gc_generate_green_card_pdf($templateData);

    $qrData = json_encode([
        'registration_number' => $registrationNumber,
        'admission_number' => (string)$submission['admission_number'],
        'student_name' => (string)$submission['full_name'],
        'program' => (string)$submission['program'],
        'college' => (string)$submission['faculty'],
        'issue_date' => $issueDate,
        'card_number' => $cardNumber,
        'verification_url' => $verificationUrl
    ], JSON_UNESCAPED_SLASHES);

    $insert = $db->prepare("
        INSERT INTO green_cards (
            submission_id, user_id, registration_number, card_number,
            qr_code_data, qr_code_image_path, full_name, program, faculty,
            student_photo_path, issue_date, expiry_date, academic_year, semester,
            pdf_path, issued_by_user_id
        ) VALUES (
            :submission_id, :user_id, :registration_number, :card_number,
            :qr_code_data, :qr_code_image_path, :full_name, :program, :faculty,
            :student_photo_path, :issue_date, :expiry_date, :academic_year, :semester,
            :pdf_path, :issued_by_user_id
        )
    ");
    $insert->execute([
        'submission_id' => $submissionId,
        'user_id' => (int)$submission['user_id'],
        'registration_number' => $registrationNumber,
        'card_number' => $cardNumber,
        'qr_code_data' => (string)$qrData,
        'qr_code_image_path' => $qr['relative_path'],
        'full_name' => (string)$submission['full_name'],
        'program' => (string)$submission['program'],
        'faculty' => (string)$submission['faculty'],
        'student_photo_path' => (string)$submission['passport_photo_path'],
        'issue_date' => $issueDate,
        'expiry_date' => $expiryDate,
        'academic_year' => $academicYear,
        'semester' => $semester,
        'pdf_path' => $pdfPath,
        'issued_by_user_id' => $issuedByUserId
    ]);

    $department = in_array($actorDepartment, ['student', 'admissions', 'finance', 'system'], true)
        ? $actorDepartment
        : 'system';

    transition_submission_status(
        $db,
        $submissionId,
        STATUS_PENDING_GREENCARD,
        STATUS_GREENCARD_ISSUED,
        $issuedByUserId,
        $department,
        "Green card issued automatically. Reg#: {$registrationNumber}, Card#: {$cardNumber}"
    );

    return [
        'created' => true,
        'card_id' => (int)$db->lastInsertId(),
        'card_number' => $cardNumber,
        'registration_number' => $registrationNumber,
        'pdf_path' => $pdfPath
    ];
}

/**
 * Registration format: YYYY-SEM-#### (e.g. 2026-01-1000)
 */
function gc_generate_registration_number(PDO $db, int $intakeYear = 0, string $intakeSemester = ''): string
{
    $year = $intakeYear > 0 ? $intakeYear : (int)date('Y');
    if ($year < 2000 || $year > 2100) {
        $year = (int)date('Y');
    }

    $semesterCode = gc_semester_code($intakeSemester);
    $prefix = sprintf('%04d-%s-', $year, $semesterCode);

    $stmt = $db->prepare("
        SELECT registration_number
        FROM document_submissions
        WHERE registration_number LIKE :prefix
        ORDER BY registration_number DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1000;
    if (is_string($last) && preg_match('/^\d{4}-\d{2}-(\d{4})$/', $last, $m)) {
        $next = ((int)$m[1]) + 1;
    }

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = $prefix . str_pad((string)($next + $attempt), 4, '0', STR_PAD_LEFT);
        $check = $db->prepare("
            SELECT COUNT(*)
            FROM document_submissions
            WHERE registration_number = :registration_number
        ");
        $check->execute(['registration_number' => $candidate]);
        if ((int)$check->fetchColumn() === 0) {
            return $candidate;
        }
    }

    throw new Exception('Unable to generate unique registration number.');
}

function gc_semester_code(string $semester): string
{
    $normalized = strtolower(trim($semester));
    if ($normalized === 'semester_1' || $normalized === 'semester 1' || $normalized === '1') {
        return '01';
    }
    if ($normalized === 'semester_2' || $normalized === 'semester 2' || $normalized === '2') {
        return '02';
    }
    if ($normalized === 'semester_3' || $normalized === 'semester 3' || $normalized === '3') {
        return '03';
    }
    return '01';
}

function gc_persist_registration_number(PDO $db, int $submissionId, int $userId, string $registrationNumber): void
{
    $stmt = $db->prepare("
        UPDATE document_submissions
        SET registration_number = :registration_number
        WHERE submission_id = :submission_id
    ");
    $stmt->execute([
        'registration_number' => $registrationNumber,
        'submission_id' => $submissionId
    ]);

    $stmt = $db->prepare("
        UPDATE student_profiles
        SET registration_number = :registration_number
        WHERE user_id = :user_id
    ");
    $stmt->execute([
        'registration_number' => $registrationNumber,
        'user_id' => $userId
    ]);

    $stmt = $db->prepare("
        UPDATE admissions_verifications
        SET registration_number = :registration_number,
            registration_generated_at = NOW()
        WHERE submission_id = :submission_id
    ");
    $stmt->execute([
        'registration_number' => $registrationNumber,
        'submission_id' => $submissionId
    ]);
}

function gc_generate_card_number(PDO $db): string
{
    $prefix = 'GC' . date('Y');

    $stmt = $db->prepare("
        SELECT card_number
        FROM green_cards
        WHERE card_number LIKE :prefix
        ORDER BY card_number DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last) {
        $next = ((int)substr((string)$last, strlen($prefix))) + 1;
    }

    return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

function gc_generate_qr_code_asset(string $payload, string $cardNumber): array
{
    $relativePath = 'uploads/qr_codes/' . $cardNumber . '.png';
    $absolutePath = QR_CODE_DIR . $cardNumber . '.png';
    $remoteUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($payload);

    $content = @file_get_contents($remoteUrl);
    if ($content !== false) {
        @file_put_contents($absolutePath, $content);
    }

    $localImage = gc_image_source_from_relative_path($relativePath);
    if ($localImage !== null) {
        return [
            'relative_path' => $relativePath,
            'image_src' => $localImage
        ];
    }

    return [
        'relative_path' => null,
        'image_src' => $remoteUrl
    ];
}

function gc_generate_green_card_pdf(array $templateData): string
{
    $cardNumber = preg_replace('/[^A-Z0-9]/', '', (string)$templateData['card_number']);
    if ($cardNumber === '') {
        throw new Exception('Invalid card number for PDF generation.');
    }

    $relativePath = 'uploads/green_cards/' . $cardNumber . '.pdf';
    $absolutePath = GREEN_CARD_DIR . $cardNumber . '.pdf';

    if (!is_dir(GREEN_CARD_DIR)) {
        mkdir(GREEN_CARD_DIR, 0755, true);
    }

    // Preferred renderer: Dompdf. Fallback: lightweight native PDF output.
    if (gc_dompdf_available()) {
        try {
            $html = gc_render_green_card_template($templateData);

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('chroot', SITE_ROOT);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $output = $dompdf->output();
            if (@file_put_contents($absolutePath, $output) !== false) {
                return $relativePath;
            }
        } catch (Throwable $e) {
            error_log('Dompdf generation failed, using fallback PDF generator: ' . $e->getMessage());
        }
    }

    gc_generate_fallback_green_card_pdf($absolutePath, $templateData);
    return $relativePath;
}

function gc_render_green_card_template(array $templateData): string
{
    if (!defined('GREEN_CARD_TEMPLATE') || !is_string(GREEN_CARD_TEMPLATE) || !file_exists(GREEN_CARD_TEMPLATE)) {
        throw new Exception('Green card template not found.');
    }

    $cardData = $templateData;
    ob_start();
    include GREEN_CARD_TEMPLATE;
    return (string)ob_get_clean();
}

function gc_resolve_director_signature_image_src(): string
{
    $candidates = [
        'assets/img/director_signature.png',
        'assets/img/director_signature.jpg',
        'assets/img/director_signature.jpeg',
        'assets/img/director_signature.webp'
    ];

    foreach ($candidates as $candidate) {
        if (is_file(SITE_ROOT . '/' . $candidate)) {
            return BASE_URL . $candidate;
        }
    }

    return '';
}

function gc_require_dompdf(): void
{
    if (!gc_dompdf_available()) {
        throw new Exception('Dompdf is required. Install dependency: composer require dompdf/dompdf');
    }
}

function gc_dompdf_available(): bool
{
    if (class_exists(Dompdf::class)) {
        return true;
    }

    $autoloadPath = SITE_ROOT . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    return class_exists(Dompdf::class);
}

function gc_generate_fallback_green_card_pdf(string $absolutePath, array $templateData): void
{
    $fields = [
        ['STUDENT\'S NAME', (string)($templateData['full_name'] ?? 'N/A')],
        ['REGISTRATION No.', (string)($templateData['registration_number'] ?? 'N/A')],
        ['COURSE', (string)($templateData['course'] ?? 'N/A')],
        ['TERM / SEMESTER', ucwords(str_replace('_', ' ', (string)($templateData['semester'] ?? 'N/A')))],
        ['YEAR', (string)($templateData['study_year'] ?? 'N/A')],
        ['ACADEMIC YEAR', (string)($templateData['academic_year'] ?? 'N/A')],
        ['DEPARTMENT', (string)($templateData['department'] ?? 'N/A')]
    ];

    $stream = '';
    // Card background + border
    $stream .= "q 0.83 0.95 0.87 rg 38 430 520 320 re f Q\n";
    $stream .= "0.30 0.63 0.45 RG 1.2 w 38 430 520 320 re S\n";
    // Watermark
    $stream .= "q 0.71 0.84 0.75 rg BT /F2 120 Tf 0.8192 0.5736 -0.5736 0.8192 176 516 Tm (KIU) Tj ET Q\n";
    // Force dark text color for readability in all viewers.
    $stream .= "0 0 0 rg\n";
    $stream .= "0 0 0 RG\n";

    // Header
    $stream .= "BT /F2 16 Tf 56 728 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('Kampala International University')) . ") Tj ET\n";
    $stream .= "BT /F2 13 Tf 56 706 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('STUDENT GREEN CARD')) . ") Tj ET\n";
    $stream .= "BT /F2 10 Tf 56 688 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('Card No: ' . (string)($templateData['card_number'] ?? ''))) . ") Tj ET\n";

    // Photo placeholder box (fallback renderer does not embed images).
    $stream .= "0.45 0.72 0.56 RG 0.8 w 428 566 112 154 re S\n";
    $stream .= "BT /F2 10 Tf 447 638 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('PHOTO')) . ") Tj ET\n";

    // Body fields
    $y = 660;
    foreach ($fields as $field) {
        $label = gc_pdf_escape_text(gc_pdf_ascii($field[0] . ':'));
        $value = gc_pdf_escape_text(gc_pdf_ascii($field[1]));
        $stream .= "BT /F2 11 Tf 56 {$y} Tm 0 0 0 rg ({$label}) Tj ET\n";
        $stream .= "BT /F2 13 Tf 206 {$y} Tm 0 0 0 rg ({$value}) Tj ET\n";
        $stream .= "0.58 0.74 0.63 RG 0.6 w 56 " . ($y - 8) . " m 414 " . ($y - 8) . " l S\n";
        $y -= 28;
    }

    $statementA = 'THIS IS TO CERTIFY THAT THE ABOVE NAMED HAS REGISTERED AS A STUDENT';
    $statementB = 'OF THE STATED COURSE FOR THE ACADEMIC YEAR INDICATED ABOVE.';
    $stream .= "BT /F2 8 Tf 56 488 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii($statementA)) . ") Tj ET\n";
    $stream .= "BT /F2 8 Tf 56 474 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii($statementB)) . ") Tj ET\n";

    $stream .= "BT /F2 9 Tf 56 458 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('CERTIFICATE ISSUED BY')) . ") Tj ET\n";
    $stream .= "0.40 0.67 0.49 RG 0.8 w 310 455 m 520 455 l S\n";
    $stream .= "BT /F2 10 Tf 332 460 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii((string)($templateData['director_signature'] ?? 'DIRECTOR SIGNATURE'))) . ") Tj ET\n";
    $stream .= "BT /F2 9 Tf 340 442 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('DIRECTOR OF ADMISSIONS')) . ") Tj ET\n";
    $stream .= "BT /F2 8 Tf 56 444 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('Issue Date: ' . (string)($templateData['issue_date'] ?? '') . '   Expiry: ' . (string)($templateData['expiry_date'] ?? ''))) . ") Tj ET\n";
    $stream .= "BT /F2 7 Tf 56 434 Tm 0 0 0 rg (" . gc_pdf_escape_text(gc_pdf_ascii('Verify: ' . (string)($templateData['verification_url'] ?? ''))) . ") Tj ET\n";

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>";
    $objects[4] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $count = count($objects);

    for ($i = 1; $i <= $count; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . ($count + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $count; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . ($count + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    if (@file_put_contents($absolutePath, $pdf) === false) {
        throw new Exception('Failed to persist green card PDF.');
    }
}

function gc_pdf_escape_text(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function gc_pdf_ascii(string $text): string
{
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
    if ($converted === false || $converted === '') {
        $converted = preg_replace('/[^\x20-\x7E]/', '?', $text);
        if (!is_string($converted)) {
            return '';
        }
    }
    return $converted;
}

function gc_image_source_from_relative_path(string $relativePath): ?string
{
    $normalized = gc_normalize_relative_path($relativePath);
    if ($normalized === null) {
        return null;
    }

    $absolutePath = SITE_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        return null;
    }

    $mime = @mime_content_type($absolutePath);
    if (!$mime || strpos((string)$mime, 'image/') !== 0) {
        return null;
    }

    $raw = @file_get_contents($absolutePath);
    if ($raw === false) {
        return null;
    }

    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

function gc_normalize_relative_path(string $path): ?string
{
    $trimmed = trim(str_replace('\\', '/', $path));
    if ($trimmed === '' || strpos($trimmed, '..') !== false) {
        return null;
    }

    if (preg_match('/^[a-z]+:\/\//i', $trimmed)) {
        return null;
    }

    return ltrim($trimmed, '/');
}

function gc_placeholder_photo_data_uri(): string
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="240" viewBox="0 0 200 240"><rect width="200" height="240" fill="#f0f4f8"/><circle cx="100" cy="85" r="42" fill="#c9d2dc"/><rect x="45" y="140" width="110" height="62" rx="12" fill="#c9d2dc"/><text x="100" y="224" text-anchor="middle" font-family="Arial" font-size="14" fill="#5a6876">No Photo</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
