<?php
/**
 * Expects $cardData with:
 * full_name, registration_number, admission_number, course, college,
 * card_number, issue_date, expiry_date, verification_url, photo_src, qr_src,
 * department, semester, study_year, academic_year, director_signature,
 * director_signature_image
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 16mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0b1220;
            background: #eef7f0;
        }
        .sheet { width: 100%; padding: 10mm 0; }
        .card-shell {
            width: 176mm;
            margin: 0 auto;
            border-radius: 12px;
            border: 1.3px solid #0f5132;
            background: linear-gradient(180deg, #eaf8ef 0%, #d8f0df 100%);
            overflow: hidden;
            position: relative;
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
            padding: 0 10mm;
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
        .head .university {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }
        .head .title {
            margin-top: 1.2mm;
            font-size: 10px;
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
        .left { width: 49mm; padding-right: 5mm; }
        .right { width: auto; }
        .photo-box {
            width: 40mm;
            height: 49mm;
            border: 1px solid #6ea784;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }
        .qr-box {
            margin-top: 4mm;
            width: 30mm;
            height: 30mm;
            border: 1px solid #6ea784;
            border-radius: 4px;
            background: #fff;
            padding: 1.8mm;
        }
        .qr-box img { width: 100%; height: 100%; object-fit: contain; }
        .meta {
            margin-top: 3mm;
            font-size: 8px;
            color: #0f172a;
            line-height: 1.45;
            word-wrap: break-word;
        }
        .row {
            margin-bottom: 2.2mm;
            border-bottom: 1px dotted #7ea98c;
            padding-bottom: 1.2mm;
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
        .cert-text {
            margin-top: 3.2mm;
            font-size: 8px;
            color: #000000;
            line-height: 1.52;
            text-transform: uppercase;
            font-weight: 800;
        }
        .signature-row {
            margin-top: 1.6mm;
            display: table;
            width: 100%;
        }
        .sig-label, .sig-block {
            display: table-cell;
            vertical-align: bottom;
        }
        .sig-label {
            width: 44%;
            font-size: 8.6px;
            font-weight: 800;
            color: #000000;
            text-transform: uppercase;
        }
        .sig-block {
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
        .footer .left-f, .footer .right-f {
            display: table-cell;
            vertical-align: middle;
        }
        .footer .right-f {
            text-align: right;
            font-weight: 700;
            color: #0b5d3b;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="card-shell">
        <div class="card-watermark" aria-hidden="true">KIU</div>
        <div class="head">
            <div class="university">Kampala International University</div>
            <div class="title">Student Green Card</div>
        </div>

        <div class="content">
            <div class="left">
                <div class="photo-box">
                    <img src="<?php echo htmlspecialchars((string)($cardData['photo_src'] ?? '')); ?>" alt="Student Photo">
                </div>
                <?php if (!empty($cardData['qr_src'])): ?>
                <div class="qr-box">
                    <img src="<?php echo htmlspecialchars((string)$cardData['qr_src']); ?>" alt="QR Code">
                </div>
                <?php endif; ?>
                <div class="meta">Verify: <?php echo htmlspecialchars((string)($cardData['verification_url'] ?? '')); ?></div>
            </div>

            <div class="right">
                <div class="row"><div class="label">STUDENT'S NAME</div><div class="value"><?php echo htmlspecialchars((string)($cardData['full_name'] ?? '')); ?></div></div>
                <div class="row"><div class="label">REGISTRATION No.</div><div class="value"><?php echo htmlspecialchars((string)($cardData['registration_number'] ?? '')); ?></div></div>
                <div class="row"><div class="label">COURSE</div><div class="value"><?php echo htmlspecialchars((string)($cardData['course'] ?? '')); ?></div></div>
                <div class="row"><div class="label">TERM / SEMESTER</div><div class="value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($cardData['semester'] ?? '')))); ?></div></div>
                <div class="row"><div class="label">YEAR</div><div class="value"><?php echo htmlspecialchars((string)($cardData['study_year'] ?? '')); ?></div></div>
                <div class="row"><div class="label">ACADEMIC YEAR</div><div class="value"><?php echo htmlspecialchars((string)($cardData['academic_year'] ?? '')); ?></div></div>
                <div class="row"><div class="label">DEPARTMENT</div><div class="value"><?php echo htmlspecialchars((string)($cardData['department'] ?? $cardData['college'] ?? '')); ?></div></div>

                <div class="cert-text">
                    THIS IS TO CERTIFY THAT THE ABOVE NAMED HAS REGISTERED AS A STUDENT
                    OF THE STATED COURSE FOR THE ACADEMIC YEAR INDICATED ABOVE.
                </div>

                <div class="signature-row">
                    <div class="sig-label">CERTIFICATE ISSUED BY</div>
                    <div class="sig-block">
                        <?php if (!empty($cardData['director_signature_image'])): ?>
                            <div class="sig-image-wrap">
                                <img class="sig-image" src="<?php echo htmlspecialchars((string)$cardData['director_signature_image']); ?>" alt="Director Signature">
                            </div>
                        <?php else: ?>
                            <div class="sig-name"><?php echo htmlspecialchars((string)($cardData['director_signature'] ?? 'DIRECTOR SIGNATURE')); ?></div>
                        <?php endif; ?>
                        <span class="sig-line"></span>
                        <div class="sig-title">DIRECTOR OF ADMISSIONS</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="left-f">Issued: <?php echo htmlspecialchars((string)($cardData['issue_date'] ?? '')); ?> | Expires: <?php echo htmlspecialchars((string)($cardData['expiry_date'] ?? '')); ?></div>
            <div class="right-f">Official Green Card</div>
        </div>
    </div>
</div>
</body>
</html>