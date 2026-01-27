<?php
$page_title = 'Quên mật khẩu - ShopToolNro';
require_once '../../config/constants.php';
require_once '../../config/database.php';
include '../layout/header.php';

$turnstile_site_key = getenv('TURNSTILE_SITE_KEY') ?: '';
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<div class="main-content auth-container fade-in container-sm">
    <h1 class="text-center mb-30">Quên mật khẩu</h1>
    <form id="forgotForm" class="no-global-loading">
        <p class="mb-20 text-center">Nhập email của bạn để nhận liên kết đặt lại mật khẩu.</p>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="nhap@co.email">
        </div>
        <div class="form-group">
            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_site_key); ?>"
                data-theme="dark"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Gửi liên kết</button>
        <p class="mt-15 text-center"><a href="/ShopToolNro/views/pages/login.php">Quay lại đăng nhập</a></p>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('forgotForm');

        // Fallback notification
        const notify = window.showNotification || function (msg, type) { alert(msg); };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            if (window.setButtonLoading) window.setButtonLoading(btn, true);
            else btn.disabled = true;

            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            try {
                const response = await fetch('/ShopToolNro/api/auth/forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    notify(result.message, 'success');
                    form.reset();
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