<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /ShopToolNro/index.php');
    exit;
}

$database = new Database();
$db = $database->connect();

// Get filter parameters
$filter = $_GET['filter'] ?? 'all'; // all, processed, unprocessed
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
if ($filter === 'processed') {
    $conditions[] = 'processed.eq.true';
} elseif ($filter === 'unprocessed') {
    $conditions[] = 'processed.eq.false';
}

// Fetch SePay transactions
// Fetch SePay transactions
$queryParams = http_build_query(array_merge([
    'select' => '*',
    'order' => 'created_at.desc',
    'limit' => $limit,
    'offset' => $offset
], !empty($conditions) ? array_combine(
        array_map(fn($c) => explode('.', $c)[0], $conditions),
        array_map(fn($c) => explode('.', $c, 2)[1], $conditions)
    ) : []));

$result = $db->callApi('sepay_transactions?' . $queryParams, 'GET');
$transactions = $result && $result->code == 200 ? $result->response : [];

// Get total count
$countParams = http_build_query(array_merge([
    'select' => 'count',
], !empty($conditions) ? array_combine(
        array_map(fn($c) => explode('.', $c)[0], $conditions),
        array_map(fn($c) => explode('.', $c, 2)[1], $conditions)
    ) : []));

$countResult = $db->callApi('sepay_transactions?' . $countParams, 'GET');
$totalCount = $countResult && $countResult->code == 200 ? ($countResult->response[0]['count'] ?? 0) : 0;
$totalPages = ceil($totalCount / $limit);

// Get webhook logs (last 50)
$logsParams = http_build_query([
    'select' => '*',
    'order' => 'created_at.desc',
    'limit' => 50
]);
$logsResult = $db->callApi('sepay_webhook_logs?' . $logsParams, 'GET');
$webhookLogs = $logsResult && $logsResult->code == 200 ? $logsResult->response : [];

// Get statistics
$statsProcessed = $db->callApi('sepay_transactions?select=count&processed=eq.true', 'GET');
$statsUnprocessed = $db->callApi('sepay_transactions?select=count&processed=eq.false', 'GET');
$statsTotalAmount = $db->callApi('sepay_transactions?select=amount_in.sum()', 'GET');

$stats = [
    'processed' => $statsProcessed && $statsProcessed->code == 200 ? ($statsProcessed->response[0]['count'] ?? 0) : 0,
    'unprocessed' => $statsUnprocessed && $statsUnprocessed->code == 200 ? ($statsUnprocessed->response[0]['count'] ?? 0) : 0,
    'total_amount' => $statsTotalAmount && $statsTotalAmount->code == 200 ? ($statsTotalAmount->response[0]['sum'] ?? 0) : 0
];

$pageTitle = 'Quản lý Webhook SePay';
include '../layout/header.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 12px;
        color: white;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .stat-card.success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .stat-card.warning {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stat-card.info {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stat-label {
        font-size: 13px;
        opacity: 0.9;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 28px;
        font-weight: bold;
    }

    .filter-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .filter-tab {
        padding: 10px 20px;
        border: 2px solid #667eea;
        background: white;
        color: #667eea;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
    }

    .filter-tab:hover {
        background: #f0f0f0;
    }

    .filter-tab.active {
        background: #667eea;
        color: white;
    }

    .transaction-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .transaction-table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }

    .transaction-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
    }

    .transaction-table tr:hover {
        background: #f8f9fa;
    }

    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
    }

    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }

    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }

    .amount-in {
        color: #28a745;
        font-weight: bold;
    }

    .amount-out {
        color: #dc3545;
        font-weight: bold;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .pagination a,
    .pagination span {
        padding: 8px 12px;
        border: 1px solid #ddd;
        background: white;
        color: #333;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .pagination a:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .pagination .active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .section-title {
        font-size: 20px;
        font-weight: bold;
        margin: 30px 0 15px 0;
        color: #333;
    }

    .webhook-url-box {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #ddd;
        margin-bottom: 20px;
    }

    .webhook-url-box code {
        background: #fff;
        padding: 8px 12px;
        border-radius: 4px;
        display: block;
        font-family: 'Courier New', monospace;
        word-break: break-all;
    }

    .btn-copy {
        margin-top: 10px;
        padding: 8px 16px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-copy:hover {
        background: #5568d3;
    }

    @media (max-width: 768px) {
        .transaction-table {
            font-size: 12px;
        }

        .transaction-table th,
        .transaction-table td {
            padding: 8px;
        }

        .stat-value {
            font-size: 22px;
        }
    }
</style>

<div class="container" style="max-width: 1400px; margin: 40px auto; padding: 0 20px;">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-webhook"></i> Quản lý Webhook SePay
    </h1>

    <!-- Webhook URL -->
    <div class="webhook-url-box">
        <strong>Webhook URL:</strong>
        <code
            id="webhookUrl"><?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/ShopToolNro/api/webhooks/sepay_receiver.php</code>
        <button class="btn-copy" onclick="copyWebhookUrl()">
            <i class="fas fa-copy"></i> Copy URL
        </button>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-label">Đã xử lý</div>
            <div class="stat-value">
                <?php echo number_format($stats['processed']); ?>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-label">Chưa xử lý</div>
            <div class="stat-value">
                <?php echo number_format($stats['unprocessed']); ?>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-label">Tổng tiền nhận</div>
            <div class="stat-value">
                <?php echo number_format($stats['total_amount'], 0, ',', '.'); ?>₫
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> Tất cả
        </a>
        <a href="?filter=processed" class="filter-tab <?php echo $filter === 'processed' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Đã xử lý
        </a>
        <a href="?filter=unprocessed" class="filter-tab <?php echo $filter === 'unprocessed' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Chưa xử lý
        </a>
    </div>

    <!-- Transactions Table -->
    <h2 class="section-title">Giao dịch SePay</h2>
    <div style="overflow-x: auto;">
        <table class="transaction-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ngày GD</th>
                    <th>Nội dung</th>
                    <th>Tiền vào</th>
                    <th>Tiền ra</th>
                    <th>Trạng thái</th>
                    <th>User</th>
                    <th>Topup ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                            Chưa có giao dịch nào
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $trans): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($trans['id']); ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($trans['transaction_date'])); ?>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($trans['transaction_content'] ?? 'N/A'); ?>
                            </td>
                            <td class="amount-in">
                                <?php echo $trans['amount_in'] > 0 ? '+' . number_format($trans['amount_in'], 0, ',', '.') . '₫' : '-'; ?>
                            </td>
                            <td class="amount-out">
                                <?php echo $trans['amount_out'] > 0 ? '-' . number_format($trans['amount_out'], 0, ',', '.') . '₫' : '-'; ?>
                            </td>
                            <td>
                                <?php if ($trans['processed']): ?>
                                    <span class="badge badge-success">Đã xử lý</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Chưa xử lý</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $trans['matched_user_id'] ? '#' . $trans['matched_user_id'] : '-'; ?>
                            </td>
                            <td>
                                <?php echo $trans['matched_topup_id'] ? '#' . $trans['matched_topup_id'] : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                    <i class="fas fa-chevron-left"></i> Trước
                </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active">
                        <?php echo $i; ?>
                    </span>
                <?php else: ?>
                    <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                    Sau <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Webhook Logs -->
    <h2 class="section-title">Lịch sử Webhook (50 gần nhất)</h2>
    <div style="overflow-x: auto;">
        <table class="transaction-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Thời gian</th>
                    <th>IP</th>
                    <th>Trạng thái</th>
                    <th>Transaction ID</th>
                    <th>Lỗi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($webhookLogs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                            Chưa có log nào
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($webhookLogs as $log): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($log['id']); ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                            </td>
                            <td>
                                <?php if ($log['success']): ?>
                                    <span class="badge badge-success">Thành công</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Thất bại</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $log['transaction_id'] ? '#' . $log['transaction_id'] : '-'; ?>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($log['error_message'] ?? '-'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function copyWebhookUrl() {
        const url = document.getElementById('webhookUrl').textContent;
        navigator.clipboard.writeText(url).then(() => {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Đã copy!';
            btn.style.background = '#28a745';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '#667eea';
            }, 2000);
        });
    }

    // Auto refresh every 30 seconds
    setTimeout(() => {
        location.reload();
    }, 30000);
</script>

<?php include '../layout/footer.php'; ?>