<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/TopupRequest.php';

$data = json_decode(file_get_contents('php://input'), true);
$topupId = $data['topup_id'] ?? 0;

if (!$topupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid topup ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $topupClass = new TopupRequest($db);
    
    // Cancel the topup (verifies user ownership and status)
    $success = $topupClass->cancelTopupRequest($topupId, $_SESSION['user_id']);
    
    if ($success) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Yêu cầu đã được hủy']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Không thể hủy yêu cầu này (có thể đã được xử lý)']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>