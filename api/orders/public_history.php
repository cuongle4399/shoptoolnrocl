<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/classes/Order.php';
require_once __DIR__ . '/../../src/classes/User.php';
require_once __DIR__ . '/../../src/classes/Product.php';

try {
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    $orderClass = new Order($db);
    $userClass = new User($db);
    $productClass = new Product($db);
    
    // Lấy 10 đơn hàng đã hoàn thành gần nhất
    $endpoint = "orders?completed_at=not.is.null&order=created_at.desc&limit=10";
    $result = $db->callApi($endpoint, 'GET');
    
    if (!$result || $result->code != 200) {
        throw new Exception('Failed to fetch orders');
    }
    
    $orders = $result->response ?? [];
    $publicOrders = [];
    
    // Debug log
    error_log("Total orders from Supabase: " . count($orders));
    
    foreach ($orders as $order) {
        if (empty($order['id'])) continue;
        
        // Debug log từng order
        error_log("Processing order ID: " . $order['id'] . ", user_id: " . ($order['user_id'] ?? 'null') . ", product_id: " . ($order['product_id'] ?? 'null'));
        
        // Lấy thông tin user và product
        $user = $userClass->getUserById($order['user_id']);
        $product = $productClass->getProductById($order['product_id'], false);
        
        if (!$user || !$product) {
            error_log("Skipping order " . $order['id'] . " - User or Product not found");
            continue;
        }
        
        error_log("Order " . $order['id'] . " - User: " . $user['username'] . ", Product: " . $product['name']);
        
        // Ẩn tên người dùng (chỉ hiển thị ký tự đầu và cuối)
        $username = $user['username'];
        $maskedUsername = mb_substr($username, 0, 1) . str_repeat('*', max(3, mb_strlen($username) - 2)) . mb_substr($username, -1);
        
        // Format thời gian
        $createdAt = $order['created_at'];
        $timeAgo = '';
        
        try {
            $datetime = new DateTime($createdAt);
            $now = new DateTime();
            $interval = $now->diff($datetime);
            
            if ($interval->days > 0) {
                $timeAgo = $interval->days . ' ngày trước';
            } elseif ($interval->h > 0) {
                $timeAgo = $interval->h . ' giờ trước';
            } elseif ($interval->i > 0) {
                $timeAgo = $interval->i . ' phút trước';
            } else {
                $timeAgo = 'Vừa xong';
            }
        } catch (Exception $e) {
            $timeAgo = 'Gần đây';
        }
        
        $publicOrders[] = [
            'username' => $maskedUsername,
            'product_name' => $product['name'],
            'time_ago' => $timeAgo,
            'created_at' => $createdAt
        ];
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $publicOrders
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi server: ' . $e->getMessage()
    ]);
}
