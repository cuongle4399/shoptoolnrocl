<?php
require_once __DIR__ . '/functions.php';

function authenticate() {
    $token = getAuthToken();
    
    if (!$token) {
        response('error', 'Không có quyền truy cập', null);
    }
    
    $payload = verifyJWT($token);
    if (!$payload) {
        response('error', 'Token không hợp lệ hoặc đã hết hạn', null);
    }
    
    return $payload;
}

function requireRole($required_role) {
    $auth = authenticate();
    
    if ($auth['role'] !== $required_role && $auth['role'] !== 'admin') {
        response('error', 'Không đủ quyền', null);
    }
    
    return $auth;
}
?>
