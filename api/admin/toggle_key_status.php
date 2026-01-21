<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$key_id = isset($data['key_id']) ? (int)$data['key_id'] : null;
$status = $data['status'] ?? null;

if (!$key_id || !in_array($status, ['active', 'inactive'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid key_id or status']);
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

    $result = $db->callApi($endpoint, 'PATCH', $payload);

    // Check if API call was successful (200-299 range for success)
    if (!$result || !($result->code >= 200 && $result->code < 300)) {
        error_log('toggle_key_status.php API response: ' . json_encode([
            'code' => $result->code ?? 'null',
            'response' => $result->response ?? 'null'
        ]));
        throw new Exception('Failed to update key status (HTTP ' . ($result->code ?? 'unknown') . ')');
    }

    // Log the action
    $username = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'unknown';
    error_log("Admin {$username} toggled key {$key_id} to {$status}");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Key status updated successfully',
        'status' => $status
    ]);

} catch (Exception $e) {
    error_log('toggle_key_status.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
