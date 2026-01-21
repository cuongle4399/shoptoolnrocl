<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user, only in logs

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

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents("php://input"), true);

// Input validation
if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid user_id parameter',
        'debug' => ['received' => $data]
    ]);
    exit;
}

try {
    $user_id = (int)$data['user_id'];
    $endpoint = "users?id=eq." . $user_id;
    
    // Get current user status
    $get = $db->callApi($endpoint, 'GET');
    
    if (!$get || $get->code !== 200) {
        throw new Exception('Failed to fetch user data. API returned code: ' . ($get->code ?? 'none'));
    }
    
    if (empty($get->response)) {
        throw new Exception('User not found with ID: ' . $user_id);
    }

    $current = $get->response[0];
    $current_status = $current['status'] ?? 'active';
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';

    // Prevent admin from locking their own account
    if ($user_id === (int)$_SESSION['user_id'] && $new_status === 'inactive') {
        throw new Exception('Cannot lock your own account. Use another admin account to perform this action.');
    }

    // Update status
    $result = $db->callApi($endpoint, 'PATCH', ['status' => $new_status]);

    if (!$result || ($result->code !== 200 && $result->code !== 204)) {
        throw new Exception('Failed to update user status. API returned code: ' . ($result->code ?? 'none'));
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'User status updated successfully',
        'data' => [
            'user_id' => $user_id,
            'old_status' => $current_status,
            'new_status' => $new_status
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Toggle User Status Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>
