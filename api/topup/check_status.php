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

$topupId = $_GET['id'] ?? 0;

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
    $topup = $topupClass->getTopupById($topupId);
    
    if (!$topup) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Topup not found']);
        exit;
    }
    
    // Verify user owns this topup
    if ($topup['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => $topup['status'],
        'amount' => $topup['amount'],
        'created_at' => $topup['created_at'],
        'approved_at' => $topup['approved_at'] ?? null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>