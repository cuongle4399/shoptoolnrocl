<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/classes/User.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Method not allowed');
}

// Authenticate admin
$auth = requireRole('admin');
$current_admin_id = $auth['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = $input['user_id'] ?? null;
$new_role = $input['role'] ?? null;

if (!$target_user_id || !$new_role) {
    response('error', 'User ID and role are required');
}

// Ngăn admin tự đổi quyền của chính mình
if ((int)$target_user_id === (int)$current_admin_id) {
    response('error', 'Không thể thay đổi quyền của chính bạn');
}

// Validate role
$valid_roles = ['customer', 'admin'];
if (!in_array($new_role, $valid_roles)) {
    response('error', 'Invalid role');
}

try {
    // Initialize database and user class
    $database = new Database();
    $db = $database->connect();
    $userClass = new User($db);
    
    // Get target user info
    $user = $userClass->getUserById($target_user_id);
    
    if (!$user) {
        response('error', 'User not found');
    }
    
    $old_role = $user['role'] ?? 'customer';
    
    // Update role
    $success = $userClass->changeUserRole($target_user_id, $new_role);
    
    if ($success) {
        response('success', 'User role updated successfully', [
            'user_id' => $target_user_id,
            'old_role' => $old_role,
            'new_role' => $new_role
        ]);
    } else {
        response('error', 'Failed to update user role');
    }
    
} catch (Exception $e) {
    response('error', 'Failed to update user role: ' . $e->getMessage());
}
?>
