<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Không có quyền']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/Order.php';
require_once '../../src/classes/User.php';
require_once '../../src/classes/ProductDuration.php';
require_once '../../src/classes/License.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Kết nối cơ sở dữ liệu thất bại']);
    exit;
}

$order = new Order($db);
$user = new User($db);

$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields - check for missing/zero price specifically
if (empty($data['product_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu mã sản phẩm']);
    exit;
}

// Duration ID is REQUIRED (must be > 0)
if (empty($data['duration_id']) || !is_numeric($data['duration_id']) || (int) $data['duration_id'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Thiếu thời hạn sử dụng. Vui lòng chọn gói thời gian trên trang sản phẩm']);
    exit;
}

if (!isset($data['total_price']) || !is_numeric($data['total_price']) || (float) $data['total_price'] < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tổng tiền không hợp lệ (không được âm)']);
    exit;
}

try {
    $userId = (int) $_SESSION['user_id'];
    $productId = (int) $data['product_id'];
    $durationId = (int) $data['duration_id'];
    $totalPrice = (float) $data['total_price'];
    $paymentMethod = $data['payment_method'] ?? null;

    // Prepare order data
    $providedKey = !empty($data['idempotency_key']) ? trim($data['idempotency_key']) : null;
    $idempotencyKey = $providedKey ?? generate_uuid_v4();

    $orderData = [
        'user_id' => $userId,
        'product_id' => $productId,
        'product_duration_id' => $durationId,
        'total_price' => $totalPrice,
        'idempotency_key' => $idempotencyKey
    ];

    $isWalletPayment = !empty($paymentMethod) && $paymentMethod === 'wallet';

    // For wallet payment, verify balance first
    if ($isWalletPayment) {
        $userBalance = $user->getUserById($userId);
        if (!$userBalance || $userBalance['balance'] < $totalPrice) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Số dư không đủ']);
            exit;
        }

        // Deduct balance (OPTIMIZED - single operation)
        $user->updateUserBalance($userId, -$totalPrice);
    }

    // Create order
    $created = $order->createOrder($orderData);
    if (!$created) {
        throw new Exception('Tạo đơn hàng thất bại');
    }

    // If wallet payment, generate license and complete order
    if ($isWalletPayment) {
        try {
            $license = new License($db);
            $keyStr = generateLicenseKey();
            $expires_at = null;

            // Get duration to calculate expiry
            $durClass = new ProductDuration($db);
            $duration = $durClass->getById($durationId);

            if ($duration && !empty($duration['duration_days'])) {
                // ✅ Dùng gmdate() để lưu UTC
                $expires_at = gmdate('Y-m-d\\TH:i:s\\Z', strtotime('+' . intval($duration['duration_days']) . ' days'));
            }

            // Create license key
            $createKeyData = [
                'license_key' => $keyStr,
                'user_info' => json_encode(['user_id' => $userId]),
                'product_id' => $productId,
                'expires_at' => $expires_at,
                'status' => 'active'
            ];

            $createdKey = $license->createKey($createKeyData);

            // Complete order with license
            $order->updateOrder($created['id'], [
                'infokey_id' => $createdKey['id'],
                'completed_at' => gmdate('Y-m-d\\TH:i:s\\Z') // ✅ UTC
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Thanh toán thành công',
                'order_id' => $created['id'],
                'license_key' => $keyStr
            ]);
        } catch (Exception $e) {
            // If license creation fails, still report order created
            echo json_encode([
                'success' => true,
                'message' => 'Đã tạo đơn nhưng tạo key thất bại. Vui lòng liên hệ hỗ trợ.',
                'order_id' => $created['id']
            ]);
        }
    } else {
        // Pending payment - return order for further processing
        echo json_encode([
            'success' => true,
            'message' => 'Tạo đơn hàng thành công',
            'order_id' => $created['id'],
            'status' => 'pending'
        ]);
    }

    // Clear caches
    User::clearCache();
    License::clearCache();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>