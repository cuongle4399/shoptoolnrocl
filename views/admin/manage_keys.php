<?php
$page_title = 'Quản lý Key - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/License.php';
require_once '../../src/classes/Product.php';
require_once '../../src/classes/User.php';

$database = new Database();
$db = $database->connect();
$licenseClass = new License($db);
$productClass = new Product($db);
$userClass = new User($db);

$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$keys = $licenseClass->getAllKeys($perPage + 1, $offset);
$hasNext = count($keys) > $perPage;
if ($hasNext) array_pop($keys);
if ($page > 1 && empty($keys)) { header('Location: ?page=' . ($page - 1)); exit; }
function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }
$products = $productClass->getAllProducts(false); // include inactive for admin actions
$productMap = [];
foreach ($products as $p) { $productMap[$p['id']] = $p['name']; }
?>

<div class="main-content fade-in">
    <h1>Quản lý License Key</h1>
    
    <table>
        <thead>
            <tr>
                <th>License Key</th>
                <th>Sản phẩm</th>
                <th>Người dùng</th>
                <th>HWID</th>
                <th>Trạng thái</th>
                <th>Hết hạn</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($keys)): foreach ($keys as $key): 
                $getUserName = '-';
                if (!empty($key['user_id'])) {
                    $user = $userClass->getUserById((int)$key['user_id']);
                    if ($user) $getUserName = htmlspecialchars($user['username']);
                }
            ?>
                <tr>
                    <td><code style="background: #f0f0f0; padding: 3px 5px; border-radius: 3px;"><?php echo htmlspecialchars(substr($key['license_key'], 0, 15)); ?>...</code></td>
                    <td><?php echo htmlspecialchars($productMap[$key['product_id']] ?? 'Unknown'); ?></td>
                    <td><?php echo $getUserName; ?></td>
                    <td><code style="background: #f0f0f0; padding: 3px 5px; border-radius: 3px;"><?php echo htmlspecialchars($key['hwid'] ? substr($key['hwid'], 0, 15) . '...' : '-'); ?></code></td>
                    <td>
                        <span style="padding: 3px 8px; border-radius: 3px; background: <?php echo $key['status'] === 'active' ? '#d4edda' : '#f8d7da'; ?>;">
                            <?php echo $key['status'] === 'active' ? 'Hoạt động' : 'Bị khóa'; ?>
                        </span>
                    </td>
                    <td><?php echo $key['expires_at'] ? date('d/m/Y', strtotime($key['expires_at'])) : 'Không hạn'; ?></td>
                    <td>
                        <?php if ($key['status'] === 'active'): ?>
                            <button class="btn btn-warning btn-sm" onclick="toggleKeyStatus(<?php echo $key['id']; ?>, 'inactive')">Khóa</button>
                        <?php else: ?>
                            <button class="btn btn-success btn-sm" onclick="toggleKeyStatus(<?php echo $key['id']; ?>, 'active')">Mở khóa</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if (empty($keys)): ?>
        <div class="empty-state">Không có license key nào</div>
    <?php endif; ?>

<?php
    $hasPrev = $page > 1;
    $showPagination = $hasPrev || $hasNext;
?>

<?php if ($showPagination): ?>
    <div style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:14px;">
        <?php if ($hasPrev): ?>
            <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
        <?php endif; ?>
        <div style="padding:6px 12px;color:var(--text-light);">Trang <?php echo $page; ?></div>
        <?php if ($hasNext): ?>
            <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<script>
function toggleKeyStatus(keyId, newStatus) {
    const action = newStatus === 'active' ? 'mở khóa' : 'khóa';
    if (!confirm(`Bạn chắc chắn muốn ${action} key này?`)) {
        return;
    }

    fetch('/ShopToolNro/api/admin/toggle_key_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({key_id: keyId, status: newStatus})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            showNotification(`Đã ${action} thành công`, 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showNotification(data.message || 'Lỗi', 'error');
        }
    }).catch(err => {
        showNotification('Lỗi: ' + err.message, 'error');
    });
}
</script>

<?php include '../layout/footer.php'; ?>
