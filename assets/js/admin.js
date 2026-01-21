// ===== PAGINATION STATE =====
const paginationState = {
    users: { currentPage: 1, totalPages: 1, itemsPerPage: 7, allData: [] },
    products: { currentPage: 1, totalPages: 1, itemsPerPage: 7, allData: [] },
    keys: { currentPage: 1, totalPages: 1, itemsPerPage: 7, allData: [] },
    topups: { currentPage: 1, totalPages: 1, itemsPerPage: 7, allData: [] },
    hwid: { currentPage: 1, totalPages: 1, itemsPerPage: 7, allData: [] },
    promos: { currentPage: 1, totalPages: 1, itemsPerPage: 7, allData: [] }
};

// ===== PAGINATION UTILITY FUNCTIONS =====
function setupPagination(tableName, totalItems) {
    const state = paginationState[tableName];
    state.totalPages = Math.ceil(totalItems / state.itemsPerPage);
    state.currentPage = 1;
    renderPaginationControls(tableName);
}

function getPaginatedData(tableName) {
    const state = paginationState[tableName];
    const start = (state.currentPage - 1) * state.itemsPerPage;
    const end = start + state.itemsPerPage;
    return state.allData.slice(start, end);
}

function renderPaginationControls(tableName) {
    const state = paginationState[tableName];
    const paginationContainer = document.getElementById(`${tableName}Pagination`);
    
    if (!paginationContainer) return;
    if (state.totalPages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let html = '<nav class="pagination-controls" style="margin-top: 20px;"><ul class="pagination justify-content-center">';
    
    // Previous button
    html += `<li class="page-item ${state.currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="goToPage('${tableName}', ${state.currentPage - 1}); return false;">← Trước</a>
    </li>`;
    
    // Page numbers
    for (let i = 1; i <= state.totalPages; i++) {
        if (i === 1 || i === state.totalPages || (i >= state.currentPage - 1 && i <= state.currentPage + 1)) {
            html += `<li class="page-item ${i === state.currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage('${tableName}', ${i}); return false;">${i}</a>
            </li>`;
        } else if (i === 2 && state.currentPage > 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        } else if (i === state.totalPages - 1 && state.currentPage < state.totalPages - 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Next button
    html += `<li class="page-item ${state.currentPage === state.totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="goToPage('${tableName}', ${state.currentPage + 1}); return false;">Sau →</a>
    </li>`;
    
    html += '</ul></nav>';
    paginationContainer.innerHTML = html;
}

function goToPage(tableName, page) {
    const state = paginationState[tableName];
    if (page >= 1 && page <= state.totalPages) {
        state.currentPage = page;
        renderTableData(tableName);
        renderPaginationControls(tableName);
        // Scroll to table
        document.getElementById(tableName + 'Body')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function renderTableData(tableName) {
    const state = paginationState[tableName];
    const data = getPaginatedData(tableName);
    
    switch(tableName) {
        case 'users':
            renderUsersTable(data);
            break;
        case 'products':
            renderProductsTable(data);
            break;
        case 'keys':
            renderKeysTable(data);
            break;
        case 'topups':
            renderTopupsTable(data);
            break;
        case 'hwid':
            renderHwidTable(data);
            break;
        case 'promos':
            renderPromosTable(data);
            break;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
});

function switchAdminTab(tabName, event) {
    event.preventDefault();
    
    document.querySelectorAll('.tab-content').forEach(el => {
        el.style.display = 'none';
    });
    
    document.querySelectorAll('.sidebar .nav-link').forEach(el => {
        el.classList.remove('active');
    });
    
    document.getElementById(tabName + 'Tab').style.display = 'block';
    event.target.closest('.nav-link').classList.add('active');
    
    // Load data for specific tabs
    if (tabName === 'users') loadUsers();
    if (tabName === 'products') loadProducts();
    if (tabName === 'keys') loadKeys();
    if (tabName === 'topup') loadTopups();
    if (tabName === 'hwid') loadHwidRequests();
    if (tabName === 'promo') loadPromos();
}

async function loadDashboard() {
    // Load stats - bạn cần tạo các API endpoints để lấy thống kê
    // Tạm thời sử dụng dữ liệu mẫu
    document.getElementById('totalUsers').textContent = '0';
    document.getElementById('totalProducts').textContent = '0';
    document.getElementById('pendingTopups').textContent = '0';
    document.getElementById('pendingOrders').textContent = '0';
}

async function loadUsers() {
    // Gọi API để lấy danh sách users
    const usersBody = document.getElementById('usersBody');
    if (!usersBody) return;
    
    usersBody.innerHTML = '<tr><td colspan="8" class="text-center">Đang tải...</td></tr>';
    
    const result = await API.adminGetUsers();
    
    if (result.status === 'success') {
        const users = result.data.users;
        
        if (users.length === 0) {
            usersBody.innerHTML = '<tr><td colspan="8" class="text-center">Không có người dùng</td></tr>';
            paginationState.users.allData = [];
            setupPagination('users', 0);
            return;
        }
        
        // Store all data and setup pagination
        paginationState.users.allData = users;
        setupPagination('users', users.length);
        renderTableData('users');
    } else {
        usersBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${result.message}</td></tr>`;
    }
}

function renderUsersTable(users) {
    const usersBody = document.getElementById('usersBody');
    
    if (users.length === 0) {
        usersBody.innerHTML = '<tr><td colspan="8" class="text-center">Không có dữ liệu</td></tr>';
        return;
    }
    
    usersBody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>${user.username}</td>
            <td>${user.email || 'N/A'}</td>
            <td>${user.balance || 0}</td>
            <td><span class="badge bg-${user.role === 'admin' ? 'danger' : 'primary'}">${user.role}</span></td>
            <td><span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status || 'active'}</span></td>
            <td>${new Date(user.created_at).toLocaleDateString('vi-VN')}</td>
            <td>
                <button class="btn btn-sm btn-warning" onclick="showChangePasswordModal(${user.id}, '${user.username}')">Đổi MK</button>
                <button class="btn btn-sm btn-info" onclick="showChangeRoleModal(${user.id}, '${user.username}', '${user.role}')">Đổi Quyền</button>
            </td>
        </tr>
    `).join('');
}

async function loadProducts() {
    // Load products
    const productsList = document.getElementById('productsList');
    productsList.innerHTML = '<div class="col-12 text-center">Đang tải...</div>';
    
    // TODO: Thêm API call
}

async function loadKeys() {
    const keysBody = document.getElementById('keysBody');
    keysBody.innerHTML = '<tr><td colspan="5" class="text-center">Đang tải...</td></tr>';
    
    // TODO: Thêm API call
}

async function loadTopups() {
    const topupBody = document.getElementById('topupBody');
    topupBody.innerHTML = '<tr><td colspan="7" class="text-center">Đang tải...</td></tr>';
    
    // TODO: Thêm API call
}

async function loadHwidRequests() {
    const hwidBody = document.getElementById('hwidBody');
    hwidBody.innerHTML = '<tr><td colspan="5" class="text-center">Đang tải...</td></tr>';
    
    // TODO: Thêm API call
}

async function loadPromos() {
    const promosBody = document.getElementById('promosBody');
    promosBody.innerHTML = '<tr><td colspan="5" class="text-center">Đang tải...</td></tr>';
    
    // TODO: Thêm API call
}

// Modals
function showAddProductModal() {
    new bootstrap.Modal(document.getElementById('addProductModal')).show();
}

function showAddKeyModal() {
    new bootstrap.Modal(document.getElementById('addKeyModal')).show();
}

function showAddPromoModal() {
    new bootstrap.Modal(document.getElementById('addPromoModal')).show();
}

function showChangePasswordModal(userId, username) {
    document.getElementById('changePassUserId').value = userId;
    document.getElementById('changePassUsername').textContent = username;
    new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
}

function showChangeRoleModal(userId, username, currentRole) {
    document.getElementById('changeRoleUserId').value = userId;
    document.getElementById('changeRoleUsername').textContent = username;
    document.getElementById('changeRoleSelect').value = currentRole;
    new bootstrap.Modal(document.getElementById('changeRoleModal')).show();
}

// Form submissions
async function handleAddProduct(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('name', document.getElementById('productName').value);
    formData.append('description', document.getElementById('productDescription').value);
    formData.append('category', document.getElementById('productCategory').value);
    formData.append('tutorial_video_url', document.getElementById('productVideoUrl').value);
    formData.append('software_link', document.getElementById('productSoftwareLink').value);
    
    if (document.getElementById('productImage').files.length > 0) {
        formData.append('image', document.getElementById('productImage').files[0]);
    }
    if (document.getElementById('productDemoImage').files.length > 0) {
        formData.append('demo_image', document.getElementById('productDemoImage').files[0]);
    }
    
    try {
        const response = await fetch('<?php echo SITE_URL; ?>/api/admin/products/create.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            showAlert('Sản phẩm đã được tạo!', 'success');
            document.getElementById('addProductForm').reset();
            bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
            loadProducts();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Lỗi: ' + error.message, 'error');
    }
}

async function handleAddKey(event) {
    event.preventDefault();
    
    const data = {
        hwid: document.getElementById('keyHwid').value,
        user_info: document.getElementById('keyUserInfo').value,
        expires_at: document.getElementById('keyExpiresAt').value || null
    };
    
    const result = await API.call('/admin/keys/create.php', 'POST', data);
    
    if (result.status === 'success') {
        showAlert('Key đã được tạo: ' + result.data.license_key, 'success');
        event.target.reset();
        bootstrap.Modal.getInstance(document.getElementById('addKeyModal')).hide();
        loadKeys();
    } else {
        showAlert(result.message, 'error');
    }
}

async function handleAddPromo(event) {
    event.preventDefault();
    
    const data = {
        code: document.getElementById('promoCode').value,
        discount_percent: parseInt(document.getElementById('promoDiscountPercent').value) || 0,
        discount_amount: parseFloat(document.getElementById('promoDiscountAmount').value) || 0,
        max_uses: parseInt(document.getElementById('promoMaxUses').value) || 0,
        min_order_amount: parseFloat(document.getElementById('promoMinOrder').value) || 0,
        expires_at: document.getElementById('promoExpires').value || null
    };
    
    const result = await API.call('/admin/promotion/create.php', 'POST', data);
    
    if (result.status === 'success') {
        showAlert('Mã khuyễn mãi đã được tạo!', 'success');
        event.target.reset();
        bootstrap.Modal.getInstance(document.getElementById('addPromoModal')).hide();
        loadPromos();
    } else {
        showAlert(result.message, 'error');
    }
}

async function handleUpdateNotification(event) {
    event.preventDefault();
    
    const data = {
        message: document.getElementById('notificationMessage').value,
        active: document.getElementById('notificationActive').checked
    };
    
    const result = await API.call('/admin/notification/update.php', 'POST', data);
    
    if (result.status === 'success') {
        showAlert('Thông báo đã được cập nhật!', 'success');
    } else {
        showAlert(result.message, 'error');
    }
}

async function handleAdminChangePassword(event) {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    setButtonLoading(btn, true);
    
    const old_password = document.getElementById('adminOldPassword').value;
    const new_password = document.getElementById('adminNewPassword').value;
    const confirm_password = document.getElementById('adminConfirmPassword').value;
    
    const result = await API.changePassword(old_password, new_password, confirm_password);
    setButtonLoading(btn, false);
    
    if (result.success) {
        showAlert('Mật khẩu đã được thay đổi thành công!', 'success');
        event.target.reset();
    } else {
        showAlert(result.message || 'Lỗi khi đổi mật khẩu', 'error');
    }
}

async function handleChangeUserPassword(event) {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    setButtonLoading(btn, true);
    
    const userId = document.getElementById('changePassUserId').value;
    const new_password = document.getElementById('changePassNewPassword').value;
    const confirm_password = document.getElementById('changePassConfirmPassword').value;
    
    if (!new_password || !confirm_password) {
        showAlert('Vui lòng nhập mật khẩu mới', 'error');
        setButtonLoading(btn, false);
        return;
    }
    
    if (new_password !== confirm_password) {
        showAlert('Mật khẩu xác nhận không khớp', 'error');
        setButtonLoading(btn, false);
        return;
    }
    
    const result = await API.changePassword('', new_password, confirm_password, userId);
    setButtonLoading(btn, false);
    
    if (result.success) {
        showAlert('Mật khẩu người dùng đã được thay đổi thành công!', 'success');
        event.target.reset();
        bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
        loadUsers();
    } else {
        showAlert(result.message || 'Lỗi khi đổi mật khẩu', 'error');
    }
}

async function handleChangeUserRole(event) {
    event.preventDefault();
    
    const userId = document.getElementById('changeRoleUserId').value;
    const newRole = document.getElementById('changeRoleSelect').value;
    
    const result = await API.adminChangeUserRole(userId, newRole);
    
    if (result.status === 'success') {
        showAlert('Quyền người dùng đã được cập nhật!', 'success');
        bootstrap.Modal.getInstance(document.getElementById('changeRoleModal')).hide();
        loadUsers();
    } else {
        showAlert(result.message, 'error');
    }
}
