<?php
$page_title = 'Quản lý giao dịch SePay - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/');
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->connect();

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Get transactions
$endpoint = "sepay_transactions?order=created_at.desc&limit=" . ($perPage + 1) . "&offset=" . $offset;
$result = $db->callApi($endpoint, 'GET');

$transactions = [];
$hasNext = false;

if ($result && $result->code == 200 && !empty($result->response)) {
    $transactions = $result->response;
    $hasNext = count($transactions) > $perPage;
    if ($hasNext) {
        array_pop($transactions);
    }
}

function pageLink($p)
{
    $qs = $_GET;
    $qs['page'] = $p;
    return '?' . http_build_query($qs);
}
?>

<div class="main-content fade-in">
    <h1>Quản lý giao dịch SePay</h1>

    <div class="card card-pad mt-15">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Tổng giao dịch hôm nay</div>
                <div class="stat-value" id="todayCount">-</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tổng tiền vào hôm nay</div>
                <div class="stat-value" id="todayAmountIn">-</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Đã xử lý</div>
                <div class="stat-value" id="processedCount">-</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Chưa xử lý</div>
                <div class="stat-value" id="pendingCount">-</div>
            </div>
        </div>
    </div>

    <div class="table-wrapper mt-20">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ngày GD</th>
                    <th>Nội dung</th>
                    <th>Tiền vào</th>
                    <th>Tiền ra</th>
                    <th>Trạng thái</th>
                    <th>User</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Chưa có giao dịch nào</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $trans): ?>
                        <tr>
                            <td>
                                <?php echo $trans['id']; ?>
                            </td>
                            <td>
                                <?php echo convertToVNTime($trans['transaction_date']); ?>
                            </td>
                            <td>
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($trans['transaction_content'] ?? ''); ?>
                                </div>
                            </td>
                            <td class="text-success">
                                <?php echo $trans['amount_in'] > 0 ? '+' . number_format($trans['amount_in'], 0, ',', '.') . ' ₫' : '-'; ?>
                            </td>
                            <td class="text-danger">
                                <?php echo $trans['amount_out'] > 0 ? '-' . number_format($trans['amount_out'], 0, ',', '.') . ' ₫' : '-'; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $trans['processed'] ? 'approved' : 'pending'; ?>">
                                    <?php echo $trans['processed'] ? 'Đã xử lý' : 'Chưa xử lý'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($trans['matched_user_id']): ?>
                                    <span class="text-info">User ID:
                                        <?php echo $trans['matched_user_id']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $trans['id']; ?>)">
                                    Chi tiết
                                </button>
                                <?php if (!$trans['processed'] && $trans['amount_in'] > 0): ?>
                                    <button class="btn btn-sm btn-success"
                                        onclick="processTransaction(<?php echo $trans['id']; ?>)">
                                        Xử lý
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($page > 1 || $hasNext): ?>
        <div class="flex-center mt-20">
            <?php if ($page > 1): ?>
                <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
            <?php endif; ?>
            <div class="page-indicator">Trang
                <?php echo $page; ?>
            </div>
            <?php if ($hasNext): ?>
                <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Chi tiết giao dịch</h3>
            <span class="modal-close" onclick="closeModal('detailModal')">&times;</span>
        </div>
        <div class="modal-body" id="detailContent">
            Loading...
        </div>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 10px;
        color: white;
    }

    .stat-label {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
    }

    .text-success {
        color: #28a745;
        font-weight: 600;
    }

    .text-danger {
        color: #dc3545;
        font-weight: 600;
    }

    .text-info {
        color: #17a2b8;
    }

    .text-muted {
        color: #6c757d;
    }
</style>

<script>
    // Load stats
    async function loadStats() {
        try {
            const res = await fetch('/ShopToolNro/api/webhooks/sepay_stats.php');
            const data = await res.json();

            if (data.success) {
                document.getElementById('todayCount').textContent = data.stats.today_count || 0;
                document.getElementById('todayAmountIn').textContent =
                    new Intl.NumberFormat('vi-VN').format(data.stats.today_amount_in || 0) + ' ₫';
                document.getElementById('processedCount').textContent = data.stats.processed_count || 0;
                document.getElementById('pendingCount').textContent = data.stats.pending_count || 0;
            }
        } catch (e) {
            console.error('Failed to load stats:', e);
        }
    }

    async function viewDetails(id) {
        document.getElementById('detailContent').innerHTML = 'Loading...';
        document.getElementById('detailModal').classList.add('active');

        try {
            const res = await fetch(`/ShopToolNro/api/webhooks/sepay_detail.php?id=${id}`);
            const data = await res.json();

            if (data.success) {
                const trans = data.transaction;
                document.getElementById('detailContent').innerHTML = `
                    <div class="detail-grid">
                        <div><strong>ID:</strong> ${trans.id}</div>
                        <div><strong>Gateway:</strong> ${trans.gateway || '-'}</div>
                        <div><strong>Ngày GD:</strong> ${trans.transaction_date}</div>
                        <div><strong>Số TK:</strong> ${trans.account_number || '-'}</div>
                        <div><strong>Tiền vào:</strong> <span class="text-success">${new Intl.NumberFormat('vi-VN').format(trans.amount_in)} ₫</span></div>
                        <div><strong>Tiền ra:</strong> <span class="text-danger">${new Intl.NumberFormat('vi-VN').format(trans.amount_out)} ₫</span></div>
                        <div><strong>Nội dung:</strong> ${trans.transaction_content || '-'}</div>
                        <div><strong>Mã tham chiếu:</strong> ${trans.reference_number || '-'}</div>
                        <div><strong>Trạng thái:</strong> ${trans.processed ? 'Đã xử lý' : 'Chưa xử lý'}</div>
                        <div><strong>User ID:</strong> ${trans.matched_user_id || '-'}</div>
                        <div><strong>Topup ID:</strong> ${trans.matched_topup_id || '-'}</div>
                    </div>
                `;
            } else {
                document.getElementById('detailContent').innerHTML = '<p class="text-danger">Không thể tải chi tiết</p>';
            }
        } catch (e) {
            document.getElementById('detailContent').innerHTML = '<p class="text-danger">Lỗi: ' + e.message + '</p>';
        }
    }

    async function processTransaction(id) {
        if (!confirm('Xử lý giao dịch này?')) return;

        try {
            const res = await fetch('/ShopToolNro/api/webhooks/sepay_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transaction_id: id })
            });

            const data = await res.json();

            if (data.success) {
                showNotification(data.message || 'Đã xử lý thành công', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message || 'Xử lý thất bại', 'error');
            }
        } catch (e) {
            showNotification('Lỗi: ' + e.message, 'error');
        }
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    // Load stats on page load
    loadStats();
</script>

<?php include '../layout/footer.php'; ?>