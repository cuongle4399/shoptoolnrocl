<?php
// delete_product.php - Admin delete product (JSON only responses)
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
session_start();

// Function to delete image from Supabase Storage
function deleteSupabaseImage($imageUrl) {
    if (empty($imageUrl)) return true;
    
    try {
        $supabaseUrl = SUPABASE_URL;
        $bucket = SUPABASE_BUCKET;
        $jwtSecret = getenv('JWT_SECRET') ?: JWT_SECRET;
        
        // Extract filename from URL
        if (strpos($imageUrl, '/storage/v1/object/public/' . $bucket . '/') !== false) {
            $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
        } else {
            error_log("Not a Supabase URL, skipping: $imageUrl");
            return true; // Not a Supabase URL, skip
        }
        
        // Create JWT token
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'role' => 'service_role',
            'iss' => 'supabase',
            'iat' => time(),
            'exp' => time() + 3600
        ]);
        $header64 = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', "$header64.$payload64", $jwtSecret, true);
        $signature64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $jwtToken = "$header64.$payload64.$signature64";
        
        // Delete file from Supabase Storage
        $deleteUrl = $supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $filename;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $deleteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $jwtToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Accept 200, 204, or 404 (not found) as success
        if ($httpCode === 200 || $httpCode === 204 || $httpCode === 404) {
            error_log("Deleted image $filename from Supabase Storage: HTTP $httpCode");
            return true;
        } else {
            error_log("Failed to delete image $filename: HTTP $httpCode - $response");
            return true; // Return true anyway to not block product deletion
        }
    } catch (Exception $e) {
        error_log("Exception deleting image: " . $e->getMessage());
        return true; // Return true to not block product deletion
    }
}

// Initialize response array
$response = ['success' => false, 'message' => 'Unknown error'];
http_response_code(500);

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(401);
        $response = ['success' => false, 'message' => 'Unauthorized - Admin only'];
        throw new Exception('Unauthorized');
    }

    require_once '../../config/database.php';
    require_once '../../config/constants.php';
    require_once '../../src/classes/Product.php';
    require_once '../../src/classes/ErrorLogger.php';

    $database = new Database();
    $db = $database->connect();

    if (!$db) {
        http_response_code(500);
        $response = ['success' => false, 'message' => 'Database connection failed'];
        throw new Exception('Database connection failed');
    }

    $errorLogger = new ErrorLogger();

    $rawInput = file_get_contents("php://input");
    error_log("Delete request: " . $rawInput);

    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        $response = ['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()];
        throw new Exception('Invalid JSON');
    }

    if (empty($data['product_id'])) {
        http_response_code(400);
        $response = ['success' => false, 'message' => 'Missing product_id'];
        throw new Exception('Missing product_id');
    }

    $productClass = new Product($db);
    $productId = (int)$data['product_id'];

    error_log("Deleting product ID: " . $productId);
    
    // Get product details first to extract image URLs using REST API
    $endpoint = "products?id=eq." . $productId . "&limit=1";
    $apiResult = $db->callApi($endpoint, 'GET');
    
    if ($apiResult && $apiResult->code == 200 && !empty($apiResult->response)) {
        $product = $apiResult->response[0];
        
        // Try to delete product images from Supabase Storage (but don't fail if images don't exist)
        if (!empty($product['card_image_url'])) {
            deleteSupabaseImage($product['card_image_url']);
        }
        if (!empty($product['demo_image_url'])) {
            deleteSupabaseImage($product['demo_image_url']);
        }
    } else {
        error_log("Product not found or API error: " . json_encode($apiResult));
    }

    // Delete product from database
    if ($productClass->deleteProduct($productId)) {
        error_log("Product ID: " . $productId . " deleted successfully");
        http_response_code(200);
        $response = ['success' => true, 'message' => 'Sản phẩm và ảnh đã được xóa'];
    } else {
        http_response_code(500);
        $response = ['success' => false, 'message' => 'Không thể xóa sản phẩm'];
        throw new Exception('Failed to delete product');
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log('Product delete exception: ' . $e->getMessage());
    if (isset($errorLogger)) {
        $errorLogger->logError('product', 'delete', $e->getMessage(), $e->getTraceAsString(), $data ?? []);
    }
    $response = ['success' => false, 'message' => 'Lỗi xóa sản phẩm: ' . $e->getMessage()];
}

// Always output JSON response
echo json_encode($response);
exit;
?>