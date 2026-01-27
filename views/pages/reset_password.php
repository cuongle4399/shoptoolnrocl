<?php
$page_title = 'Đặt lại mật khẩu - ShopToolNro';
require_once '../../config/constants.php';
require_once '../../config/database.php';
include '../layout/header.php';

$token = $_GET['token'] ?? '';
$turnstile_site_key = getenv('TURNSTILE_SITE_KEY') ?: '';
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<div class="main-content auth-container fade-in container-sm">
    <h1 class="text-center mb-30">Đặt lại mật khẩu</h1>

    <?php if (empty($token)): ?>
        <div class="alert alert-error">Token không hợp lệ hoặc thiếu. Vui lòng kiểm tra lại liên kết trong email.</div>
        <p class="text-center"><a href="/ShopToolNro/views/pages/login.php" class="btn btn-secondary">Về trang chủ</a></p>
    <?php else: ?>
        <form id="resetForm" class="no-global-loading">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label>Mật khẩu mới</label>
                <div class="password-input-wrapper">
                    <input type="password" name="password" required minlength="6">
                </div>
            </div>
            <div class="form-group">
                <label>Xác nhận mật khẩu</label>
                <div class="password-input-wrapper">
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
            </div>
            <div class="form-group">
                <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_site_key); ?>"
                    data-theme="dark"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-fullwidth">Cập nhật mật khẩu</button>
        </form>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('resetForm');
        if (!form) return;

        const notify = window.showNotification || function (msg, type) { alert(msg); };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const pwd = form.querySelector('[name="password"]').value;
            const cfm = form.querySelector('[name="confirm_password"]').value;

            if (pwd !== cfm) {
                notify('Mật khẩu không khớp', 'error');
                return;
            }

            if (window.setButtonLoading) window.setButtonLoading(btn, true);
            else btn.disabled = true;

            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            try {
                const response = await fetch('/ShopToolNro/api/auth/reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    notify(result.message, 'success');
                    setTimeout(() => {
                        window.location.href = '/ShopToolNro/views/pages/login.php';
                    }, 2000);
                } else {
                    notify(result.message, 'error');
                    if (window.turnstile) turnstile.reset();
                }
            } catch (err) {
                notify('Lỗi kết nối', 'error');
            } finally {
                if (window.setButtonLoading) window.setButtonLoading(btn, false);
                else btn.disabled = false;
            }
        });
    });
</script>

<?php include '../layout/footer.php'; ?>