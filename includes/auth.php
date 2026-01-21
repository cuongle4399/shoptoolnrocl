<?php
require_once __DIR__ . '/functions.php';

function authenticate() {
    $token = getAuthToken();
    
    if (!$token) {
        response('error', 'Unauthorized', null);
    }
    
    $payload = verifyJWT($token);
    if (!$payload) {
        response('error', 'Invalid or expired token', null);
    }
    
    return $payload;
}

function requireRole($required_role) {
    $auth = authenticate();
    
    if ($auth['role'] !== $required_role && $auth['role'] !== 'admin') {
        response('error', 'Forbidden', null);
    }
    
    return $auth;
}
?>
