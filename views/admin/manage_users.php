<?php
$page_title = 'Quản lý người dùng - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/User.php';

$database = new Database();
$db = $database->connect();
$userClass = new User($db);

$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$users = $userClass->getAllUsers($perPage + 1, $offset); // fetch one extra to detect next page
$hasNext = count($users) > $perPage;
if ($hasNext) array_pop($users);
if ($page > 1 && empty($users)) { header('Location: ?page=' . ($page - 1)); exit; }
if (!is_array($users)) { $users = []; }

function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }
?>

<div class="main-content fade-in">
    <h1>Quản lý người dùng</h1> 
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên đăng nhập</th>
                    <th>Email</th>
                    <th>Số dư</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo number_format($user['balance'] ?? 0, 0, ',', '.'); ?> ₫</td>
                        <td>
                            <select onchange="changeRole(<?php echo $user['id']; ?>, this.value)" class="select-sm" <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled title="Không thể thay đổi quyền của chính bạn"' : ''; ?>>
                                <option value="customer" <?php echo ($user['role'] ?? '') === 'customer' ? 'selected' : ''; ?>>Khách hàng</option>
                                <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </td>
                        <td><?php echo ($user['status'] ?? '') === 'active' ? 'Kích hoạt' : 'Khóa'; ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="editBalance(<?php echo $user['id']; ?>)">Điều chỉnh</button>
                            <button class="btn btn-danger btn-sm" onclick="toggleUserStatus(<?php echo $user['id']; ?>, this)">
                                <?php echo ($user['status'] ?? '') === 'active' ? 'Khóa' : 'Mở'; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">
                            <div class="empty-state">Chưa có người dùng</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
        $hasPrev = $page > 1;
        $showPagination = $hasPrev || $hasNext;
    ?>

    <?php if ($showPagination): ?>
        <div class="flex-center mt-14">
            <?php if ($hasPrev): ?>
                <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
            <?php endif; ?>
            <div class="page-indicator">Trang <?php echo $page; ?></div>
            <?php if ($hasNext): ?>
                <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Modal điều chỉnh số dư -->
<div id="balanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Điều chỉnh số dư</h3>
            <span class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</span>
        </div>
        <form id="balanceForm">
            <input type="hidden" name="user_id">
            <div class="form-group">
                <label>Số tiền cộng/trừ</label>
                <input type="text" name="amount" class="format-currency" required placeholder="Nhập số tiền (âm để trừ)">
            </div>
            <div class="form-group">
                <label>Lý do</label>
                <textarea name="reason" placeholder="Lý do điều chỉnh"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-fullwidth">Cập nhật</button>
        </form>
    </div>
</div>

<script>
// Fallback showNotification nếu main.js chưa load
if (typeof showNotification === 'undefined') {
    window.showNotification = function(message, type = 'success', duration = 3200) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = `<div class="toast-body">${message}</div><button class="toast-close" aria-label="Close">&times;</button>`;

        container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                toast.classList.add('visible');
            });
        });

        const remove = () => {
            toast.classList.remove('visible');
            toast.classList.add('closing');
            setTimeout(() => { 
                try { toast.remove(); } catch(e){} 
            }, 300);
        };

        toast.querySelector('.toast-close').addEventListener('click', remove);
        setTimeout(remove, duration);
    };
}

// Make all functions global
window.editBalance = function(userId) {
    console.log('editBalance called:', userId);
    document.querySelector('input[name="user_id"]').value = userId;
    document.getElementById('balanceModal').classList.add('active');
};

window.changeRole = function(userId, role) {
    console.log('changeRole called:', userId, role);
    fetch('/ShopToolNro/api/admin/change_user_role.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: userId, role: role})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('Đã cập nhật quyền hạn', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showNotification(data.message || 'Lỗi', 'error');
            // Reload để reset select về giá trị cũ
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showNotification('Lỗi kết nối: ' + err.message, 'error');
        setTimeout(() => location.reload(), 1000);
    });
};

// Make function global
window.toggleUserStatus = function(userId, btn) {
    console.log('toggleUserStatus called:', userId, btn);
    const action = (btn && btn.textContent && btn.textContent.trim().toLowerCase() === 'khóa') ? 'khóa' : 'mở';
    if (!confirm('Bạn có chắc muốn ' + action + ' tài khoản này?')) return;

    // Disable button during request
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Processing...';
    }

    console.log('Calling API toggle_user_status...');
    fetch('/ShopToolNro/api/admin/toggle_user_status.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: userId})
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'HTTP Error ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Data parsed:', data);
        if (data.success) {
            showNotification(data.message || 'Đã cập nhật trạng thái', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            // Re-enable button on error
            if (btn) {
                btn.disabled = false;
                btn.textContent = action === 'khóa' ? 'Khóa' : 'Mở';
            }
            const errorMsg = data.message || 'Unknown error';
            const debugInfo = data.debug ? '\n\nDebug: ' + JSON.stringify(data.debug, null, 2) : '';
            showNotification(errorMsg + debugInfo, 'error');
            console.error('API Error:', data);
        }
    })
    .catch(err => {
        console.error('Fetch Error:', err);
        // Re-enable button on error
        if (btn) {
            btn.disabled = false;
            btn.textContent = action === 'khóa' ? 'Khóa' : 'Mở';
        }
        showNotification('Lỗi kết nối: ' + err.message + '\n\nKiểm tra Console (F12) để xem chi tiết', 'error');
    });
};

document.getElementById('balanceForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    // Parse số tiền từ input đã format
    const amountInput = e.target.querySelector('input[name="amount"]');
    if (amountInput && amountInput.value) {
        data.amount = parseFormattedNumber(amountInput.value);
    }
    
    try {
        const response = await fetch('/ShopToolNro/api/admin/adjust_user_balance.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Đã điều chỉnh số dư', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Lỗi', 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>