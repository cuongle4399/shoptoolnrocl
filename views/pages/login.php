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
        if (btn) btn.innerHTML = '👁️';
    } else {
        input.type = 'password';
        if (btn) btn.innerHTML = '👁️‍🗨️';
    }
}

// Helper to set button loading state - GLOBAL FUNCTION
window.setButtonLoading = function(button, isLoading) {
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
                <button type="button" class="password-toggle-btn" onclick="togglePassword('loginPassword'); return false;" title="Hiện/Ẩn mật khẩu">
                    👁️‍🗨️
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-fullwidth">Đăng nhập</button>
        <p class="mt-15 text-center">Chưa có tài khoản? <a href="/ShopToolNro/views/pages/register.php">Đăng ký</a></p>
    </form>
</div>

<script>
// Fallback showNotification nếu main.js chưa load
if (typeof showNotification === 'undefined') {
    window.showNotification = function(message, type = 'success', duration = 3200) {
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
                try { toast.remove(); } catch(e){} 
            }, 300);
        };

        toast.querySelector('.toast-close').addEventListener('click', remove);
        setTimeout(remove, duration);
    };
}

// Đợi DOM và main.js load xong
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

<?php include '../layout/footer.php'; ?>
