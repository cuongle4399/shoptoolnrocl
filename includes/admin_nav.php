<?php
// Sidebar menu item component
// Use this in your admin dashboard
?>
<style>
    .admin-sidebar {
        background: #2c3e50;
        color: white;
        padding: 0;
        border-radius: 0;
    }

    .admin-sidebar a {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: white;
        text-decoration: none;
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
    }

    .admin-sidebar a:hover {
        background: #34495e;
        border-left-color: #3498db;
    }

    .admin-sidebar a.active {
        background: #3498db;
        border-left-color: #2980b9;
    }

    .admin-sidebar a .icon {
        margin-right: 12px;
        font-size: 18px;
    }
</style>

<nav class="admin-sidebar">
    <a href="/admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin_dashboard.php' ? 'active' : ''; ?>">
        <span class="icon">🏠</span>
        <span>Trang chủ</span>
    </a>
    <a href="/pages/admin/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/pages/admin') !== false ? 'active' : ''; ?>">
        <span class="icon">🛠️</span>
        <span>Quản lý sản phẩm</span>
    </a>
    <a href="/views/admin/manage_users.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'manage_users') !== false ? 'active' : ''; ?>">
        <span class="icon">👥</span>
        <span>Quản lý người dùng</span>
    </a>
    <a href="/views/admin/manage_keys.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'manage_keys') !== false ? 'active' : ''; ?>">
        <span class="icon">🔑</span>
        <span>Quản lý License Key</span>
    </a>
    <a href="/views/admin/error_logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'error_logs.php' ? 'active' : ''; ?>">
        <span class="icon">⚠️</span>
        <span>Nhật ký lỗi</span>
    </a>
    <a href="/admin_logout.php">
        <span class="icon">🚪</span>
        <span>Đăng xuất</span>
    </a>
</nav>
