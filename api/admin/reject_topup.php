<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/TopupRequest.php';

$database = new Database();
$db = $database->connect();
$topupClass = new TopupRequest($db);

$input = json_decode(file_get_contents('php://input'), true);
$topup_id = isset($input['topup_id']) ? (int)$input['topup_id'] : 0;
$rejection_reason = isset($input['rejection_reason']) ? trim($input['rejection_reason']) : null;

try {
    if (!$topup_id) throw new Exception('topup_id required');

    $topup = $topupClass->getTopupById($topup_id);
    if (!$topup) throw new Exception('Yêu cầu nạp tiền không tồn tại');
    if ($topup['status'] !== 'pending') throw new Exception('Yêu cầu này không ở trạng thái chờ');

    if (!$topupClass->updateTopupStatus($topup_id, 'rejected', $_SESSION['user_id'], $rejection_reason)) {
        throw new Exception('Không thể từ chối yêu cầu');
    }

    echo json_encode(['success' => true, 'message' => 'Đã từ chối yêu cầu']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
