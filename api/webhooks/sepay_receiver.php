<?php
// ====================================================================
// SePay Webhook Receiver (Optimized & Clean)
// ====================================================================

// 1. Đọc dữ liệu input stream (chỉ đọc 1 lần)
$raw_data = file_get_contents('php://input');

// Ghi log thô
file_put_contents(__DIR__ . '/sepay.log', date('Y-m-d H:i:s') . ' - HIT: ' . $raw_data . PHP_EOL, FILE_APPEND);

header('Content-Type: application/json');

// 2. Load env clean (không session)
require_once __DIR__ . '/../../includes/env_loader.php';
loadEnv(__DIR__ . '/../../.env');
$env = $_ENV; // env_loader populates $_ENV

// 3. Auth Check (Server-to-Server)
$secret = $env['SEPAY_WEBHOOK_SECRET'] ?? getenv('SEPAY_WEBHOOK_SECRET') ?? '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

// Nếu có config secret thì bắt buộc phải check
if (!empty($secret)) {
    // Chấp nhận cả 2 dạng: "Apikey <token>" hoặc gửi token trực tiếp
    $isValid = false;

    // Check 1: Chuẩn Authorization: Apikey ...
    if ($authHeader === 'Apikey ' . $secret) {
        $isValid = true;
    }
    // Check 2: Header tùy chỉnh cũ (fallback)
    elseif (
        ($_SERVER['HTTP_X_CLIENT_KEY'] ?? '') === $secret ||
        ($_SERVER['HTTP_X_API_KEY'] ?? '') === $secret
    ) {
        $isValid = true;
    }

    if (!$isValid) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

// 4. Parse JSON
$data = json_decode($raw_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// 5. Kết nối DB (Cần cẩn thận không include file nào có session_start)
// Database.php của bạn có session_start() không?
// Kiểm tra: config/database.php thường an toàn.
require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->connect();

// Lấy dữ liệu
$amount_in = $data['transferAmount'] ?? 0;
$content = $data['content'] ?? '';
$transaction_date = $data['transactionDate'] ?? '';
$reference_number = $data['referenceCode'] ?? '';
$gateway = $data['gateway'] ?? '';
$transfer_type = $data['transferType'] ?? '';

// Chỉ xử lý giao dịch nhận tiền (in)
if ($transfer_type !== 'in') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ignored outgoing transaction']);
    exit;
}

// deduplication check
$isDuplicate = false;
if (!empty($reference_number)) {
    $checkParams = http_build_query(['reference_number' => 'eq.' . $reference_number, 'amount_in' => 'eq.' . $amount_in, 'select' => 'id']);
    $checkRes = $db->callApi('sepay_transactions?' . $checkParams, 'GET');
    if (!empty($checkRes->response))
        $isDuplicate = true;
}

if ($isDuplicate) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Duplicate']);
    exit;
}

// Insert Transaction (Lưu Log)
$transactionData = [
    'gateway' => $gateway,
    'transaction_date' => $transaction_date,
    'account_number' => $data['accountNumber'] ?? '',
    'sub_account' => $data['subAccount'] ?? '',
    'amount_in' => $amount_in,
    'amount_out' => 0,
    'accumulated' => $data['accumulated'] ?? 0,
    'code' => $data['code'] ?? '',
    'transaction_content' => $content,
    'reference_number' => $reference_number,
    'description' => $data['description'] ?? '',
    'raw_data' => $data
];
$res = $db->callApi('sepay_transactions', 'POST', $transactionData);
$transaction_id = $res->response[0]['id'] ?? null;

// ============================================
// PROCESSING LOGIC (PHP Pure)
// ============================================
$processed = false;
$desc = strtolower($content);
$cleanDesc = preg_replace('/[^a-z0-9]/', '', $desc);
$amountStr = strval(intval($amount_in));

// Logic: Match by 'shoptoolnrocl' + TopupID (e.g. shoptoolnrocl123)
if (preg_match('/shoptoolnrocl(\d+)/', $cleanDesc, $matches)) {
    $extractedId = (int) $matches[1];

    // Find the pending topup request by ID
    // We strictly check for 'pending' status to avoid double processing or processing old requests
    $q = http_build_query(['id' => 'eq.' . $extractedId, 'status' => 'eq.pending', 'limit' => 1]);
    $topupRes = $db->callApi('topup_requests?' . $q, 'GET');

    if (!empty($topupRes->response)) {
        $topup = $topupRes->response[0];

        // Security Check: Amount must match or be greater than requested
        // Allow small difference? No, usually exact match. 
        // Let's allow strictly >= request amount
        if ($amount_in >= $topup['amount']) {
            processSuccess($db, $topup['user_id'], $topup['id'], $transaction_id, $amount_in, $topup['description']);
            $processed = true;
        }
    }
}

// 6. Trả về đúng chuẩn 200 JSON
http_response_code(200);
echo json_encode(['success' => true]);
exit;


// Helper function to update DB
function processSuccess($db, $userId, $topupId, $transId, $amount, $desc)
{
    // + Tiền User
    $u = $db->callApi('users?id=eq.' . $userId, 'GET');
    $bal = $u->response[0]['balance'] + $amount;
    $db->callApi('users?id=eq.' . $userId, 'PATCH', ['balance' => $bal]);

    // Update Topup
    $db->callApi('topup_requests?id=eq.' . $topupId, 'PATCH', [
        'status' => 'approved',
        'approved_at' => date('Y-m-d H:i:s'),
        'description' => $desc . ' [Auto]'
    ]);

    // Update Trans
    if ($transId) {
        $db->callApi('sepay_transactions?id=eq.' . $transId, 'PATCH', [
            'processed' => true,
            'matched_topup_id' => $topupId,
            'matched_user_id' => $userId,
            'processed_at' => date('Y-m-d H:i:s')
        ]);
    }
}
?>