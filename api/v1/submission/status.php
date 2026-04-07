<?php
/**
 * API Endpoint - Get submission status
 * Usage: GET /api/v1/submission/status.php?submission_id=123
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../../config/init.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$submission_id = $_GET['submission_id'] ?? null;

if (!$submission_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Submission ID required']);
    exit;
}

try {
    if (
        !table_exists($db, 'document_submissions') ||
        !table_exists($db, 'admissions_verifications') ||
        !table_exists($db, 'finance_clearances') ||
        !table_exists($db, 'green_cards')
    ) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => "Database migration required: regulation workflow tables are missing."
        ]);
        exit;
    }

    $stmt = $db->prepare("
        SELECT ds.*,
               av.is_approved AS admissions_approved,
               av.verification_notes,
               fc.is_cleared AS finance_approved,
               fc.is_pending AS finance_pending,
               gc.registration_number, gc.card_id
        FROM document_submissions ds
        LEFT JOIN admissions_verifications av ON ds.submission_id = av.submission_id
        LEFT JOIN finance_clearances fc ON ds.submission_id = fc.submission_id
        LEFT JOIN green_cards gc ON ds.submission_id = gc.submission_id
        WHERE ds.submission_id = :submission_id AND ds.user_id = :user_id
    ");
    
    $stmt->execute([
        'submission_id' => $submission_id,
        'user_id' => $_SESSION['user_id']
    ]);
    
    $submission = $stmt->fetch();
    
    if (!$submission) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Submission not found']);
        exit;
    }

    $transition_map = get_workflow_transition_map();
    if (!array_key_exists($submission['status'], $transition_map)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unknown workflow status detected']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'submission_id' => $submission['submission_id'],
            'status' => $submission['status'],
            'submitted_at' => $submission['submitted_at'],
            'reviewed_at' => $submission['last_updated_at'] ?? null,
            'is_approved' => $submission['admissions_approved'],
            'finance_approved' => $submission['finance_approved'],
            'finance_pending' => $submission['finance_pending'],
            'verification_notes' => $submission['verification_notes'],
            'registration_number' => $submission['registration_number'],
            'has_greencard' => !empty($submission['card_id']),
            'next_allowed_statuses' => $transition_map[$submission['status']]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
