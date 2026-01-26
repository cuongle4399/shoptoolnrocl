<?php
// api/orders/by_product.php - UPDATED (Removed status field, use completed_at instead)
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../src/classes/Order.php';
require_once '../../src/classes/User.php';
require_once '../../src/classes/License.php';

if (!isset($_GET['product_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu mã sản phẩm']);
    exit;
}

$database = new Database();
$db = $database->connect();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Kết nối cơ sở dữ liệu thất bại']);
    exit;
}

$product_id = (int) $_GET['product_id'];
$orderClass = new Order($db);
$userClass = new User($db);
$licenseClass = new License($db);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền']);
    exit;
}

$user_id = ($_SESSION['role'] === 'admin') ? null : $_SESSION['user_id'];
$orders = $orderClass->getOrdersByProductId($product_id, $user_id);

$orders = is_array($orders) ? array_filter($orders, function ($o) {
    return is_array($o) && !empty($o['id']);
}) : [];
$orders = array_values($orders);
error_log("api/orders/by_product: returning " . count($orders) . " orders for product " . $product_id);

$result = [];
foreach ($orders as $o) {
    $customer = $userClass->getUserById($o['user_id']);
    $customerName = $customer ? $customer['username'] : ('User-' . $o['user_id']);
    $key = !empty($o['license_key']) ? $licenseClass->getKeyByLicense($o['license_key'], $o['product_id'] ?? null) : null;

    // Calculate status based on completed_at
    $orderStatus = !empty($o['completed_at']) ? 'completed' : 'pending';

    // Convert timestamps to Vietnam timezone
    $expires_at_vn = null;
    if (!empty($key['expires_at'])) {
        try {
            $dt = new DateTime($key['expires_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
            $expires_at_vn = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $expires_at_vn = $key['expires_at'];
        }
    }

    $created_at_vn = null;
    if (!empty($o['created_at'])) {
        try {
            $dt = new DateTime($o['created_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
            $created_at_vn = $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $created_at_vn = $o['created_at'];
        }
    }

    $result[] = [
        'id' => $o['id'],
        'user_id' => $o['user_id'],
        'customer_name' => $customerName,
        'total_price' => $o['total_price'] ?? ($o['total'] ?? 0),
        'status' => $orderStatus,
        'completed_at' => $o['completed_at'] ?? null,
        'license_key' => $o['license_key'] ?? null,
        'expires_at' => $expires_at_vn, // ✅ Giờ Việt Nam
        'created_at' => $created_at_vn  // ✅ Giờ Việt Nam
    ];
}

echo json_encode(['success' => true, 'orders' => $result]);
?>