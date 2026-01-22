const API_BASE = 'http://localhost/ShopToolNro/api';
let token = localStorage.getItem('token');

// Helper functions for loading overlay
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('active');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

class API {
    static async call(endpoint, method = 'GET', data = null) {
        const url = API_BASE + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (token) {
            options.headers['Authorization'] = 'Bearer ' + token;
        }

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            showLoading();
            const response = await fetch(url, options);
            const contentType = response.headers.get('content-type');
            
            if (!contentType || !contentType.includes('application/json')) {
                console.error('Invalid content-type:', contentType, response.statusText);
                hideLoading();
                return { 
                    success: false,
                    status: 'error', 
                    message: 'Server returned invalid response: ' + response.statusText 
                };
            }
            
            const result = await response.json();
            hideLoading();
            
            // Ensure consistent format: always has 'success' and 'status' fields
            if (!result.hasOwnProperty('success')) {
                result.success = (result.status === 'success');
            }
            if (!result.hasOwnProperty('status')) {
                result.status = result.success ? 'success' : 'error';
            }
            
            return result;
        } catch (error) {
            console.error('API call error:', error);
            hideLoading();
            return { success: false, status: 'error', message: 'Network error: ' + error.message };
        }
    }

    // Products
    static getProducts(page = 1) {
        return this.call(`/products/list.php?page=${page}`);
    }

    static getProductDetail(id) {
        return this.call(`/products/detail.php?id=${id}`);
    }

    // Auth
    static register(username, email, password, confirm_password) {
        return this.call('/auth/register.php', 'POST', {
            username, email, password, confirm_password
        });
    }

    static login(username, password) {
        return this.call('/auth/login.php', 'POST', {
            username, password
        });
    }

    // Account
    static getProfile() {
        return this.call('/account/profile.php');
    }

    static changePassword(old_password, new_password, confirm_password, target_user_id = null) {
        const data = { old_password, new_password, confirm_password };
        if (target_user_id) {
            data.target_user_id = target_user_id;
        }
        return this.call('/change_password.php', 'POST', data);
    }

    static adminChangeUserRole(user_id, role) {
        return this.call('/admin/change_user_role.php', 'POST', {
            user_id, role
        });
    }

    static adminGetUsers(page = 1) {
        return this.call(`/admin/users/list.php?page=${page}`);
    }

    // Orders
    // quantity param removed in new schema; payment_method can be 'balance' or others if added later
    static createOrder(product_id, payment_method, duration_id = null, idempotency_key = null) {
        const key = idempotency_key || (function generateUUID(){
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        })();
        return this.call('/orders/create.php', 'POST', {
            product_id, payment_method, duration_id, idempotency_key: key
        });
    }

    static getOrders(page = 1) {
        return this.call(`/orders/list.php?page=${page}`);
    }

    // Thêm các hàm API còn thiếu
    static createTopup(amount, method) {
        return this.call('/topup/create.php', 'POST', {
            amount, method
        });
    }

    static getHwidHistory(page = 1) {
        return this.call(`/account/hwid_history.php?page=${page}`);
    }

    static requestHwidChange(license_key, new_hwid, reason) {
        // Deprecated: request/approve flow removed. Use Order UI or call /api/hwid/set_hwid.php directly.
        return Promise.resolve({ status: 'error', message: 'Deprecated: request-based HWID change removed. Use direct HWID set via /api/hwid/set_hwid.php or the Orders UI.' });
    }

    // Public
    static getNotification() {
        return this.call('/public/notification.php');
    }

    // Check Key (for Winform)
    static checkKey(hwid, license_key) {
        return this.call('/check_key.php', 'POST', {
            hwid, license_key
        });
    }
}

function showAlert(message, type = 'success') {
    // Chuyển sang showNotification nếu có
    if (typeof showNotification !== 'undefined') {
        showNotification(message, type);
        return;
    }
    
    // Fallback showNotification với CSS toast mới
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
    
    const duration = type === 'error' ? 5000 : 3200;
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function isLoggedIn() {
    return !!token;
}

function logout() {
    token = null;
    localStorage.removeItem('token');
    location.href = 'http://localhost/ShopToolNro/';
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
        button.textContent = button.dataset.originalText || 'Gửi';
    }
}