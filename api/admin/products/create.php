<?php
// create.php - FIXED (Removed status completely)
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Method not allowed');
}

$auth = requireRole(ROLE_ADMIN);

$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? '';
$software_link = $_POST['software_link'] ?? '';
$tutorial_video_url = $_POST['tutorial_video_url'] ?? '';
// Normalize common YouTube URL formats to embeddable URL
$tutorial_video_url = normalizeYoutubeEmbedUrl($tutorial_video_url);

if (!$name) {
    response('error', 'Product name is required');
}

try {
    $image_url = null;
    $demo_image_url = null;
    
    // Upload ảnh card sản phẩm
    if (isset($_FILES['image'])) {
        $upload = uploadFile($_FILES['image']);
        if ($upload['status']) {
            $image_url = $upload['url'];
        }
    }
    
    // Upload ảnh demo
    if (isset($_FILES['demo_image'])) {
        $upload = uploadFile($_FILES['demo_image']);
        if ($upload['status']) {
            $demo_image_url = $upload['url'];
        }
    }
    
    $database = new Database(); 
    $db = $database->connect();

    // CRITICAL: Removed 'status' field - products table does NOT have this column
    // According to schema, products table has these columns:
    // id, name, description, image_url, demo_image_url, tutorial_video_url, 
    // software_link, category, created_at, updated_at, created_by_admin
    $payload = [
        'name' => $name,
        'description' => $description,
        'image_url' => $image_url,
        'demo_image_url' => $demo_image_url,
        'category' => $category,
        'software_link' => $software_link,
        'tutorial_video_url' => $tutorial_video_url,
        'created_by_admin' => $auth['user_id'] ?? null
        // Do NOT set 'created_at' - database has DEFAULT now()
        // Do NOT set 'status' - column does not exist!
    ];

    error_log("Creating product with payload: " . json_encode($payload));

    $result = $db->callApi('products', 'POST', $payload);
    
    if (!($result && ($result->code == 201 || $result->code == 200) && !empty($result->response))) {
        $errMsg = 'API Failure: HTTP ' . ($result->code ?? 'unknown');
        if (isset($result->response)) {
            $errMsg .= ' Response: ' . json_encode($result->response);
        }
        error_log("Product creation failed: " . $errMsg);
        throw new Exception($errMsg);
    }

    $product = $result->response[0];
    
    // Log audit
    $db->callApi('audit_logs', 'POST', [
        'action' => 'PRODUCT_CREATED', 
        'username' => $auth['username'], 
        'details' => "Product: {$product['name']}", 
        'status' => 'success', 
        'created_at' => date('c')
    ]);

    response('success', 'Product created', ['product' => $product]);
    
} catch (Exception $e) {
    logAudit('PRODUCT_CREATE_ERROR', $auth['username'] ?? '', $e->getMessage(), 'error');
    error_log('PRODUCT_CREATE_ERROR: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    response('error', 'Failed to create product: ' . $e->getMessage());
}
?>