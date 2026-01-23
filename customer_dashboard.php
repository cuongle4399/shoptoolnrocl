<?php
require_once 'config.php';

$msg = '';
$error = '';
$key_info = null;

// Hằng số session
define('CUSTOMER_LOGGED_IN', 'customer_logged_in');
define('CUSTOMER_HWID', 'customer_hwid');
define('CUSTOMER_LICENSE_KEY', 'customer_license_key');
define('CUSTOMER_INFO', 'customer_info');

// ============================================
// DOWNLOAD KEY FILE - Handler
// ============================================
if (isset($_GET['download_key'])) {
    require_once 'handlers/download_key.php';
    exit;
}

// ============================================
// KIỂM TRA ĐĂNG NHẬP
// ============================================
if (!isset($_SESSION[CUSTOMER_LOGGED_IN]) || $_SESSION[CUSTOMER_LOGGED_IN] !== true) {
    header("Location: index.php?mode=customer");
    exit;
}

// Lấy thông tin key từ database
$key_info = getKeyByLicense($_SESSION[CUSTOMER_LICENSE_KEY] ?? '');

if (!$key_info || $key_info['status'] === 'banned') {
    $error = "Phiên đăng nhập đã hết hạn hoặc Key bị BAN.";
    safeLogout();
    header("Location: index.php?mode=customer");
    exit;
}

$_SESSION[CUSTOMER_HWID] = $key_info['hwid'];
$_SESSION[CUSTOMER_INFO] = $key_info['user_info'];

// ============================================
// LOGOUT
// ============================================
if (isset($_GET['logout'])) {
    safeLogout();
    header("Location: index.php?mode=customer");
    exit;
}

// ============================================
// CẬP NHẬT HWID
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_hwid') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Lỗi bảo mật: CSRF Token không hợp lệ!";
        logAudit('csrf_fail', $_SESSION[CUSTOMER_HWID], 'CSRF token mismatch on HWID update', 'failed');
    } else {
        $new_hwid = sanitizeInput($_POST['new_hwid'] ?? '');
        $res = handleUpdateHwidAndKey($_SESSION[CUSTOMER_LICENSE_KEY], $new_hwid, $_SESSION[CUSTOMER_INFO]);
        if ($res['success']) {
            $msg = "Đổi HWID và License Key thành công!";
            $_SESSION[CUSTOMER_HWID] = $new_hwid;
            $_SESSION[CUSTOMER_LICENSE_KEY] = $res['key'];
            $key_info = getKeyByLicense($res['key']);
        } else {
            $error = $res['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5,user-scalable=yes,viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Thông tin tài khoản</title>
    <link rel="stylesheet" href="css/customer_dashboard.css">
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <link rel="icon" href="img/Logo.ico" type="image/x-icon">
</head>
<body>
    <div class="customer-portal">
        <div class="account-header">
            <h3>Thông tin tài khoản</h3>
            <a href="?logout=1" onclick="return confirm('Bạn chắc chắn muốn thoát?');">Thoát</a>
        </div>

        <div class="content-wrapper">
            <?php if ($msg): ?>
                <div class="message-success">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="account-greeting">
                <strong>Xin chào:</strong> 
                <?php echo htmlspecialchars($_SESSION[CUSTOMER_INFO] ?: 'Khách hàng'); ?>
            </div>

            <?php if ($key_info): ?>
                <?php $status = getLicenseStatus($key_info); ?>
                
                <h3>Thông tin License</h3>
                <ul class="key-info-list">
                    <li>
                        <strong>Trạng thái</strong> 
                        <span style="color: <?php echo $status['color']; ?>;" class="font-weight-bold">
                            <?php 
                            if ($status['text'] === 'BANNED') echo $status['text'];
                            elseif ($status['text'] === 'EXPIRED') echo $status['text'];
                            elseif (strpos($status['text'], 'Cảnh báo') !== false) echo $status['text'];
                            else echo $status['text'];
                            ?>
                        </span>
                    </li>

                    <li>
                        <strong>HWID:</strong> 
                        <span class="hwid-badge"><?php echo htmlspecialchars(substr($key_info['hwid'], 0, 20)); ?></span> 
                    </li>

                    <li>
                        <strong>Hết hạn:</strong> 
                        <span style="color: <?php echo $status['color']; ?>;" class="fw-600"><?php echo htmlspecialchars($key_info['expires_at']); ?></span>
                    </li>

                    <li>
                        <strong>Còn lại:</strong> 
                        <span style="color: <?php echo $status['color']; ?>;" class="fw-600"><?php echo $status['remaining_days']; ?> ngày</span>
                    </li>

                    <?php if ($status['remaining_days'] > 0 && $status['remaining_days'] <= 3): ?>
                        <li class="warning-box" style="grid-column: 1 / -1;">
                            <strong class="text-secondary">Cảnh báo:</strong>
                            <span class="text-secondary">License sắp hết hạn trong <strong><?php echo $status['remaining_days']; ?> ngày</strong></span>
                        </li>
                    <?php endif; ?>

                    <li style="grid-column: 1 / -1;">
                        <strong>License Key:</strong>
                        <textarea id="current-key" readonly><?php echo htmlspecialchars($key_info['license_key']); ?></textarea>
                        <div class="key-action-buttons">
                            <button type="button" class="btn-copy" onclick="copyToClipboard('current-key')">Copy Key</button>
                            <a href="?download_key=1" class="btn-download">Xuất File</a>
                        </div>
                    </li>
                </ul>

                <!-- Chức năng Đổi HWID được ẩn -->
                <!-- Người dùng có thể liên hệ support để thay đổi HWID -->

            <?php else: ?>
                <div class="message-error">
                    Lỗi: Không thể tải thông tin key.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="javascript/index.js"></script>
    <script src="assets/js/mobile-responsive.js"></script>
</body>
</html>