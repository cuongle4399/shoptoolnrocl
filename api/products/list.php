<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../src/classes/Product.php';
require_once __DIR__ . '/../../src/classes/ProductDuration.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    response('error', 'Phương thức không được hỗ trợ');
}

try {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    // Initialize database
    $database = new Database();
    $db = $database->connect();
    
    // Get products
    $productClass = new Product($db);
    $products = $productClass->getAllProducts(true, $limit, $offset);
    
    // Get total count - fetch all to count (Supabase limitation)
    $allProducts = $productClass->getAllProducts(true, 0, 0);
    $total = count($allProducts);
    
    // Enrich each product with its durations
    $enrichedProducts = [];
    $durationClass = new ProductDuration($db);
    
    foreach ($products as $product) {
        $durations = $durationClass->getDurationsByProductId($product['id'], true);
        $product['durations'] = $durations;
        $enrichedProducts[] = $product;
    }
    
    response('success', 'Lấy danh sách sản phẩm thành công', [
        'products' => $enrichedProducts,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Product list error: " . $e->getMessage());
    response('error', 'Không thể tải danh sách sản phẩm');
}
?>
