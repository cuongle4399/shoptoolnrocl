<?php
$page_title = 'Đăng ký - ShopToolNro';
include '../layout/header.php';
?>

<script>
// Toggle password visibility - LOAD EARLY
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const btn = input.parentElement.querySelector('.password-toggle-btn');
    if (input.type === 'password') {
        input.type = 'text';
        if (btn) btn.textContent = 'Ẩn';
    } else {
        input.type = 'password';
        if (btn) btn.textContent = 'Hiện';
    }
}

// Helper to set button loading state
function setButtonLoading(button, isLoading) {
    if (!button) return;
    if (isLoading) {
        button.classList.add('loading');
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = '';
    } else {
        button.classList.remove('loading');
        button.disabled = false;
        button.textContent = button.dataset.originalText || 'Đăng ký';
    }
}
</script>

<div class="main-content fade-in container-narrow">
    <h1 class="text-center mb-30">Đăng ký</h1>
    <form id="registerForm">
        <div class="form-group">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Mật khẩu</label>
            <div class="password-input-wrapper">
                <input type="password" name="password" id="registerPassword" required>
                <button type="button" class="password-toggle-btn" onclick="togglePassword('registerPassword'); return false;">
                    Hiện
                </button>
            </div>
        </div>
        <div class="form-group">
            <label>Xác nhận mật khẩu</label>
            <div class="password-input-wrapper">
                <input type="password" name="confirm_password" id="registerConfirmPassword" required>
                <button type="button" class="password-toggle-btn" onclick="togglePassword('registerConfirmPassword'); return false;">
                    Hiện
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Đăng ký</button>
        <p class="mt-15 text-center">Đã có tài khoản? <a href="/ShopToolNro/views/pages/login.php">Đăng nhập</a></p>
    </form>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    setButtonLoading(btn, true);
    
    const username = document.querySelector('input[name="username"]').value;
    const email = document.querySelector('input[name="email"]').value;
    const password = document.querySelector('input[name="password"]').value;
    const confirm_password = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirm_password) {
        showNotification('Mật khẩu không khớp', 'error');
        setButtonLoading(btn, false);
        return;
    }

    // Basic strength check
    if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
        showNotification('Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ và số', 'error');
        setButtonLoading(btn, false);
        return;
    }
    
    try {
        const response = await fetch('/ShopToolNro/api/auth/register.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username, email, password, confirm_password})
        });
        
        const result = await response.json();
        setButtonLoading(btn, false);
        
        if (result.success) {
            showNotification(result.message || 'Đã đăng ký', 'success');
            setTimeout(() => { window.location.href = '/ShopToolNro/views/pages/login.php'; }, 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        setButtonLoading(btn, false);
        showNotification('Lỗi: ' + (error.message || ''), 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>
