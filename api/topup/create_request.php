<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../src/classes/TopupRequest.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    response('error', 'Unauthorized');
}

$database = new Database();
$db = $database->connect();

error_log('=== TOPUP CREATE DEBUG ===');
error_log('User ID: ' . $_SESSION['user_id']);
error_log('DB Connection: ' . ($db ? 'OK' : 'FAILED'));

if (!$db) {
    http_response_code(500);
    error_log('Database connection failed');
    response('error', 'Database connection failed');
}

$topupClass = new TopupRequest($db);

$input = file_get_contents("php://input");
error_log('Raw Input: ' . $input);

$data = json_decode($input, true);
error_log('Decoded Data: ' . json_encode($data));

try {
    // Validate input data
    if (!isset($data['amount']) || !is_numeric($data['amount'])) {
        http_response_code(400);
        error_log('Invalid amount: ' . ($data['amount'] ?? 'not set'));
        response('error', 'Số tiền không hợp lệ');
    }
    
    if (!isset($data['method']) || !in_array($data['method'], ['vietqr', 'manual', 'bank_transfer'])) {
        http_response_code(400);
        error_log('Invalid method: ' . ($data['method'] ?? 'not set'));
        response('error', 'Phương thức nạp tiền không hợp lệ');
    }

    $amount = (float)$data['amount'];
    $method = $data['method'];
    
    // Validate amount
    if ($amount < 10000) {
        http_response_code(400);
        error_log('Amount too low: ' . $amount);
        response('error', 'Số tiền không hợp lệ (tối thiểu 10.000)');
    }
    
    // Check max reasonable amount (e.g., 100 million VND)
    if ($amount > 100000000) {
        http_response_code(400);
        error_log('Amount too high: ' . $amount);
        response('error', 'Số tiền quá lớn');
    }
    
    $topupData = [
        'user_id' => (int)$_SESSION['user_id'],
        'amount' => $amount,
        'method' => $method,
        'status' => 'pending'
    ];
    
    error_log('Topup Data: ' . json_encode($topupData));
    
    // Create topup request
    $created = $topupClass->createTopupRequest($topupData);
    
    error_log('Create Result: ' . json_encode($created));
    error_log('Create Result Type: ' . gettype($created));
    
    if ($created !== null && $created !== false) {
        $responseData = [];
        
        // Extract topup_id if available
        $topup_id = null;
        if (is_array($created) && isset($created['id'])) {
            $topup_id = $created['id'];
        } elseif (is_object($created) && isset($created->id)) {
            $topup_id = $created->id;
        }

        if ($topup_id) {
            $responseData['topup_id'] = $topup_id;
        }

        http_response_code(201);
        error_log('Success: Created topup');
        response('success', 'Yêu cầu nạp tiền đã được tạo', $responseData);
        
    } else {
        http_response_code(400);
        error_log('createTopupRequest returned false/null');
        error_log('Check: $created value: ' . var_export($created, true));
        response('error', 'Không thể tạo yêu cầu nạp tiền. Vui lòng thử lại!');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Exception: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    response('error', 'Lỗi: ' . $e->getMessage());
}
?>
