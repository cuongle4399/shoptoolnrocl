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

$auth = authenticate();
$current_user_id = $auth['user_id'];
$current_user_role = $auth['role'];

$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = $input['user_id'] ?? $current_user_id;
$old_password = $input['old_password'] ?? '';
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

// Validate input
if (!$new_password || !$confirm_password) {
    response('error', 'All fields required');
}

if (strlen($new_password) < 6) {
    response('error', 'New password at least 6 characters');
}

if ($new_password !== $confirm_password) {
    response('error', 'New passwords do not match');
}

// Determine if this is self change or admin change
$is_self_change = ($target_user_id == $current_user_id);
$is_admin_changing_other = ($current_user_role === 'admin' && !$is_self_change);

// Self change requires old password
if ($is_self_change && !$old_password) {
    response('error', 'Old password required');
}

try {
    // Get target user info
    $stmt = $pdo->prepare("SELECT id, password_, username FROM public.users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        response('error', 'User not found');
    }
    
    // For self change, verify old password
    if ($is_self_change) {
        if (!verifyPassword($old_password, $user['password_'])) {
            logAudit($pdo, 'PASSWORD_CHANGE_FAILED', $auth['username'], "Wrong old password for user {$user['username']}", 'failed');
            response('error', 'Wrong old password');
        }
    } else if (!$is_admin_changing_other) {
        // Only self or admin can change password
        response('error', 'Forbidden');
    }
    
    $new_hash = hashPassword($new_password);
    $updateStmt = $pdo->prepare("UPDATE public.users SET password_ = ? WHERE id = ?");
    $updateStmt->execute([$new_hash, $target_user_id]);
    
    $details = $is_self_change 
        ? 'Password changed by user' 
        : "Password changed by admin {$auth['username']} for user {$user['username']}";
    
    logAudit($pdo, 'PASSWORD_CHANGED', $auth['username'], $details, 'success');
    
    response('success', 'Password changed successfully');
    
} catch (Exception $e) {
    logAudit($pdo, 'PASSWORD_CHANGE_ERROR', $auth['username'] ?? '', $e->getMessage(), 'error');
    response('error', 'Failed to change password: ' . $e->getMessage());
}
?>
