<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    response('error', 'Method not allowed');
}

$auth = authenticate();
$user_id = $auth['user_id'];

try {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name
        FROM public.orders o
        JOIN public.products p ON o.product_id = p.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $orders = $stmt->fetchAll();
    
    // Đếm tổng
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM public.orders WHERE user_id = ?");
    $countStmt->execute([$user_id]);
    $total = $countStmt->fetch()['total'];
    
    response('success', 'Orders fetched', [
        'orders' => $orders,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    response('error', 'Failed to fetch orders');
}
?>
