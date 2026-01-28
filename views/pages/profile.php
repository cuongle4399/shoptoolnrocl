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

    <div class="profile-header card card-pad mt-25"
        style="display: flex; align-items: center; gap: 30px; border-left: 5px solid var(--accent);">
        <div class="profile-avatar-container" style="position: relative;">
            <img src="<?php echo htmlspecialchars($_SESSION['user_avatar'] ?? getRandomAvatar()); ?>" alt="Avatar"
                style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        </div>
        <div class="profile-basic-info">
            <h2 style="text-align: left; margin-bottom: 5px; color: var(--accent);">
                <?php echo htmlspecialchars($user['username']); ?>
            </h2>
            <p style="color: var(--text-secondary); margin-bottom: 5px;"><i class="fas fa-envelope"></i>
                <?php echo htmlspecialchars($user['email']); ?></p>
            <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
                <div class="stat-item">
                    <span
                        style="display: block; font-size: 0.8em; color: var(--text-muted); text-transform: uppercase;">Số
                        dư hiện tại</span>
                    <span class="accent-amount"
                        style="font-size: 1.2em; font-weight: 700;"><?php echo number_format($user['balance'], 0, ',', '.'); ?>
                        ₫</span>
                </div>
                <div class="stat-item">
                    <span
                        style="display: block; font-size: 0.8em; color: var(--text-muted); text-transform: uppercase;">Vai
                        trò</span>
                    <span
                        style="color: var(--text-primary); font-weight: 600;"><?php echo $user['role'] === 'admin' ? '🔥 Quản trị viên' : '👤 Khách hàng'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <h3 class="mt-30" style="color: var(--accent);">Đổi mật khẩu</h3>
    <form id="changePasswordForm" class="mt-15 card card-pad no-global-loading">
        <div class="form-group">
            <label>Mật khẩu hiện tại</label>
            <div class="password-input-wrapper">
                <input type="password" name="old_password" id="old_password" required
                    placeholder="Nhập mật khẩu hiện tại">
                <button type="button" class="password-toggle-btn"
                    onclick="togglePassword('old_password'); return false;">👁️‍🗨️</button>
            </div>
        </div>
        <div class="form-group">
            <label>Mật khẩu mới</label>
            <div class="password-input-wrapper">
                <input type="password" name="new_password" id="new_password" required
                    placeholder="Tối thiểu 8 ký tự, có chữ và số">
                <button type="button" class="password-toggle-btn"
                    onclick="togglePassword('new_password'); return false;">👁️‍🗨️</button>
            </div>
            <div id="pwStrengthMeter" class="strength-meter mt-10">
                <div class="strength-bar"></div>
            </div>
            <small id="pwStrengthText" class="text-muted" style="font-size: 0.8em;"></small>
        </div>
        <div class="form-group">
            <label>Xác nhận mật khẩu mới</label>
            <div class="password-input-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" required
                    placeholder="Nhập lại mật khẩu mới">
                <button type="button" class="password-toggle-btn"
                    onclick="togglePassword('confirm_password'); return false;">👁️‍🗨️</button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Cập nhật mật khẩu</button>
    </form>

    <h3 class="mt-30" style="color: var(--accent);">Thay đổi Email</h3>
    <form id="changeEmailForm" class="mt-15 card card-pad no-global-loading">
        <div class="form-group">
            <label>Email mới</label>
            <input type="email" name="email" required placeholder="nhap@email.moi" style="background: var(--bg-white);">
        </div>
        <div class="form-group">
            <label>Mật khẩu xác nhận</label>
            <div class="password-input-wrapper">
                <input type="password" name="password" id="email_verify_password" required
                    placeholder="Nhập mật khẩu để xác nhận">
                <button type="button" class="password-toggle-btn"
                    onclick="togglePassword('email_verify_password'); return false;">👁️‍🗨️</button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Xác nhận đổi Email</button>
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

    // Generic toggle password visibility (if not in header)
    if (typeof togglePassword === 'undefined') {
        window.togglePassword = function (id) {
            const input = document.getElementById(id);
            if (!input) return;
            const btn = input.parentElement.querySelector('.password-toggle-btn');
            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = '👁️';
            } else {
                input.type = 'password';
                btn.innerHTML = '👁️‍🗨️';
            }
        };
    }

    // Password Strength Meter
    const newPwInput = document.getElementById('new_password');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.getElementById('pwStrengthText');

    newPwInput.addEventListener('input', function () {
        const val = this.value;
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        strengthBar.className = 'strength-bar';
        if (val.length === 0) {
            strengthBar.style.width = '0%';
            strengthText.textContent = '';
        } else if (score <= 1) {
            strengthBar.style.width = '25%';
            strengthBar.classList.add('low');
            strengthText.textContent = 'Yếu';
            strengthText.style.color = '#ef4444';
        } else if (score === 2) {
            strengthBar.style.width = '50%';
            strengthBar.classList.add('medium');
            strengthText.textContent = 'Trung bình';
            strengthText.style.color = '#f59e0b';
        } else if (score === 3) {
            strengthBar.style.width = '75%';
            strengthBar.classList.add('good');
            strengthText.textContent = 'Mạnh';
            strengthText.style.color = '#10b981';
        } else {
            strengthBar.style.width = '100%';
            strengthBar.classList.add('strong');
            strengthText.textContent = 'Rất mạnh';
            strengthText.style.color = '#06b6d4';
        }
    });

    document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        if (data.new_password !== data.confirm_password) {
            showNotification('Mật khẩu xác nhận không khớp', 'error');
            return;
        }

        if (data.new_password.length < 6) {
            showNotification('Mật khẩu mới phải có ít nhất 6 ký tự', 'error');
            return;
        }

        if (typeof setButtonLoading !== 'undefined') setButtonLoading(btn, true);
        else btn.disabled = true;

        try {
            const response = await fetch('/ShopToolNro/api/user/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    old_password: data.old_password,
                    new_password: data.new_password
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Đã đổi mật khẩu thành công. Vui lòng đăng nhập lại.', 'success');
                e.target.reset();
                strengthBar.style.width = '0%';
                strengthText.textContent = '';
                setTimeout(() => window.location.href = '/ShopToolNro/views/pages/logout.php', 2000);
            } else {
                showNotification(result.message || 'Lỗi khi đổi mật khẩu', 'error');
                if (typeof setButtonLoading !== 'undefined') setButtonLoading(btn, false);
                else btn.disabled = false;
            }
        } catch (error) {
            showNotification('Không thể kết nối máy chủ', 'error');
            if (typeof setButtonLoading !== 'undefined') setButtonLoading(btn, false);
            else btn.disabled = false;
        }
    });

    document.getElementById('changeEmailForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const data = Object.fromEntries(new FormData(e.target));

        if (typeof setButtonLoading !== 'undefined') setButtonLoading(btn, true);
        else btn.disabled = true;

        try {
            const response = await fetch('/ShopToolNro/api/user/update_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                showNotification('Cập nhật email thành công!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(result.message, 'error');
                if (typeof setButtonLoading !== 'undefined') setButtonLoading(btn, false);
                else btn.disabled = false;
            }
        } catch (error) {
            showNotification('Lỗi kết nối máy chủ', 'error');
            if (typeof setButtonLoading !== 'undefined') setButtonLoading(btn, false);
            else btn.disabled = false;
        }
    });
</script>

<?php include '../layout/footer.php'; ?>