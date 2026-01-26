<?php
// update_product.php - Admin update product (JSON only responses)
if (ob_get_level()) {
    @ob_clean();
}
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    @ob_end_clean();
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
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
        throw new Exception('Database connection failed');
    }

    $product = new Product($db);
    $durClass = new ProductDuration($db);
    $errorLogger = new ErrorLogger();

    // Read JSON input from request body
    $rawInput = file_get_contents("php://input");
    error_log("Raw input: " . substr($rawInput, 0, 500));

    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    $id = (int) ($data['id'] ?? 0);
    if (!$id) {
        throw new Exception('Missing product id');
    }

    error_log("Updating product ID: " . $id);

    $productData = [
        'name' => $data['name'] ?? null,
        'description' => $data['description'] ?? null,
        'image_url' => $data['image_url'] ?? null,
        'demo_image_url' => isset($data['demo_image_url']) ? (is_array($data['demo_image_url']) ? json_encode($data['demo_image_url']) : $data['demo_image_url']) : null,
        'tutorial_video_url' => $data['tutorial_video_url'] ?? null,
        'software_link' => $data['software_link'] ?? null,
        'category' => $data['category'] ?? null
    ];

    // Remove null fields to avoid unnecessary updates
    $productData = array_filter($productData, function ($v) {
        return $v !== null; });

    error_log("Product data to update: " . json_encode($productData));

    // Only update if there's data to update
    if (!empty($productData)) {
        $updated = $product->updateProduct($id, $productData);
        if (!$updated) {
            throw new Exception('Cập nhật sản phẩm thất bại');
        }
    }

    // Handle durations - soft delete old and create new (to avoid FK constraints)
    if (isset($data['durations']) && is_array($data['durations'])) {
        // Use soft delete instead of hard delete because existing orders restrict deletion
        $durClass->softDeleteByProductId($id);

        foreach ($data['durations'] as $d) {
            // Skip invalid entries
            if (!isset($d['price']))
                continue;

            $toInsert = [
                'product_id' => $id,
                'duration_days' => isset($d['duration_days']) ? (is_null($d['duration_days']) ? null : (int) $d['duration_days']) : null,
                'duration_label' => $d['duration_label'] ?? $d['label'] ?? '', // Prevent null (NOT NULL constraint)
                'price' => (float) $d['price'],
                'status' => 'active'
            ];

            // Generate default label if empty
            if (empty($toInsert['duration_label'])) {
                if (is_null($toInsert['duration_days'])) {
                    $toInsert['duration_label'] = 'Vĩnh viễn';
                } else {
                    $toInsert['duration_label'] = $toInsert['duration_days'] . ' ngày';
                }
            }

            try {
                $durResult = $durClass->create($toInsert);
                if (!$durResult) {
                    throw new Exception('Failed to create duration: ' . json_encode($toInsert));
                }
            } catch (Exception $e) {
                error_log('Duration update error: ' . $e->getMessage());
                $errorLogger->logError('product', 'duration_update', $e->getMessage(), $e->getTraceAsString(), $toInsert);
                throw $e;
            }
        }
    }

    @ob_end_clean();
    exit(json_encode([
        'success' => true,
        'message' => 'Sản phẩm đã được cập nhật',
        'data' => ['id' => $id]
    ]));

} catch (Exception $e) {
    @ob_end_clean();
    http_response_code(400);
    error_log('Product update exception: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    exit(json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]));
}
?>