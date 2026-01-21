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

// prepare optional Database API fallback
$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents("php://input"), true);

try {
    $message = trim($data['message'] ?? '');
    $active = isset($data['active']) ? (bool)$data['active'] : true;

    if ($message === '') throw new Exception('Message is required');

    if (!empty($pdo)) {
        // Use local Postgres
        $stmt = $pdo->prepare("INSERT INTO public.shop_notification (message) VALUES (?) RETURNING id");
        $stmt->execute([$message]);
        $new = $stmt->fetch();

        logAudit($pdo, 'NOTIFICATION_CREATE', $_SESSION['username'] ?? '', "Notification created id={$new['id']}", 'success');

        echo json_encode(['success' => true, 'message' => 'Đã thêm thông báo', 'notification' => $new]);
        exit;
    }

    // Fallback: call Supabase REST API
    $payload = ['message' => $message];
    $result = $db->callApi('shop_notification', 'POST', $payload);

    if ($result && ($result->code == 201 || $result->code == 200)) {
        echo json_encode(['success' => true, 'message' => 'Đã thêm thông báo', 'notification' => $result->response[0] ?? null]);
    } else {
        $msg = 'Thêm thất bại';
        if (is_object($result) && isset($result->response) && is_array($result->response)) {
            $first = $result->response[0] ?? null;
            if (is_array($first) && isset($first['message'])) $msg = $first['message'];
        }
        throw new Exception($msg);
    }

} catch (Exception $e) {
    // Safe logging using whatever DB is available
    @logAudit($pdo ?? null, 'NOTIFICATION_CREATE_ERROR', $_SESSION['username'] ?? '', $e->getMessage(), 'error');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
