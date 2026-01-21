<?php
$page_title = 'Trang chủ - ShopToolNro';
include '../layout/header.php';

require_once '../../config/database.php';
require_once '../../src/classes/Product.php';
require_once '../../src/classes/ProductDuration.php';
require_once '../../src/classes/Notification.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    echo '<div class="alert alert-danger">Lỗi kết nối cơ sở dữ liệu</div>';
    include '../layout/footer.php';
    exit;
}

$productClass = new Product($db);
$durationClass = new ProductDuration($db);
$notificationClass = new Notification($db);

$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$products = $productClass->getAllProducts(true, $perPage + 1, $offset); // fetch one extra to detect next page
$hasNext = count($products) > $perPage;
if ($hasNext) array_pop($products);

if ($page > 1 && empty($products)) {
    header('Location: ?page=' . ($page - 1));
    exit;
}

// Get all durations for a product with prices and labels
function getPricesForProduct($product_id, $durationClass) {
    $durations = $durationClass->getDurationsByProductId($product_id, true);
    if (empty($durations)) {
        return [];
    }
    // Sort by duration_days (NULL goes to end = permanent)
    usort($durations, function($a, $b) {
        $a_days = $a['duration_days'] ?? 999;
        $b_days = $b['duration_days'] ?? 999;
        return $a_days <=> $b_days;
    });
    return $durations;
}

function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }

$notification = $notificationClass->getActiveNotification();
?>

<div class="main-content fade-in">
    <!-- Hero section removed to reduce clutter -->

    <?php if ($notification): ?>
        <div class="alert alert-info mb-20">
            <strong><?php echo htmlspecialchars($notification['message']); ?></strong>
        </div>
    <?php endif; ?>
    
    <h2>Danh sách sản phẩm</h2>
    
    <div class="search-bar">
        <input type="text" id="searchProduct" class="search-input" placeholder="Tìm kiếm sản phẩm...">
    </div>
    
    <div class="product-listing products-grid" id="productsContainer">
        <?php if (empty($products)): ?>
            <div class="empty-state empty-state-lg">Không có sản phẩm</div>
        <?php else: foreach ($products as $item): ?>
            <div class="product-card fade-in" data-name="<?php echo strtolower($item['name']); ?>">
                <?php 
                    $imageUrl = $item['image_url'];
                    if (empty($imageUrl)) {
                        // Fallback: use demo_image_url or placeholder
                        if (!empty($item['demo_image_url'])) {
                            $demoUrls = json_decode($item['demo_image_url'], true);
                            $imageUrl = is_array($demoUrls) && !empty($demoUrls[0]) ? $demoUrls[0] : 'https://via.placeholder.com/400x250?text=' . urlencode($item['name']);
                        } else {
                            $imageUrl = 'https://via.placeholder.com/400x250?text=' . urlencode($item['name']);
                        }
                    }
                ?>
                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-image">
                <div class="product-info">
                    <h3 class="product-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p class="product-description"><?php echo htmlspecialchars(substr($item['description'], 0, 80)); ?>...</p>
                    
                    <!-- Display all product durations with prices -->
                    <?php 
                        $durations = getPricesForProduct($item['id'], $durationClass);
                        if (!empty($durations)): 
                    ?>
                        <div class="product-prices">
                            <?php foreach ($durations as $duration): ?>
                                <div class="price-item">
                                    <span class="duration-label">
                                        <?php echo htmlspecialchars($duration['duration_label']); ?>
                                    </span>
                                    <span class="duration-price">
                                        <?php echo number_format($duration['price'], 0, ',', '.'); ?> ₫
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="product-price">Giá cạnh tranh</div>
                    <?php endif; ?>
                    
                    <a href="/ShopToolNro/views/pages/product-detail.php?id=<?php echo $item['id']; ?>" class="btn btn-primary btn-block mt-10">Xem chi tiết</a>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

<?php
    $hasPrev = $page > 1;
    $showPagination = $hasPrev || $hasNext;
?>

<?php if ($showPagination): ?>
    <div class="flex-center mt-14">
        <?php if ($hasPrev): ?>
            <a class="btn" href="<?php echo pageLink($page - 1);?>">&laquo; Trước</a>
        <?php endif; ?>
        <div class="page-indicator">Trang <?php echo $page; ?></div>
        <?php if ($hasNext): ?>
            <a class="btn" href="<?php echo pageLink($page + 1);?>">Tiếp &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Modal: Orders for product -->
<div id="productOrdersModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Đơn hàng</h3>
            <span class="modal-close" onclick="document.getElementById('productOrdersModal').classList.remove('active')">&times;</span>
        </div>
        <div class="modal-body">
            <p class="text-center">Chọn một sản phẩm để xem đơn hàng</p>
        </div>
    </div>
</div>

<style>
.product-prices {
    margin: 10px 0;
    padding: 8px;
    background: #f5f5f5;
    border-radius: 4px;
}

.price-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 13px;
}

.duration-label {
    font-weight: 500;
    color: #333;
}

.duration-price {
    color: #e74c3c;
    font-weight: bold;
}
</style>

<script>
document.getElementById('searchProduct').addEventListener('keyup', (e) => {
    const keyword = e.target.value.toLowerCase();
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name;
        card.style.display = name.includes(keyword) ? 'block' : 'none';
    });
});

// View orders from product card
async function showOrdersForProduct(productId) {
    const modal = document.getElementById('productOrdersModal');
    const body = modal.querySelector('.modal-body');
    body.innerHTML = '<p class="text-center">Đang tải...</p>';
    modal.classList.add('active');

    try {
        const res = await fetch('/ShopToolNro/api/orders/by_product.php?product_id=' + productId);
        const data = await res.json();
        if (!data.success) {
            body.innerHTML = '<p class="text-center">' + (data.message || 'Không có đơn hàng.') + '</p>';
            return;
        }

        const orders = data.orders || [];
        if (orders.length === 0) {
            body.innerHTML = '<p class="text-center">Không có đơn hàng.</p>';
            return;
        }

        let html = '<table class="full-table"><thead><tr class="table-row"><th>ID</th><th>Người mua</th><th>Giá</th><th>License</th><th>Hết hạn</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead><tbody>'; 
        orders.forEach(o => {
            html += '<tr class="table-row--thin">';
            html += '<td>' + o.id + '</td>';
            html += '<td>' + (o.customer_name || '-') + '</td>';
            html += '<td>' + new Intl.NumberFormat('vi-VN').format(o.total_price || 0) + ' ₫</td>';
            html += '<td>' + (o.license_key ? '<code>' + o.license_key + '</code>' : '-') + '</td>';
            let expiresText = '-';
            if (o.license_key && !o.expires_at) expiresText = 'Vĩnh viễn';
            else if (o.expires_at) expiresText = new Date(o.expires_at).toLocaleString();
            html += '<td>' + expiresText + '</td>';
            html += '<td>' + (o.status || '-') + '</td>';
            html += '<td>' + (o.created_at ? new Date(o.created_at).toLocaleString() : '-') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';

        body.innerHTML = html;
    } catch (err) {
        body.innerHTML = '<p class="text-center">Lỗi: ' + (err.message || '') + '</p>';
    }
}

// Attach listeners
document.addEventListener('click', (e) => {
    if (e.target && e.target.classList.contains('view-orders-btn')) {
        const pid = e.target.getAttribute('data-product-id');
        showOrdersForProduct(pid);
    }
});
</script>

<!-- Homepage Notification Modal -->
<div id="homepageNotificationModal" class="homepage-notification-modal">
    <div class="notification-modal-content">
        <button class="notification-close-btn" onclick="closeHomepageNotification()">×</button>
        <div class="notification-icon" id="notificationIcon">📢</div>
        <h2 id="notificationTitle">Thông báo quan trọng</h2>
        <p id="notificationMessage">Chào mừng bạn đến với <strong>ShopToolNro</strong>! 🎉</p>
        <div class="notification-actions">
            <button class="btn btn-primary" onclick="closeHomepageNotification()">Đã hiểu</button>
        </div>
    </div>
</div>

<style>
.homepage-notification-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 8888;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

.homepage-notification-modal.active {
    display: flex;
    animation: fadeIn 0.4s ease-in-out;
}

.notification-modal-content {
    background: white;
    border-radius: 16px;
    padding: 50px 40px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    animation: slideUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-width: 450px;
    position: relative;
    overflow: hidden;
}

.notification-modal-content::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.notification-close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 32px;
    height: 32px;
    border: none;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 24px;
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 1;
}

.notification-close-btn:hover {
    background: #e5e7eb;
    color: #1f2937;
    transform: rotate(90deg);
}

.notification-icon {
    font-size: 60px;
    margin-bottom: 20px;
    animation: bounce 0.6s ease-in-out;
    position: relative;
    z-index: 1;
}

.notification-modal-content h2 {
    margin: 0 0 15px 0;
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    position: relative;
    z-index: 1;
}

.notification-modal-content p {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #4b5563;
    line-height: 1.6;
    position: relative;
    z-index: 1;
}

.notification-modal-content p strong {
    color: #3b82f6;
    font-weight: 600;
}

.notification-actions {
    margin-top: 30px;
    position: relative;
    z-index: 1;
}

.notification-actions .btn {
    padding: 12px 35px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.notification-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(59, 130, 246, 0.4);
}

.notification-actions .btn:active {
    transform: translateY(0);
}

@keyframes bounce {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        transform: translateY(40px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>

<script>
// Notification data from PHP
const notificationData = <?php echo $notification ? json_encode($notification) : 'null'; ?>;

function initializeHomepageNotification() {
    // Check if there's a notification from admin
    if (!notificationData) {
        return; // No notification, don't show modal
    }
    
    // Check if notification has been dismissed in the last 30 minutes
    const dismissedTime = localStorage.getItem('homepageNotificationDismissed');
    const now = Date.now();
    const thirtyMinutes = 30 * 60 * 1000;
    
    if (dismissedTime && (now - parseInt(dismissedTime)) < thirtyMinutes) {
        // Notification was dismissed within the last 30 minutes, don't show it
        return;
    }
    
    // Update modal content with notification data
    updateNotificationContent();
    
    // Show the notification
    const modal = document.getElementById('homepageNotificationModal');
    if (modal) {
        modal.classList.add('active');
    }
}

function updateNotificationContent() {
    if (!notificationData) return;
    
    // Set title
    const titleEl = document.getElementById('notificationTitle');
    if (titleEl && notificationData.title) {
        titleEl.textContent = notificationData.title;
    }
    
    // Set message
    const messageEl = document.getElementById('notificationMessage');
    if (messageEl && notificationData.message) {
        messageEl.innerHTML = notificationData.message;
    }
    
    // Set icon if available
    const iconEl = document.getElementById('notificationIcon');
    if (iconEl && notificationData.icon) {
        iconEl.textContent = notificationData.icon;
    }
}

function closeHomepageNotification() {
    const modal = document.getElementById('homepageNotificationModal');
    if (modal) {
        modal.classList.remove('active');
        // Store dismissal time in localStorage
        localStorage.setItem('homepageNotificationDismissed', Date.now().toString());
    }
}

// Initialize notification when page loads
document.addEventListener('DOMContentLoaded', () => {
    initializeHomepageNotification();
});

// Also initialize if DOM is already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHomepageNotification);
} else {
    initializeHomepageNotification();
}
</script>

<?php include '../layout/footer.php'; ?>
