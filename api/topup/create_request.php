<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../src/classes/TopupRequest.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    response('error', 'Không có quyền');
}

$database = new Database();
$db = $database->connect();

error_log('=== TOPUP CREATE DEBUG ===');
error_log('User ID: ' . $_SESSION['user_id']);
error_log('DB Connection: ' . ($db ? 'OK' : 'FAILED'));

if (!$db) {
    http_response_code(500);
    error_log('Database connection failed');
    response('error', 'Kết nối cơ sở dữ liệu thất bại');
}

// Fail fast if Supabase credentials are missing
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_KEY') ?: getenv('SUPABASE_ANON_KEY');
if (!$supabaseUrl || !$supabaseKey) {
    http_response_code(500);
    error_log('Supabase env missing: SUPABASE_URL or SERVICE/ANON key');
    response('error', 'Thiếu cấu hình Supabase (SUPABASE_URL / SERVICE_KEY). Vui lòng bổ sung .env');
}

$topupClass = new TopupRequest($db);

$input = file_get_contents("php://input");
error_log('Raw Input: ' . $input);

$data = json_decode($input, true);
error_log('Decoded Data: ' . json_encode($data));

try {
    // Validate input data - handle both numeric and formatted strings (e.g., "200.000" with Vietnamese thousand separator)
    if (!isset($data['amount'])) {
        http_response_code(400);
        error_log('Amount not set');
        response('error', 'Số tiền là bắt buộc');
    }

    // Remove thousand separator dots and convert to float
    $amountStr = (string)$data['amount'];
    $amountStr = str_replace('.', '', $amountStr); // Remove Vietnamese thousand separators
    $amountStr = str_replace(',', '.', $amountStr); // Convert comma to decimal point if present
    
    if (!is_numeric($amountStr)) {
        http_response_code(400);
        error_log('Invalid amount format: ' . $data['amount']);
        response('error', 'Số tiền không hợp lệ');
    }

        $amount = (float)$amountStr;
        // Method is optional; when present we persist it in description (table has no method column)
        $method = $data['method'] ?? null;
    
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
    
        // Build a safe description; avoid sending unsupported columns to Supabase
        $description = $data['description'] ?? null;
        if (!$description && $method) {
            $description = 'method:' . $method;
        }

    $topupData = [
        'user_id' => (int)$_SESSION['user_id'],
        'amount' => $amount,
            'description' => $description,
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
        response('error', 'Không thể tạo yêu cầu nạp tiền. Kiểm tra lại cấu hình Supabase và thử lại.');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Exception: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    response('error', 'Lỗi: ' . $e->getMessage());
}
?>
