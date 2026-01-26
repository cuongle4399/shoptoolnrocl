<?php
$page_title = 'Quản lý Key - ShopToolNro';
include '../layout/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /ShopToolNro/views/pages/login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/License.php';

$database = new Database();
$db = $database->connect();
$license = new License($db);

$myKeys = $license->getUserKeys($_SESSION['user_id']);
?>

<div class="main-content fade-in">
    <h1>Quản lý Key đã mua</h1>

    <div class="card card-pad mt-15" style="width: 100%; overflow: hidden;">
        <div class="table-wrapper">
            <table class="mt-15" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>License Key</th>
                        <th>Trạng thái</th>
                        <th>Hết hạn</th>
                        <th>HWID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myKeys)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Bạn chưa có key nào.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myKeys as $key): ?>
                            <tr>
                                <td>
                                    <?php
                                    // Handle product name from joined data
                                    echo htmlspecialchars($key['products']['name'] ?? 'Unknown Product');
                                    ?>
                                </td>
                                <td>
                                    <div class="code-box">
                                        <span class="blur-text" onclick="this.classList.toggle('blur-text')">
                                            <?php echo htmlspecialchars($key['license_key']); ?>
                                        </span>
                                        <button class="copy-btn-mini"
                                            onclick="copyToClipboard('<?php echo $key['license_key']; ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($key['status']); ?>">
                                        <?php
                                        $statusMap = [
                                            'active' => 'Hoạt động',
                                            'inactive' => 'Vô hiệu',
                                            'banned' => 'Bị khóa',
                                            'expired' => 'Hết hạn'
                                        ];
                                        // Check expiry logic explicitly if status is active but date passed
                                        $isExpired = !empty($key['expires_at']) && strtotime($key['expires_at']) < time();
                                        if ($isExpired && $key['status'] === 'active') {
                                            echo 'Hết hạn';
                                        } else {
                                            echo $statusMap[$key['status']] ?? $key['status'];
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    if (empty($key['expires_at'])) {
                                        echo '<span class="text-success">Vĩnh viễn</span>';
                                    } else {
                                        $expiry = strtotime($key['expires_at']);
                                        $isExpired = $expiry < time();
                                        echo '<span class="' . ($isExpired ? 'text-danger' : '') . '">';
                                        echo convertToVNTime($key['expires_at']);
                                        echo '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (empty($key['hwid'])): ?>
                                        <span class="text-muted">Chưa kích hoạt</span>
                                    <?php else: ?>
                                        <code><?php echo htmlspecialchars(substr($key['hwid'], 0, 15)) . '...'; ?></code>
                                        <!-- Optional: Button to reset HWID if allowed -->
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .blur-text {
        filter: blur(4px);
        cursor: pointer;
        transition: filter 0.3s;
        user-select: none;
    }

    .blur-text:hover {
        filter: blur(2px);
    }

    /* When toggled off */
    .blur-text.blur-text {
        /* class is present */
    }

    /* Toggle logic: remove class to unblur */
    span:not(.blur-text) {
        filter: none;
    }

    .code-box {
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: monospace;
        background: rgba(0, 0, 0, 0.05);
        padding: 4px 8px;
        border-radius: 4px;
        max-width: fit-content;
    }

    .copy-btn-mini {
        background: none;
        border: none;
        color: #667eea;
        cursor: pointer;
        font-size: 12px;
        padding: 2px;
    }

    .copy-btn-mini:hover {
        color: #5a67d8;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
    }

    .status-active {
        background: #d4edda;
        color: #155724;
    }

    .status-inactive {
        background: #e2e3e5;
        color: #383d41;
    }

    .status-banned {
        background: #f8d7da;
        color: #721c24;
    }

    .status-expired {
        background: #fff3cd;
        color: #856404;
    }

    .text-success {
        color: #28a745;
    }

    .text-danger {
        color: #dc3545;
    }

    .text-muted {
        color: #6c757d;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .code-box {
            font-size: 11px;
        }
    }
</style>

<script>
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Đã copy Key vào bộ nhớ tạm', 'success');
            });
        } else {
            // Fallback
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand("Copy");
            textArea.remove();
            showNotification('Đã copy Key', 'success');
        }
    }
</script>

<?php include '../layout/footer.php'; ?>