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

    if (!empty($pdo)) {
        $stmt = $pdo->prepare("DELETE FROM public.shop_notification WHERE id = ?");
        $stmt->execute([$id]);

        logAudit($pdo, 'NOTIFICATION_DELETE', $_SESSION['username'] ?? '', "Notification deleted id={$id}", 'success');

        echo json_encode(['success' => true, 'message' => 'Đã xóa']);
        exit;
    }

    // Fallback: Supabase
    $endpoint = 'shop_notification?id=eq.' . $id;
    $result = $db->callApi($endpoint, 'DELETE');

    if ($result && ($result->code == 200 || $result->code == 204)) {
        echo json_encode(['success' => true, 'message' => 'Đã xóa']);
    } else {
        throw new Exception('Xóa thất bại');
    }

} catch (Exception $e) {
    @logAudit($pdo ?? null, 'NOTIFICATION_DELETE_ERROR', $_SESSION['username'] ?? '', $e->getMessage(), 'error');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
