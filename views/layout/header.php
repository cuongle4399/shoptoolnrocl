<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include helper functions for timezone conversion
require_once __DIR__ . '/../../includes/functions.php';

$userBalance = '0 ₫';
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../../config/database.php';
        if (isset($pdo) && $pdo) {
            $stmt = $pdo->prepare("SELECT balance, avatar_url FROM public.users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $res = $stmt->fetch();
            if ($res) {
                $userBalance = number_format($res['balance'], 0, ',', '.') . ' ₫';
                // Always sync avatar from DB if it exists
                if (!empty($res['avatar_url'])) {
                    $_SESSION['user_avatar'] = $res['avatar_url'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Header balance fetch error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width,initial-scale=1,maximum-scale=5,user-scalable=yes,viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" content="#00bcd4">
    <title><?php echo $page_title ?? 'ShopToolNro'; ?></title>
    <link rel="icon" type="image/x-icon" href="/ShopToolNro/img/Logo.ico">
    <link rel="manifest" href="/ShopToolNro/manifest.json">
    <link rel="apple-touch-icon" href="/ShopToolNro/img/Logo.ico">

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/ShopToolNro/assets/css/style.css">
    <link rel="stylesheet" href="/ShopToolNro/assets/css/header-mobile-fix.css">
    <link rel="stylesheet" href="/ShopToolNro/assets/css/mobile-performance.css">
    <link rel="stylesheet" href="/ShopToolNro/assets/css/mobile-responsive.css">
    <link rel="stylesheet" href="/ShopToolNro/assets/css/table-scroll.css">
    <link rel="stylesheet" href="/ShopToolNro/assets/css/auth.css">

    <!-- Preload critical scripts -->
    <link rel="preload" href="/ShopToolNro/assets/js/api.js" as="script">
    <link rel="preload" href="/ShopToolNro/assets/js/main.js" as="script">

    <script defer src="/ShopToolNro/assets/js/api.js"></script>
    <script defer src="/ShopToolNro/assets/js/main.js"></script>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <script defer src="/ShopToolNro/assets/js/admin.js"></script>
    <?php endif; ?>
</head>

<body class="vibrant">
    <?php if (isset($_SESSION['user_id'])):
        // Lấy avatar từ session hoặc gán mặc định (logic random đã có sẵn trong session check)
        if (!isset($_SESSION['user_avatar'])) {
            $_SESSION['user_avatar'] = getRandomAvatar();
        }
        $userAvatarUrl = $_SESSION['user_avatar'];
        ?>
        <header class="topbar">
            <div class="topbar-content">
                <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu" type="button">☰</button>
                <div class="logo">ShopToolNroCL</div>
                <div class="header-user-info">
                    <div class="user-avatar" aria-hidden="true">
                        <img src="<?php echo htmlspecialchars($userAvatarUrl); ?>" alt="Avatar" width="32" height="32"
                            loading="eager">
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Tài khoản'); ?></span>
                    <span class="user-balance">Số dư:
                        <strong id="headerBalance"><?php echo $userBalance; ?></strong></span>
                    <a href="/ShopToolNro/views/pages/topup.php" class="btn-topup">Nạp tiền</a>
                    <a href="/ShopToolNro/api/auth/logout.php" class="btn-logout">Đăng xuất</a>
                </div>

                <script>
                    // Keep the script as a real-time update fallback if needed, or remove if total SSR is desired
                    // For now, let's keep it but it will only overwrite if API succeeds
                    document.addEventListener('DOMContentLoaded', async () => {
                        try {
                            const res = await fetch('/ShopToolNro/api/account/profile.php');
                            const data = await res.json();
                            if (data.status === 'success' && data.data && data.data.user) {
                                document.getElementById('headerBalance').textContent =
                                    new Intl.NumberFormat('vi-VN').format(data.data.user.balance) + ' ₫';
                            }
                        } catch (err) { }
                    });
                </script>
            </div>
        </header>

        <aside class="sidebar" id="mainSidebar">
            <div class="sidebar-header-mobile">
                <span class="sidebar-title">Menu</span>
                <button id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="Đóng menu" type="button">✕</button>
            </div>
            <div class="sidebar-inner">
                <div class="sidebar-logo" aria-hidden="true"></div>
                <nav>
                    <a href="/ShopToolNro/">Trang chủ</a>
                    <!-- Chức năng Cập nhập HWID Tool đã bị ẩn -->
                    <a href="/ShopToolNro/views/pages/orders.php">Quản lý đơn hàng</a>
                    <a href="/ShopToolNro/views/pages/topup_history.php">Lịch sử nạp tiền</a>
                    <?php if ($_SESSION['role'] !== 'admin'): ?>
                        <a href="/ShopToolNro/views/pages/my_keys.php">Quản lý Key đã mua</a>
                    <?php endif; ?>
                    <a href="/ShopToolNro/views/pages/profile.php">Đổi mật khẩu</a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <hr>
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
        <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

        <main class="content">
        <?php else: ?>
            <header>
                <div class="header-content">
                    <div class="logo">ShopToolNroCL</div>
                    <nav class="header-nav-guest">
                        <div class="nav-row-1">
                            <a href="/ShopToolNro/">Trang chủ</a>
                            <a href="https://www.facebook.com/cuong.le.810822" target="_blank"
                                rel="noopener noreferrer">Liên hệ</a>
                        </div>
                        <div class="nav-row-2">
                            <a href="/ShopToolNro/views/pages/login.php">Đăng nhập</a>
                            <a href="/ShopToolNro/views/pages/register.php">Đăng ký</a>
                        </div>
                    </nav>
                </div>
            </header>

            <main>
            <?php endif; ?>

            <!-- Alert Container -->
            <div id="alertContainer" class="alert-container"></div>