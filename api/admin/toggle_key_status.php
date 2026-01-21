<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Authorization check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Admin access required',
        'debug' => [
            'session_exists' => isset($_SESSION['user_id']),
            'role' => $_SESSION['role'] ?? 'none'
        ]
    ]);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed. Use POST.',
        'debug' => ['method' => $_SERVER['REQUEST_METHOD']]
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$key_id = isset($data['key_id']) ? (int)$data['key_id'] : null;
$status = $data['status'] ?? null;

// Input validation
if (!$key_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Missing key_id parameter',
        'debug' => ['received' => $data]
    ]);
    exit;
}

if (!in_array($status, ['active', 'inactive'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid status. Must be "active" or "inactive"',
        'debug' => ['received_status' => $status]
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Call Supabase API to update key status
    $endpoint = "infokey?id=eq.{$key_id}";
    $payload = ['status' => $status];

    error_log("Updating key {$key_id} to status {$status}");
    $result = $db->callApi($endpoint, 'PATCH', $payload);

    // Check if API call was successful
    if (!$result || !($result->code >= 200 && $result->code < 300)) {
        $errorDetails = [
            'code' => $result->code ?? 'null',
            'response' => $result->response ?? 'null'
        ];
        error_log('toggle_key_status.php API response: ' . json_encode($errorDetails));
        throw new Exception('Failed to update key status (HTTP ' . ($result->code ?? 'unknown') . '). Check if key exists.');
    }

    // Log the action
    $username = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'unknown';
    error_log("Admin {$username} toggled key {$key_id} to {$status}");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Key status updated successfully',
        'data' => [
            'key_id' => $key_id,
            'new_status' => $status
        ]
    ]);

} catch (Exception $e) {
    error_log('toggle_key_status.php error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'key_id' => $key_id ?? 'not set',
            'status' => $status ?? 'not set'
        ]
    ]);
}
?>
