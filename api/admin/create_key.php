<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// This endpoint has been disabled
// Key creation/editing is no longer allowed through the API
// Keys are managed through the Supabase database directly
http_response_code(403);
echo json_encode([
    'success' => false,
    'message' => 'Key creation endpoint has been disabled. Keys can only be managed through the admin panel.'
]);
?>

