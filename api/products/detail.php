<?php
if (ob_get_level()) { @ob_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../src/classes/Product.php';
require_once __DIR__ . '/../../src/classes/ProductDuration.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$product_id = (int)($_GET['id'] ?? 0);

if (!$product_id) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Product ID required']));
}

try {
    $database = new Database();
    $db = $database->connect();

    // Fetch product by id (only active products)
    $productClass = new Product($db);
    $product = $productClass->getProductById($product_id, true);
    
    if (!$product) {
        http_response_code(404);
        exit(json_encode(['status' => 'error', 'message' => 'Product not found']));
    }

    // Fetch durations
    $durationClass = new ProductDuration($db);
    $durations = $durationClass->getDurationsByProductId($product_id, true);

    http_response_code(200);
    exit(json_encode([
        'status' => 'success',
        'message' => 'Product details',
        'data' => [
            'product' => $product,
            'durations' => $durations
        ]
    ]));

} catch (Exception $e) {
    error_log("Product detail error: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Failed to fetch product']));
}
