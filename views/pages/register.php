<?php
$page_title = 'Đăng ký - ShopToolNro';
include '../layout/header.php';
?>

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
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Xác nhận mật khẩu</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Đăng ký</button>
        <p class="mt-15 text-center">Đã có tài khoản? <a href="/ShopToolNro/views/pages/login.php">Đăng nhập</a></p>
    </form>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.querySelector('input[name="username"]').value;
    const email = document.querySelector('input[name="email"]').value;
    const password = document.querySelector('input[name="password"]').value;
    const confirm_password = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirm_password) {
        showNotification('Mật khẩu không khớp', 'error');
        return;
    }

    // Basic strength check
    if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
        showNotification('Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ và số', 'error');
        return;
    }
    
    try {
        const response = await fetch('/ShopToolNro/api/auth/register.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username, email, password, confirm_password})
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification(result.message || 'Đã đăng ký', 'success');
            setTimeout(() => { window.location.href = '/ShopToolNro/views/pages/login.php'; }, 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + (error.message || ''), 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>
