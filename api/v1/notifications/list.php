<?php
/**
 * API Endpoint - Get user notifications
 * Usage: GET /api/v1/notifications/list.php
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

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    $where_clause = "WHERE user_id = :user_id";
    if ($unread_only) {
        $where_clause .= " AND read_at IS NULL";
    }
    
    $stmt = $db->prepare("
        SELECT * FROM notifications
        $where_clause
        ORDER BY created_at DESC
        LIMIT :limit
    ");
    
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'count' => count($notifications)
    ]);
    
} catch (Exception $e) {
    error_log("API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
