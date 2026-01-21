<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/functions.php';

// Convert PHP warnings/notices into exceptions
set_error_handler(function($errno, $errstr, $errfile, $errline){
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($e){
    $logId = function_exists('api_log_error') ? api_log_error($e->getMessage(), ['file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>$e->getTraceAsString()]) : null;
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error. Log ID: ' . $logId, 'log_id' => $logId]);
    exit;
});

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/License.php';

$database = new Database();
$db = $database->connect();
$licenseClass = new License($db);

$data = json_decode(file_get_contents('php://input'), true);
$licenseKey = trim($data['license_key'] ?? '');
$newHWID = trim($data['new_hwid'] ?? '');

error_log("set_hwid.php: START - license_key=$licenseKey, new_hwid=$newHWID, user_id=" . $_SESSION['user_id']);

$hwidRegex = '/^[A-Za-z0-9\-_]{8,64}$/';

if (!preg_match($hwidRegex, $newHWID)) {
    error_log("set_hwid.php: Invalid HWID format");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'HWID không hợp lệ. Vui lòng nhập 8-64 ký tự: chữ/số, dấu - hoặc _']);
    exit;
}

if (empty($licenseKey)) {
    error_log("set_hwid.php: Empty license key");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'License key không được để trống']);
    exit;
}

try {
    // 1. Get license info
    $license = $licenseClass->getKeyByLicense($licenseKey);
    
    if (!$license) {
        error_log("set_hwid.php: License not found");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'License key không tồn tại']);
        exit;
    }
    
    error_log("set_hwid.php: License found - id=" . $license['id'] . ", hwid=" . ($license['hwid'] ?? 'null'));
    
    // 2. Check ownership via orders table (orders now reference infokey_id)
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $isOwner = false;
    
    // Query orders to find owner
    $ordersEndpoint = "orders?infokey_id=eq." . urlencode($license['id']);
    $ordersResult = $db->callApi($ordersEndpoint, 'GET');
    
    error_log("set_hwid.php: Orders query result code=" . ($ordersResult ? $ordersResult->code : 'null'));
    
    if ($ordersResult && $ordersResult->code == 200 && !empty($ordersResult->response)) {
        foreach ($ordersResult->response as $order) {
            error_log("set_hwid.php: Order found - id=" . $order['id'] . ", user_id=" . $order['user_id']);
            if (isset($order['user_id']) && $order['user_id'] == $_SESSION['user_id']) {
                $isOwner = true;
                break;
            }
        }
    } else {
        error_log("set_hwid.php: No orders found for this license");
    }
    
    error_log("set_hwid.php: isAdmin=$isAdmin, isOwner=$isOwner");
    
    // 3. Permission check
    if (!$isOwner && !$isAdmin) {
        error_log("set_hwid.php: Permission denied");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này']);
        exit;
    }

    // 4. All owners (and admins) update HWID immediately — approval/history removed
    $apiResult = $licenseClass->updateKeyHWID($newHWID, $licenseKey);
    $success = ($apiResult && ($apiResult->code == 200 || $apiResult->code == 204));

    if ($success) {
        // Audit
        try { $db->callApi('audit_logs', 'POST', ['action' => 'HWID_UPDATED', 'username' => $_SESSION['username'] ?? 'unknown', 'details' => "Key: $licenseKey", 'status' => 'success', 'created_at' => date('c')]); } catch (Exception $e) {}

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'HWID đã được lưu thành công']);
        exit;
    } else {
        error_log("set_hwid.php: Update failed - check Supabase logs");
        $message = 'Không thể cập nhật HWID. Vui lòng kiểm tra quyền truy cập Supabase.';
        if (getenv('APP_DEBUG') && isset($apiResult->response)) {
            $message .= ' Chi tiết: ' . json_encode($apiResult->response);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
} catch (Exception $e) {
    error_log("set_hwid.php: Exception caught - " . $e->getMessage());
    error_log("set_hwid.php: Stack trace - " . $e->getTraceAsString());
    $logId = function_exists('api_log_error') ? api_log_error($e->getMessage(), ['file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>$e->getTraceAsString(),'input'=>$data ?? null]) : null;
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'log_id' => $logId]);
}
?>