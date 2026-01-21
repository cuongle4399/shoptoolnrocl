document.addEventListener('DOMContentLoaded', function() {
    // Authentication checked on server-side in PHP, no need to check again
});

async function handleChangePassword(event) {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    setButtonLoading(btn, true);
    
    const old_password = document.getElementById('oldPassword').value;
    const new_password = document.getElementById('newPassword').value;
    const confirm_password = document.getElementById('confirmNewPassword').value;
    
    const result = await API.changePassword(old_password, new_password, confirm_password);
    setButtonLoading(btn, false);
    
    if (result.success) {
        showAlert('Mật khẩu đã được thay đổi thành công!', 'success');
        document.querySelector('form[onsubmit="handleChangePassword(event)"]').reset();
    } else {
        showAlert(result.message || 'Lỗi khi đổi mật khẩu', 'error');
    }
}

function switchTab(tabName, event) {
    event.preventDefault();
    
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(el => {
        el.style.display = 'none';
    });
    
    // Remove active class from all nav links
    document.querySelectorAll('.sidebar .nav-link').forEach(el => {
        el.classList.remove('active');
    });
    
    // Show selected tab
    const tabElement = document.getElementById(tabName + 'Tab');
    if (tabElement) {
        tabElement.style.display = 'block';
    }
    
    event.target.closest('.nav-link').classList.add('active');
}
