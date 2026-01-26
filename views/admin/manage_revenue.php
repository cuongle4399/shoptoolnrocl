<?php
// manage_revenue.php - Admin revenue dashboard
$page_title = 'Quản lý doanh thu - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/Order.php';
require_once '../../src/classes/TopupRequest.php';
require_once '../../src/classes/User.php';

$database = new Database();
$db = $database->connect();
$orderClass = new Order($db);
$topupClass = new TopupRequest($db);
$userClass = new User($db);

// Totals
$totalOrderRevenue = $orderClass->getTotalRevenue();
$allTopups = $topupClass->getAllTopupRequests(1000, 0);
$approvedTopups = array_filter($allTopups, fn($t) => ($t['status'] ?? '') === 'approved');
$totalApprovedTopups = array_sum(array_map(fn($t) => (float)($t['amount'] ?? 0), $approvedTopups));

// Pagination for orders
$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$allOrders = $orderClass->getAllOrders(1000, 0);
$totalOrders = count($allOrders);
$recentOrders = array_slice($allOrders, $offset, $perPage + 1);
$hasNextOrders = count($recentOrders) > $perPage;
if ($hasNextOrders) array_pop($recentOrders);

// Pagination for topups
$topupsPerPage = 7;
$topupPage = isset($_GET['topup_page']) ? max(1, (int)$_GET['topup_page']) : 1;
$topupOffset = ($topupPage - 1) * $topupsPerPage;

$totalApprovedCount = count($approvedTopups);
$recentTopups = array_slice($approvedTopups, $topupOffset, $topupsPerPage + 1);
$hasNextTopups = count($recentTopups) > $topupsPerPage;
if ($hasNextTopups) array_pop($recentTopups);

function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }
function topupPageLink($p) { $qs = $_GET; $qs['topup_page'] = $p; return '?' . http_build_query($qs); }

// Helper function to get order status based on completed_at
function getOrderStatus($order) {
    return !empty($order['completed_at']) ? 'Đã hoàn thành' : 'Chờ xử lý';
}
?>

<div class="main-content fade-in">
    <h1>Quản lý doanh thu</h1>

    <div class="grid-2 mt-18">
        <div class="gradient-card gradient-blue card-pad">
            <h3>Doanh thu từ đơn hàng</h3>
            <p class="revenue-amount"><span class="money"><?php echo number_format($totalOrderRevenue, 0, ',', '.'); ?> ₫</span></p>
            <p class="revenue-desc">Tổng doanh thu từ tất cả đơn hàng đã hoàn thành</p>
        </div>

        <div class="gradient-card gradient-green card-pad">
            <h3>Tiền nạp đã duyệt</h3>
            <p class="revenue-amount"><span class="money"><?php echo number_format($totalApprovedTopups, 0, ',', '.'); ?> ₫</span></p>
            <p class="revenue-desc">Tổng tiền người dùng đã nạp (đã duyệt)</p>
        </div>
    </div>

    <h3 class="mt-28">Đơn hàng gần đây</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>ID</th><th>Người mua</th><th>Tổng</th><th>Trạng thái</th><th>Ngày</th></tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="5" class="text-center">Không có đơn hàng</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $o): ?>
                        <?php $buyer = $userClass->getUserById($o['user_id']); ?>
                        <tr>
                            <td><?php echo $o['id']; ?></td>
                            <td><?php echo htmlspecialchars($buyer['username'] ?? 'UID:'.$o['user_id']); ?></td>
                            <td><span class="money"><?php echo number_format($o['total_price'] ?? 0, 0, ',', '.'); ?> ₫</span></td>
                            <td><?php echo htmlspecialchars(getOrderStatus($o)); ?></td>
                            <td><?php echo convertToVNTime($o['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalOrders > $perPage): ?>
    <div class="flex-center mt-14">
        <?php if ($page > 1): ?>
            <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
        <?php endif; ?>
        <div class="page-indicator">Trang <?php echo $page; ?>/<?php echo ceil($totalOrders / $perPage); ?></div>
        <?php if ($hasNextOrders): ?>
            <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <h3 style="margin-top:28px;">Nạp tiền đã duyệt gần đây</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>ID</th><th>Người dùng</th><th>Số tiền</th><th>Ghi chú</th><th>Ngày</th></tr>
            </thead>
            <tbody>
                <?php if (empty($recentTopups)): ?>
                    <tr><td colspan="5" class="text-center">Không có ghi nhận nạp tiền đã duyệt</td></tr> 
                <?php else: ?>
                    <?php foreach ($recentTopups as $t): ?>
                        <?php $user = $userClass->getUserById($t['user_id']); ?>
                        <tr>
                            <td><?php echo $t['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username'] ?? 'UID:'.$t['user_id']); ?></td>
                            <td><span class="money"><?php echo number_format($t['amount'] ?? 0, 0, ',', '.'); ?> ₫</span></td>
                            <td><?php echo htmlspecialchars($t['admin_note'] ?? '-'); ?></td>
                            <td><?php echo convertToVNTime($t['approved_at'] ?? $t['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalApprovedCount > $topupsPerPage): ?>
    <div class="flex-center mt-14">
        <?php if ($topupPage > 1): ?>
            <a class="btn" href="<?php echo topupPageLink($topupPage - 1); ?>">&laquo; Trước</a>
        <?php endif; ?>
        <div class="page-indicator">Trang <?php echo $topupPage; ?>/<?php echo ceil($totalApprovedCount / $topupsPerPage); ?></div>
        <?php if ($hasNextTopups): ?>
            <a class="btn" href="<?php echo topupPageLink($topupPage + 1); ?>">Tiếp &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php include '../layout/footer.php'; ?>