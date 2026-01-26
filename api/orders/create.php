<?php
// api/orders/create.php - UPDATED (Removed status field)
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/classes/Order.php';
require_once __DIR__ . '/../../src/classes/User.php';
require_once __DIR__ . '/../../src/classes/Product.php';
require_once __DIR__ . '/../../src/classes/ProductDuration.php';
require_once __DIR__ . '/../../src/classes/License.php';
require_once __DIR__ . '/../../src/classes/PromotionCode.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Phương thức không được hỗ trợ');
}

$auth = authenticate();
$user_id = $auth['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$product_id = (int) ($input['product_id'] ?? 0);
$duration_id = isset($input['duration_id']) ? (int) $input['duration_id'] : null;
$promotion_code = isset($input['promotion_code']) ? trim($input['promotion_code']) : null;
$idempotency_key = !empty($input['idempotency_key']) ? trim($input['idempotency_key']) : null;
$payment_method = $input['payment_method'] ?? 'vietqr';

if (!$product_id || empty($duration_id)) {
    response('error', 'Sản phẩm hoặc gói thời hạn không hợp lệ');
}

try {
    $database = new Database();
    $db = $database->connect();

    $productClass = new Product($db);
    $product = $productClass->getProductById($product_id);

    if (!$product) {
        response('error', 'Không tìm thấy sản phẩm');
    }

    $durationClass = new ProductDuration($db);
    $duration = $durationClass->getById($duration_id);

    if (!$duration || $duration['product_id'] != $product_id || $duration['status'] !== 'active') {
        response('error', 'Gói thời hạn không tồn tại hoặc không hoạt động');
    }

    $total_price = floatval($duration['price']);
    $promotion_id = null;

    // Apply promotion if provided
    if (!empty($promotion_code)) {
        $promoClass = new PromotionCode($db);
        $promo = $promoClass->getByCode($promotion_code);

        if ($promo) {
            if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < time()) {
                response('error', 'Mã khuyến mãi đã hết hạn');
            }

            if (!empty($promo['max_uses']) && $promo['usage_count'] >= $promo['max_uses']) {
                response('error', 'Mã khuyến mãi đã hết lượt sử dụng');
            }

            if (!empty($promo['min_order_amount']) && $total_price < $promo['min_order_amount']) {
                response('error', 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng khuyến mãi');
            }

            if (!empty($promo['discount_percent'])) {
                $discount = $total_price * ($promo['discount_percent'] / 100);
                $total_price -= $discount;
            } elseif (!empty($promo['discount_amount'])) {
                $total_price -= floatval($promo['discount_amount']);
            }

            $total_price = max(0, $total_price);
            $promotion_id = $promo['id'];
        } else {
            response('error', 'Mã khuyến mãi không hợp lệ hoặc đã hết hạn');
        }
    }

    $userClass = new User($db);
    $user = $userClass->getUserById($user_id);

    if (!$user) {
        response('error', 'Không tìm thấy người dùng');
    }

    if (!$idempotency_key) {
        $idempotency_key = generate_uuid_v4();
    }

    // Create order WITHOUT 'status' field - use NULL for completed_at (order is pending)
    $orderClass = new Order($db);
    $orderData = [
        'user_id' => $user_id,
        'product_id' => $product_id,
        'product_duration_id' => $duration_id,
        'total_price' => $total_price,
        'promotion_code_id' => $promotion_id,
        'idempotency_key' => $idempotency_key,
        'completed_at' => null
    ];

    $order = $orderClass->createOrder($orderData);

    if (!$order) {
        response('error', 'Tạo đơn hàng thất bại');
    }

    // If wallet payment, deduct balance and mark order completed
    if ($payment_method === 'wallet') {
        if ($user['balance'] < $total_price) {
            response('error', 'Số dư không đủ');
        }

        if (!$userClass->updateUserBalance($user_id, -$total_price)) {
            response('error', 'Khấu trừ số dư thất bại');
        }

        $license = new License($db);
        $keyStr = generateLicenseKey();
        $expires_at = null;

        if (!empty($duration['duration_days'])) {
            // ✅ Dùng gmdate() để lưu UTC vào database
            $expires_at = gmdate('Y-m-d H:i:s', strtotime('+' . intval($duration['duration_days']) . ' days'));
        }

        $createKeyData = [
            'license_key' => $keyStr,
            'user_info' => json_encode(['user_id' => $user_id, 'product_id' => $product_id]),
            'product_id' => (int) $product_id,
            'expires_at' => $expires_at,
            'hwid' => null,
            'status' => 'active'
        ];

        $createdKey = $license->createKey($createKeyData);
        if (!$createdKey) {
            $userClass->updateUserBalance($user_id, $total_price);
            response('error', 'Tạo key sau thanh toán thất bại');
        }

        $infokey_id = !empty($createdKey['id']) ? $createdKey['id'] : null;
        $orderClass->updateOrder($order['id'], [
            'completed_at' => gmdate('Y-m-d\\TH:i:s\\Z'), // ✅ UTC ISO 8601 format
            'infokey_id' => $infokey_id
        ]);

        response('success', 'Tạo đơn và thanh toán thành công', [
            'order_id' => $order['id'],
            'total_price' => $total_price,
            'license_key' => $keyStr,
            'expires_at' => $expires_at
        ]);
    }

    response('success', 'Tạo đơn hàng thành công', [
        'order_id' => $order['id'],
        'total_price' => $total_price,
        'vietqr_link' => ($payment_method === 'vietqr') ? generateVietQRLink($total_price, $order['id']) : null
    ]);

} catch (Exception $e) {
    $msg = $e->getMessage();
    error_log("Order creation error: " . $msg);

    if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'idempotency') !== false) {
        if (!empty($idempotency_key)) {
            $database = new Database();
            $db = $database->connect();
            $orderClass = new Order($db);
            $endpoint = "orders?idempotency_key=eq." . urlencode($idempotency_key) . "&limit=1";
            $result = $db->callApi($endpoint, 'GET');
            if ($result && $result->code == 200 && !empty($result->response)) {
                $existing = $result->response[0];
                response('success', 'Phát hiện đơn hàng trùng', [
                    'order_id' => $existing['id']
                ]);
            }
        }
    }

    response('error', 'Tạo đơn hàng thất bại: ' . $msg);
}
?>

<!-- ========================================== -->
<?php
// api/orders/get_orders.php - UPDATED (Removed status field)
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    response('error', 'Phương thức không được hỗ trợ');
}

$auth = authenticate();
$user_id = $auth['user_id'];

try {
    $page = (int) ($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name
        FROM public.orders o
        JOIN public.products p ON o.product_id = p.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $orders = $stmt->fetchAll();

    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM public.orders WHERE user_id = ?");
    $countStmt->execute([$user_id]);
    $total = $countStmt->fetch()['total'];

    response('success', 'Lấy đơn hàng thành công', [
        'orders' => $orders,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    response('error', 'Không thể tải danh sách đơn hàng');
}
?>