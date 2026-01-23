<?php
$page_title = 'Mã khuyến mãi - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/PromotionCode.php';

$database = new Database();
$db = $database->connect();
$promoClass = new PromotionCode($db);

$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$promos = $promoClass->getAllPromoCodes($perPage + 1, $offset);
$hasNext = count($promos) > $perPage;
if ($hasNext) array_pop($promos);

if ($page > 1 && empty($promos)) { header('Location: ?page=' . ($page - 1)); exit; }
function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }
?>

<div class="main-content fade-in">
    <h1>Quản lý mã khuyến mãi</h1> 
    
    <button class="btn btn-primary" onclick="openCreatePromoModal()" style="margin-bottom: 20px;">+ Tạo mã mới</button>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Giảm giá</th>
                    <th>Số lần dùng</th>
                    <th>Giá trị đơn tối thiểu</th>
                    <th>Hết hạn</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($promos)): foreach ($promos as $promo): ?> 


                    <tr>
                        <td><strong><?php echo htmlspecialchars($promo['code']); ?></strong></td>
                        <td>
                            <?php 
                            if ($promo['discount_percent']) {
                                echo $promo['discount_percent'] . '%';
                            } elseif ($promo['discount_amount']) {
                                echo number_format($promo['discount_amount'], 0, ',', '.') . ' ₫';
                            }
                            ?>
                        </td>
                        <td><?php echo $promo['usage_count']; ?><?php echo $promo['max_uses'] ? '/' . $promo['max_uses'] : ''; ?></td>
                        <td><?php echo $promo['min_order_amount'] ? number_format($promo['min_order_amount'], 0, ',', '.') . ' ₫' : 'Không'; ?></td>
                        <td><?php echo $promo['expires_at'] ? date('d/m/Y', strtotime($promo['expires_at'])) : 'Không hạn'; ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="editPromo(<?php echo $promo['id']; ?>)">Sửa</button>
                            <button class="btn btn-danger btn-sm" onclick="deletePromo(<?php echo $promo['id']; ?>)">Xóa</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($promos)): ?>
        <div class="empty-state">Không có mã khuyến mãi</div>
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

<script>
// Expose promo data to JS for quick edit prefilling
window.promos = {};
<?php foreach ($promos as $promo): ?>
window.promos[<?php echo $promo['id']; ?>] = <?php echo json_encode($promo); ?>;
<?php endforeach; ?>
</script>

<!-- Modal tạo/sửa mã -->
<div id="promoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Tạo mã khuyến mãi</h3>
            <span class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</span>
        </div>
        <form id="promoForm">
            <input type="hidden" name="promo_id">
            <div class="form-group" style="display:flex;gap:10px;align-items:center;">
                <div style="flex:1">
                    <label>Mã khuyến mãi</label>
                    <input type="text" name="code" required placeholder="VD: SUMMER2024">
                </div>
                <div style="width:140px;">
                    <button type="button" class="btn btn-outline" onclick="generatePromoCode()">🔀 Sinh mã</button>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>Giảm giá (%)</label>
                    <input type="number" name="discount_percent" min="0" max="100">
                </div>
                <div class="form-group">
                    <label>Hoặc giảm (₫)</label>
                    <input type="text" name="discount_amount" class="format-currency" placeholder="Số tiền giảm">
                </div>
            </div>
            <div class="form-group">
                <label>Số lần dùng tối đa</label>
                <input type="number" name="max_uses" placeholder="Để trống = không giới hạn">
            </div>
            <div class="form-group">
                <label>Giá trị đơn tối thiểu</label>
                <input type="text" name="min_order_amount" class="format-currency" placeholder="0 = không yêu cầu">
            </div>
            <div class="form-group">
                <label>Ngày hết hạn</label>
                <input type="date" name="expires_at">
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

function openCreatePromoModal() {
    document.getElementById('promoForm').reset();
    document.querySelector('input[name="promo_id"]').value = '';
    document.querySelector('#promoModal .modal-title').textContent = 'Tạo mã khuyến mãi';
    document.getElementById('promoModal').classList.add('active');
}

function editPromo(promoId) {
    const promo = window.promos[promoId];
    if (!promo) return openCreatePromoModal();

    document.querySelector('input[name="promo_id"]').value = promoId;
    document.querySelector('input[name="code"]').value = promo.code || '';
    document.querySelector('input[name="discount_percent"]').value = promo.discount_percent || '';
    
    // Format số tiền khi load vào form
    const discountAmountInput = document.querySelector('input[name="discount_amount"]');
    if (promo.discount_amount) {
        discountAmountInput.value = formatNumber(promo.discount_amount);
    } else {
        discountAmountInput.value = '';
    }
    
    const minOrderAmountInput = document.querySelector('input[name="min_order_amount"]');
    if (promo.min_order_amount) {
        minOrderAmountInput.value = formatNumber(promo.min_order_amount);
    } else {
        minOrderAmountInput.value = '';
    }
    
    document.querySelector('input[name="max_uses"]').value = promo.max_uses || '';
    
    // set date input in YYYY-MM-DD if exists
    if (promo.expires_at) {
        const d = new Date(promo.expires_at);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        document.querySelector('input[name="expires_at"]').value = `${yyyy}-${mm}-${dd}`;
    } else {
        document.querySelector('input[name="expires_at"]').value = '';
    }

    document.querySelector('#promoModal .modal-title').textContent = 'Sửa mã khuyến mãi';
    document.getElementById('promoModal').classList.add('active');
}

function deletePromo(promoId) {
    if (confirm('Xóa mã khuyến mãi này?')) {
        fetch('/ShopToolNro/api/admin/delete_promo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({promo_id: promoId})
        }).then(r => r.json()).then(data => {
            if (data.success) {
                showNotification('Đã xóa', 'success');
                setTimeout(() => location.reload(), 700);
            } else {
                showNotification(data.message || 'Lỗi', 'error');
            }
        });
    }
}

// Sinh mã ngẫu nhiên ví dụ: PROMO-6A7B
function generatePromoCode() {
    const charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // avoid ambiguous chars
    const len = 6;
    let s = '';
    for (let i = 0; i < len; i++) s += charset.charAt(Math.floor(Math.random() * charset.length));
    const code = 'PROMO-' + s;
    document.querySelector('input[name="code"]').value = code;
    showNotification('Mã đã sinh: ' + code, 'success');
}

document.getElementById('promoForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    // Parse số tiền từ các input đã format
    const discountAmountInput = e.target.querySelector('input[name="discount_amount"]');
    const minOrderAmountInput = e.target.querySelector('input[name="min_order_amount"]');
    
    if (discountAmountInput && discountAmountInput.value) {
        data.discount_amount = parseFormattedNumber(discountAmountInput.value);
    }
    if (minOrderAmountInput && minOrderAmountInput.value) {
        data.min_order_amount = parseFormattedNumber(minOrderAmountInput.value);
    }
    
    if (!data.discount_percent && !data.discount_amount) {
        showNotification('Phải nhập giảm giá (% hoặc tiền)', 'error');
        return;
    }

    try {
        const isEdit = data.promo_id && data.promo_id.trim() !== '';
        const endpoint = isEdit ? '/ShopToolNro/api/admin/update_promo.php' : '/ShopToolNro/api/admin/create_promo.php';

        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Đã lưu', 'success');
            setTimeout(() => location.reload(), 700);
        } else {
            showNotification(result.message || 'Lỗi', 'error');
        }
    } catch (error) {
        showNotification(error.message || 'Lỗi', 'error');
    }
});
</script>

<?php include '../layout/footer.php'; ?>
