<?php
// ====================================================================
// SePay Webhook Receiver
// Receives transaction notifications from SePay and auto-approves topup
// ====================================================================

header('Content-Type: application/json');

// Load environment variables from .env file
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        if (strpos($line, '=') === false)
            continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!empty($key) && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Get configuration from environment
$enableIpCheck = filter_var($_ENV['SEPAY_ENABLE_IP_CHECK'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$whitelistIps = !empty($_ENV['SEPAY_WHITELIST_IPS'])
    ? array_map('trim', explode(',', $_ENV['SEPAY_WHITELIST_IPS']))
    : ['103.124.92.0/24', '171.244.50.0/24'];

// Get client IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Function to check if IP is in whitelist
function isIpWhitelisted($ip, $whitelist)
{
    foreach ($whitelist as $range) {
        if (strpos($range, '/') !== false) {
            // CIDR notation
            list($subnet, $mask) = explode('/', $range);
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = -1 << (32 - (int) $mask);
            if (($ip_long & $mask_long) == ($subnet_long & $mask_long)) {
                return true;
            }
        } else {
            // Single IP
            if ($ip === $range) {
                return true;
            }
        }
    }
    return false;
}

// Verify IP whitelist (controlled by .env)
if ($enableIpCheck && !isIpWhitelisted($client_ip, $whitelistIps)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'IP not whitelisted']);
    exit;
}

// Verify webhook secret (if configured)
$webhookSecret = $_ENV['SEPAY_WEBHOOK_SECRET'] ?? '';
if (!empty($webhookSecret)) {
    // SePay gửi secret qua header x-client-key hoặc x-api-key (theo docs SePay)
    $receivedSecret = $_SERVER['HTTP_X_CLIENT_KEY']
        ?? $_SERVER['HTTP_X_API_KEY']
        ?? $_SERVER['HTTP_X_WEBHOOK_SECRET']
        ?? '';

    if (empty($receivedSecret)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Missing webhook secret']);
        exit;
    }

    if (!hash_equals($webhookSecret, $receivedSecret)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid webhook secret']);
        exit;
    }
}

require_once __DIR__ . '/../../config/database.php';

try {
    // Get raw POST data
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data);

    if (!is_object($data)) {
        throw new Exception('Invalid JSON data');
    }

    // Extract transaction data from SePay
    $gateway = $data->gateway ?? '';
    $transaction_date = $data->transactionDate ?? '';
    $account_number = $data->accountNumber ?? '';
    $sub_account = $data->subAccount ?? '';
    $transfer_type = $data->transferType ?? '';
    $transfer_amount = $data->transferAmount ?? 0;
    $accumulated = $data->accumulated ?? 0;
    $code = $data->code ?? '';
    $transaction_content = $data->content ?? '';
    $reference_number = $data->referenceCode ?? '';
    $description = $data->description ?? '';

    // Determine amount in/out
    $amount_in = 0;
    $amount_out = 0;

    if ($transfer_type == "in") {
        $amount_in = $transfer_amount;
    } else if ($transfer_type == "out") {
        $amount_out = $transfer_amount;
    }

    // Connect to Supabase
    $database = new Database();
    $db = $database->connect();

    // Prepare data for insertion
    $transactionData = [
        'gateway' => $gateway,
        'transaction_date' => $transaction_date,
        'account_number' => $account_number,
        'sub_account' => $sub_account,
        'amount_in' => $amount_in,
        'amount_out' => $amount_out,
        'accumulated' => $accumulated,
        'code' => $code,
        'transaction_content' => $transaction_content,
        'reference_number' => $reference_number,
        'description' => $description,
        'raw_data' => json_decode($raw_data, true) // Store as JSONB
    ];

    // Insert transaction into database
    $result = $db->callApi('sepay_transactions', 'POST', $transactionData);

    if (!$result || $result->code != 201) {
        throw new Exception('Failed to insert transaction: ' . ($result->message ?? 'Unknown error'));
    }

    $transaction_id = $result->response[0]['id'] ?? null;

    // Log webhook call
    $logData = [
        'request_body' => json_decode($raw_data, true),
        'request_headers' => getallheaders(),
        'ip_address' => $client_ip,
        'success' => true,
        'transaction_id' => $transaction_id
    ];

    $db->callApi('sepay_webhook_logs', 'POST', $logData);

    // ====================================================================
    // LOGIC XỬ LÝ GIAO DỊCH TỰ ĐỘNG (PHP)
    // 1. Chuẩn hóa nội dung (xóa ký tự đặc biệt, giữ lại a-z, 0-9)
    // 2. Chiến lược 1: Regex Neo Số Tiền (Chính xác cao cho format shoptoolnro{user}{amount})
    // 3. Chiến lược 2: Quét đơn chờ (Fallback)
    // ====================================================================

    $processed = false;
    $processMessage = 'Transaction stored';
    $matchedTopupId = null;

    if ($transaction_id && $amount_in > 0) {
        $desc = strtolower($transaction_content);
        $cleanDesc = preg_replace('/[^a-z0-9]/', '', $desc); // Ví dụ: shoptoolnroadmin10000
        $amountStr = strval(intval($amount_in)); // 10000

        $matchedTopup = null;
        $matchedUserId = null;

        // --- CHIẾN LƯỢC 1: Regex Neo Số Tiền ---
        $extractedUser = null;
        if (preg_match('/shoptoolnro([a-z0-9]+)' . $amountStr . '/', $cleanDesc, $matches)) {
            $extractedUser = $matches[1];
        }

        if ($extractedUser) {
            // Tìm user có username khớp
            $userRes = $db->callApi('users?username=ilike.' . $extractedUser, 'GET');
            if ($userRes && $userRes->code == 200 && !empty($userRes->response)) {
                $user = $userRes->response[0];

                // Tìm đơn nạp pending của user này với số tiền chính xác
                $queryParams = http_build_query([
                    'user_id' => 'eq.' . $user['id'],
                    'amount' => 'eq.' . $amount_in,
                    'status' => 'eq.pending',
                    'limit' => 1
                ]);
                $topupRes = $db->callApi('topup_requests?' . $queryParams, 'GET');

                if ($topupRes && $topupRes->code == 200 && !empty($topupRes->response)) {
                    $matchedTopup = $topupRes->response[0];
                    $matchedUserId = $user['id'];
                    $processMessage = "Match Strategy 1: Username '$extractedUser'";
                }
            }
        }

        // --- CHIẾN LƯỢC 2: Quét đơn chờ (Fallback) ---
        if (!$matchedTopup) {
            $queryParams = http_build_query([
                'amount' => 'eq.' . $amount_in,
                'status' => 'eq.pending',
                'order' => 'created_at.asc'
            ]);
            $topupsResult = $db->callApi('topup_requests?' . $queryParams, 'GET');

            if ($topupsResult && $topupsResult->code == 200 && !empty($topupsResult->response)) {
                foreach ($topupsResult->response as $topup) {
                    $userRes = $db->callApi('users?id=eq.' . $topup['user_id'] . '&select=username', 'GET');
                    if ($userRes && $userRes->code == 200 && !empty($userRes->response)) {
                        $username = strtolower($userRes->response[0]['username']);
                        $simpleUsername = preg_replace('/[^a-z0-9]/', '', $username);

                        $targetStrict = 'shoptoolnro' . $simpleUsername . $amountStr;
                        $targetLoose = 'shoptoolnro' . $username;

                        if (strpos($cleanDesc, $targetStrict) !== false || strpos($desc, $targetLoose) !== false) {
                            $matchedTopup = $topup;
                            $matchedUserId = $topup['user_id'];
                            $processMessage = "Match Strategy 2: Pending Scan for '$username'";
                            break;
                        }
                    }
                }
            }
        }

        // --- XỬ LÝ KẾT QUẢ ---
        if ($matchedTopup) {
            // 1. Update user balance
            $userRes = $db->callApi('users?id=eq.' . $matchedUserId, 'GET');
            if ($userRes && $userRes->code == 200) {
                $currentBalance = $userRes->response[0]['balance'];
                $newBalance = $currentBalance + $amount_in;

                $db->callApi('users?id=eq.' . $matchedUserId, 'PATCH', ['balance' => $newBalance]);

                // 2. Approve topup
                $db->callApi('topup_requests?id=eq.' . $matchedTopup['id'], 'PATCH', [
                    'status' => 'approved',
                    'approved_at' => date('Y-m-d H:i:s'),
                    'description' => $matchedTopup['description'] . ' [Auto SePay: ' . $transaction_id . ']',
                ]);

                // 3. Update transaction as processed
                $db->callApi('sepay_transactions?id=eq.' . $transaction_id, 'PATCH', [
                    'processed' => true,
                    'matched_topup_id' => $matchedTopup['id'],
                    'matched_user_id' => $matchedUserId,
                    'processed_at' => date('Y-m-d H:i:s')
                ]);

                $processed = true;
                $processMessage .= " -> Auto-approved topup #" . $matchedTopup['id'];
                $matchedTopupId = $matchedTopup['id'];
            }
        } else {
            $processMessage = "No matching pending topup found for format in desc: $cleanDesc";
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $processMessage,
        'transaction_id' => $transaction_id,
        'processed' => $processed,
        'topup_id' => $matchedTopupId
    ]);

} catch (Exception $e) {
    // Log error
    if (isset($db)) {
        $logData = [
            'request_body' => json_decode($raw_data ?? '{}', true),
            'request_headers' => getallheaders(),
            'ip_address' => $client_ip,
            'success' => false,
            'error_message' => $e->getMessage()
        ];

        $db->callApi('sepay_webhook_logs', 'POST', $logData);
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>