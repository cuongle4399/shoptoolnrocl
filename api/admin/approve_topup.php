<?php
// ====================================================================
// API: Approve Topup Request
// Explicitly adds balance to user account
// ====================================================================

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền (admin)']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/TopupRequest.php';
require_once '../../src/classes/User.php';

$database = new Database();
$db = $database->connect();
$topupClass = new TopupRequest($db);
$userClass = new User($db);

$input = json_decode(file_get_contents('php://input'), true);
$topup_id = isset($input['topup_id']) ? (int) $input['topup_id'] : 0;

try {
    if (!$topup_id) {
        throw new Exception('Thiếu topup_id');
    }

    $topup = $topupClass->getTopupById($topup_id);
    if (!$topup) {
        throw new Exception('Không tìm thấy yêu cầu nạp');
    }

    // Check current status - prevent double approval
    if ($topup['status'] !== 'pending') {
        throw new Exception('Yêu cầu không ở trạng thái chờ (hiện tại: ' . $topup['status'] . ')');
    }

    // IMPORTANT: Add balance to user BEFORE updating status
    $balanceUpdated = $userClass->updateUserBalance($topup['user_id'], $topup['amount']);
    if (!$balanceUpdated) {
        throw new Exception('Không thể cộng tiền vào tài khoản người dùng');
    }

    // Update topup status to 'approved'
    $statusUpdated = $topupClass->updateTopupStatus($topup_id, 'approved', $_SESSION['user_id'], null);
    if (!$statusUpdated) {
        // If status update fails, we should ideally rollback the balance
        // But for now, just throw error (balance was already added)
        throw new Exception('Duyệt yêu cầu nạp thất bại (tiền đã được cộng)');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Yêu cầu nạp đã được duyệt. Số dư đã cộng vào tài khoản.',
        'topup_id' => $topup_id,
        'amount' => $topup['amount'],
        'user_id' => $topup['user_id']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>