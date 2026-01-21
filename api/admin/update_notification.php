<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents("php://input"), true);

try {
    $id = (int)($data['notify_id'] ?? 0);
    if (!$id) throw new Exception('notify_id required');

    $message = trim($data['message'] ?? '');
    if ($message === '') throw new Exception('Message is required');

    if (!empty($pdo)) {
        $stmt = $pdo->prepare("UPDATE public.shop_notification SET message = ? WHERE id = ?");
        $stmt->execute([$message, $id]);

        logAudit($pdo, 'NOTIFICATION_UPDATE', $_SESSION['username'] ?? '', "Notification updated id={$id}", 'success');

        echo json_encode(['success' => true, 'message' => 'Đã cập nhật thông báo']);
        exit;
    }

    // Fallback: Supabase
    $payload = ['message' => $message];
    $endpoint = 'shop_notification?id=eq.' . $id;
    $result = $db->callApi($endpoint, 'PATCH', $payload);

    if ($result && ($result->code == 200 || $result->code == 204)) {
        echo json_encode(['success' => true, 'message' => 'Đã cập nhật thông báo']);
    } else {
        throw new Exception('Cập nhật thất bại');
    }

} catch (Exception $e) {
    @logAudit($pdo ?? null, 'NOTIFICATION_UPDATE_ERROR', $_SESSION['username'] ?? '', $e->getMessage(), 'error');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>