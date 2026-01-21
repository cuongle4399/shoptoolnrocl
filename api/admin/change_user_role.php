<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Method not allowed');
}

// Check if PDO connection is available
if (!isset($pdo) || $pdo === null) {
    response('error', 'Database connection error');
}

$auth = requireRole('admin');
$current_admin_id = $auth['user_id'];
$current_admin_name = $auth['username'];

$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = $input['user_id'] ?? null;
$new_role = $input['role'] ?? null;

if (!$target_user_id || !$new_role) {
    response('error', 'User ID and role are required');
}

// Ngăn admin tự đổi quyền admin của chính mình
if ($target_user_id == $current_admin_id) {
    logAudit($pdo, 'ROLE_CHANGE_FAILED', $current_admin_name, "Attempt to change own role", 'failed');
    response('error', 'Cannot change your own admin privileges');
}

// Validate role
$valid_roles = ['user', 'admin'];
if (!in_array($new_role, $valid_roles)) {
    response('error', 'Invalid role');
}

try {
    // Get target user info
    $stmt = $pdo->prepare("SELECT id, username, role FROM public.users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        response('error', 'User not found');
    }
    
    $old_role = $user['role'];
    
    // Update role
    $updateStmt = $pdo->prepare("UPDATE public.users SET role = ? WHERE id = ?");
    $updateStmt->execute([$new_role, $target_user_id]);
    
    $details = "Role changed from '{$old_role}' to '{$new_role}' for user {$user['username']}";
    logAudit($pdo, 'ROLE_CHANGED', $current_admin_name, $details, 'success');
    
    response('success', 'User role updated successfully', [
        'user_id' => $target_user_id,
        'old_role' => $old_role,
        'new_role' => $new_role
    ]);
    
} catch (Exception $e) {
    logAudit($pdo, 'ROLE_CHANGE_ERROR', $current_admin_name, $e->getMessage(), 'error');
    response('error', 'Failed to update user role: ' . $e->getMessage());
}
?>
