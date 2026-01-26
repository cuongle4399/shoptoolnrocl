<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/TopupRequest.php';

$topupId = $_GET['id'] ?? 0;

if (!$topupId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID yêu cầu nạp không hợp lệ']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();

    if (!$db) {
        throw new Exception('Kết nối cơ sở dữ liệu thất bại');
    }

    $topupClass = new TopupRequest($db);
    $topup = $topupClass->getTopupById($topupId);

    if (!$topup) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy yêu cầu nạp']);
        exit;
    }

    // Verify user owns this topup
    if ($topup['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Không đủ quyền']);
        exit;
    }

    // Convert timestamps to Vietnam timezone
    $created_at_vn = null;
    if (!empty($topup['created_at'])) {
        try {
            $dt = new DateTime($topup['created_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
            $created_at_vn = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $created_at_vn = $topup['created_at'];
        }
    }

    $approved_at_vn = null;
    if (!empty($topup['approved_at'])) {
        try {
            $dt = new DateTime($topup['approved_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
            $approved_at_vn = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $approved_at_vn = $topup['approved_at'];
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => $topup['status'],
        'amount' => $topup['amount'],
        'created_at' => $created_at_vn,      // ✅ Giờ Việt Nam
        'approved_at' => $approved_at_vn     // ✅ Giờ Việt Nam
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>