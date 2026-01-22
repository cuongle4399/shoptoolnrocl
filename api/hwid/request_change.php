<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/License.php';

$input = json_decode(file_get_contents('php://input'), true);
$licenseKey = trim($input['license_key'] ?? '');
$newHWID = trim($input['new_hwid'] ?? '');

$hwidRegex = '/^[A-Za-z0-9\-_]{8,64}$/';
if (!preg_match($hwidRegex, $newHWID)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'HWID mới không hợp lệ']);
    exit;
}
if (empty($licenseKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'License key không được để trống']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    $licenseClass = new License($db);

    $license = $licenseClass->getKeyByLicense($licenseKey);
    if (!$license) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'License key không tồn tại']);
        exit;
    }

    // Verify ownership or admin
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $isOwner = false;

    $ordersEndpoint = "orders?infokey_id=eq." . urlencode($license['id']);
    $ordersResult = $db->callApi($ordersEndpoint, 'GET');
    if ($ordersResult && $ordersResult->code == 200 && !empty($ordersResult->response)) {
        foreach ($ordersResult->response as $order) {
            if (isset($order['user_id']) && $order['user_id'] == $_SESSION['user_id']) { $isOwner = true; break; }
        }
    }

    if (!$isAdmin && !$isOwner) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này']);
        exit;
    }

    // Apply update immediately
    $apiResult = $licenseClass->updateKeyHWID($newHWID, $licenseKey);
    $success = ($apiResult && ($apiResult->code == 200 || $apiResult->code == 204));
    if ($success) {
        try { $db->callApi('audit_logs', 'POST', ['action' => 'HWID_UPDATED', 'username' => $_SESSION['username'] ?? 'unknown', 'details' => "Key: $licenseKey", 'status' => 'success', 'created_at' => date('c')]); } catch (Exception $e) {}
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'HWID đã được cập nhật']);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Không thể cập nhật HWID.'] );
        exit;
    }

} catch (Exception $e) {
    $logId = function_exists('api_log_error') ? api_log_error($e->getMessage(), ['file'=>$e->getFile(),'line'=>$e->getLine()]) : null;
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Log ID: ' . $logId, 'log_id' => $logId]);
}?>

