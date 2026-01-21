<?php
// delete_product.php - Admin delete product (JSON only responses)
if (ob_get_level()) { @ob_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized - Admin only']));
}

require_once '../../config/database.php';
require_once '../../src/classes/Product.php';
require_once '../../src/classes/ErrorLogger.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$errorLogger = new ErrorLogger();

$rawInput = file_get_contents("php://input");
error_log("Delete request: " . $rawInput);

$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]));
}

if (empty($data['product_id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Missing product_id']));
}

try {
    $productClass = new Product($db);
    $productId = (int)$data['product_id'];

    error_log("Deleting product ID: " . $productId);

    if ($productClass->deleteProduct($productId)) {
        error_log("Product ID: " . $productId . " deleted successfully");
        exit(json_encode(['success' => true, 'message' => 'Sản phẩm đã được xóa']));
    } else {
        throw new Exception('Không thể xóa sản phẩm');
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log('Product delete exception: ' . $e->getMessage());
    $errorLogger->logError('product', 'delete', $e->getMessage(), $e->getTraceAsString(), $data ?? []);
    exit(json_encode(['success' => false, 'message' => 'Lỗi xóa sản phẩm: ' . $e->getMessage()]));
}
?>