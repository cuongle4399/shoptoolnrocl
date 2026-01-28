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

<!-- Password Setup Modal for New Google Users -->
<div id="googleSetupModal" class="modal-overlay"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div class="modal-content"
        style="background: linear-gradient(180deg, rgba(37,37,37,0.98), rgba(26,26,26,1)); padding: 40px; border-radius: 20px; width: 95%; max-width: 450px; border: 2px solid var(--border-color); box-shadow: 0 15px 50px rgba(0,0,0,0.6);">
        <h2 class="text-center mb-20" style="color: var(--accent); margin-bottom: 20px;">🎉 Chào mừng bạn!</h2>
        <p class="text-center mb-25" style="color: #ccc; font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
            Chào <strong id="setupName" style="color: var(--accent-light);"></strong>!<br>
            Đây là lần đầu bạn đăng nhập bằng Google. Vui lòng thiết lập mật khẩu để hoàn tất tạo tài khoản.
        </p>

        <form id="googleSetupForm" class="no-global-loading">
            <input type="hidden" id="setupEmail" name="email">
            <input type="hidden" id="setupGoogleId" name="google_id">
            <input type="hidden" id="setupAvatar" name="avatar">
            <input type="hidden" id="setupBaseName" name="name">

            <div class="form-group">
                <label style="color: var(--text-primary);">Mật khẩu mới</label>
                <div class="password-input-wrapper">
                    <input type="password" id="setupPassword" name="password" required placeholder="Tối thiểu 6 ký tự"
                        style="background: #1a1a1a; border-color: var(--border-color); color: white;">
                    <button type="button" class="password-toggle-btn"
                        onclick="togglePassword('setupPassword'); return false;">👁️‍🗨️</button>
                </div>
            </div>
            <div class="form-group">
                <label style="color: var(--text-primary);">Xác nhận mật khẩu</label>
                <div class="password-input-wrapper">
                    <input type="password" id="setupConfirmPassword" name="confirm_password" required
                        placeholder="Nhập lại mật khẩu"
                        style="background: #1a1a1a; border-color: var(--border-color); color: white;">
                    <button type="button" class="password-toggle-btn"
                        onclick="togglePassword('setupConfirmPassword'); return false;">👁️‍🗨️</button>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="button" class="btn btn-secondary flex-1" onclick="closeSetupModal()"
                    style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #444; background: #333; color: white; cursor: pointer;">Hủy</button>
                <button type="submit" id="setupSubmitBtn" class="btn btn-primary flex-1"
                    style="flex: 1; padding: 12px; border-radius: 8px; background: var(--accent-gradient); border: none; color: white; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 188, 212, 0.3);">Hoàn
                    tất</button>
            </div>
        </form>
    </div>
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
                if (data.action === 'setup_required') {
                    openSetupModal(data.google_info);
                } else {
                    showNotification('Đăng nhập thành công!', 'success');
                    setTimeout(() => window.location.href = '/ShopToolNro/', 500);
                }
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

    function openSetupModal(info) {
        document.getElementById('setupName').textContent = info.name;
        document.getElementById('setupEmail').value = info.email;
        document.getElementById('setupGoogleId').value = info.google_id;
        document.getElementById('setupAvatar').value = info.avatar;
        document.getElementById('setupBaseName').value = info.name;

        const modal = document.getElementById('googleSetupModal');
        modal.style.display = 'flex';
        modal.classList.add('fade-in');
    }

    function closeSetupModal() {
        document.getElementById('googleSetupModal').style.display = 'none';
        const gBtn = document.getElementById('googleSignInBtnV2');
        if (gBtn) gBtn.style.opacity = 1;
    }

    document.getElementById('googleSetupForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('setupSubmitBtn');
        setButtonLoading(btn, true);

        const formData = {
            email: document.getElementById('setupEmail').value,
            google_id: document.getElementById('setupGoogleId').value,
            avatar: document.getElementById('setupAvatar').value,
            name: document.getElementById('setupBaseName').value,
            password: document.getElementById('setupPassword').value,
            confirm_password: document.getElementById('setupConfirmPassword').value
        };

        try {
            const res = await fetch('/ShopToolNro/api/auth/google_setup_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const data = await res.json();
            setButtonLoading(btn, false);

            if (data.success) {
                showNotification('Thiết lập tài khoản thành công!', 'success');
                setTimeout(() => window.location.href = '/ShopToolNro/', 500);
            } else {
                showNotification(data.message || 'Có lỗi xảy ra', 'error');
            }
        } catch (err) {
            setButtonLoading(btn, false);
            showNotification('Lỗi kết nối server', 'error');
        }
    });
</script>

<script>
    // Fallback setButtonLoading if main.js hasn't loaded
    if (typeof setButtonLoading === 'undefined') {
        window.setButtonLoading = function (button, isLoading) {
            if (!button) return;
            if (isLoading) {
                button.classList.add('btn-loading');
                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = 'Đang xử lý...';
            } else {
                button.classList.remove('btn-loading');
                button.disabled = false;
                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                }
            }
        };
    }

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