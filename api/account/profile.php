<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = authenticate();
$user_id = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user profile info
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, email, balance, role, status, created_at, updated_at
            FROM public.users WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        response('success', 'Profile fetched', ['user' => $user]);
    } catch (Exception $e) {
        response('error', 'Failed to fetch profile');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Change password
    $data = json_decode(file_get_contents('php://input'), true);
    $old_password = $data['old_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        http_response_code(400);
        response('error', 'All fields are required');
        exit;
    }

    if ($new_password !== $confirm_password) {
        http_response_code(400);
        response('error', 'New passwords do not match');
        exit;
    }

    if (strlen($new_password) < 6) {
        http_response_code(400);
        response('error', 'Password must be at least 6 characters');
        exit;
    }

    try {
        // Verify old password
        $stmt = $pdo->prepare("SELECT password_ FROM public.users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($old_password, $user['password_'])) {
            http_response_code(401);
            response('error', 'Current password is incorrect');
            exit;
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            UPDATE public.users 
            SET password_ = ?, updated_at = now()
            WHERE id = ?
        ");
        $stmt->execute([$hashed_password, $user_id]);

        response('success', 'Password changed successfully');
    } catch (Exception $e) {
        error_log('Change password error: ' . $e->getMessage());
        http_response_code(500);
        response('error', 'Failed to change password');
    }
} else {
    http_response_code(405);
    response('error', 'Method not allowed');
}
?>
