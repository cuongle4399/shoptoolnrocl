<?php
$page_title = 'Quản lý thông báo - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/Notification.php';

// Prefer a Database wrapper (falls back to PDO or to Supabase API) so Notification has a working backend
$database = new Database();
$db = $database->connect();
$notificationClass = new Notification($db);

$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$notifications = $notificationClass->getAllNotifications($perPage + 1, $offset);
$hasNext = count($notifications) > $perPage;
if ($hasNext) array_pop($notifications);

if ($page > 1 && empty($notifications)) { header('Location: ?page=' . ($page - 1)); exit; }
function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }
?>

<div class="main-content fade-in">
    <h1>Thông báo cửa hàng</h1>
    
    <button class="btn btn-primary mb-20" onclick="document.getElementById('notifyModal').classList.add('active')">+ Thêm thông báo</button>
    
    <table>
        <thead>
            <tr>
                <th>Nội dung</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($notifications)): ?>
                <tr><td colspan="2"><div class="empty-state">Không có thông báo nào</div></td></tr>
            <?php else: foreach ($notifications as $notify): ?>
                <tr data-id="<?php echo $notify['id']; ?>" data-message="<?php echo htmlspecialchars($notify['message'], ENT_QUOTES); ?>">
                    <td><?php echo htmlspecialchars(substr($notify['message'], 0, 80)); ?><?php echo strlen($notify['message']) > 80 ? '...' : ''; ?></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="editNotify(<?php echo $notify['id']; ?>)">Sửa</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteNotify(<?php echo $notify['id']; ?>)">Xóa</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

<?php
    $hasPrev = $page > 1;
    $showPagination = $hasPrev || $hasNext;
?>

<?php if ($showPagination): ?>
    <div class="flex-center mt-14">
        <?php if ($hasPrev): ?>
            <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
        <?php endif; ?>
        <div class="page-indicator">Trang <?php echo $page; ?></div>
        <?php if ($hasNext): ?>
            <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?> 

</div>

<!-- Modal thêm thông báo -->
<div id="notifyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Thêm thông báo</h3>
            <span class="modal-close" onclick="this.closest('.modal').classList.remove('active')">&times;</span>
        </div>
        <form id="notifyForm">
            <input type="hidden" name="notify_id">
            <div class="form-group">
                <label>Nội dung</label>
                <textarea name="message" required placeholder="Nhập nội dung thông báo"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-fullwidth">Lưu</button>
        </form>

        <div class="mt-12">
            <h4>Preview</h4>
            <div id="notifyPreview" class="mt-8"></div>
        </div>
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

function editNotify(notifyId) {
    const row = document.querySelector('tr[data-id="' + notifyId + '"]');
    if (!row) return;
    document.querySelector('input[name="notify_id"]').value = notifyId;
    document.querySelector('textarea[name="message"]').value = row.dataset.message || '';

    document.getElementById('notifyModal').classList.add('active');
    updatePreview();
}

function updatePreview() {
    const message = (document.querySelector('textarea[name="message"]')?.value || '').trim();
    const preview = document.getElementById('notifyPreview');
    if (!preview) return;
    // small banner preview
    preview.innerHTML = `<div class="site-banner preview-banner mt-8">` +
        `<div class="banner-inner"><div class="banner-content">${message}</div><div class="spacer-40"></div></div></div>`; 
}

// update preview on input changes
document.querySelectorAll('#notifyForm input, #notifyForm textarea').forEach(el => {
    el.addEventListener('input', updatePreview);
    el.addEventListener('change', updatePreview);
});

// initialize preview for create form
updatePreview();
 

function deleteNotify(notifyId) {
    if (confirm('Xóa thông báo này?')) {
        fetch('/ShopToolNro/api/admin/delete_notification.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({notify_id: notifyId})
        }).then(async (r) => {
            const ct = r.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) {
                return r.json();
            }
            const txt = await r.text();
            throw new Error(txt || 'Invalid server response');
        }).then(data => {
            if (data.success) {
                showNotification('Đã xóa', 'success');
                setTimeout(() => location.reload(), 700);
            } else {
                showNotification(data.message || 'Lỗi', 'error');
            }
        }).catch(err => {
            showNotification(err.message || 'Lỗi', 'error');
        });
    }
}

document.getElementById('notifyForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);

    // only send the fields we need
    const payload = { message: data.message || '' };
    const notifyId = data.notify_id ? parseInt(data.notify_id, 10) : 0;
    const endpoint = notifyId ? '/ShopToolNro/api/admin/update_notification.php' : '/ShopToolNro/api/admin/create_notification.php';
    if (notifyId) payload.notify_id = notifyId;

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });

        const ct = response.headers.get('content-type') || '';
        let result;
        if (ct.indexOf('application/json') !== -1) {
            result = await response.json();
        } else {
            const txt = await response.text();
            throw new Error(txt || 'Invalid server response');
        }

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