<?php
$page_title = 'Đăng nhập - ShopToolNro';
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
        button.textContent = button.dataset.originalText || 'Đăng nhập';
    }
}
</script>

<div class="main-content fade-in container-sm">
    <h1 class="text-center mb-30">Đăng nhập</h1> 
    <form id="loginForm">
        <div class="form-group">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Mật khẩu</label>
            <div class="password-input-wrapper">
                <input type="password" name="password" id="loginPassword" required>
                <button type="button" class="password-toggle-btn" onclick="togglePassword('loginPassword'); return false;">
                    Hiện
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Đăng nhập</button>
        <p class="mt-15 text-center">Chưa có tài khoản? <a href="/ShopToolNro/views/pages/register.php">Đăng ký</a></p>
    </form>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    setButtonLoading(btn, true);
    
    const username = document.querySelector('input[name="username"]').value;
    const password = document.querySelector('input[name="password"]').value;
    
    try {
        const response = await fetch('/ShopToolNro/api/auth/login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username, password})
        });
        
        const result = await response.json();
        setButtonLoading(btn, false);
        
        if (result.success) {
            showNotification('Đăng nhập thành công', 'success');
            setTimeout(() => { window.location = '/ShopToolNro/'; }, 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        setButtonLoading(btn, false);
        showNotification(error.message || 'Lỗi', 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>
