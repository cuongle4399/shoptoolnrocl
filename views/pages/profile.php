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
        <p><strong>Số dư:</strong> <span class="accent-amount"><?php echo number_format($user['balance'], 0, ',', '.'); ?> ₫</span></p>
        <p><strong>Vai trò:</strong> <?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng'; ?></p>
    </div>
    
    <h3 class="mt-30">Đổi mật khẩu</h3>
    <form id="changePasswordForm" class="mt-15">
        <div class="form-group">
            <label>Mật khẩu hiện tại</label>
            <input type="password" name="old_password" required>
        </div>
        <div class="form-group">
            <label>Mật khẩu mới</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="form-group">
            <label>Xác nhận mật khẩu mới</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
    </form>
</div>

<script>
document.getElementById('changePasswordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
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
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        showNotification(result.message || (result.success ? 'Thành công' : 'Lỗi'), result.success ? 'success' : 'error');
        if (result.success) {
            document.getElementById('changePasswordForm').reset();
        }
    } catch (error) {
        showNotification('Lỗi: ' + (error.message || ''), 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>
