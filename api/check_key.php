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

    // Call Supabase check_license function
    // Signature: check_license(p_product_id, p_license_key, p_hwid, p_bind_hwid)
    $endpoint = 'rest/v1/rpc/check_license';
    $payload = [
        'p_product_id' => $product_id,
        'p_license_key' => $license_key,
        'p_hwid' => $hwid,
        'p_bind_hwid' => $bind_hwid
    ];

    $result = $db->callApi($endpoint, 'POST', $payload);

    if (!$result) {
        throw new Exception('API call failed');
    }

    // check_license returns array of records
    if ($result->code != 200 || empty($result->response)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'valid' => false,
            'message' => 'License verification failed',
            'error_code' => 'VERIFICATION_FAILED'
        ]);
        exit;
    }

    if (!is_array($result->response) || empty($result->response)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'valid' => false,
            'message' => 'Invalid response from server',
            'error_code' => 'INVALID_RESPONSE'
        ]);
        exit;
    }

    $response = $result->response[0];

    // Prepare response
    $valid = (bool)($response['valid'] ?? false);
    $message = $response['message'] ?? 'Unknown error';

    http_response_code($valid ? 200 : 401);
    echo json_encode([
        'success' => $valid,
        'valid' => $valid,
        'message' => $message,
        'error_code' => $valid ? 'SUCCESS' : 'INVALID_KEY',
        'data' => [
            'infokey_id' => $response['infokey_id'] ?? null,
            'product_id' => $response['product_id'] ?? $product_id,
            'license_key' => $response['license_key'] ?? $license_key,
            'hwid' => $response['hwid'] ?? null,
            'status' => $response['status'] ?? 'inactive',
            'expires_at' => $response['expires_at'] ?? null
        ]
    ]);

} catch (Exception $e) {
    error_log('check_key.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_code' => 'SERVER_ERROR'
    ]);
}
?>
