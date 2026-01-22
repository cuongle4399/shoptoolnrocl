<?php
// create_product.php - Admin create product (JSON only responses)
if (ob_get_level()) { @ob_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    @ob_end_clean();
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Không có quyền (chỉ admin)']));
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../src/classes/Product.php';
require_once '../../src/classes/ProductDuration.php';
require_once '../../src/classes/ErrorLogger.php';

try {
    $database = new Database();
    $db = $database->connect();

    if (!$db) {
        throw new Exception('Kết nối cơ sở dữ liệu thất bại');
    }

    $errorLogger = new ErrorLogger();

    // Read JSON input from request body
    $rawInput = file_get_contents("php://input");
    error_log("Raw input: " . substr($rawInput, 0, 500));

    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON không hợp lệ: ' . json_last_error_msg());
    }

    // Require essential product fields
    if (empty($data['name'])) {
        throw new Exception('Thiếu trường bắt buộc: name');
    }
    if (empty($data['description'])) {
        throw new Exception('Thiếu trường bắt buộc: description');
    }

    // Ensure durations provided
    if (empty($data['durations']) || !is_array($data['durations']) || count($data['durations']) === 0) {
        throw new Exception('Vui lòng thêm ít nhất 1 thời hạn bản (durations)');
    }

    $productClass = new Product($db);

    // Handle demo_image_url
    $demoImageUrl = $data['demo_image_url'] ?? null;
    if ($demoImageUrl && is_string($demoImageUrl)) {
        $parsed = json_decode($demoImageUrl, true);
        if ($parsed && is_array($parsed)) {
            $demoImageUrl = json_encode($parsed);
        }
    }

    // Remove 'status' field - not stored in products table per schema
    $productData = [
        'name' => trim($data['name']),
        'description' => trim($data['description']),
        'image_url' => $data['image_url'] ?? null,
        'demo_image_url' => $demoImageUrl,
        'tutorial_video_url' => $data['tutorial_video_url'] ?? null,
        'software_link' => $data['software_link'] ?? null,
        'category' => $data['category'] ?? null
    ];
    
    // Remove null values to avoid sending them to the database
    $productData = array_filter($productData, function($v) { return $v !== null; });

    error_log("Creating product with data: " . json_encode($productData));

    $createdProduct = $productClass->createProduct($productData);
    
    if (!$createdProduct) {
        throw new Exception('Không thể tạo sản phẩm');
    }

    // Insert durations
    if (!empty($data['durations']) && is_array($data['durations'])) {
        $durClass = new ProductDuration($db);
        
        foreach ($data['durations'] as $d) {
            $toInsert = [
                'product_id' => $createdProduct['id'],
                'duration_days' => isset($d['duration_days']) ? (is_null($d['duration_days']) ? null : (int)$d['duration_days']) : null,
                'duration_label' => $d['duration_label'] ?? $d['label'] ?? null,
                'price' => (float)($d['price'] ?? 0),
                'status' => 'active'
            ];
            
            try {
                $durResult = $durClass->create($toInsert);
                if (!$durResult) {
                    throw new Exception('Không thể tạo thời hạn');
                }
            } catch (Exception $e) {
                error_log('Duration creation error: ' . $e->getMessage());
                $errorLogger->logError('product', 'duration_creation', $e->getMessage(), $e->getTraceAsString(), $toInsert);
                throw $e;
            }
        }
    }

    @ob_end_clean();
    exit(json_encode([
        'success' => true, 
        'message' => 'Sản phẩm đã được tạo',
        'product' => $createdProduct
    ]));

} catch (Exception $e) {
    @ob_end_clean();
    http_response_code(400);
    error_log('Product creation exception: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    exit(json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]));
}
?>
