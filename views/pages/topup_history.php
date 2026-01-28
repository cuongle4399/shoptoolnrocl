<?php
$page_title = 'Lịch sử nạp tiền - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /ShopToolNro/views/pages/login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../src/classes/TopupRequest.php';
require_once '../../src/classes/User.php';

$database = new Database();
$db = $database->connect();
$topupClass = new TopupRequest($db);
$userClass = new User($db);

$user = $userClass->getUserById($_SESSION['user_id']);

// SSR Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// We fetch $perPage + 1 to see if there is a next page
$topupRequests = $topupClass->getUserTopupRequests($_SESSION['user_id'], $perPage + 1, $offset);
$hasNextPage = count($topupRequests) > $perPage;
if ($hasNextPage) {
    array_pop($topupRequests);
}

function pageLink($p)
{
    $qs = $_GET;
    $qs['page'] = $p;
    return '?' . http_build_query($qs);
}
?>

<div class="main-content fade-in">
    <h1>Lịch sử nạp tiền</h1>

    <div class="card card-pad mt-15" style="width: 100%; overflow: hidden;">
        <div class="table-wrapper">
            <table class="mt-15" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Số tiền</th>
                        <th>Mô tả</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topupRequests)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Chưa có yêu cầu nạp tiền nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topupRequests as $req): ?>
                            <tr>
                                <td><?php echo number_format($req['amount'] ?? 0, 0, ',', '.'); ?> ₫</td>
                                <td><?php echo htmlspecialchars($req['description'] ?? ''); ?></td>
                                <td>
                                    <span
                                        class="status-badge status-<?php echo htmlspecialchars($req['status'] ?? 'pending'); ?>">
                                        <?php
                                        $statusText = [
                                            'pending' => 'Chờ duyệt',
                                            'approved' => 'Đã duyệt',
                                            'rejected' => 'Từ chối',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        echo $statusText[$req['status']] ?? ($req['status'] ?? 'unknown');
                                        ?>
                                    </span>
                                    <?php if ($req['status'] === 'rejected' && !empty($req['rejection_reason'])): ?>
                                        <div class="rejection-reason"
                                            title="<?php echo htmlspecialchars($req['rejection_reason']); ?>">
                                            <small>(Lý do: <?php echo htmlspecialchars($req['rejection_reason']); ?>)</small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo convertToVNTime($req['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination (SSR) -->
        <?php if ($page > 1 || $hasNextPage): ?>
            <div class="flex-center mt-20 mb-20">
                <?php if ($page > 1): ?>
                    <a class="btn" href="<?php echo pageLink($page - 1); ?>">&laquo; Trước</a>
                <?php endif; ?>

                <div class="page-indicator" style="margin: 0 15px;">Trang <?php echo $page; ?></div>

                <?php if ($hasNextPage): ?>
                    <a class="btn" href="<?php echo pageLink($page + 1); ?>">Tiếp &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        min-width: 80px;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-approved {
        background: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .status-cancelled {
        background: #e2e8f0;
        color: #4a5568;
    }

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table th,
    table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    @media (max-width: 540px) {
        .main-content {
            padding: 0 10px;
        }

        .card.card-pad {
            padding: 15px;
            box-sizing: border-box;
        }
    }
</style>

<?php include '../layout/footer.php'; ?>