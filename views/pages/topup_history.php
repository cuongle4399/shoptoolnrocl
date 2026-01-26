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
$topupRequests = $topupClass->getUserTopupRequests($_SESSION['user_id']);
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
                <tbody id="topupHistory">
                    <?php if (empty($topupRequests)): ?>
                        <tr id="emptyRow">
                            <td colspan="4" class="text-center">Chưa có yêu cầu nạp tiền nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topupRequests as $req): ?>
                            <tr>
                                <td><?php echo number_format($req['amount'], 0, ',', '.'); ?> ₫</td>
                                <td><?php echo htmlspecialchars($req['description'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $req['status']; ?>">
                                        <?php
                                        $statusText = [
                                            'pending' => 'Chờ duyệt',
                                            'approved' => 'Đã duyệt',
                                            'rejected' => 'Từ chối'
                                        ];
                                        echo $statusText[$req['status']] ?? $req['status'];
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

        <!-- Pagination -->
        <div id="paginationContainer" style="margin-top: 15px; text-align: center; padding-bottom: 20px;"></div>
    </div>
</div>

<style>
    /* Status Badge */
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

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        flex-wrap: wrap;
    }

    .pagination button {
        padding: 6px 12px;
        border: 1px solid #ddd;
        background: white;
        color: #333;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }

    .pagination button:hover {
        background: #f0f0f0;
        border-color: #667eea;
    }

    .pagination button.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Pagination
        const rowsPerPage = 10;
        let currentPage = 1;

        function setupPagination() {
            const tableBody = document.getElementById('topupHistory');
            const rows = Array.from(tableBody.querySelectorAll('tr')).filter(row => !row.id?.includes('empty'));

            if (rows.length === 0) return;

            const totalPages = Math.ceil(rows.length / rowsPerPage);

            function showPage(page) {
                rows.forEach(row => row.style.display = 'none');
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                rows.slice(start, end).forEach(row => row.style.display = '');
            }

            function renderPagination() {
                const container = document.getElementById('paginationContainer');
                container.innerHTML = '';

                if (totalPages <= 1) return;

                const pagination = document.createElement('div');
                pagination.className = 'pagination';

                // Nút Previous
                const prevBtn = document.createElement('button');
                prevBtn.textContent = '← Trước';
                prevBtn.disabled = currentPage === 1;
                prevBtn.onclick = () => {
                    if (currentPage > 1) {
                        currentPage--;
                        showPage(currentPage);
                        renderPagination();
                    }
                };
                pagination.appendChild(prevBtn);

                // Số trang
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.textContent = i;
                    btn.className = i === currentPage ? 'active' : '';
                    btn.onclick = () => {
                        currentPage = i;
                        showPage(currentPage);
                        renderPagination();
                    };
                    pagination.appendChild(btn);
                }

                // Nút Next
                const nextBtn = document.createElement('button');
                nextBtn.textContent = 'Sau →';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.onclick = () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        showPage(currentPage);
                        renderPagination();
                    }
                };
                pagination.appendChild(nextBtn);

                container.appendChild(pagination);
            }

            showPage(currentPage);
            renderPagination();
        }

        setupPagination();
    });
</script>

<?php include '../layout/footer.php'; ?>