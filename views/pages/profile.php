<?php
$page_title = 'Tài khoản - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /ShopToolNro/views/pages/login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/User.php';

$database = new Database();
$db = $database->connect();
$userClass = new User($db);

$user = $userClass->getUserById($_SESSION['user_id']);
?>

<div class="main-content fade-in container-narrow">
    <h1>Tài khoản của tôi</h1>

    <div class="card card-pad mt-20">
        <p><strong>Tên đăng nhập:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Số dư:</strong> <span
                class="accent-amount"><?php echo number_format($user['balance'], 0, ',', '.'); ?> ₫</span></p>
        <p><strong>Vai trò:</strong> <?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng'; ?></p>
    </div>

    <h3 class="mt-30">Đổi mật khẩu</h3>
    <form id="changePasswordForm" class="mt-15">
        <div class="form-group">
            <label>Mật khẩu hiện tại</label>
            <div class="password-input-wrapper">
                <input type="password" name="old_password" required>
            </div>
        </div>
        <div class="form-group">
            <label>Mật khẩu mới</label>
            <div class="password-input-wrapper">
                <input type="password" name="new_password" required>
            </div>
        </div>
        <div class="form-group">
            <label>Xác nhận mật khẩu mới</label>
            <div class="password-input-wrapper">
                <input type="password" name="confirm_password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
    </form>

    <h3 class="mt-30">Đổi Email</h3>
    <form id="changeEmailForm" class="mt-15">
        <div class="form-group">
            <label>Email mới</label>
            <input type="email" name="email" required placeholder="nhap@email.moi">
        </div>
        <div class="form-group">
            <label>Mật khẩu xác nhận</label>
            <div class="password-input-wrapper">
                <input type="password" name="password" required placeholder="Xác nhận bằng mật khẩu hiện tại">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Cập nhật Email</button>
    </form>
</div>

<script>
    // Fallback showNotification nếu main.js chưa load
    if (typeof showNotification === 'undefined') {
        window.showNotification = function (message, type = 'success', duration = 3200) {
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
                    try { toast.remove(); } catch (e) { }
                }, 300);
            };

            toast.querySelector('.toast-close').addEventListener('click', remove);
            setTimeout(remove, duration);
        };
    }

    document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        console.log('Change password form submitted');

        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        if (data.new_password !== data.confirm_password) {
            showNotification('Mật khẩu không khớp', 'error');
            return;
        }

        // Basic strength check
        if (data.new_password.length < 8 || !/[A-Za-z]/.test(data.new_password) || !/[0-9]/.test(data.new_password)) {
            showNotification('Mật khẩu mới phải có ít nhất 8 ký tự, bao gồm chữ và số', 'error');
            return;
        }

        delete data.confirm_password;

        try {
            const response = await fetch('/ShopToolNro/api/user/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            console.log('Response status:', response.status);
            if (!response.ok) {
                const err = await response.json();
                throw new Error(err.message || 'HTTP Error ' + response.status);
            }

            const result = await response.json();
            console.log('Result:', result);

            if (result.success) {
                showNotification(result.message || 'Đổi mật khẩu thành công', 'success');
                console.log('Password changed successfully, clearing form...');
                document.getElementById('changePasswordForm').reset();

                // Reload after 1.5 seconds to show user they need to re-login
                console.log('Reloading in 1500ms...');
                setTimeout(() => {
                    console.log('Reloading now...');
                    location.reload();
                }, 1500);
            } else {
                showNotification(result.message || 'Lỗi khi đổi mật khẩu', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Lỗi: ' + (error.message || 'Không thể kết nối server') + '\n\nKiểm tra Console (F12)', 'error');
        }
    });

    document.getElementById('changeEmailForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;

        const data = Object.fromEntries(new FormData(e.target));

        try {
            const response = await fetch('/ShopToolNro/api/user/update_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(result.message, 'error');
                btn.disabled = false;
            }
        } catch (error) {
            showNotification('Lỗi kết nối', 'error');
            btn.disabled = false;
        }
    });
</script>

<?php include '../layout/footer.php'; ?>