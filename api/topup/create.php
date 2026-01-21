<?php
header('Content-Type: application/json');
session_start();

// Debug log
$logFile = __DIR__ . '/../../logs/api_debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] {$message}\n", 3, $logFile);
}

writeLog('=== TOPUP CREATE REQUEST START ===');
writeLog('Method: ' . $_SERVER['REQUEST_METHOD']);

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog('ERROR: Not POST method');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    writeLog('ERROR: User not authenticated');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

writeLog('User ID: ' . $_SESSION['user_id']);

// Include required files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/classes/TopupRequest.php';

writeLog('Includes loaded');

// Initialize Supabase connection
try {
    $database = new Database();
    $db = $database->connect();
    
    writeLog('DB Type: ' . gettype($db));
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    writeLog('Database connection: OK');
} catch (Exception $e) {
    writeLog('Database Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get input data
$input = file_get_contents("php://input");
writeLog('Raw Input: ' . $input);

$data = json_decode($input, true);
writeLog('Decoded Data: ' . json_encode($data));

if (!$data) {
    writeLog('ERROR: Failed to decode JSON');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Validate input
$user_id = (int)$_SESSION['user_id'];
$amount = isset($data['amount']) ? (float)$data['amount'] : 0;
$description = isset($data['description']) ? trim($data['description']) : null;

writeLog('User ID: ' . $user_id);
writeLog('Amount: ' . $amount);
writeLog('Description: ' . ($description ?? 'none'));

// Validate amount
if ($amount < 10000) {
    writeLog('ERROR: Amount too low');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Số tiền không hợp lệ (tối thiểu 10.000)']);
    exit;
}

// Insert into database using TopupRequest class (Supabase API)
try {
    $topupClass = new TopupRequest($db);
    writeLog('TopupRequest class initialized');
    
    $topupData = [
        'user_id' => $user_id,
        'amount' => $amount,
        'description' => $description,
        'status' => 'pending'
    ];
    
    writeLog('Topup Data: ' . json_encode($topupData));
    
    // Create topup request via Supabase API
    $created = $topupClass->createTopupRequest($topupData);
    
    writeLog('Create Result: ' . json_encode($created));
    writeLog('Create Result Type: ' . gettype($created));
    
    if ($created !== null && $created !== false) {
        writeLog('SUCCESS: Topup request created');
        
        $response = [
            'success' => true,
            'message' => 'Yêu cầu nạp tiền đã được tạo. Vui lòng chờ admin duyệt.'
        ];
        
        // Extract ID if available
        if (is_array($created) && isset($created['id'])) {
            $response['topup_id'] = $created['id'];
            $response['amount'] = (float)$created['amount'];
            $response['status'] = $created['status'];
            writeLog('Topup ID: ' . $created['id']);
        } elseif (is_object($created) && isset($created->id)) {
            $response['topup_id'] = $created->id;
            $response['amount'] = (float)$created->amount;
            $response['status'] = $created->status;
            writeLog('Topup ID: ' . $created->id);
        }
        
        http_response_code(201);
        echo json_encode($response);
    } else {
        writeLog('ERROR: createTopupRequest returned null/false');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Không thể tạo yêu cầu nạp tiền. Vui lòng thử lại!'
        ]);
    }
    
} catch (Exception $e) {
    writeLog('Exception: ' . $e->getMessage());
    writeLog('Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}

writeLog('=== TOPUP CREATE REQUEST END ===');
?>