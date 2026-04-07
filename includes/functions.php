<?php
/**
 * Common Helper Functions
 */

/**
 * Sanitize input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate random string
 */
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Format currency
 */
function format_currency($amount, $currency = PAYMENT_CURRENCY) {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format date
 */
function format_date($date, $format = DISPLAY_DATE_FORMAT) {
    return date($format, strtotime($date));
}

/**
 * Calculate time ago
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return $difference . ' seconds ago';
    } elseif ($difference < 3600) {
        return floor($difference / 60) . ' minutes ago';
    } elseif ($difference < 86400) {
        return floor($difference / 3600) . ' hours ago';
    } elseif ($difference < 604800) {
        return floor($difference / 86400) . ' days ago';
    } else {
        return format_date($datetime);
    }
}

/**
 * Get status badge HTML
 */
function get_status_badge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'under_review' => '<span class="badge badge-info">Under Review</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        'pending_admissions' => '<span class="badge badge-warning">Pending Admissions</span>',
        'under_admissions_review' => '<span class="badge badge-info">Admissions Review</span>',
        'admissions_approved' => '<span class="badge badge-success">Admissions Approved</span>',
        'admissions_rejected' => '<span class="badge badge-danger">Admissions Rejected</span>',
        'resubmission_requested' => '<span class="badge badge-warning">Resubmission Requested</span>',
        'pending_finance' => '<span class="badge badge-warning">Pending Finance</span>',
        'under_finance_review' => '<span class="badge badge-info">Finance Review</span>',
        'finance_approved' => '<span class="badge badge-success">Finance Approved</span>',
        'finance_rejected' => '<span class="badge badge-danger">Finance Rejected</span>',
        'finance_pending' => '<span class="badge badge-warning">Finance Pending</span>',
        'pending_greencard' => '<span class="badge badge-info">Pending Green Card</span>',
        'greencard_issued' => '<span class="badge badge-success">Green Card Issued</span>',
        'cancelled' => '<span class="badge badge-secondary">Cancelled</span>',
        'incomplete' => '<span class="badge badge-warning">Incomplete</span>',
        'suspicious' => '<span class="badge badge-danger">Suspicious</span>',
        'mismatch' => '<span class="badge badge-warning">Mismatch</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
}

/**
 * Allowed workflow status transitions for document_submissions.
 */
function get_workflow_transition_map() {
    return [
        STATUS_PENDING_ADMISSIONS => [STATUS_UNDER_ADMISSIONS_REVIEW, STATUS_CANCELLED],
        STATUS_UNDER_ADMISSIONS_REVIEW => [STATUS_PENDING_FINANCE, STATUS_RESUBMISSION_REQUESTED, STATUS_ADMISSIONS_REJECTED, STATUS_CANCELLED],
        STATUS_ADMISSIONS_APPROVED => [STATUS_PENDING_FINANCE, STATUS_UNDER_FINANCE_REVIEW, STATUS_CANCELLED], // legacy compatibility
        STATUS_RESUBMISSION_REQUESTED => [STATUS_PENDING_ADMISSIONS, STATUS_CANCELLED],
        STATUS_ADMISSIONS_REJECTED => [STATUS_PENDING_ADMISSIONS, STATUS_CANCELLED],
        STATUS_PENDING_FINANCE => [STATUS_UNDER_FINANCE_REVIEW, STATUS_FINANCE_PENDING, STATUS_FINANCE_REJECTED, STATUS_PENDING_GREENCARD, STATUS_CANCELLED],
        STATUS_UNDER_FINANCE_REVIEW => [STATUS_FINANCE_PENDING, STATUS_FINANCE_REJECTED, STATUS_PENDING_GREENCARD, STATUS_CANCELLED],
        STATUS_FINANCE_PENDING => [STATUS_UNDER_FINANCE_REVIEW, STATUS_FINANCE_REJECTED, STATUS_PENDING_GREENCARD, STATUS_CANCELLED],
        STATUS_FINANCE_APPROVED => [STATUS_PENDING_GREENCARD, STATUS_CANCELLED], // legacy compatibility
        STATUS_FINANCE_REJECTED => [STATUS_PENDING_ADMISSIONS, STATUS_CANCELLED],
        STATUS_PENDING_GREENCARD => [STATUS_GREENCARD_ISSUED, STATUS_CANCELLED],
        STATUS_GREENCARD_ISSUED => [],
        STATUS_CANCELLED => []
    ];
}

/**
 * Check whether a workflow status transition is allowed.
 */
function can_transition_workflow_status($from_status, $to_status) {
    if ($from_status === $to_status) {
        return true;
    }

    $map = get_workflow_transition_map();
    if (!array_key_exists($from_status, $map)) {
        return false;
    }

    return in_array($to_status, $map[$from_status], true);
}

/**
 * Throws when transition is not allowed.
 */
function assert_workflow_transition($from_status, $to_status) {
    if (!can_transition_workflow_status($from_status, $to_status)) {
        throw new Exception("Invalid workflow transition: {$from_status} -> {$to_status}");
    }
}

/**
 * Atomically transition document_submissions.status and write workflow history.
 * extra_updates keys must be valid document_submissions column names.
 */
function transition_submission_status($db, $submission_id, $from_status, $to_status, $changed_by_user_id, $department, $notes = '', $extra_updates = []) {
    assert_workflow_transition($from_status, $to_status);

    $set_clauses = ['status = :to_status'];
    $params = [
        'to_status' => $to_status,
        'submission_id' => $submission_id,
        'from_status' => $from_status
    ];

    foreach ($extra_updates as $column => $value) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new Exception("Invalid update column: {$column}");
        }

        $param_name = 'extra_' . $column;
        $set_clauses[] = "{$column} = :{$param_name}";
        $params[$param_name] = $value;
    }

    $sql = "
        UPDATE document_submissions
        SET " . implode(",\n            ", $set_clauses) . "
        WHERE submission_id = :submission_id
          AND status = :from_status
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() !== 1) {
        throw new Exception("Workflow transition failed (stale state): {$from_status} -> {$to_status}");
    }

    if (table_exists($db, 'workflow_history')) {
        $history_stmt = $db->prepare("
            INSERT INTO workflow_history (submission_id, from_status, to_status, changed_by_user_id, department, notes)
            VALUES (:submission_id, :from_status, :to_status, :changed_by_user_id, :department, :notes)
        ");
        $history_stmt->execute([
            'submission_id' => $submission_id,
            'from_status' => $from_status,
            'to_status' => $to_status,
            'changed_by_user_id' => $changed_by_user_id,
            'department' => $department,
            'notes' => $notes
        ]);
    }
}

/**
 * Send JSON response
 */
function json_response($success, $message, $data = [], $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Redirect
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 */
function has_role($required_role) {
    if (!is_logged_in()) return false;
    
    $user_role = $_SESSION['role'] ?? '';
    
    // Admin has access to everything
    if ($user_role === ROLE_ADMIN) return true;
    
    // Check specific role
    if (is_array($required_role)) {
        return in_array($user_role, $required_role);
    }
    
    return $user_role === $required_role;
}

/**
 * Require login
 */
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
    }
}

/**
 * Require role
 */
function require_role($required_role) {
    require_login();
    if (!has_role($required_role)) {
        http_response_code(403);
        die('Access denied');
    }
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render CSRF hidden input field.
 */
function csrf_token_field() {
    $token = htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log activity
 */
function log_activity($action, $details = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, changes_summary, ip_address, user_agent)
            VALUES (:user_id, :action, :details, :ip, :user_agent)
        ");
        
        $stmt->execute([
            'user_id' => $_SESSION['user_id'] ?? null,
            'action' => $action,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Get file extension
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format file size
 */
function format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Check whether a table exists in the current database.
 */
function table_exists($db, $table_name) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name");
        $stmt->execute(['table_name' => $table_name]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Table exists check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch semester exchange rate (USD -> UGX).
 * Uses semester_exchange_rates table when available, otherwise falls back to default.
 */
function get_semester_exchange_rate_ugx($db, $intake_year, $intake_semester) {
    $default_rate = 3800.00;

    if (!table_exists($db, 'semester_exchange_rates')) {
        return $default_rate;
    }

    try {
        $stmt = $db->prepare("\n+            SELECT usd_to_ugx_rate\n+            FROM semester_exchange_rates\n+            WHERE intake_year = :intake_year\n+              AND intake_semester = :intake_semester\n+              AND is_active = 1\n+            ORDER BY effective_from DESC, rate_id DESC\n+            LIMIT 1\n+        ");
        $stmt->execute([
            'intake_year' => $intake_year,
            'intake_semester' => $intake_semester
        ]);

        $rate = $stmt->fetchColumn();
        if ($rate !== false && (float)$rate > 0) {
            return (float)$rate;
        }
    } catch (Exception $e) {
        error_log('Exchange rate lookup failed: ' . $e->getMessage());
    }

    return $default_rate;
}

/**
 * Convert amount to UGX from supported currencies.
 */
function convert_amount_to_ugx($amount, $currency, $usd_to_ugx_rate) {
    $amount = (float)$amount;
    $currency = strtoupper((string)$currency);

    if ($currency === 'USD') {
        return round($amount * (float)$usd_to_ugx_rate, 2);
    }

    return round($amount, 2);
}

/**
 * Determine the fee policy profile for a programme/faculty.
 */
function resolve_program_fee_profile($program, $faculty = '') {
    $program_l = strtolower((string)$program);
    $faculty_l = strtolower((string)$faculty);

    if (strpos($program_l, 'phd') !== false || strpos($program_l, 'master') !== false || strpos($program_l, 'masters') !== false) {
        return ['level' => 'masters_phd', 'default_functional_fee' => 0.00, 'research_fee' => 1000000.00];
    }

    if (strpos($program_l, 'certificate') !== false || strpos($program_l, 'national certificate') !== false) {
        return ['level' => 'certificate', 'default_functional_fee' => 250000.00, 'research_fee' => 0.00];
    }

    if (strpos($program_l, 'diploma') !== false) {
        return ['level' => 'diploma', 'default_functional_fee' => 400000.00, 'research_fee' => 0.00];
    }

    $health_hint = (strpos($faculty_l, 'health') !== false)
        || (strpos($program_l, 'medicine') !== false)
        || (strpos($program_l, 'pharmacy') !== false)
        || (strpos($program_l, 'nursing') !== false)
        || (strpos($program_l, 'public health') !== false);

    if ($health_hint) {
        return ['level' => 'health_science_degree', 'default_functional_fee' => 600000.00, 'research_fee' => 0.00];
    }

    return ['level' => 'bachelors', 'default_functional_fee' => 500000.00, 'research_fee' => 0.00];
}

/**
 * Calculate fee requirements under finance rules.
 */
function calculate_finance_fee_requirements($fee_structure, $program, $faculty = '') {
    $profile = resolve_program_fee_profile($program, $faculty);

    $tuition = 0.00;
    $functional = $profile['default_functional_fee'];

    if (!empty($fee_structure)) {
        $tuition = (float)($fee_structure['tuition_amount'] ?? 0);
        if (isset($fee_structure['functional_fees']) && $fee_structure['functional_fees'] !== null) {
            $functional = (float)$fee_structure['functional_fees'];
        }
    }

    $research_fee = (float)$profile['research_fee'];
    $total_required = $tuition + $functional + $research_fee;
    $threshold_50_percent = $total_required * 0.5;
    $bursary_tuition_threshold = $tuition * 0.5;

    return [
        'level' => $profile['level'],
        'tuition_fee' => round($tuition, 2),
        'functional_fee' => round($functional, 2),
        'research_fee' => round($research_fee, 2),
        'total_required_fee' => round($total_required, 2),
        'threshold_50_percent' => round($threshold_50_percent, 2),
        'bursary_tuition_threshold' => round($bursary_tuition_threshold, 2)
    ];
}

/**
 * Check bursary status from forwarded bursary list.
 */
function get_bursary_status_for_submission($db, $submission) {
    $claims_bursary = !empty($submission['is_bursary']);

    if (!table_exists($db, 'bursary_forward_list')) {
        return [
            'claims_bursary' => $claims_bursary,
            'status' => $claims_bursary ? 'No' : 'No',
            'is_confirmed' => false,
            'list_record' => null
        ];
    }

    $record = null;
    try {
        $stmt = $db->prepare("\n+            SELECT *\n+            FROM bursary_forward_list\n+            WHERE is_active = 1\n+              AND (\n+                    user_id = :user_id\n+                 OR admission_number = :admission_number\n+                 OR full_name = :full_name\n+              )\n+            ORDER BY forwarded_at DESC, bursary_id DESC\n+            LIMIT 1\n+        ");
        $stmt->execute([
            'user_id' => (int)$submission['user_id'],
            'admission_number' => (string)($submission['admission_number'] ?? ''),
            'full_name' => (string)($submission['full_name'] ?? '')
        ]);
        $record = $stmt->fetch();
    } catch (Exception $e) {
        error_log('Bursary lookup failed: ' . $e->getMessage());
    }

    if (!$record) {
        return [
            'claims_bursary' => $claims_bursary,
            'status' => 'No',
            'is_confirmed' => false,
            'list_record' => null
        ];
    }

    $confirmation = strtolower((string)($record['confirmation_status'] ?? 'confirmed'));
    if ($confirmation === 'pending') {
        return [
            'claims_bursary' => $claims_bursary,
            'status' => 'Pending Confirmation',
            'is_confirmed' => false,
            'list_record' => $record
        ];
    }

    return [
        'claims_bursary' => $claims_bursary,
        'status' => 'Yes',
        'is_confirmed' => true,
        'list_record' => $record
    ];
}

/**
 * Attempt to extract bank slip amount from OCR text when available.
 */
function extract_bank_slip_amount_for_submission($db, $submission_id, $fallback_amount = 0.00) {
    $fallback = (float)$fallback_amount;

    if (!table_exists($db, 'document_uploads')) {
        return ['amount' => $fallback, 'source' => 'submitted_field'];
    }

    try {
        $stmt = $db->prepare("\n+            SELECT ocr_extracted_text\n+            FROM document_uploads\n+            WHERE submission_id = :submission_id\n+              AND document_type IN ('bank_slip', 'bank-slip', 'receipt')\n+            ORDER BY uploaded_at DESC, document_id DESC\n+            LIMIT 1\n+        ");
        $stmt->execute(['submission_id' => $submission_id]);
        $ocr_text = (string)($stmt->fetchColumn() ?: '');

        if ($ocr_text !== '') {
            $patterns = [
                '/(?:amount|amt|paid|total)\s*[:=]?\s*(?:ugx|ush|usd)?\s*([0-9][0-9,]*(?:\\.[0-9]{1,2})?)/i',
                '/(?:ugx|ush|usd)\s*([0-9][0-9,]*(?:\\.[0-9]{1,2})?)/i'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $ocr_text, $matches)) {
                    $raw = str_replace(',', '', $matches[1]);
                    $amount = (float)$raw;
                    if ($amount > 0) {
                        return ['amount' => round($amount, 2), 'source' => 'ocr_text'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Bank slip extraction failed: ' . $e->getMessage());
    }

    return ['amount' => $fallback, 'source' => 'submitted_field'];
}

/**
 * Generate admission number
 */
function generate_admission_number($year = null) {
    if (!$year) {
        $year = date('Y');
    }
    return 'KIU/' . $year . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Send email notification
 */
function send_email($to, $subject, $message, $from = SMTP_FROM_EMAIL) {
    // This is a placeholder - implement actual email sending using PHPMailer
    // or similar library
    return mail($to, $subject, $message, "From: $from");
}

/**
 * Get current user info
 */
function get_logged_in_user() {
    if (!is_logged_in()) return null;
    
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT u.*, sp.full_name, sp.phone_number
            FROM users u
            LEFT JOIN student_profiles sp ON u.user_id = sp.user_id
            WHERE u.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Failed to get current user: " . $e->getMessage());
        return null;
    }
}
