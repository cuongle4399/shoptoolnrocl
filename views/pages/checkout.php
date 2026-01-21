<?php
$page_title = 'Thanh toán - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /ShopToolNro/views/pages/login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/Product.php';
require_once '../../src/classes/User.php';
require_once '../../src/classes/PromotionCode.php';
require_once '../../src/classes/ProductDuration.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    echo '<div class="alert alert-danger">Lỗi kết nối cơ sở dữ liệu</div>';
    include '../layout/footer.php';
    exit;
}

$productClass = new Product($db);
$userClass = new User($db);
$promoClass = new PromotionCode($db);

$product = $productClass->getProductById($_GET['product_id'] ?? 0);
$durationClass = new ProductDuration($db);
$user = $userClass->getUserById($_SESSION['user_id']);

$selectedDuration = null;
$durationNotFound = false;
if (!empty($_GET['duration_id'])) {
    $selectedDuration = $durationClass->getById($_GET['duration_id']);
    if (!$selectedDuration) $durationNotFound = true;
}

if (!$product || !$user) {
    echo '<div class="alert alert-danger">Sản phẩm hoặc người dùng không tồn tại</div>';
    include '../layout/footer.php';
    exit;
}

$discount = 0;
// Prefer explicit duration record; if missing but caller provided duration_price (client-side), use it as fallback
$finalPrice = $selectedDuration ? $selectedDuration['price'] : $product['price'];
if (empty($selectedDuration) && isset($_GET['duration_price'])) {
    // sanitize numeric input
    $p = str_replace(',', '', $_GET['duration_price']);
    $finalPrice = is_numeric($p) ? (float)$p : $finalPrice;
} 
?>

<div class="main-content fade-in container-md">
    <div id="notificationContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;"></div>
    <h1>Thanh toán</h1>
    
    <div class="info-card">
        <?php if (!empty($durationNotFound)): ?>
            <div class="alert alert-warning">Không tìm thấy thời hạn bạn đã chọn. Hiển thị giá mặc định; vui lòng quay lại trang sản phẩm để chọn lại nếu cần.</div>
        <?php endif; ?>

        <h3><?php echo htmlspecialchars($product['name']); ?><?php echo $selectedDuration ? ' — ' . htmlspecialchars($selectedDuration['label'] ?? ($selectedDuration['duration_days'] . ' ngày')) : ''; ?></h3>
        <p><strong>Giá gốc:</strong> <span id="basePrice"><?php echo number_format($finalPrice, 0, ',', '.'); ?></span> ₫</p>
        <p><strong>Giảm giá:</strong> <span id="discountAmount">0</span> ₫</p>
        <p class="accent-amount">
            <strong>Tổng:</strong> <span id="totalPrice"><?php echo number_format($finalPrice, 0, ',', '.'); ?></span> ₫
        </p>
        <p><strong>Số dư hiện tại:</strong> <?php echo number_format($user['balance'], 0, ',', '.'); ?> ₫</p>
    </div>
    
    <form id="checkoutForm">
        <input type="hidden" name="duration_id" value="<?php echo $selectedDuration['id'] ?? ''; ?>">
        <div class="form-group">
            <label>Mã khuyến mãi (tùy chọn)</label>

        
            <div class="flex-between gap-10">
                <input type="text" id="promoCode" name="promo_code" placeholder="Nhập mã khuyến mãi">
                <button type="button" class="btn btn-secondary" onclick="applyPromo()">Áp dụng</button>
            </div>
        </div>
        
        <div class="form-group">
            <label>Phương thức thanh toán</label>
            <select name="payment_method" required onchange="updatePaymentMethod()">
                <option value="">-- Chọn --</option>
                <option value="wallet">Ví điểm (Số dư: <?php echo number_format($user['balance'], 0, ',', '.'); ?> ₫)</option>
            </select>
        </div>
        
        <div id="walletInfo" class="info-box hidden mt-15">
            <p>Sẽ trừ <span id="walletAmount"><?php echo number_format($finalPrice, 0, ',', '.'); ?></span> ₫ từ ví của bạn</p>
        </div>
        
        <div class="form-group">
            <label>Ghi chú (tùy chọn)</label>
            <textarea name="notes" placeholder="Nhập ghi chú của bạn"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary btn-fullwidth btn-lg">Thanh toán</button>
    </form>
</div>

<script>
// Notification system
function showNotification(message, type = 'info') {
    const container = document.getElementById('notificationContainer');
    const notificationId = 'notif-' + Date.now();
    
    const notificationClass = {
        'success': 'success',
        'error': 'danger',
        'warning': 'warning',
        'info': 'info'
    }[type] || type;
    
    const notification = document.createElement('div');
    notification.id = notificationId;
    notification.className = 'alert alert-' + notificationClass + ' alert-dismissible fade show';
    notification.style.marginBottom = '10px';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    container.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const el = document.getElementById(notificationId);
        if (el) el.remove();
    }, 5000);
}

let currentDiscount = 0;
const basePrice = <?php echo $finalPrice; ?>;

function updatePaymentMethod() {
    const method = document.querySelector('select[name="payment_method"]').value;
    document.getElementById('walletInfo').style.display = method === 'wallet' ? 'block' : 'none';
}

function applyPromo() {
    const code = document.getElementById('promoCode').value.trim();
    
    if (!code) {
        showNotification('Vui lòng nhập mã khuyến mãi', 'warning');
        return;
    }
    
    fetch('/ShopToolNro/api/checkout/validate_promo.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({code: code, order_amount: basePrice})
    }).then(r => r.json()).then(data => {
        if (data.valid) {
            const discount = data.discount.discount_percent 
                ? (basePrice * data.discount.discount_percent / 100)
                : data.discount.discount_amount;
            
            currentDiscount = discount;
            const finalPrice = basePrice - discount;
            
            document.getElementById('discountAmount').textContent = number_format(discount);
            document.getElementById('totalPrice').textContent = number_format(finalPrice);
            document.getElementById('walletAmount').textContent = number_format(finalPrice);
            
            showNotification('Áp dụng thành công!', 'success');
        } else {
            showNotification(data.message || 'Lỗi', 'error');
        }
    });
}

function number_format(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Đang xử lý...';
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.product_id = <?php echo $product['id']; ?>;
    data.total_price = basePrice - currentDiscount;
    
    try {
        const response = await fetch('/ShopToolNro/api/checkout/process.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Thanh toán thành công', 'success');
            setTimeout(() => { window.location.href = '/ShopToolNro/views/pages/orders.php'; }, 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + (error.message || ''), 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
});
</script>

<?php include '../layout/footer.php'; ?>
