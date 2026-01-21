const API_BASE = 'http://localhost/ShopToolNro/api';
let token = localStorage.getItem('token');

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
            const response = await fetch(url, options);
            const contentType = response.headers.get('content-type');
            
            if (!contentType || !contentType.includes('application/json')) {
                console.error('Invalid content-type:', contentType, response.statusText);
                return { 
                    success: false,
                    status: 'error', 
                    message: 'Server returned invalid response: ' + response.statusText 
                };
            }
            
            const result = await response.json();
            
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
    static createOrder(product_id, /* quantity - ignored in new schema */, payment_method, duration_id = null, idempotency_key = null) {
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
    const alertHTML = `
        <div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
        alertContainer.innerHTML = alertHTML;
        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);
    }
}

function isLoggedIn() {
    return !!token;
}

function logout() {
    token = null;
    localStorage.removeItem('token');
    location.href = 'http://localhost/ShopToolNro/';
}
