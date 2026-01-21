<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Method not allowed');
}

$auth = requireRole(ROLE_ADMIN);

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$active = (bool)($input['active'] ?? true);

if (!$message) {
    response('error', 'Message required');
}

try {
    if (!empty($pdo)) {
        // Xóa thông báo cũ
        $pdo->prepare("DELETE FROM public.shop_notification")->execute();
        
        // Tạo thông báo mới
        $stmt = $pdo->prepare("
            INSERT INTO public.shop_notification (message, active, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
            RETURNING id
        ");
        
        $stmt->execute([$message, $active]);
        $notif = $stmt->fetch();

        logAudit($pdo, 'NOTIFICATION_UPDATED', $auth['username'], $message, 'success');
        response('success', 'Notification updated', ['notification_id' => $notif['id']]);
        exit;
    }

    // Fallback: use Supabase REST API
    $database = new Database();
    $db = $database->connect();

    // Delete existing notifications by id
    $existing = $db->callApi('shop_notification?select=id', 'GET');
    if ($existing && $existing->code == 200 && !empty($existing->response)) {
        foreach ($existing->response as $row) {
            $db->callApi('shop_notification?id=eq.' . intval($row['id']), 'DELETE');
        }
    }

    // Insert new
    $result = $db->callApi('shop_notification', 'POST', ['message' => $message, 'active' => $active]);
    if ($result && ($result->code == 201 || $result->code == 200)) {
        response('success', 'Notification updated', ['notification_id' => $result->response[0]['id'] ?? null]);
    }

    response('error', 'Failed to update notification');

} catch (Exception $e) {
    logAudit($pdo ?? null, 'NOTIFICATION_ERROR', $auth['username'], $e->getMessage(), 'error');
    response('error', 'Failed to update notification');
}
?>
