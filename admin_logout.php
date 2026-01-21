<?php
require_once 'config.php';

// 🔒 GHI LOG LOGOUT
if (isset($_SESSION['admin_username'])) {
    logAudit('admin_logout', $_SESSION['admin_username'], 'Admin logged out', 'success');
}

// 🔒 LOGOUT AN TOÀN
safeLogout();

// ✅ CHUYỂN HƯỚNG VỀ LOGIN (SỬA LỖI: Đổi từ index.php?logout=1 sang index.php?mode=admin_login)
header('Location: index.php?mode=admin_login&logout=1');
exit;
?>