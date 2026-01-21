<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$database = new Database();
$db = $database->connect();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    response('error', 'Method not allowed');
}

try {
    if (!empty($pdo)) {
        $stmt = $pdo->prepare("
            SELECT id, message FROM public.shop_notification
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute();
        $notification = $stmt->fetch();
        response('success', 'Notification fetched', ['notification' => $notification]);
        exit;
    }

    // Fallback to API - schema now only has id and message fields
    $result = $db->callApi('shop_notification?order=id.desc&limit=1', 'GET');
    if ($result && $result->code == 200 && !empty($result->response)) {
        $notification = $result->response[0];
        response('success', 'Notification fetched', ['notification' => $notification]);
        exit;
    }

    response('success', 'No notification', ['notification' => null]);

} catch (Exception $e) {
    response('success', 'No notification', ['notification' => null]);
}
?>
