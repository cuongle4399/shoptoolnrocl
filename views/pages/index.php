<?php
$page_title = 'Trang chủ - ShopToolNro';
include '../layout/header.php';

require_once '../../config/database.php';
require_once '../../src/classes/ProductOptimized.php';
require_once '../../src/classes/Notification.php';
require_once '../../src/classes/Order.php';
require_once '../../src/classes/User.php';
require_once '../../src/classes/Product.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    echo '<div class="alert alert-danger">Lỗi kết nối cơ sở dữ liệu</div>';
    include '../layout/footer.php';
    exit;
}

$productClass = new ProductOptimized($db);
$notificationClass = new Notification($db);
$orderClass = new Order($db);
$userClass = new User($db);
$baseProductClass = new Product($db);

$notification = null;
if (!isset($_SESSION['user_id'])) {
    $notification = $notificationClass->getActiveNotification();
    if ($notification) {
        $notification = [
            'id' => $notification['id'] ?? null,
            'title' => $notification['title'] ?? 'Thông báo quan trọng',
            'message' => $notification['message'] ?? '',
            'icon' => $notification['icon'] ?? '📢'
        ];
    }
}

// Build avatar pool for public order feed (exclude logo files)
$avatarFiles = glob(__DIR__ . '/../../img/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
$avatarFiles = array_values(array_filter($avatarFiles, function ($path) {
    $name = strtolower(basename($path));
    return strpos($name, 'ico') === false;
}));
$avatarUrls = array_map(function ($path) {
    return '/ShopToolNro/img/' . basename($path);
}, $avatarFiles);

// --- SSR PUBLIC ORDERS (Recent Activity) ---
$publicOrders = [];
if (!isset($_SESSION['user_id'])) {
    try {
        $endpoint = "orders?completed_at=not.is.null&order=completed_at.desc&limit=10";
        $result = $db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200) {
            $rawOrders = $result->response ?? [];
            foreach ($rawOrders as $order) {
                $u = $userClass->getUserById($order['user_id']);
                $p = $baseProductClass->getProductById($order['product_id'], false);
                if (!$u || !$p)
                    continue;

                $username = $u['username'];
                $maskedUsername = mb_substr($username, 0, 1) . str_repeat('*', max(3, mb_strlen($username) - 2)) . mb_substr($username, -1);

                $rawTimestamp = $order['completed_at'] ?? $order['created_at'] ?? null;
                $timeAgo = 'Gần đây';
                if ($rawTimestamp) {
                    try {
                        $dt = new DateTime($rawTimestamp, new DateTimeZone('UTC'));
                        $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                        $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
                        $diff = $now->diff($dt);
                        if ($diff->days > 0)
                            $timeAgo = $diff->days . ' ngày trước';
                        elseif ($diff->h > 0)
                            $timeAgo = $diff->h . ' giờ trước';
                        elseif ($diff->i > 0)
                            $timeAgo = $diff->i . ' phút trước';
                        else
                            $timeAgo = 'Vừa xong';
                    } catch (Exception $e) {
                    }
                }

                $publicOrders[] = [
                    'username' => $maskedUsername,
                    'product_name' => $p['name'],
                    'time_ago' => $timeAgo,
                    'avatar_seed' => $username . ($p['name'] ?? '')
                ];
            }
        }
    } catch (Exception $e) {
    }
}

// --- SSR PRODUCT LISTING ---
$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$productData = $productClass->getAllProductsWithDurations($perPage, $offset);
$products = $productData['products'] ?? [];
$totalProducts = $productData['total'] ?? 0;
$totalPages = ceil($totalProducts / $perPage);

function pageLink($p)
{
    $qs = $_GET;
    $qs['page'] = $p;
    return '?' . http_build_query($qs);
}

function pickAvatarFromPool($seed, $pool)
{
    if (empty($pool))
        return null;
    $hash = 0;
    for ($i = 0; $i < strlen($seed); $i++) {
        $hash = ($hash * 31 + ord($seed[$i])) % 1000000;
    }
    return $pool[$hash % count($pool)];
}
?>

<div class="main-content fade-in">
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="public-orders-section">
            <div class="public-orders-header">
                <h3>🔥 Hoạt động mua hàng gần đây</h3>
                <p class="subtitle">Khách hàng tin tưởng và sử dụng dịch vụ của chúng tôi</p>
            </div>
            <div class="public-orders-list" id="publicOrdersList">
                <?php if (empty($publicOrders)): ?>
                    <p class="text-center" style="padding: 20px; color: var(--text-muted);">Chưa có hoạt động nào</p>
                <?php else: ?>
                    <?php
                    // Initial JS will handle paging if we want, or we can just show top 3/2
                    // Let's keep the JS for public orders slider/paging but initial data is SSR
                    foreach ($publicOrders as $po):
                        $avatar = pickAvatarFromPool($po['username'], $avatarUrls);
                        ?>
                        <div class="public-order-item" style="display: none;">
                            <div class="public-order-avatar <?php echo $avatar ? 'has-img' : ''; ?>">
                                <?php if ($avatar): ?>
                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="avatar">
                                <?php else: ?>
                                    <?php echo htmlspecialchars($po['username'][0]); ?>
                                <?php endif; ?>
                            </div>
                            <div class="public-order-content">
                                <div class="public-order-user"><?php echo htmlspecialchars($po['username']); ?></div>
                                <div class="public-order-product">đã mua: <?php echo htmlspecialchars($po['product_name']); ?></div>
                                <div class="public-order-time"><?php echo htmlspecialchars($po['time_ago']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (count($publicOrders) > 1): ?>
                <div class="public-orders-pagination" id="publicOrdersPagination">
                    <button class="pagination-btn" id="prevPageBtn">&laquo; Trước</button>
                    <span class="pagination-info" id="paginationInfo">Trang 1 / 1</span>
                    <button class="pagination-btn" id="nextPageBtn">Sau &raquo;</button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>Danh sách sản phẩm</h2>

    <div class="search-bar">
        <input type="text" id="searchProduct" class="search-input" placeholder="Tìm kiếm sản phẩm...">
    </div>

    <div class="product-listing products-grid" id="productsContainer">
        <?php if (empty($products)): ?>
            <div class="empty-state empty-state-lg">Không có sản phẩm nào.</div>
        <?php else: ?>
            <?php foreach ($products as $index => $item): ?>
                <div class="product-card show" data-name="<?php echo htmlspecialchars(strtolower($item['name'] ?? '')); ?>"
                    style="animation-delay: <?php echo $index * 0.05; ?>s">
                    <?php
                    $imageUrl = $item['image_url'];
                    if (!$imageUrl) {
                        if (!empty($item['demo_image_url'])) {
                            try {
                                $demoUrls = json_decode($item['demo_image_url'], true);
                                $imageUrl = (is_array($demoUrls) && !empty($demoUrls[0])) ? $demoUrls[0] : 'https://via.placeholder.com/400x250?text=' . urlencode($item['name']);
                            } catch (Exception $e) {
                                $imageUrl = 'https://via.placeholder.com/400x250?text=' . urlencode($item['name']);
                            }
                        } else {
                            $imageUrl = 'https://via.placeholder.com/400x250?text=' . urlencode($item['name']);
                        }
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>"
                        class="product-image" loading="lazy">
                    <div class="product-info">
                        <h3 class="product-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="product-description">
                            <?php
                            $desc = $item['description'] ?? '';
                            echo htmlspecialchars(strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '...' : $desc);
                            ?>
                        </p>

                        <?php if (!empty($item['durations'])): ?>
                            <div class="product-prices">
                                <?php foreach ($item['durations'] as $d): ?>
                                    <div class="price-item">
                                        <span class="duration-label"><?php echo htmlspecialchars($d['duration_label']); ?></span>
                                        <span class="duration-price"><?php echo number_format($d['price'], 0, ',', '.'); ?> ₫</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="product-price">Giá cạnh tranh</div>
                        <?php endif; ?>

                        <div class="flex-between gap-10 mt-10">
                            <a href="/ShopToolNro/views/pages/product-detail.php?id=<?php echo $item['id']; ?>"
                                class="btn btn-primary flex-1">Xem chi tiết</a>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <button class="btn btn-outline view-orders-btn" data-product-id="<?php echo $item['id']; ?>"
                                    title="Xem đơn hàng">📦</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div id="productsPagination" class="flex-center mt-14">
            <?php if ($page > 1): ?>
                <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
            <?php endif; ?>
            <div class="page-indicator">Trang <?php echo $page; ?> / <?php echo $totalPages; ?></div>
            <?php if ($page < $totalPages): ?>
                <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Modal: Orders for product -->
    <div id="productOrdersModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Đơn hàng</h3>
                <span class="modal-close"
                    onclick="document.getElementById('productOrdersModal').classList.remove('active')">&times;</span>
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
        // SSR Search functionality
        document.getElementById('searchProduct').addEventListener('input', (e) => {
            const keyword = e.target.value.toLowerCase();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.dataset.name || '';
                card.style.display = name.includes(keyword) ? 'block' : 'none';
            });
        });

        // View orders from product card (for Admin)
        async function showOrdersForProduct(productId) {
            const modal = document.getElementById('productOrdersModal');
            if (!modal) return;
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
                } else {
                    let html = '<div class="table-responsive"><table class="full-table"><thead><tr class="table-row"><th>ID</th><th>Người mua</th><th>Giá</th><th>License</th><th>Hết hạn</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead><tbody>';
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
                    html += '</tbody></table></div>';
                    body.innerHTML = html;
                }
            } catch (err) {
                body.innerHTML = '<p class="text-center">Lỗi: ' + (err.message || '') + '</p>';
            }
        }

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.view-orders-btn');
            if (btn) showOrdersForProduct(btn.getAttribute('data-product-id'));
        });

        // Pagination for SSR Public Orders (Static data slider)
        let activityPage = 1;
        const itemsPerActFull = window.innerWidth >= 769 ? 3 : 2;
        const actItems = Array.from(document.querySelectorAll('.public-order-item'));

        function renderActivity() {
            const pagination = document.getElementById('publicOrdersPagination');
            if (actItems.length === 0) return;

            const totalPages = Math.ceil(actItems.length / itemsPerActFull);
            const start = (activityPage - 1) * itemsPerActFull;
            const end = start + itemsPerActFull;

            actItems.forEach((it, i) => {
                if (i >= start && i < end) it.style.display = 'flex';
                else it.style.display = 'none';
            });

            if (pagination) {
                const info = document.getElementById('paginationInfo');
                if (info) info.textContent = `Trang ${activityPage} / ${totalPages}`;
                const prev = document.getElementById('prevPageBtn');
                const next = document.getElementById('nextPageBtn');
                if (prev) prev.disabled = activityPage === 1;
                if (next) next.disabled = activityPage === totalPages;
            }
        }

        document.getElementById('prevPageBtn')?.addEventListener('click', () => { if (activityPage > 1) { activityPage--; renderActivity(); } });
        document.getElementById('nextPageBtn')?.addEventListener('click', () => { if (activityPage < Math.ceil(actItems.length / itemsPerActFull)) { activityPage++; renderActivity(); } });

        renderActivity();
    </script>

</div>
<?php if (!isset($_SESSION['user_id'])): ?>
    <style>
        .homepage-notification-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
            padding: 20px;
            box-sizing: border-box;
        }

        .homepage-notification-modal.active {
            display: flex;
            animation: modalFadeIn 0.3s ease;
        }

        .notification-modal-content {
            background: #1e1e1e;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px 30px;
            width: 100%;
            max-width: 480px;
            text-align: center;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalScaleIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            margin: auto;
        }

        .notification-icon {
            font-size: 80px;
            margin-bottom: 24px;
            display: flex;
            justify-content: center;
            filter: drop-shadow(0 0 20px rgba(0, 188, 212, 0.3));
        }

        .notification-modal-content h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #fff;
            border: none;
            padding: 0;
        }

        .notification-modal-content p {
            font-size: 16px;
            line-height: 1.6;
            color: #a0a0a0;
            margin-bottom: 30px;
        }

        .notification-close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            color: #666;
            font-size: 32px;
            cursor: pointer;
            transition: color 0.2s;
            line-height: 1;
            padding: 5px;
        }

        .notification-close-btn:hover {
            color: #fff;
        }

        .notification-actions .btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 12px;
            margin: 0;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes modalScaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @media (max-width: 480px) {
            .notification-modal-content {
                padding: 30px 20px;
                border-radius: 20px;
            }

            .notification-icon {
                font-size: 60px;
            }

            .notification-modal-content h2 {
                font-size: 20px;
            }

            .notification-modal-content p {
                font-size: 14px;
            }
        }
    </style>

    <div id="homepageNotificationModal" class="homepage-notification-modal">
        <div class="notification-modal-content">
            <button class="notification-close-btn" onclick="closeHomepageNotification()">&times;</button>
            <div class="notification-icon" id="notificationIcon">📢</div>
            <h2 id="notificationTitle">Thông báo</h2>
            <p id="notificationMessage"></p>
            <div class="notification-actions">
                <button class="btn btn-primary" onclick="closeHomepageNotification()">Đã hiểu</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const notificationData = <?php echo $notification ? json_encode($notification) : 'null'; ?>;

            function initializeHomepageNotification() {
                if (!notificationData || !notificationData.message) return;

                const titleEl = document.getElementById('notificationTitle');
                const messageEl = document.getElementById('notificationMessage');
                const iconEl = document.getElementById('notificationIcon');
                const modalEl = document.getElementById('homepageNotificationModal');

                if (titleEl) titleEl.textContent = notificationData.title;
                if (messageEl) messageEl.innerHTML = notificationData.message;
                if (iconEl) iconEl.textContent = notificationData.icon;

                setTimeout(() => {
                    modalEl?.classList.add('active');
                    document.body.style.overflow = 'hidden'; // Prevent scroll
                }, 500);
            }

            window.closeHomepageNotification = function () {
                document.getElementById('homepageNotificationModal')?.classList.remove('active');
                document.body.style.overflow = ''; // Restore scroll
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeHomepageNotification);
            } else {
                initializeHomepageNotification();
            }
        })();
    </script>
<?php endif; ?>
<?php include '../layout/footer.php'; ?>