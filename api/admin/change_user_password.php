<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Phương thức không được hỗ trợ');
}

// Check if PDO connection is available
if (!isset($pdo) || $pdo === null) {
    response('error', 'Lỗi kết nối cơ sở dữ liệu');
}

$auth = requireRole('admin');
$admin_id = $auth['user_id'];
$admin_name = $auth['username'];

$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = $input['user_id'] ?? null;
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

// Validate input
if (!$target_user_id || !$new_password || !$confirm_password) {
    response('error', 'Thiếu mã người dùng hoặc mật khẩu');
}

if (strlen($new_password) < 6) {
    response('error', 'Mật khẩu mới phải tối thiểu 6 ký tự');
}

if ($new_password !== $confirm_password) {
    response('error', 'Mật khẩu nhập lại không khớp');
}

try {
    // Get target user
    $stmt = $pdo->prepare("SELECT id, username FROM public.users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        response('error', 'Không tìm thấy người dùng');
    }

    // Hash and update password
    $new_hash = $new_password;
    $updateStmt = $pdo->prepare("UPDATE public.users SET password_ = ? WHERE id = ?");
    $updateStmt->execute([$new_hash, $target_user_id]);

    $details = "Password changed by admin {$admin_name} for user {$user['username']}";
    logAudit($pdo, 'ADMIN_PASSWORD_CHANGE', $admin_name, $details, 'success');

    response('success', 'Đổi mật khẩu người dùng thành công', [
        'user_id' => $target_user_id,
        'username' => $user['username']
    ]);

} catch (Exception $e) {
    logAudit($pdo, 'ADMIN_PASSWORD_CHANGE_ERROR', $admin_name, $e->getMessage(), 'error');
    response('error', 'Failed to change user password: ' . $e->getMessage());
}
?>