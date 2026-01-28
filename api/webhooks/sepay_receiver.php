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

    // The trigger will automatically process the transaction
    // But we can also call the function manually for immediate response
    if ($transaction_id && $amount_in > 0) {
        $processResult = $db->callApi(
            'rpc/process_sepay_transaction',
            'POST',
            ['p_transaction_id' => $transaction_id]
        );

        if ($processResult && $processResult->code == 200) {
            $processData = $processResult->response;

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Transaction received and processed',
                'transaction_id' => $transaction_id,
                'processing_result' => $processData
            ]);
        } else {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Transaction received (processing pending)',
                'transaction_id' => $transaction_id
            ]);
        }
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Transaction received',
            'transaction_id' => $transaction_id
        ]);
    }

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