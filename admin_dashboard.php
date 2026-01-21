<?php
require_once 'config.php';

// ===== HÀM FORMAT THỜI GIAN =====
function formatDateTime($dateString) {
    if (empty($dateString)) {
        return 'Chưa có';
    }
    try {
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return $dateString;
        }
        return date('d-m-Y H:i:s', $timestamp);
    } catch (Exception $e) {
        return $dateString;
    }
}

// ===== SESSION CHECK =====
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Backward compatibility check for old session
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: admin_login.php');
        exit;
    }
}

if (isset($_SESSION['admin_login_time']) && time() - $_SESSION['admin_login_time'] > 1800) {
    logAudit('admin_session_timeout', $_SESSION['admin_username'] ?? $_SESSION['username'], 'Session expired', 'failed');
    safeLogout(); 
    header('Location: admin_login.php?expired=1'); 
    exit;
}

$_SESSION['admin_login_time'] = time(); 

$message = '';
$error = '';

// ===== HANDLE POST ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Lỗi bảo mật: CSRF Token không hợp lệ hoặc đã hết hạn!";
        logAudit('csrf_fail', $_SESSION['admin_username'], 'CSRF token mismatch on action: ' . ($_POST['action'] ?? 'N/A'), 'failed');
    } else {
        $act = $_POST['action'];
        $res = ['success' => false, 'message' => ''];
        
        // ===== ROUTER HÀNH ĐỘNG =====
        if ($act === 'create') {
            $res = handleCreateLicense(
                sanitizeInput($_POST['hwid'] ?? ''), 
                sanitizeInput($_POST['user_info'] ?? ''), 
                (int)($_POST['days'] ?? 0)
            );
        } 
        elseif ($act === 'ban') {
            $license_key = sanitizeInput($_POST['license_key'] ?? '');
            if (empty($license_key)) {
                $error = "Lỗi: Không tìm thấy License Key!";
            } else {
                $res = handleBanLicense($license_key);
            }
        } 
        elseif ($act === 'unban') {
            $license_key = sanitizeInput($_POST['license_key'] ?? '');
            if (empty($license_key)) {
                $error = "Lỗi: Không tìm thấy License Key!";
            } else {
                $res = handleUnbanLicense($license_key);
            }
        } 
        elseif ($act === 'renew') {
            $license_key = sanitizeInput($_POST['license_key'] ?? '');
            $renew_days = (int)($_POST['renew_days'] ?? 0);
            if (empty($license_key)) {
                $error = "Lỗi: Không tìm thấy License Key!";
            } elseif ($renew_days < 1) {
                $error = "Lỗi: Số ngày không hợp lệ!";
            } else {
                $res = handleRenewLicense($license_key, $renew_days);
            }
        } 
        elseif ($act === 'delete') {
            $license_key = sanitizeInput($_POST['license_key'] ?? '');
            if (empty($license_key)) {
                $error = "Lỗi: Không tìm thấy License Key!";
            } else {
                $res = handleDeleteLicense($license_key);
            }
        }

        // Set message hoặc error
        if ($res['success']) {
            $message = $res['message'];
        } elseif (!$error && isset($res['message'])) {
            $error = $res['message'];
        }
    }
}

// ===== LẤY DANH SÁCH KEYS =====
try {
    $all_keys = getAllKeys();
} catch (Exception $e) {
    $error = "Lỗi khi tải dữ liệu từ Supabase. Vui lòng kiểm tra log và cấu hình.";
    $all_keys = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" href="img/Logo.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/admin_dashboard.css">
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <header class="header-bar">
            <h2>🛡️ Admin Dashboard</h2>
            <div>
                User: <b><?php echo htmlspecialchars($_SESSION['admin_username']); ?></b> | 
                <a href="admin_logout.php">Đăng xuất</a>
            </div>
        </header>

        <!-- MESSAGES -->
        <?php if ($message): ?>
            <div class="message-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- CREATE KEY FORM -->
        <fieldset>
            <legend>Tạo License Key Mới</legend>
            <form method="POST" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <label style="display: flex; align-items: center; gap: 8px;">
                    HWID: 
                    <input type="text" name="hwid" required placeholder="8-64 ký tự (A-Z, 0-9, -, _)" style="width: 200px;">
                </label>
                
                <label style="display: flex; align-items: center; gap: 8px;">
                    User Info: 
                    <input type="text" name="user_info" placeholder="Thông tin khách hàng" style="width: 200px;">
                </label>
                
                <label style="display: flex; align-items: center; gap: 8px;">
                    Ngày: 
                    <input type="number" name="days" value="30" min="1" max="10000" style="width: 160px;">
                </label>
                
                <button type="submit" style="background-color: #007bff; padding: 8px 20px; white-space: nowrap;">➕ Tạo Key</button>
            </form>
        </fieldset>
        
        <!-- TABLE HEADER -->
        <h3 style="margin-bottom: 15px;">📊 Tổng số Key: <span style="color: #007bff;"><?php echo count($all_keys); ?></span></h3>
        
        <!-- KEYS TABLE -->
        <table id="keysTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>HWID</th>
                    <th>User Info</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Expires At</th>
                    <th>Last Check</th>
                    <th>License Key</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_keys)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px; color: #999;">
                            KHÔNG CÓ DỮ LIỆU - Vui lòng tạo License Key
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_keys as $key): ?>
                        <?php $status = getLicenseStatus($key); ?>
                        <tr class="key-row" 
                            data-key-info='<?php echo htmlspecialchars(json_encode($key, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>' 
                            data-status='<?php echo htmlspecialchars($status['text'], ENT_QUOTES, 'UTF-8'); ?>' 
                            data-remaining-days='<?php echo htmlspecialchars($status['remaining_days'], ENT_QUOTES, 'UTF-8'); ?>'>
                            <td><?php echo htmlspecialchars($key['id']); ?></td>
                            <td><?php echo htmlspecialchars($key['hwid'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($key['user_info'] ?? ''); ?></td>
                            <td style="color: <?php echo $status['color']; ?>; font-weight: bold;">
                                <?php echo htmlspecialchars($status['text']); ?>
                            </td>
                            <td><?php echo formatDateTime($key['created_at'] ?? ''); ?></td>
                            <td><?php echo formatDateTime($key['expires_at'] ?? ''); ?></td>
                            <td><?php echo formatDateTime($key['last_check'] ?? ''); ?></td>
                            <td>
                                <textarea readonly rows="1" cols="30"><?php echo htmlspecialchars($key['license_key'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- KEY INFO SECTION -->
        <div id="selectedKeyInfo" class="card" style="display: none; margin-top: 30px; border: 2px solid var(--accent); border-radius: 8px;">
            <h3 style="margin-top: 0; color: var(--accent);">📋 Thông tin & Thao tác Key Đã chọn</h3>
            
            <!-- KEY DETAILS -->
            <div class="key-details-grid card" style="margin-bottom: 20px; padding: 15px; border-radius: 6px;">
                <strong>ID:</strong>
                <span id="info-id">-</span>
                
                <strong>User Info:</strong>
                <span id="info-user-info">-</span>
                
                <strong>License Key:</strong>
                <input type="text" id="info-license-key" readonly class="monospace-input" style="width:100%; padding:8px; border-radius:4px; font-family: monospace;">
                
                <strong>HWID:</strong>
                <input type="text" id="info-hwid" readonly class="monospace-input" style="width:100%; padding:8px; border-radius:4px; font-family: monospace;">
                
                <strong>Status:</strong>
                <span id="info-status" style="font-weight: bold;">-</span>
                
                <strong>Remaining Days:</strong>
                <span id="info-remaining-days" style="font-weight: bold;">-</span>
                
                <strong>Created At:</strong>
                <span id="info-created">-</span>
                
                <strong>Expires At:</strong>
                <span id="info-expires">-</span>
                
                <strong>Last Check:</strong>
                <span id="info-last-check">-</span>
                
                <span></span>
            </div>

            <!-- COPY BUTTONS -->
            <div class="copy-buttons" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <button type="button" onclick="copyToClipboard('info-license-key')" style="padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; flex: 1; min-width: 150px;">
                    📋 Copy License Key
                </button>
                <button type="button" onclick="copyToClipboard('info-hwid')" style="padding: 10px 20px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; flex: 1; min-width: 150px;">
                    📋 Copy HWID
                </button>
            </div>

            <hr style="margin: 20px 0; border: none; border-top: 2px solid #dee2e6;">

            <!-- ACTION BUTTONS -->
            <h4 style="color: #007bff; margin-bottom: 15px;">⚡ Thao tác nhanh</h4>
            
            <div class="action-buttons-group" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                
                <!-- BAN/UNBAN FORM - FIXED -->
                <form method="POST" id="ban-unban-form" style="margin: 0; flex: 1 1 180px;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="ban-unban-action" value="ban">
                    <input type="hidden" name="license_key" id="ban-unban-key" value="">
                    <button type="submit" id="btn-ban-unban" style="width: 100%; padding: 12px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">
                        🔒 BAN KEY
                    </button>
                </form>
                
                <!-- RENEW FORM -->
                <form method="POST" style="margin: 0; display: flex; gap: 5px; align-items: center; flex: 2 1 250px; padding: 5px; background-color: #e9ecef; border-radius: 4px;">
                    <input type="hidden" name="action" value="renew">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="license_key" id="action-renew-key" value="">
                    <label style="font-weight: bold; white-space: nowrap; margin-left: 5px; font-size: 13px;">Gia hạn:</label>
                    <input type="number" name="renew_days" value="30" min="1" max="10000" style="flex-grow: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">
                    <span style="margin-right: 5px; white-space: nowrap; font-size: 13px;">ngày</span>
                    <button type="submit" style="padding: 10px 15px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px;">
                        ⏱️ GIA HẠN
                    </button>
                </form>
                
                <!-- DELETE FORM -->
                <form method="POST" onsubmit="return confirm('XÓA VĨNH VIỄN key này? Hành động không thể hoàn tác!');" style="margin: 0; flex: 1 1 180px;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="license_key" id="action-delete-key" value="">
                    <button type="submit" style="width: 100%; padding: 12px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px;">
                        🗑️ XÓA KEY
                    </button>
                </form>
            </div>

            <!-- CLOSE BUTTON -->
            <button type="button" onclick="closeKeyInfo()" style="width: 100%; padding: 10px; background-color: #e9ecef; color: #333; border: 1px solid #ced4da; border-radius: 4px; cursor: pointer; font-weight: bold;">
                Đóng
            </button>
        </div>

        <br><br>
        
        <!-- DEBUG INFO -->
        <details class="debug-info" style="margin-top: 20px;"> 
            <summary style="cursor: pointer; font-weight: bold;">ℹ️ Thông tin kết nối</summary>
            <pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; font-size: 12px; margin: 10px 0 0 0;">
Supabase URL: <?php echo SUPABASE_URL; ?>
Table Name: <?php echo TABLE_NAME; ?>
API Key Status: Đã cấu hình
Session Admin: <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'N/A'); ?>
Login Time: <?php echo date('d-m-Y H:i:s', $_SESSION['admin_login_time'] ?? 0); ?>
Total Keys: <?php echo count($all_keys); ?>
PHP Version: <?php echo phpversion(); ?>
            </pre>
        </details>
    </div>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Fallback showNotification nếu main.js/admin.js chưa load
        if (typeof showNotification === 'undefined') {
            window.showNotification = function(message, type = 'success', duration = 3200) {
                console.log('showNotification called:', {message, type, duration});
                let container = document.querySelector('.toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'toast-container';
                    document.body.appendChild(container);
                }
                
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-body">${message}</div>
                    <button class="toast-close" onclick="this.parentElement.remove()">×</button>
                `;
                
                container.appendChild(toast);
                requestAnimationFrame(() => toast.classList.add('show'));
                
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            };
        }
        
        // ===== FORMAT TIME IN JAVASCRIPT =====
        function formatDateTime(dateString) {
            if (!dateString) return 'Chưa có';
            
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                
                const pad = (n) => String(n).padStart(2, '0');
                const day = pad(date.getDate());
                const month = pad(date.getMonth() + 1);
                const year = date.getFullYear();
                const hours = pad(date.getHours());
                const minutes = pad(date.getMinutes());
                const seconds = pad(date.getSeconds());
                
                return `${day}-${month}-${year} ${hours}:${minutes}:${seconds}`;
            } catch (e) {
                return dateString;
            }
        }

        // ===== XỬ LÝ CLICK VÀO HÀNG BẢNG =====
        document.querySelectorAll('.key-row').forEach(row => {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function() {
                try {
                    const keyInfo = JSON.parse(this.getAttribute('data-key-info'));
                    const status = this.getAttribute('data-status');
                    const remainingDays = this.getAttribute('data-remaining-days');

                    // ===== CẬP NHẬT THÔNG TIN CHI TIẾT =====
                    document.getElementById('info-id').textContent = keyInfo.id || '-';
                    document.getElementById('info-user-info').textContent = keyInfo.user_info || '-';
                    document.getElementById('info-license-key').value = keyInfo.license_key || '-';
                    document.getElementById('info-hwid').value = keyInfo.hwid || '-';
                    document.getElementById('info-status').textContent = status || '-';
                    document.getElementById('info-remaining-days').textContent = remainingDays + ' ngày' || '-';
                    
                    // ===== FORMAT THỜI GIAN CHI TIẾT =====
                    document.getElementById('info-created').textContent = formatDateTime(keyInfo.created_at) || '-';
                    document.getElementById('info-expires').textContent = formatDateTime(keyInfo.expires_at) || '-';
                    document.getElementById('info-last-check').textContent = formatDateTime(keyInfo.last_check) || 'Chưa check';

                    // ===== CẬP NHẬT CÁC FORM HÀO ĐỘNG =====
                    document.getElementById('ban-unban-key').value = keyInfo.license_key || '';
                    document.getElementById('action-renew-key').value = keyInfo.license_key || '';
                    document.getElementById('action-delete-key').value = keyInfo.license_key || '';

                    // ===== CẬP NHẬT NÚT BAN/UNBAN =====
                    const btnBanUnban = document.getElementById('btn-ban-unban');
                    const actionInput = document.getElementById('ban-unban-action');
                    if (status === 'BANNED') {
                        btnBanUnban.textContent = 'MỞ BAN';
                        btnBanUnban.style.backgroundColor = '#28a745';
                        actionInput.value = 'unban';
                    } else {
                        btnBanUnban.textContent = '🔒 BAN KEY';
                        btnBanUnban.style.backgroundColor = '#dc3545';
                        actionInput.value = 'ban';
                    }

                    // ===== HIỂN THỊ PHẦN CHI TIẾT =====
                    document.getElementById('selectedKeyInfo').style.display = 'block';
                    document.getElementById('selectedKeyInfo').scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (e) {
                    console.error('Lỗi xử lý dữ liệu:', e);
                    showNotification('Lỗi khi xử lý dữ liệu key!', 'error');
                }
            });
        });

        // ===== COPY VÀO CLIPBOARD =====
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.value || element.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Đã sao chép!', 'success');
            }).catch(err => {
                console.error('Lỗi sao chép:', err);
                showNotification('Lỗi khi sao chép!', 'error');
            });
        }

        // ===== ĐÓNG PHẦN CHI TIẾT =====
        function closeKeyInfo() {
            document.getElementById('selectedKeyInfo').style.display = 'none';
        }
    </script>
</body>
</html>