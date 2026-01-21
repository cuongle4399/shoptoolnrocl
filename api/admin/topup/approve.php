<?php
// ====================================================================
// API: Admin Approve/Reject Topup Request
// Using Supabase - trigger on_topup_approved() auto-adds balance
// ====================================================================

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/classes/TopupRequest.php';

$database = new Database();
$db = $database->connect();
$topupClass = new TopupRequest($db);

$input = json_decode(file_get_contents('php://input'), true);
$topup_id = isset($input['topup_id']) ? (int)$input['topup_id'] : 0;
$approved = isset($input['approved']) ? (bool)$input['approved'] : false;
$rejection_reason = isset($input['rejection_reason']) ? trim($input['rejection_reason']) : null;

if (!$topup_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'topup_id is required']);
    exit;
}

try {
    $topup = $topupClass->getTopupById($topup_id);
    
    if (!$topup) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Topup request not found']);
        exit;
    }
    
    if ($topup['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This request is not in pending status']);
        exit;
    }
    
    $newStatus = $approved ? 'approved' : 'rejected';
    
    // Update topup status
    // If approved: Supabase trigger on_topup_approved() will automatically add balance
    if (!$topupClass->updateTopupStatus($topup_id, $newStatus, $_SESSION['user_id'], $rejection_reason)) {
        throw new Exception('Failed to update topup status');
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $approved ? 'Topup approved. Balance has been added.' : 'Topup rejected.',
        'topup_id' => $topup_id,
        'status' => $newStatus
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
