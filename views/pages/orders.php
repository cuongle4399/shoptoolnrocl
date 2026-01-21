<?php include '../layout/header.php'; ?>
<?php
require_once '../../config/database.php';
require_once '../../src/classes/Order.php';
require_once '../../src/classes/User.php';
require_once '../../src/classes/Product.php';

$database = new Database();
$db = $database->connect();
$orderClass = new Order($db);
$userClass = new User($db);
$productClass = new Product($db);

$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $orders = $orderClass->getAllOrders($perPage + 1, $offset);
} else {
    $orders = $orderClass->getOrdersByUserId($_SESSION['user_id'] ?? 0, $perPage + 1, $offset);
}
$hasNext = count($orders) > $perPage;
if ($hasNext) array_pop($orders);

// Defensive: remove invalid / empty entries returned from API (prevents empty table rows)
$orders = is_array($orders) ? array_filter($orders, function($o) {
    return is_array($o) && !empty($o['id']);
}) : [];
// Reindex array after filtering
$orders = array_values($orders);
// Log for debugging to detect unexpected empty items
error_log("orders.php: loaded orders count = " . count($orders));

// If current page is beyond available results, redirect back one page
if ($page > 1 && empty($orders)) {
    header('Location: ?page=' . ($page - 1));
    exit;
}

function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }

function compactStr($s, $front = 8, $back = 4) {
    $s = (string)$s;
    if (mb_strlen($s) <= ($front + $back + 3)) return htmlspecialchars($s);
    $start = mb_substr($s, 0, $front);
    $end = mb_substr($s, -$back);
    return htmlspecialchars($start . '...' . $end);
}
?>

<div class="container">
    <div class="flex-between">
        <h1>Quản lý đơn hàng</h1>
    </div>
    <style>
    .compact-value { display:flex; gap:6px; align-items:center; }
    .truncated { font-family:monospace; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block; vertical-align:middle; }
    .btn-sm { padding:4px 8px; font-size:0.85em; }
    
    /* Notification Styles */
    #notificationContainer {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
    }
    .notification {
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease-out;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .notification.success {
        background-color: #4caf50;
        color: white;
    }
    .notification.error {
        background-color: #f44336;
        color: white;
    }
    .notification.info {
        background-color: #2196F3;
        color: white;
    }
    .notification.warning {
        background-color: #ff9800;
        color: white;
    }
    .notification .close-btn {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 20px;
        line-height: 1;
        margin-left: 15px;
    }
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    </style>
    <?php require_once '../../src/classes/License.php'; $licenseClass = new License($db); ?>
    <table class="table">
        <thead>
            <tr>
                <th>Tên sản phẩm</th>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <th>Người mua</th>
                <?php endif; ?>
                <th>License</th>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <th>HWID</th>
                <?php endif; ?>
                <th>Hết hạn</th>
                <th>Ngày tạo</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <?php 
                        // Resolve license record: prefer explicit license_key (legacy), otherwise use infokey_id
                        $key = null;
                        if (!empty($order['license_key'])) {
                            $key = $licenseClass->getKeyByLicense($order['license_key'], $order['product_id'] ?? null);
                        } elseif (!empty($order['infokey_id'])) {
                            $key = $licenseClass->getKeyById($order['infokey_id']);
                        }
                        // string to display/use for license actions
                        $license_display = $order['license_key'] ?? ($key['license_key'] ?? null);
                        $customer = $userClass->getUserById($order['user_id']);
                        $customerName = $customer ? $customer['username'] : ('User-' . $order['user_id']);
                        $product = $productClass->getProductById($order['product_id'] ?? null, false);
                        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name'] ?? '-'); ?></td>
                        <?php if ($isAdmin): ?>
                            <td><?php echo htmlspecialchars($customerName); ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if (!empty($license_display)): ?>
                                <div class="compact-value">
                                    <span class="truncated" title="<?php echo htmlspecialchars($license_display); ?>"><?php echo compactStr($license_display, 8, 4); ?></span>
                                    <button class="btn btn-sm copy-btn" data-copy="<?php echo htmlspecialchars($license_display); ?>">Copy</button>
                                    <button class="btn btn-sm toggle-btn" data-target="license-<?php echo $order['id']; ?>">Details</button>
                                </div>
                                <div class="full-value hide mt-4" id="license-<?php echo $order['id']; ?>"><?php echo htmlspecialchars($license_display); ?></div>
                            <?php else: echo '-'; endif; ?>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td>
                            <?php if ($key && !empty($key['hwid'])): ?>
                                <div class="compact-value">
                                    <span class="truncated" title="<?php echo htmlspecialchars($key['hwid']); ?>"><?php echo compactStr($key['hwid'], 8, 4); ?></span>
                                    <button class="btn btn-sm copy-btn" data-copy="<?php echo htmlspecialchars($key['hwid']); ?>">Copy</button>
                                    <button class="btn btn-sm toggle-btn" data-target="hwid-<?php echo $order['id']; ?>">Details</button>
                                </div>
                                <div class="full-value hide mt-4" id="hwid-<?php echo $order['id']; ?>"><?php echo htmlspecialchars($key['hwid']); ?></div>
                            <?php else: echo '-'; endif; ?>
                        </td>
                        <?php endif; ?>
                        <td><?php if ($key) { echo !empty($key['expires_at']) ? date('d/m/Y H:i', strtotime($key['expires_at'])) : 'Vĩnh viễn'; } else { echo '-'; } ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <?php if (isset($_SESSION['user_id']) && ($order['user_id'] == $_SESSION['user_id'] || $isAdmin)): ?>
                                <?php
                                    // Riêng admin: không hiển thị link tải, chỉ quản lý HWID
                                    if ($isAdmin) {
                                        // Admin chỉ quản lý HWID
                                        if (!empty($license_display)): ?>
                                            <?php if (empty($key['hwid'])): ?>
                                                <button class="btn" onclick="openHWIDModal('<?php echo htmlspecialchars($license_display); ?>', '')">Nhập HWID</button>
                                            <?php else: ?>
                                                <button class="btn" onclick="openHWIDModal('<?php echo htmlspecialchars($license_display); ?>', '<?php echo htmlspecialchars($key['hwid'] ?? ''); ?>')">Đổi HWID</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php 
                                    } else {
                                        // Người dùng bình thường: hiển thị link tải
                                        $showLicenseDownload = !empty($key) && !empty($key['hwid']);
                                        $showProductLink = !empty($product) && !empty($product['software_link']) && !$showLicenseDownload;
                                    ?>

                                        <?php if (!empty($license_display)): ?>
                                            <?php if (!empty($key) && empty($key['hwid'])): ?>
                                                <button class="btn" onclick="openHWIDModal('<?php echo htmlspecialchars($license_display); ?>', '')">Nhập HWID</button>
                                            <?php else: ?>
                                                <button class="btn" onclick="openHWIDModal('<?php echo htmlspecialchars($license_display); ?>', '<?php echo htmlspecialchars($key['hwid'] ?? ''); ?>')">Đổi HWID</button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($showLicenseDownload): ?>
                                            <a class="btn" href="/ShopToolNro/api/download/license_download.php?license=<?php echo urlencode($license_display); ?>">Tải sản phẩm</a>
                                        <?php elseif ($showProductLink): ?>
                                            <a class="btn" href="<?php echo htmlspecialchars($product['software_link']); ?>" target="_blank">Tải sản phẩm</a>
                                        <?php endif; ?>

                                        <?php if (!$showLicenseDownload && !$showProductLink): ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    <?php } ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (empty($orders)): ?>
        <div class="empty-state">Không có đơn hàng nào.</div>
    <?php endif; ?>

<?php
    $hasPrev = $page > 1;
    $showPagination = $hasPrev || $hasNext;
?>

<?php if ($showPagination): ?>
    <div class="flex-center mt-14">
        <?php if ($hasPrev): ?>
            <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
        <?php endif; ?>
        <div class="page-indicator">Trang <?php echo $page; ?></div>
        <?php if ($hasNext): ?>
            <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<!-- HWID Change Modal -->
<div id="hwidModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Yêu cầu đổi HWID</h3>
            <span class="modal-close" onclick="closeHWIDModal()">&times;</span>
        </div>
        <form id="hwidForm">
                    <div class="modal-body">
                <input type="hidden" id="hwid_license" name="license_key" />
                <input type="hidden" id="hwid_mode" name="mode" value="set" />
                <p class="small mb-8">Bạn chỉ cần nhập <strong>HWID mới</strong>; nếu bạn là chủ sở hữu, hệ thống sẽ cập nhật ngay.</p>
                <div class="form-group"><label>HWID mới</label><input type="text" id="hwid_new" name="new_hwid" required class="w-100" placeholder="Nhập HWID mới (8-64 ký tự)" /></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeHWIDModal()">Hủy</button>
                <button type="submit" class="btn btn-primary" id="hwidSubmitBtn">Gửi yêu cầu</button>
            </div>
        </form>
    </div>
</div>

<div id="notificationContainer"></div>

<script>
// Notification system
function showNotification(message, type = 'info') {
    const container = document.getElementById('notificationContainer');
    const notificationId = 'notif-' + Date.now();
    
    const notification = document.createElement('div');
    notification.id = notificationId;
    notification.className = 'notification ' + type;
    notification.innerHTML = `
        ${message}
        <button class="close-btn" onclick="this.parentElement.remove();">×</button>
    `;
    
    container.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const el = document.getElementById(notificationId);
        if (el) {
            el.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => el.remove(), 300);
        }
    }, 5000);
}

// Add slideOut animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

</script>

<script>
function openHWIDModal(license, currentHWID) {
    var modal = document.getElementById('hwidModal');
    if (!modal) return;
    document.getElementById('hwid_license').value = license;
    document.getElementById('hwid_new').value = '';
    document.getElementById('hwid_mode').value = 'set';
    document.getElementById('hwidSubmitBtn').textContent = 'Lưu HWID';
    modal.classList.add('active');
}

function closeHWIDModal() {
    var modal = document.getElementById('hwidModal');
    if (!modal) return;
    modal.classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('hwidForm');
    if (!form) return;
    form.addEventListener('submit', async function(e){
        e.preventDefault();
        var newHWID = (document.getElementById('hwid_new').value || '').trim();
        var hwidRegex = /^[A-Za-z0-9\-_]{8,64}$/;
        if (!hwidRegex.test(newHWID)) {
            showNotification('HWID không hợp lệ. Vui lòng nhập 8-64 ký tự: chữ/số, dấu - hoặc _.', 'error');
            return;
        }
        var payload = {
            new_hwid: newHWID,
            license_key: document.getElementById('hwid_license').value,
            reason: 'User action via order page'
        };
        try {
            var resp = await fetch('/ShopToolNro/api/hwid/set_hwid.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
            });
            var text = await resp.text();
            var data;
            try { data = JSON.parse(text); } catch (e) { throw new Error('Server response is not JSON: ' + text); }
            if (resp.ok && data.success) {
                showNotification('HWID đã được lưu', 'success');
                closeHWIDModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message || ('Lỗi server: ' + (resp.status || '') + ' ' + text), 'error');
            }
        } catch (err) {
            console.error(err);
            showNotification('Lỗi gửi yêu cầu: ' + (err.message || err), 'error');
        }
    });

    // Copy and toggle handlers for license/hwid
    document.querySelectorAll('.copy-btn').forEach(function(btn){
        btn.addEventListener('click', async function(){
            var text = this.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(text);
                var old = this.textContent;
                this.textContent = 'Copied';
                var el = this;
                setTimeout(function(){ el.textContent = old; }, 1500);
            } catch (e) {
                showNotification('Không thể copy: ' + e.message, 'error');
            }
        });
    });

    document.querySelectorAll('.toggle-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = this.getAttribute('data-target');
            var el = document.getElementById(id);
            if (!el) return;
            if (el.style.display === 'none' || getComputedStyle(el).display === 'none') {
                el.style.display = 'block';
                this.textContent = 'Hide';
            } else {
                el.style.display = 'none';
                this.textContent = 'Details';
            }
        });
    });

});
</script>

<?php include '../layout/footer.php'; ?>