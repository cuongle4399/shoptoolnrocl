<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ShopToolNro'; ?></title>
    <link rel="icon" type="image/x-icon" href="/ShopToolNro/img/Logo.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/ShopToolNro/assets/css/style.css">
    <script defer src="/ShopToolNro/assets/js/api.js"></script>
    <script defer src="/ShopToolNro/assets/js/main.js"></script>
</head>
<body class="vibrant">
<?php if (isset($_SESSION['user_id'])): ?>
    <?php
        // Fetch latest user info (balance, etc.) for display in header
        require_once __DIR__ . '/../../config/database.php';
        require_once __DIR__ . '/../../src/classes/User.php';
        $database = new Database();
        $db = $database->connect();
        $userInfo = null;
        if ($db && isset($_SESSION['user_id'])) {
            $userClass = new User($db);
            $userInfo = $userClass->getUserById($_SESSION['user_id']);
        }
    ?>
    <header class="topbar">
        <div class="topbar-content">
            <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu" onclick="document.getElementById('mainSidebar').classList.toggle('open'); document.body.classList.toggle('sidebar-open'); document.getElementById('sidebarBackdrop').classList.toggle('visible');">☰</button>
            <div class="logo">ShopToolNro</div>
            <div class="header-user-info">
                <?php if ($userInfo): ?>
                    <span class="user-name"><?php echo htmlspecialchars($userInfo['username']); ?></span>
                    <span class="user-balance">Số dư: <strong><?php echo number_format($userInfo['balance'] ?? 0, 0, ',', '.'); ?> ₫</strong></span>
                    <a href="/ShopToolNro/views/pages/topup.php" class="btn-topup">Nạp tiền</a>
                    <a href="/ShopToolNro/api/auth/logout.php" class="btn-logout">Đăng xuất</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="mainSidebar">
        <div class="sidebar-inner">
            <div class="sidebar-logo" aria-hidden="true"></div>
            <nav>
                <a href="/ShopToolNro/">Trang chủ</a>
                <!-- Chức năng Cập nhập HWID Tool đã bị ẩn -->
                <a href="/ShopToolNro/views/pages/orders.php">Quản lý đơn hàng</a>
                <a href="/ShopToolNro/views/pages/profile.php">Đổi mật khẩu</a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <hr>
                    <h5 style="padding: 12px 20px; margin: 0; font-weight: 600; color: #999; font-size: 12px; text-transform: uppercase;">Admin Tools</h5>
                    <a href="/ShopToolNro/views/admin/manage_products.php">Quản lý sản phẩm</a>
                    <a href="/ShopToolNro/views/admin/manage_keys.php">Quản lý License Key</a>
                    <a href="/ShopToolNro/views/admin/manage_users.php">Quản lý người dùng</a>
                    <a href="/ShopToolNro/views/admin/manage_shop_notification.php">Quản lý thông báo</a>
                    <a href="/ShopToolNro/views/admin/manage_topup.php">Quản lý nạp tiền</a>
                    <a href="/ShopToolNro/views/admin/manage_revenue.php">Quản lý doanh thu</a>
                    <a href="/ShopToolNro/views/admin/manage_promos.php">Quản lý khuyến mãi</a>
                <?php endif; ?>
            </nav>
        </div>
    </aside>
    <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true" onclick="document.getElementById('mainSidebar').classList.remove('open'); document.body.classList.remove('sidebar-open'); this.classList.remove('visible');"></div>

    <main class="content">
<?php else: ?>
    <header>
        <div class="header-content">
            <div class="logo">ShopToolNro</div>
            <nav>
                <a href="/ShopToolNro/">Trang chủ</a>
                <a href="/ShopToolNro/views/pages/login.php">Đăng nhập</a>
                <a href="/ShopToolNro/views/pages/register.php">Đăng ký</a>
            </nav>
        </div>
    </header>

    <main>
<?php endif; ?>

<!-- Alert Container -->
<div id="alertContainer" class="alert-container"></div>
