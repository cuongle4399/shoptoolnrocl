<?php
$page_title = 'Admin Dashboard - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/User.php';
require_once '../../src/classes/Order.php';
require_once '../../src/classes/TopupRequest.php';

$database = new Database();
$db = $database->connect();
$userClass = new User($db);
$orderClass = new Order($db);
$topupClass = new TopupRequest($db);

// Use limited fetches for listing while using full fetch for totals (non-optimal but safe)
$usersAll = $userClass->getAllUsers();
$users = $userClass->getAllUsers(5, 0);
$ordersAll = $orderClass->getAllOrders();
$orders = $orderClass->getAllOrders(5, 0);
$topupRequestsAll = $topupClass->getAllTopupRequests();
$topupRequests = $topupClass->getPendingRequests(5, 0);
?>

<div class="main-content fade-in">
    <h1>Admin Dashboard</h1>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 30px 0;">
        <div class="gradient-card gradient-blue" style="padding: 20px; border-radius: 10px;">
            <h3>Tổng người dùng</h3>
            <p style="font-size: 32px; font-weight: bold;"><?php echo count($usersAll); ?></p>
        </div>
        <div class="gradient-card gradient-pink" style="padding: 20px; border-radius: 10px;">
            <h3>Tổng đơn hàng</h3>
            <p style="font-size: 32px; font-weight: bold;"><?php echo count($ordersAll); ?></p>
        </div>
        <div class="gradient-card gradient-cyan" style="padding: 20px; border-radius: 10px;">
            <h3>Yêu cầu nạp tiền</h3>
            <p style="font-size: 32px; font-weight: bold;"><?php echo count($topupRequestsAll); ?></p>
        </div>
        <div class="gradient-card gradient-green" style="padding: 20px; border-radius: 10px;">
            <h3>Doanh thu</h3>
            <p style="font-size: 32px; font-weight: bold;">
                <?php echo number_format(array_sum(array_column($orders, 'total_price')), 0, ',', '.'); ?>đ</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0;">
        <div>
            <h3><a href="/ShopToolNro/views/admin/manage_users.php" style="text-decoration: none; color: inherit;">Quản
                    lý người dùng →</a></h3>
            <div class="table-wrapper">
                <table style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Tên đăng nhập</th>
                            <th>Email</th>
                            <th>Số dư</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo number_format($user['balance'], 0, ',', '.'); ?> ₫</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h3><a href="/ShopToolNro/views/admin/manage_topup.php" style="text-decoration: none; color: inherit;">Yêu
                    cầu nạp tiền →</a></h3>
            <div class="table-wrapper">
                <table style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Người dùng</th>
                            <th>Số tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topupRequests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['username']); ?></td>
                                <td><?php echo number_format($req['amount'], 0, ',', '.'); ?> ₫</td>
                                <td><span style="background: #fff3cd; padding: 3px 8px; border-radius: 3px;">Chờ</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin: 30px 0;">
        <div>
            <h3>
                <a href="/ShopToolNro/views/admin/manage_products.php"
                    style="text-decoration: none; color: inherit; margin-right: 10px;">Quản lý sản phẩm →</a>
                <a href="/ShopToolNro/views/admin/manage_keys.php"
                    style="text-decoration: none; color: inherit; margin-right: 10px;">Quản lý Key →</a>
                <a href="/ShopToolNro/views/admin/manage_topup.php"
                    style="text-decoration: none; color: inherit; margin-right: 10px;">Quản lý nạp tiền →</a>
                <a href="/ShopToolNro/views/admin/manage_revenue.php"
                    style="text-decoration: none; color: inherit; margin-right: 10px;">Quản lý doanh thu →</a>
                <a href="/ShopToolNro/views/admin/manage_promos.php"
                    style="text-decoration: none; color: inherit; margin-right: 10px;">Mã khuyến mãi →</a>
                <a href="/ShopToolNro/views/admin/sepay_webhooks.php"
                    style="text-decoration: none; color: inherit; margin-left: 10px;">Lịch sử Webhook →</a>
                <a href="/ShopToolNro/views/admin/logs.php"
                    style="text-decoration: none; color: inherit; margin-left: 10px;">API Error Logs →</a>
            </h3>
        </div>
    </div>
</div>

<?php include '../layout/footer.php'; ?>