// Toggle password visibility
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

document.addEventListener('DOMContentLoaded', function() {
    loadNotification();
    loadProducts();
    updateAuthUI();
});

async function loadNotification() {
    const result = await API.getNotification();
    if (result.data && result.data.notification) {
        const notif = result.data.notification;
        const banner = document.getElementById('notificationBanner');
        document.getElementById('notificationText').textContent = notif.message;
        banner.style.display = 'block';
    }
}

async function loadProducts(page = 1) {
    const result = await API.getProducts(page);
    
    if (result.status === 'success') {
        const products = result.data.products;
        const pagination = result.data.pagination;
        
        displayProducts(products);
        displayPagination(pagination);
    }
}

function normalizeYoutubeEmbedUrl(url) {
    if (!url) return '';
    try {
        let u = url.trim();
        if (!u.startsWith('http')) {
            if (u.startsWith('//')) u = 'https:' + u;
            else u = 'https://' + u;
        }
        const parsed = new URL(u);
        const host = parsed.hostname;
        if (host.includes('youtu.be')) {
            const id = parsed.pathname.substring(1);
            return 'https://www.youtube.com/embed/' + id;
        }
        if (host.includes('youtube.com')) {
            if (parsed.pathname.startsWith('/embed/')) return u;
            const params = new URLSearchParams(parsed.search);
            if (params.has('v')) return 'https://www.youtube.com/embed/' + params.get('v');
        }
        const m = u.match(/(?:v=|\/)([A-Za-z0-9_-]{6,})/);
        if (m) return 'https://www.youtube.com/embed/' + m[1];
        return u;
    } catch (e) {
        return url;
    }
}

function displayProducts(products) {
    const container = document.getElementById('productsList');
    container.innerHTML = '';
    
    products.forEach(product => {
        const card = document.createElement('div');
        card.className = 'col-md-6 col-lg-4 mb-4';
        card.innerHTML = `
            <div class="card product-card fade-in h-100">
                <img src="${product.image_url || 'https://via.placeholder.com/400x250?text=' + product.name}" 
                     class="card-img-top product-image" alt="${product.name}">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">${product.name}</h5>
                    <p class="card-text text-muted flex-grow-1">${product.description || 'Không có mô tả'}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="product-price">${new Intl.NumberFormat('vi-VN', {
                            style: 'currency',
                            currency: 'VND'
                        }).format(product.price)}</span>
                        <button class="btn btn-primary btn-sm" onclick="viewProduct(${product.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

function displayPagination(pagination) {
    if (pagination.pages <= 1) {
        document.getElementById('paginationNav').style.display = 'none';
        return;
    }
    
    const paginationList = document.getElementById('paginationList');
    paginationList.innerHTML = '';
    
    for (let i = 1; i <= pagination.pages; i++) {
        const li = document.createElement('li');
        li.className = 'page-item' + (i === pagination.page ? ' active' : '');
        li.innerHTML = `<a class="page-link" href="#" onclick="loadProducts(${i}); return false;">${i}</a>`;
        paginationList.appendChild(li);
    }
    
    document.getElementById('paginationNav').style.display = 'block';
}

async function viewProduct(productId) {
    const result = await API.getProductDetail(productId);
    
    if (result.status === 'success') {
        const product = result.data.product;
        const durations = result.data.durations || [];

        document.getElementById('productTitle').textContent = product.name;
        document.getElementById('productDescription').textContent = product.description || 'Không có mô tả';
        document.getElementById('productPrice').textContent = new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(product.price);
        document.getElementById('productCategory').textContent = product.category || 'N/A';
        
        if (product.demo_image_url) {
            try {
                const maybe = JSON.parse(product.demo_image_url);
                if (Array.isArray(maybe) && maybe.length) {
                    document.getElementById('productDemoImage').src = maybe[0];
                } else {
                    document.getElementById('productDemoImage').src = product.demo_image_url;
                }
            } catch (e) {
                document.getElementById('productDemoImage').src = product.demo_image_url;
            }
        }
        
        let tutorial = '';
        if (product.tutorial_video_url) {
            tutorial = `<iframe width="100%" height="300" src="${normalizeYoutubeEmbedUrl(product.tutorial_video_url)}" frameborder="0" allowfullscreen></iframe>`;
        } else {
            tutorial = '<p class="text-muted">Không có video hướng dẫn</p>';
        }
        document.getElementById('productTutorial').innerHTML = tutorial;

        // Durations selector (if any)
        let durationHTML = '';
        if (durations && durations.length) {
            durationHTML = `<div style="margin-top:12px;"><label><strong>Chọn thời hạn</strong></label><select id="productDurationSelect" class="form-select">`;
            durations.forEach(d => {
                const label = d.label || (d.duration_days ? d.duration_days + ' ngày' : 'Vĩnh viễn');
                durationHTML += `<option value="${d.id}" data-days="${d.duration_days || ''}">${label} — ${new Intl.NumberFormat('vi-VN').format(d.price)} ₫</option>`;
            });
            durationHTML += `</select></div>`;
        }
        document.getElementById('productDurationContainer').innerHTML = durationHTML;
        
        // Buttons
        let buttons = '';
        if (isLoggedIn()) {
            buttons = `
                <button class="btn btn-success w-100 mb-2" onclick="buyProduct(${product.id})">
                    <i class="fas fa-shopping-cart"></i> Mua ngay
                </button>
            `;
        } else {
            buttons = `
                <button class="btn btn-primary w-100" onclick="location.href='<?php echo SITE_URL; ?>/views/pages/login.php'">
                    Đăng nhập để mua
                </button>
            `;
        }
        document.getElementById('productButtons').innerHTML = buttons;
        
        const modal = new bootstrap.Modal(document.getElementById('productModal'));
        modal.show();
    }
}

async function buyProduct(productId) {
    const quantity = parseInt(document.getElementById('quantityInput').value);
    
    // Hiển thị modal chọn phương thức thanh toán
    showPaymentModal(productId, quantity);
}

function showPaymentModal(productId, quantity) {
    const paymentHTML = `
        <div class="modal fade" id="paymentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Chọn phương thức thanh toán</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group">
                            <button type="button" class="list-group-item list-group-item-action" 
                                    onclick="processPayment(${productId}, ${quantity}, 'vietqr')">
                                <i class="fas fa-qrcode"></i> VietQR
                            </button>
                            <button type="button" class="list-group-item list-group-item-action"
                                    onclick="processPayment(${productId}, ${quantity}, 'wallet')">
                                <i class="fas fa-wallet"></i> Ví (Nếu có)
                            </button>
                            <button type="button" class="list-group-item list-group-item-action"
                                    onclick="processPayment(${productId}, ${quantity}, 'bank_transfer')">
                                <i class="fas fa-university"></i> Chuyển khoản thủ công
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', paymentHTML);
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

async function processPayment(productId, quantity, method) {
    // try to read selected duration if available
    const durationSelect = document.getElementById('productDurationSelect');
    const durationId = durationSelect ? parseInt(durationSelect.value) : null;

    // Disable payment options while processing to avoid accidental double-click submissions
    const paymentButtons = document.querySelectorAll('#paymentModal .list-group .list-group-item');
    paymentButtons.forEach(b => { b.disabled = true; b.style.pointerEvents = 'none'; });

    try {
        const result = await API.createOrder(productId, quantity, method, durationId);
        
        if (result.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
            
            if (method === 'vietqr') {
                showQRModal(result.data.vietqr_link, result.data.order_id);
            } else if (method === 'wallet') {
                // If API returned license info, show it
                if (result.license_key) {
                    const expires = result.expires_at ? (new Date(result.expires_at)).toLocaleString() : 'Vĩnh viễn';
                    showAlert('Thanh toán thành công. License: ' + result.license_key + ' (Hết hạn: ' + expires + ')', 'success');
                    setTimeout(() => location.href = '/ShopToolNro/views/pages/orders.php', 2500);
                } else {
                    showAlert('Thanh toán thành công từ ví!', 'success');
                    setTimeout(() => location.href = '/ShopToolNro/views/pages/orders.php', 2000);
                }
            } else {
                showAlert('Đơn hàng đã được tạo. Vui lòng chuyển khoản và chờ admin xác nhận.', 'success');
            }
        } else {
            showAlert(result.message, 'error');
        }
    } catch (e) {
        showAlert('Lỗi khi tạo đơn: ' + (e.message || e), 'error');
    } finally {
        // Re-enable buttons if payment modal still exists (in case of error)
        paymentButtons.forEach(b => { b.disabled = false; b.style.pointerEvents = ''; });
    }
}

function showQRModal(qrLink, orderId) {
    const qrHTML = `
        <div class="modal fade" id="qrModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Quét mã QR để thanh toán</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="${qrLink}" alt="QR Code" class="img-fluid" style="max-width: 300px;">
                        <p class="mt-3 text-muted">Mã đơn hàng: <strong>${orderId}</strong></p>
                        <p>Vui lòng quét mã QR hoặc scan link trên để thanh toán</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', qrHTML);
    const modal = new bootstrap.Modal(document.getElementById('qrModal'));
    modal.show();
}

function updateAuthUI() {
    const loginBtn = document.getElementById('loginBtn');
    const accountBtn = document.getElementById('accountBtn');
    
    if (isLoggedIn()) {
        loginBtn.style.display = 'none';
        accountBtn.style.display = 'block';
    } else {
        loginBtn.style.display = 'block';
        accountBtn.style.display = 'none';
    }
}
