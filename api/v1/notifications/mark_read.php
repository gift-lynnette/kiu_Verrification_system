<?php
/**
 * API Endpoint - Mark notification as read
 * Usage: POST /api/v1/notifications/mark_read.php
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

$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? null;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
    exit;
}

try {
    $stmt = $db->prepare("
        UPDATE notifications
        SET read_at = NOW()
        WHERE notification_id = :notification_id AND user_id = :user_id
    ");
    
    $stmt->execute([
        'notification_id' => $notification_id,
        'user_id' => $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
    
} catch (Exception $e) {
    error_log("API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
