<?php
// ====================================================================
// API ENDPOINT: check_key.php - Using Supabase check_license Function
// Endpoint to verify license keys with HWID binding
// ====================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Only POST method is allowed',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

$product_id = isset($input['product_id']) ? (int)$input['product_id'] : null;
$license_key = $input['license_key'] ?? null;
$hwid = $input['hwid'] ?? null;
$bind_hwid = isset($input['bind_hwid']) ? (bool)$input['bind_hwid'] : true;

// Validate input
if (!$product_id || !$license_key || !$hwid) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Missing required fields: product_id, license_key, hwid',
        'error_code' => 'MISSING_PARAMS'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Call Supabase check_license RPC function
    // Signature: check_license(p_product_id bigint, p_license_key text, p_hwid text, p_bind_hwid boolean)
    // Returns: TABLE(valid boolean, infokey_id bigint, product_id bigint, license_key text, hwid text, status text, expires_at timestamptz, message text)
    // NOTE: callApi() already adds "rest/v1/" prefix, so we only need "rpc/check_license"
    $endpoint = 'rpc/check_license';
    $payload = [
        'p_product_id' => (int)$product_id,
        'p_license_key' => (string)$license_key,
        'p_hwid' => (string)$hwid,
        'p_bind_hwid' => (bool)$bind_hwid
    ];

    $result = $db->callApi($endpoint, 'POST', $payload);

    // Debug logging (remove in production)
    error_log("check_key.php - API call to: $endpoint");
    error_log("check_key.php - Payload: " . json_encode($payload));
    error_log("check_key.php - Result code: " . ($result->code ?? 'null'));
    error_log("check_key.php - Response: " . json_encode($result->response ?? null));

    if (!$result) {
        throw new Exception('API call failed - no result returned');
    }

    // Handle Supabase RPC error responses
    if ($result->code >= 400) {
        $errorMsg = 'License verification failed';
        if (is_array($result->response) && isset($result->response['message'])) {
            $errorMsg = $result->response['message'];
        } elseif (is_object($result->response) && isset($result->response->message)) {
            $errorMsg = $result->response->message;
        }
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'valid' => false,
            'message' => $errorMsg,
            'error_code' => 'VERIFICATION_FAILED'
        ]);
        exit;
    }

    // check_license returns array of records (even if just 1 row)
    $response = null;
    
    if (is_array($result->response)) {
        if (isset($result->response[0])) {
            // Array of objects/arrays
            $response = is_object($result->response[0]) 
                ? (array)$result->response[0] 
                : $result->response[0];
        } elseif (isset($result->response['valid'])) {
            // Single object returned as array
            $response = $result->response;
        }
    } elseif (is_object($result->response)) {
        $response = (array)$result->response;
    }

    if (empty($response)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'valid' => false,
            'message' => 'Invalid response from license check function',
            'error_code' => 'INVALID_RESPONSE'
        ]);
        exit;
    }

    // Extract response fields
    $valid = (bool)($response['valid'] ?? false);
    $message = $response['message'] ?? ($valid ? 'License valid' : 'License invalid');
    
    // Map error_code based on message
    $error_code = 'SUCCESS';
    if (!$valid) {
        $msgLower = strtolower($message);
        if (strpos($msgLower, 'not found') !== false) {
            $error_code = 'KEY_NOT_FOUND';
        } elseif (strpos($msgLower, 'expired') !== false) {
            $error_code = 'KEY_EXPIRED';
        } elseif (strpos($msgLower, 'hwid') !== false || strpos($msgLower, 'mismatch') !== false) {
            $error_code = 'HWID_MISMATCH';
        } elseif (strpos($msgLower, 'not active') !== false || strpos($msgLower, 'inactive') !== false) {
            $error_code = 'KEY_INACTIVE';
        } else {
            $error_code = 'INVALID_KEY';
        }
    }

    // ✅ user_id và username đã được trả về trực tiếp từ Supabase check_license function
    // (Không cần query thêm - đã JOIN trong SQL function)
    $user_id = $response['user_id'] ?? null;
    $username = $response['username'] ?? null;
    
    error_log("check_key.php - user_id: $user_id, username: $username");

    http_response_code($valid ? 200 : 401);
    echo json_encode([
        'success' => $valid,
        'valid' => $valid,
        'message' => $message,
        'error_code' => $error_code,
        'data' => [
            'infokey_id' => $response['infokey_id'] ?? null,
            'product_id' => $response['product_id'] ?? $product_id,
            'license_key' => $response['license_key'] ?? $license_key,
            'hwid' => $response['hwid'] ?? null,
            'status' => $response['status'] ?? 'inactive',
            'expires_at' => $response['expires_at'] ?? null,
            'user_id' => $user_id,
            'username' => $username
        ]
    ]);

} catch (Exception $e) {
    error_log('check_key.php EXCEPTION: ' . $e->getMessage());
    error_log('check_key.php TRACE: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_code' => 'SERVER_ERROR'
    ]);
}
?>
