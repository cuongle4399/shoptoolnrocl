<?php
require_once 'config.php';
require_once 'config/database.php';
require_once 'src/classes/User.php';

$error = '';

// Nếu đã login rồi, redirect đến dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Xử lý POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate Limiting cho login
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit('admin_login', $ip, 5, 300)) {
        logAudit('admin_login', 'Rate Limit', 'Too many attempts from IP: ' . $ip, 'failed');
        $error = "Bạn đã thử quá nhiều lần. Vui lòng thử lại sau 5 phút.";
    } else {
        $u = sanitizeInput($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';

        // Try to authenticate with database first
        $authenticated = false;
        $user_id = null;
        $username = null;

        try {
            $database = new Database();
            $db = $database->connect();
            if ($db) {
                $userClass = new User($db);
                $user = $userClass->getUserByUsername($u);
                
                if ($user && $user['role'] === 'admin') {
                    // Check password (plaintext as per system)
                    if ($p === $user['password_']) {
                        $authenticated = true;
                        $user_id = $user['id'];
                        $username = $user['username'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Admin login DB error: " . $e->getMessage());
        }

        // Fallback to hardcoded credentials if database auth fails
        if (!$authenticated && $u === ADMIN_USER && $p === ADMIN_PASSWORD) {
            // Try to get admin user from database or use hardcoded fallback
            try {
                $database = new Database();
                $db = $database->connect();
                if ($db) {
                    $userClass = new User($db);
                    $user = $userClass->getUserByUsername(ADMIN_USER);
                    if ($user) {
                        $authenticated = true;
                        $user_id = $user['id'];
                        $username = $user['username'];
                    }
                }
            } catch (Exception $e) {
                error_log("Admin hardcoded fallback error: " . $e->getMessage());
            }
            
            // If still not authenticated via database, use fallback
            if (!$authenticated && !$user_id) {
                $authenticated = true;
                $user_id = 0; // Temporary ID for hardcoded admin
                $username = ADMIN_USER;
            }
        }

        if ($authenticated) {
            // Chống Session Hijacking
            session_regenerate_id(true); 

            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'admin';
            $_SESSION['admin_login_time'] = time();
            $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
            
            // Keep old session vars for backward compatibility
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            
            logAudit('admin_login', $username, 'Admin logged in', 'success');

            header('Location: admin_dashboard.php');
            exit;
        } else {
            logAudit('admin_login', $u, 'Invalid credentials', 'failed');
            $error = "Sai thông tin đăng nhập!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="icon" href="img/Logo.ico" type="image/x-icon">
</head>
<body>
    <div class="customer-portal">
        <h2>🛡️ Đăng nhập Admin</h2>
        
        <div class="menu-switch">
            <a href="index.php" class="menu-customer">👤 Customer Portal</a>
            <a href="admin_login.php" class="menu-admin active">🛡️ Admin Login</a>
        </div>

        <?php if ($error): ?>
            <div class="message-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="content-wrapper">
            <h3>Đăng nhập Admin</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Tài khoản:</label>
                    <input type="text" id="username" name="username" required placeholder="Tên đăng nhập" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="password">Mật khẩu:</label>
                    <input type="password" id="password" name="password" required placeholder="Mật khẩu" autocomplete="current-password">
                </div>
                
                <button type="submit">Login</button>
            </form>
        </div>
    </div>

    <script src="javascript/index.js"></script>
</body>
</html>