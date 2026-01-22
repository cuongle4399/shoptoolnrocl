<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền (admin)']);
    exit;
}

// This endpoint has been disabled
// Key creation/editing is no longer allowed through the API
// Keys are managed through the Supabase database directly
http_response_code(403);
echo json_encode([
    'success' => false,
    'message' => 'API tạo key đã bị tắt. Chỉ quản lý key qua trang quản trị.'
]);
?>

