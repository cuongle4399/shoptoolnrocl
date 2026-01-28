<?php
$page_title = 'Đăng nhập - ShopToolNro';
// Load env/constants before reading TURNSTILE vars (needed when only .env is present)
require_once '../../config/constants.php';
require_once '../../config/database.php';
include '../layout/header.php';

$turnstile_site_key = getenv('TURNSTILE_SITE_KEY') ?: '';
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<script>
    // Toggle password visibility - LOAD EARLY
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const btn = input.parentElement.querySelector('.password-toggle-btn');
        if (input.type === 'password') {
            input.type = 'text';
            if (btn) btn.innerHTML = '👁️';
        } else {
            input.type = 'password';
            if (btn) btn.innerHTML = '👁️‍🗨️';
        }
    }

    // Helper to set button loading state - GLOBAL FUNCTION
    window.setButtonLoading = function (button, isLoading) {
        console.log('setButtonLoading called', button, isLoading);
        if (!button) return;
        if (isLoading) {
            button.classList.add('loading');
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            // KHÔNG xóa text, chỉ thêm class loading
        } else {
            button.classList.remove('loading');
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }
    };
</script>

<div class="main-content auth-container fade-in container-sm">
    <h1 class="text-center mb-30">Đăng nhập</h1>
    <form id="loginForm" class="no-global-loading">
        <div class="form-group">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <div class="d-flex justify-content-between">
                <label>Mật khẩu</label>
                <a href="/ShopToolNro/views/pages/forgot_password.php" style="font-size: 0.9em;">Quên mật khẩu?</a>
            </div>
            <div class="password-input-wrapper">
                <input type="password" name="password" id="loginPassword" required>
                <button type="button" class="password-toggle-btn"
                    onclick="togglePassword('loginPassword'); return false;" title="Hiện/Ẩn mật khẩu">
                    👁️‍🗨️
                </button>
            </div>
        </div>
        <div class="form-group">
            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_site_key); ?>"
                data-theme="dark"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth mb-25">Đăng nhập</button>

        <!-- Google Login Button Container (ID CHANGED TO V2 TO FORCE CACHE REFRESH) -->
        <div id="googleSignInBtnV2" class="mb-20"
            style="display: flex; justify-content: center; min-height: 40px; margin-top: 10px;">
        </div>

        <p class="mt-15 text-center">Chưa có tài khoản? <a href="/ShopToolNro/views/pages/register.php">Đăng ký</a></p>
    </form>
</div>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
    console.log("LOGIN_PAGE_VERSION: 2.1_FIX_CACHE");

    // Initialize GIS
    window.handleGoogleLoad = function () {
        console.log("Initializing Google Identity Services...");
        if (typeof google !== 'undefined') {
            google.accounts.id.initialize({
                client_id: "<?php echo getenv('GOOGLE_CLIENT_ID'); ?>",
                callback: handleCredentialResponse
            });
            google.accounts.id.renderButton(
                document.getElementById("googleSignInBtnV2"),
                { theme: "filled_black", size: "large", width: 340 }
            );
        } else {
            console.error("Google script not loaded yet");
            setTimeout(handleGoogleLoad, 1000);
        }
    };

    window.addEventListener('load', handleGoogleLoad);

    async function handleCredentialResponse(response) {
        console.log("Google token received, sending to server...");

        const gBtn = document.getElementById('googleSignInBtnV2');
        if (gBtn) gBtn.style.opacity = 0.5;

        if (typeof showNotification !== 'undefined') {
            showNotification('Đang kiểm tra với Server...', 'success');
        }

        try {
            // Force V2 API with timestamp
            const result = await fetch('/ShopToolNro/api/auth/google_login.php?v=2&t=' + Date.now(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ credential: response.credential })
            });

            const rawText = await result.text();
            console.log("Server Raw Response:", rawText);

            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                console.error("JSON Parse Error. Data:", rawText);
                const snippet = rawText.trim().substring(0, 150);
                throw new Error("Server trả về dữ liệu không hợp lệ (Không phải JSON). Nội dung: " + (snippet || 'Trống'));
            }

            if (data.success) {
                showNotification('Đăng nhập thành công!', 'success');
                setTimeout(() => window.location.href = '/ShopToolNro/', 500);
            } else {
                showNotification(data.message || 'Lỗi server', 'error');
                if (gBtn) gBtn.style.opacity = 1;
            }
        } catch (err) {
            console.error("Login Process Error:", err);
            alert("LỖI ĐĂNG NHẬP:\n" + err.message);
            if (gBtn) gBtn.style.opacity = 1;
        }
    }
</script>

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

    // Đợi DOM và main.js load xong
    document.addEventListener('DOMContentLoaded', function () {
        console.log('DOMContentLoaded fired');
        const loginForm = document.getElementById('loginForm');
        console.log('loginForm found:', loginForm);
        if (!loginForm) return;

        loginForm.addEventListener('submit', async (e) => {
            console.log('Form submitted!');
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            console.log('Button found:', btn);
            setButtonLoading(btn, true);

            const username = document.querySelector('input[name="username"]').value;
            const password = document.querySelector('input[name="password"]').value;
            const turnstileToken = document.querySelector('[name="cf-turnstile-response"]')?.value;

            if (!turnstileToken) {
                setButtonLoading(btn, false);
                showNotification('Vui lòng hoàn thành xác minh Turnstile', 'error');
                return;
            }

            try {
                const response = await fetch('/ShopToolNro/api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password, 'cf-turnstile-response': turnstileToken })
                });

                const result = await response.json();
                setButtonLoading(btn, false);

                if (result.success) {
                    showNotification('Đăng nhập thành công', 'success');
                    setTimeout(() => { window.location = '/ShopToolNro/'; }, 700);
                } else {
                    showNotification(result.message || 'Lỗi', 'error');
                    // Reset Turnstile khi đăng nhập thất bại
                    if (typeof turnstile !== 'undefined') {
                        turnstile.reset();
                    }
                }
            } catch (error) {
                setButtonLoading(btn, false);
                showNotification(error.message || 'Lỗi', 'error');
                // Reset Turnstile khi có lỗi
                if (typeof turnstile !== 'undefined') {
                    turnstile.reset();
                }
            }
        });
    });
</script>

<?php include '../layout/footer.php'; ?>