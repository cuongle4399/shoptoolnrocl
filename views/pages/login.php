<?php
$page_title = 'Đăng nhập - ShopToolNro';
include '../layout/header.php';
?>

<div class="main-content fade-in container-sm">
    <h1 class="text-center mb-30">Đăng nhập</h1> 
    <form id="loginForm">
        <div class="form-group">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Mật khẩu</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Đăng nhập</button>
        <p class="mt-15 text-center">Chưa có tài khoản? <a href="/ShopToolNro/views/pages/register.php">Đăng ký</a></p>
    </form>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.querySelector('input[name="username"]').value;
    const password = document.querySelector('input[name="password"]').value;
    
    try {
        const response = await fetch('/ShopToolNro/api/auth/login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username, password})
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Đăng nhập thành công', 'success');
            setTimeout(() => { window.location = '/ShopToolNro/'; }, 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Lỗi', 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>
