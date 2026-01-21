<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    response('error', 'Method not allowed');
}

$auth = requireRole(ROLE_ADMIN);

try {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT id, username, email, balance, role, status, created_at, last_login
        FROM public.users
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $users = $stmt->fetchAll();
    
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM public.users");
    $total = $countStmt->fetch()['total'];
    
    response('success', 'Users fetched', [
        'users' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    response('error', 'Failed to fetch users');
}
?>
