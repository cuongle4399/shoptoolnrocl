<?php
// manage_products.php - Admin product management
$page_title = 'Quản lý sản phẩm - ShopToolNro';
include '../layout/header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/views/pages/login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/Product.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    echo '<div class="alert alert-danger">Lỗi kết nối cơ sở dữ liệu</div>';
    include '../layout/footer.php';
    exit;
}

$productClass = new Product($db);
$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$products = $productClass->getAllProducts(false, $perPage + 1, $offset);
$hasNext = count($products) > $perPage;
if ($hasNext) array_pop($products);
if ($page > 1 && empty($products)) { header('Location: ?page=' . ($page - 1)); exit; }
function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }
?>

<div class="main-content fade-in">
    <h1>Quản lý sản phẩm</h1> 
    
    <button id="addProductBtn" class="btn btn-primary" style="margin-bottom: 20px;">+ Thêm sản phẩm</button>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên</th>
                    <th>Danh mục</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category'] ?? '-'); ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="editProduct(<?php echo $product['id']; ?>)">Sửa</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo $product['id']; ?>)">Xóa</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state">Không có sản phẩm</div>
    <?php endif; ?>

<?php
    $hasPrev = $page > 1;
    $showPagination = $hasPrev || $hasNext;
?>

<?php if ($showPagination): ?>
    <div style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:14px;">
        <?php if ($hasPrev): ?>
            <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
        <?php endif; ?>
        <div style="padding:6px 12px;color:var(--text-light);">Trang <?php echo $page; ?></div>
        <?php if ($hasNext): ?>
            <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<!-- Modal thêm/sửa sản phẩm -->
<div id="productModal" class="modal">
    <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3 class="modal-title">Thêm sản phẩm</h3>
            <span class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</span>
        </div>
        <form id="productForm" enctype="multipart/form-data">
            <input type="hidden" name="product_id">
            
            <div class="form-group">
                <label>Tên sản phẩm</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>Mô tả</label>
                <textarea name="description" required></textarea>
            </div>

            <div class="form-group">
                <label>Danh mục</label>
                <input type="text" name="category" placeholder="Ví dụ: Tool, Software,...">
            </div>
            
            <div class="form-group">
                <label>Ảnh sản phẩm (chính)</label>
                <input type="file" name="image_file" accept="image/*">
                <input type="hidden" name="image_url">
                <small id="imagePreview" style="display: block; margin-top: 5px;"></small>
            </div>
            
            <div class="form-group">
                <label>Ảnh demo (có thể thêm nhiều)</label>
                <input type="file" id="demoImageFiles" name="demo_image_files[]" accept="image/*" multiple>
                <input type="hidden" name="demo_image_url">
                <div id="demoPreview" class="demo-preview" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;"></div>
            </div>
            
            <div class="form-group">
                <label>Link video YouTube</label>
                <input type="text" name="tutorial_video_url" placeholder="https://www.youtube.com/watch?v=VIDEO_ID hoặc https://www.youtube.com/embed/VIDEO_ID">
            </div>
            
            <div class="form-group">
                <label>Link phần mềm</label>
                <input type="text" name="software_link" placeholder="https://...">
            </div>

            <div class="form-group">
                <label>Thời hạn bản</label>
                <div id="durationsEditor" style="border:1px dashed #ddd; padding:10px; border-radius:6px;">
                    <div id="durationsList" style="display:flex; flex-direction:column; gap:8px; margin-bottom:8px;"></div>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <div style="display:flex; gap:6px; align-items:center;">
                            <button type="button" id="addDurationBtn" class="btn btn-secondary">+ Thêm</button>
                            <button type="button" id="add7Btn" class="btn btn-sm">7 ngày</button>
                            <button type="button" id="add30Btn" class="btn btn-sm">30 ngày</button>
                            <button type="button" id="addPermBtn" class="btn btn-sm">Vĩnh viễn</button>
                        </div>
                        <small class="text-muted">Để "Vĩnh viễn" để trống ô "Số ngày". Label sẽ tự tạo khi để trống.</small>
                    </div>
                </div>
            </div>            
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu</button>
        </form>
    </div>
</div>

<script>
// Fallback showNotification nếu admin.js/main.js chưa load
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

// Upload ảnh chính
document.querySelector('input[name="image_file"]').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        const response = await fetch('/ShopToolNro/api/upload/image.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            document.querySelector('input[name="image_url"]').value = result.data.url;
            document.getElementById('imagePreview').textContent = 'Ảnh đã chọn: ' + file.name;
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        showNotification('Lỗi upload: ' + (error.message || ''), 'error');
    }
});

// Demo images: allow multiple files and previews
let demoImageUrls = [];

function renderDemoPreviews() {
    const container = document.getElementById('demoPreview');
    container.innerHTML = '';
    demoImageUrls.forEach((url, idx) => {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.width = '120px';
        wrapper.style.height = '80px';
        wrapper.style.borderRadius = '6px';
        wrapper.style.overflow = 'hidden';
        wrapper.style.background = '#111';

        const img = document.createElement('img');
        img.src = url;
        img.alt = 'Demo ' + (idx+1);
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        wrapper.appendChild(img);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '×';
        btn.title = 'Xóa ảnh này';
        btn.style.position = 'absolute';
        btn.style.top = '6px';
        btn.style.right = '6px';
        btn.style.background = 'rgba(0,0,0,0.6)';
        btn.style.color = '#fff';
        btn.style.border = 'none';
        btn.style.borderRadius = '50%';
        btn.style.width = '22px';
        btn.style.height = '22px';
        btn.style.cursor = 'pointer';
        btn.style.fontWeight = 'bold';
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            demoImageUrls.splice(idx, 1);
            document.querySelector('input[name="demo_image_url"]').value = JSON.stringify(demoImageUrls);
            renderDemoPreviews();
        });
        wrapper.appendChild(btn);
        container.appendChild(wrapper);
    });
}

document.getElementById('demoImageFiles').addEventListener('change', async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    for (const file of files) {
        const formData = new FormData();
        formData.append('image', file);
        try {
            const response = await fetch('/ShopToolNro/api/upload/image.php', { 
                method: 'POST', 
                body: formData 
            });
            const result = await response.json();
            if (result.success) {
                demoImageUrls.push(result.data.url);
            } else {
                showNotification(result.message || 'Lỗi upload', 'error');
            }
        } catch (err) {
            showNotification('Lỗi upload: ' + (err.message || ''), 'error');
        }
    }
    document.querySelector('input[name="demo_image_url"]').value = JSON.stringify(demoImageUrls);
    renderDemoPreviews();
});

// --- Durations editor helper functions ---
let durationsMain = [];

// Helper functions for number formatting
function formatNumber(value) {
    if (value === '' || value === null || value === undefined) return '';
    const num = parseFloat(value.toString().replace(/,/g, ''));
    if (isNaN(num)) return '';
    return num.toLocaleString('vi-VN');
}

function parseFormattedNumber(value) {
    if (value === '' || value === null || value === undefined) return '';
    return value.toString().replace(/,/g, '').replace(/\./g, '');
}

function renderDurations() {
    const list = document.getElementById('durationsList');
    if (!list) return;
    list.innerHTML = '';
    durationsMain.forEach((d, idx) => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.gap = '8px';
        row.style.marginBottom = '8px';
        row.style.alignItems = 'center';

        const daysInput = document.createElement('input');
        daysInput.type = 'number';
        daysInput.className = 'duration-days';
        daysInput.placeholder = 'Số ngày (để trống = Vĩnh viễn)';
        daysInput.value = d.duration_days === null ? '' : d.duration_days;
        daysInput.style.width = '140px';
        daysInput.style.padding = '6px';

        const labelInput = document.createElement('input');
        labelInput.type = 'text';
        labelInput.className = 'duration-label';
        labelInput.placeholder = "Nhãn (ví dụ: '7 ngày')";
        labelInput.value = d.label || '';
        labelInput.style.flex = '1';
        labelInput.style.padding = '6px';

        const priceInput = document.createElement('input');
        priceInput.type = 'text';
        priceInput.className = 'duration-price format-currency';
        priceInput.placeholder = 'Giá (VNĐ)';
        priceInput.value = d.price;
        priceInput.style.width = '140px';
        priceInput.style.padding = '6px';
        
        // Format giá ban đầu
        if (priceInput.value) {
            priceInput.value = formatNumber(priceInput.value);
        }
        
        // Thêm event listener để format khi nhập
        priceInput.addEventListener('input', function(e) {
            const rawValue = parseFormattedNumber(e.target.value);
            const cursorPos = e.target.selectionStart;
            const oldLength = e.target.value.length;
            e.target.value = formatNumber(rawValue);
            const newLength = e.target.value.length;
            const lengthDiff = newLength - oldLength;
            e.target.setSelectionRange(cursorPos + lengthDiff, cursorPos + lengthDiff);
        });

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-danger btn-sm remove-duration';
        removeBtn.textContent = '×';
        removeBtn.title = 'Xóa';

        removeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            durationsMain.splice(idx, 1);
            renderDurations();
        });

        daysInput.addEventListener('change', (e) => {
            const val = e.target.value.trim();
            durationsMain[idx].duration_days = val === '' ? null : parseInt(val);
            if (!durationsMain[idx].label || durationsMain[idx].label === '') {
                durationsMain[idx].label = durationsMain[idx].duration_days === null ? 'Vĩnh viễn' : (durationsMain[idx].duration_days + ' ngày');
                renderDurations();
            }
        });
        labelInput.addEventListener('change', (e) => {
            durationsMain[idx].label = e.target.value;
        });
        priceInput.addEventListener('change', (e) => {
            // Parse giá trị đã format về số
            const rawValue = parseFormattedNumber(e.target.value);
            durationsMain[idx].price = parseFloat(rawValue) || 0;
        });

        row.appendChild(daysInput);
        row.appendChild(labelInput);
        row.appendChild(priceInput);
        row.appendChild(removeBtn);
        list.appendChild(row);
    });
}

// Thêm duration buttons event listeners
const addDurationBtn = document.getElementById('addDurationBtn');
if (addDurationBtn) {
    addDurationBtn.addEventListener('click', (e) => {
        e.preventDefault();
        durationsMain.push({ duration_days: 30, label: '30 ngày', price: 0 });
        renderDurations();
    });
}

const add7Btn = document.getElementById('add7Btn');
if (add7Btn) {
    add7Btn.addEventListener('click', (e) => {
        e.preventDefault();
        durationsMain.push({ duration_days: 7, label: '7 ngày', price: 0 });
        renderDurations();
    });
}

const add30Btn = document.getElementById('add30Btn');
if (add30Btn) {
    add30Btn.addEventListener('click', (e) => {
        e.preventDefault();
        durationsMain.push({ duration_days: 30, label: '30 ngày', price: 0 });
        renderDurations();
    });
}

const addPermBtn = document.getElementById('addPermBtn');
if (addPermBtn) {
    addPermBtn.addEventListener('click', (e) => {
        e.preventDefault();
        durationsMain.push({ duration_days: null, label: 'Vĩnh viễn', price: 0 });
        renderDurations();
    });
}

async function editProduct(id) {
    try {
        const res = await fetch('/ShopToolNro/api/products/detail.php?id=' + id);
        const result = await res.json();

        if (!result || result.status !== 'success') {
            showNotification(result.message || 'Không tìm thấy sản phẩm', 'error');
            return;
        }
        const product = result.data.product;
        const durations = result.data.durations || [];

        // fill form
        const modal = document.getElementById('productModal');
        modal.classList.add('active');
        modal.querySelector('.modal-title').textContent = 'Sửa sản phẩm';
        const form = document.getElementById('productForm');
        form.querySelector('input[name="product_id"]').value = product.id;
        form.querySelector('input[name="name"]').value = product.name || '';
        form.querySelector('textarea[name="description"]').value = product.description || '';
        form.querySelector('input[name="category"]').value = product.category || '';
        form.querySelector('input[name="image_url"]').value = product.image_url || '';
        form.querySelector('input[name="tutorial_video_url"]').value = product.tutorial_video_url || '';
        form.querySelector('input[name="software_link"]').value = product.software_link || '';

        // fill previews for main image
        document.getElementById('imagePreview').textContent = product.image_url ? 'Ảnh: ' + product.image_url : '';

        // fill demo images
        demoImageUrls = [];
        if (product.demo_image_url) {
            try {
                const arr = JSON.parse(product.demo_image_url);
                demoImageUrls = Array.isArray(arr) ? arr : [product.demo_image_url];
            } catch (e) {
                demoImageUrls = [product.demo_image_url];
            }
        }
        document.querySelector('input[name="demo_image_url"]').value = JSON.stringify(demoImageUrls);
        renderDemoPreviews();

        // populate durations editor
        durationsMain = durations.map(d => ({ 
            duration_days: d.duration_days === null ? null : parseInt(d.duration_days), 
            label: d.duration_label || d.label || (d.duration_days === null ? 'Vĩnh viễn' : (d.duration_days + ' ngày')), 
            price: parseFloat(d.price) 
        }));
        renderDurations();

    } catch (err) {
        showNotification('Lỗi tải sản phẩm: ' + (err.message || ''), 'error');
        console.error(err);
    }
}

function deleteProduct(id) {
    if (confirm('Bạn chắc chắn muốn xóa sản phẩm này?')) {
        fetch('/ShopToolNro/api/admin/delete_product.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product_id: id})
        }).then(async r => {
            const text = await r.text();
            console.log('Delete response status:', r.status);
            console.log('Delete response text:', text);
            
            if (!text) {
                throw new Error('Máy chủ trả về phản hồi trống');
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Phản hồi JSON không hợp lệ: ' + text.substring(0, 100));
            }
        }).then(data => {
            if (data.success) {
                showNotification('Đã xóa', 'success');
                setTimeout(() => location.reload(), 700);
            } else {
                showNotification(data.message || 'Lỗi xóa sản phẩm', 'error');
            }
        }).catch(err => {
            console.error('Delete product error:', err);
            showNotification('Lỗi xóa sản phẩm: ' + err.message, 'error');
        });
    }
}

document.getElementById('addProductBtn').addEventListener('click', () => {
    const modal = document.getElementById('productModal');
    modal.classList.add('active');
    modal.querySelector('.modal-title').textContent = 'Thêm sản phẩm';
    const form = document.getElementById('productForm');
    form.reset();
    demoImageUrls = [];
    document.querySelector('input[name="demo_image_url"]').value = JSON.stringify([]);
    renderDemoPreviews();
    durationsMain = [];
    renderDurations();
    document.getElementById('imagePreview').textContent = '';
    form.querySelector('input[name="product_id"]').value = '';
});

document.getElementById('productForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    // Build durations array from editor
    if (!Array.isArray(durationsMain) || durationsMain.length === 0) {
        showNotification('Vui lòng thêm ít nhất 1 thời hạn bản', 'error');
        return;
    }

    const builtDurations = [];
    for (const d of durationsMain) {
        // Parse giá từ giá trị đã được lưu (đã được parse trong event change)
        const price = parseFloat(d.price);
        if (isNaN(price) || price < 0) {
            showNotification('Giá của mỗi thời hạn phải là số hợp lệ >= 0', 'error');
            return;
        }
        const days = (d.duration_days === '' || d.duration_days === null || typeof d.duration_days === 'undefined') ? null : (isNaN(parseInt(d.duration_days)) ? null : parseInt(d.duration_days));
        builtDurations.push({ 
            duration_days: days, 
            duration_label: d.label && d.label.trim() !== '' ? d.label.trim() : (days === null ? 'Vĩnh viễn' : (days + ' ngày')), 
            price: price 
        });
    }
    data.durations = builtDurations;

    try {
        let url = '/ShopToolNro/api/admin/create_product.php';
        let method = 'POST';

        if (data.product_id && data.product_id.trim() !== '') {
            url = '/ShopToolNro/api/admin/update_product.php';
            data.id = parseInt(data.product_id);
        }

        delete data.product_id;
        delete data.image_file;
        delete data.demo_image_files;

        console.log('Sending data:', data);
        
        const response = await fetch(url, {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        if (!responseText) {
            showNotification('Server không trả về dữ liệu', 'error');
            return;
        }

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseErr) {
            console.error('JSON parse error:', parseErr);
            console.error('Response was:', responseText);
            showNotification('Lỗi: Server trả về dữ liệu không hợp lệ. Xem console để chi tiết.', 'error');
            return;
        }

        if (result.success) {
            showNotification('Đã lưu sản phẩm', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        showNotification('Lỗi: ' + (error.message || ''), 'error');
        console.error(error);
    }
});
</script>

<?php include '../layout/footer.php'; ?>