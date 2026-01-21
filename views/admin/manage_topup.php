<?php
// manage_topup.php - Admin topup approvals
$page_title = 'Quản lý nạp tiền - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/TopupRequest.php';
require_once '../../src/classes/User.php';

$database = new Database();
$db = $database->connect();
$topupClass = new TopupRequest($db);
$userClass = new User($db);

$perPage = 7;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$requests = $topupClass->getPendingRequests($perPage + 1, $offset);
$hasNext = count($requests) > $perPage;
if ($hasNext) array_pop($requests);

if ($page > 1 && empty($requests)) { header('Location: ?page=' . ($page - 1)); exit; }
function pageLink($p) { $qs = $_GET; $qs['page'] = $p; return '?' . http_build_query($qs); }
?>

<div class="main-content fade-in">
    <h1>Quản lý yêu cầu nạp tiền</h1>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Người dùng</th>
                <th>Số tiền</th>
                <th>Ngày tạo</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($requests)): ?>
                <?php foreach ($requests as $r): ?>
                    <?php $user = $userClass->getUserById($r['user_id']); ?> 
                    <tr>
                        <td><?php echo $r['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username'] ?? ('UID:' . $r['user_id'])); ?></td>
                        <td><?php echo number_format($r['amount'], 0, ',', '.'); ?> ₫</td>
                        <td><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-success btn-sm" onclick="openApproveModal(<?php echo $r['id']; ?>)">Duyệt</button>
                            <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?php echo $r['id']; ?>)">Từ chối</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (empty($requests)): ?>
        <div class="empty-state">Không có yêu cầu nào</div>
    <?php endif; ?>

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

<!-- Approve Modal -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Duyệt yêu cầu</h3><span class="modal-close" onclick="closeModal('approveModal')">&times;</span></div>
        <div class="modal-body">
            <input type="hidden" id="approveTopupId">
            <div class="form-group">
                <label>Ghi chú (tuỳ chọn)</label>
                <textarea id="approveNote" placeholder="Ghi chú cho người dùng / nội bộ"></textarea>
            </div>
            <button class="btn btn-success" onclick="submitApprove()">Xác nhận duyệt và cộng tiền</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Từ chối yêu cầu</h3><span class="modal-close" onclick="closeModal('rejectModal')">&times;</span></div>
        <div class="modal-body">
            <input type="hidden" id="rejectTopupId">
            <div class="form-group">
                <label>Ghi chú (lý do từ chối)</label>
                <textarea id="rejectNote" placeholder="Lý do từ chối"></textarea>
            </div>
            <button class="btn btn-danger" onclick="submitReject()">Xác nhận từ chối</button>
        </div>
    </div>
</div>

<script>
function openApproveModal(id) {
    document.getElementById('approveTopupId').value = id;
    document.getElementById('approveNote').value = '';
    document.getElementById('approveModal').classList.add('active');
}
function openRejectModal(id) {
    document.getElementById('rejectTopupId').value = id;
    document.getElementById('rejectNote').value = '';
    document.getElementById('rejectModal').classList.add('active');
}
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

async function submitApprove() {
    const id = document.getElementById('approveTopupId').value;
    const note = document.getElementById('approveNote').value;
    try {
        const res = await fetch('/ShopToolNro/api/admin/approve_topup.php', {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({topup_id: id, admin_note: note})
        });
        const data = await res.json();
        if (data.success) { showNotification(data.message, 'success'); setTimeout(() => location.reload(), 700); }
        else showNotification(data.message || 'Lỗi', 'error');
    } catch (e) { showNotification(e.message || 'Lỗi', 'error'); }
}

async function submitReject() {
    const id = document.getElementById('rejectTopupId').value;
    const note = document.getElementById('rejectNote').value;
    try {
        const res = await fetch('/ShopToolNro/api/admin/reject_topup.php', {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({topup_id: id, admin_note: note})
        });
        const data = await res.json();
        if (data.success) { showNotification(data.message, 'success'); setTimeout(() => location.reload(), 700); }
        else showNotification(data.message || 'Lỗi', 'error');
    } catch (e) { showNotification(e.message || 'Lỗi', 'error'); }
}
</script>

<?php include '../layout/footer.php'; ?>