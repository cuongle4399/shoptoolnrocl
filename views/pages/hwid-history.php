<?php
$page_title = 'Cập nhật HWID - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /ShopToolNro/views/pages/login.php');
    exit;
}
?>

<div class="main-content fade-in">
    <h1>Cập nhật HWID</h1>
    <p>Bạn có thể cập nhật HWID trực tiếp tại đây.</p>

    <div class="container-md">
        <form id="hwidForm">
            <div class="form-group">
                <label>License Key</label>
                <input type="text" name="license_key" required>
            </div>
            <div class="form-group">
                <label>HWID mới</label>
                <input type="text" name="new_hwid" required placeholder="Nhập HWID mới (8-64 ký tự)">
            </div>
            <button type="submit" class="btn btn-primary">Cập nhật HWID</button>
        </form>
    </div>
</div>

<script>
// Fallback showNotification nếu main.js chưa load
if (typeof showNotification === 'undefined') {
    window.showNotification = function(message, type = 'success', duration = 3200) {
        console.log('showNotification called:', {message, type, duration});
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-body">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };
}

document.getElementById('hwidForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    const hwid = (data.new_hwid || '').trim();
    const hwidRegex = /^[A-Za-z0-9\-_]{8,64}$/;
    if (!hwidRegex.test(hwid)) {
        showNotification('HWID không hợp lệ. Vui lòng nhập 8-64 ký tự: chữ/số, dấu - hoặc _.', 'error');
        return;
    }

    try {
        const resp = await fetch('/ShopToolNro/api/hwid/set_hwid.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ license_key: data.license_key, new_hwid: hwid })
        });
        const result = await resp.json();
        if (resp.ok && result.success) {
            showNotification('HWID đã được cập nhật', 'success');
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (err) {
        showNotification(err.message || 'Lỗi', 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>
