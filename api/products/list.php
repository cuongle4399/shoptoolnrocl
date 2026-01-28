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
    $page = (int) ($_GET['page'] ?? 1);
    $limit = 12;
    $offset = ($page - 1) * $limit;

    // Initialize database
    $database = new Database();
    $db = $database->connect();

    // Get products with total count from headers
    $endpoint = "products?order=created_at.desc&limit=$limit&offset=$offset";
    $result = $db->callApi($endpoint, 'GET');

    $products = $result->response ?? [];
    $total = $result->total ?? count($products);

    // Enrich with durations in a single call (avoid N+1)
    if (!empty($products)) {
        $productIds = array_map(fn($p) => $p['id'], $products);
        $idList = implode(',', $productIds);
        $durationEndpoint = "product_durations?product_id=in.($idList)&status=eq.active&order=duration_days.asc,price.asc";
        $durationResult = $db->callApi($durationEndpoint, 'GET');
        $allDurations = $durationResult->response ?? [];

        // Map durations to products
        $durationsMap = [];
        foreach ($allDurations as $d) {
            $durationsMap[$d['product_id']][] = $d;
        }

        foreach ($products as &$product) {
            $product['durations'] = $durationsMap[$product['id']] ?? [];
        }
    }

    response('success', 'Lấy danh sách sản phẩm thành công', [
        'products' => $products,
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