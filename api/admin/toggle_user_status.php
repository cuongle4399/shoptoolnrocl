<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents("php://input"), true);

try {
    $endpoint = "users?id=eq." . (int)$data['user_id'];
    $get = $db->callApi($endpoint, 'GET');
    if (!($get && $get->code == 200 && !empty($get->response))) throw new Exception('User không tồn tại');

    $current = $get->response[0];
    $new_status = ($current['status'] === 'active') ? 'inactive' : 'active';

    // Prevent an admin from accidentally disabling their own account (locks them out)
    if ((int)($data['user_id'] ?? 0) === (int)($_SESSION['user_id'] ?? 0) && $current['status'] === 'active' && $new_status === 'inactive') {
        throw new Exception('Bạn không thể khoá chính tài khoản đang dùng. Hãy dùng admin khác để khoá.');
    }

    $result = $db->callApi($endpoint, 'PATCH', ['status' => $new_status]);

    if ($result && ($result->code == 200 || $result->code == 204)) {
        echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái']);
    } else {
        throw new Exception('Cập nhật thất bại');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
