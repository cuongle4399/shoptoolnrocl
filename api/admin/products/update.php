<?php
// update.php - ALREADY CORRECT (no status references)
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Method not allowed');
}

$auth = requireRole(ROLE_ADMIN);

$product_id = (int)($_POST['product_id'] ?? 0);
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? '';
$software_link = $_POST['software_link'] ?? '';
$tutorial_video_url = $_POST['tutorial_video_url'] ?? '';
// Normalize common YouTube URL formats to embeddable URL
$tutorial_video_url = normalizeYoutubeEmbedUrl($tutorial_video_url);

if (!$product_id || !$name) {
    response('error', 'Product ID and name are required');
}

try {
    // Lấy thông tin hiện tại
    $stmt = $pdo->prepare("SELECT image_url, demo_image_url FROM public.products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        response('error', 'Product not found');
    }
    
    $image_url = $product['image_url'];
    $demo_image_url = $product['demo_image_url'];
    
    // Upload ảnh card mới nếu có
    if (isset($_FILES['image'])) {
        $upload = uploadFile($_FILES['image']);
        if ($upload['status']) {
            $image_url = $upload['url'];
        }
    }
    
    // Upload ảnh demo mới nếu có
    if (isset($_FILES['demo_image'])) {
        $upload = uploadFile($_FILES['demo_image']);
        if ($upload['status']) {
            $demo_image_url = $upload['url'];
        }
    }
    
    // NOTE: No 'status' column in products table per schema
    $updateStmt = $pdo->prepare("
        UPDATE public.products 
        SET name = ?, 
            description = ?, 
            image_url = ?, 
            demo_image_url = ?, 
            category = ?, 
            software_link = ?, 
            tutorial_video_url = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->execute([
        $name, 
        $description, 
        $image_url, 
        $demo_image_url, 
        $category, 
        $software_link, 
        $tutorial_video_url, 
        $product_id
    ]);
    
    logAudit($pdo, 'PRODUCT_UPDATED', $auth['username'], "Product ID: $product_id", 'success');
    
    response('success', 'Product updated');
    
} catch (Exception $e) {
    logAudit($pdo, 'PRODUCT_UPDATE_ERROR', $auth['username'], $e->getMessage(), 'error');
    error_log('PRODUCT_UPDATE_ERROR: ' . $e->getMessage());
    response('error', 'Failed to update product: ' . $e->getMessage());
}
?>