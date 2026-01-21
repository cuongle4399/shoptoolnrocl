<?php
/**
 * FIXED VERSION: adjust_user_balance.php
 * Issues fixed:
 * 1. PostgREST API properly uses PATCH with RLS
 * 2. Proper error handling for Supabase responses
 * 3. Transaction logging with proper error recovery
 * 4. Validation prevents negative balances
 */

header('Content-Type: application/json');
session_start();

// ===== AUTHORIZATION CHECK =====
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ===== CONFIGURATION =====
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ===== INPUT PARSING & VALIDATION =====
$input = json_decode(file_get_contents('php://input'), true);

$user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$amount = isset($input['amount']) ? (float)$input['amount'] : 0;
$reason = isset($input['reason']) ? trim($input['reason']) : '';

// Validate inputs
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID không hợp lệ']);
    exit;
}

if (!is_numeric($amount) || $amount == 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Số tiền phải khác 0']);
    exit;
}

$amount = round($amount, 2);

if (abs($amount) > 999999999.99) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Số tiền vượt quá giới hạn cho phép']);
    exit;
}

try {
    // ===== CONNECT TO DATABASE =====
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception('Kết nối cơ sở dữ liệu thất bại');
    }

    // ===== FETCH CURRENT USER DATA =====
    // Using PostgREST API call
    $endpoint = "users?id=eq." . $user_id . "&select=id,username,balance,role,status";
    $getResponse = $db->callApi($endpoint, 'GET');
    
    if (!$getResponse || $getResponse->code != 200) {
        throw new Exception('Không thể lấy dữ liệu người dùng');
    }
    
    if (empty($getResponse->response)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        exit;
    }

    $user = $getResponse->response[0];
    $current_balance = isset($user['balance']) ? (float)$user['balance'] : 0;
    $new_balance = $current_balance + $amount;

    // ===== VALIDATE NEW BALANCE =====
    if ($new_balance < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Số dư không đủ']);
        exit;
    }

    // ===== UPDATE BALANCE IN DATABASE =====
    $updatePayload = ['balance' => $new_balance];
    $updateResponse = $db->callApi($endpoint, 'PATCH', $updatePayload);
    
    if (!$updateResponse || !in_array($updateResponse->code, [200, 204])) {
        throw new Exception('Cập nhật số dư thất bại: ' . ($updateResponse->message ?? 'Lỗi không xác định'));
    }

    // ===== LOG THE TRANSACTION =====
    $logPayload = [
        'action' => 'balance_adjustment',
        'user_id' => $user_id,
        'username' => $user['username'] ?? null,
        'amount_changed' => $amount,
        'previous_balance' => $current_balance,
        'new_balance' => $new_balance,
        'reason' => $reason ?: null,
        'adjusted_by' => $_SESSION['username'] ?? 'system',
        'status' => 'success',
        'created_at' => date('c')
    ];

    // Try to create audit log (non-blocking)
    $logResponse = $db->callApi('audit_logs', 'POST', $logPayload);
    
    if (!$logResponse || !in_array($logResponse->code, [201, 200])) {
        // Log failed but balance was updated - log to file as fallback
        error_log(json_encode([
            'audit_log_failed' => true,
            'user_id' => $user_id,
            'amount' => $amount,
            'timestamp' => date('c')
        ]));
    }

    // ===== SUCCESS RESPONSE =====
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật số dư thành công',
        'data' => [
            'user_id' => $user_id,
            'username' => $user['username'] ?? null,
            'previous_balance' => $current_balance,
            'amount_changed' => $amount,
            'new_balance' => $new_balance
        ]
    ]);

} catch (Exception $e) {
    // ===== ERROR LOGGING =====
    error_log(json_encode([
        'error' => 'Balance adjustment failed',
        'user_id' => $user_id ?? null,
        'message' => $e->getMessage(),
        'timestamp' => date('c'),
        'admin_id' => $_SESSION['user_id']
    ]));

    // Try to log error to database
    try {
        $errorLogPayload = [
            'action' => 'balance_adjustment_error',
            'user_id' => $user_id ?? null,
            'error_message' => $e->getMessage(),
            'attempted_by' => $_SESSION['username'] ?? 'unknown',
            'status' => 'error',
            'created_at' => date('c')
        ];
        $db->callApi('audit_logs', 'POST', $errorLogPayload);
    } catch (Exception $ignored) {
        // Audit log failed, continue with error response
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Cập nhật số dư thất bại'
    ]);
}
?>