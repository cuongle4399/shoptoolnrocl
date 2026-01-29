<?php
$page_title = 'Nạp tiền - ShopToolNro';
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

// Lấy pending topup của user
$pendingTopup = null;
if (!empty($topupRequests)) {
    foreach ($topupRequests as $req) {
        if ($req['status'] === 'pending') {
            $pendingTopup = $req;
            break;
        }
    }
}

// VietQR config + nội dung chuyển khoản cho request đang chờ
$vietqrConfig = [
    'bank_code' => VIETQR_BANK_CODE ?: '',
    'bank_name' => VIETQR_BANK_FULL_NAME ?: (VIETQR_BANK_NAME ?: (VIETQR_BANK_CODE ?: '')),
    'account_no' => VIETQR_ACCOUNT_NO ?: '',
    'account_name' => VIETQR_ACCOUNT_NAME ?: ''
];

$pendingTopupId = $pendingTopup['id'] ?? 0;
$pendingAmountRaw = $pendingTopup['amount'] ?? 0;
$normalizedUsername = strtolower(preg_replace('/[^a-z0-9]/', '', $user['username'] ?? 'user'));
$transferContent = $pendingTopup
    ? sprintf('shop%d', $pendingTopupId)
    : '';
?>

<div class="main-content fade-in">
    <h1>Nạp tiền</h1>

    <div class="mb-40">
        <!-- Form nạp tiền hoặc QR Code -->
        <div>


            <?php if (!$pendingTopup): ?>
                <!-- FORM NẠP TIỀN (khi không có pending) -->
                <form id="topupForm" class="card card-pad mt-15" style="padding: 20px;">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="amount" style="display: block; margin-bottom: 8px; font-weight: bold;">Nhập số tiền
                            (VNĐ)</label>
                        <input type="text" id="amount" name="amount" class="format-currency"
                            placeholder="Tối thiểu 10.000 VNĐ" required
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <small style="display: block; color: #666; margin-top: 5px;">Nhập số tiền muốn nạp vào tài
                            khoản</small>
                    </div>

                    <button type="submit" class="btn btn-primary"
                        style="width: 100%; padding: 12px; font-size: 16px; font-weight: bold;">
                        Tạo yêu cầu nạp tiền
                    </button>
                </form>
            <?php else: ?>
                <!-- QR CODE + FORM CHUYỂN KHOẢN (khi có pending) -->
                <div class="card card-pad mt-15 pending-layout">
                    <!-- QR CODE COL -->
                    <div class="qr-col">
                        <div class="qr-code-box">
                            <img id="qrImage" src="" alt="QR Code">
                            <p>Quét mã QR để thanh toán</p>
                        </div>
                    </div>

                    <!-- FORM INFO COL -->
                    <div class="info-col">
                        <div class="transfer-form">
                            <h4 class="transfer-title">Hoặc nhập thông tin chuyển khoản:</h4>

                            <div class="form-field">
                                <label>Ngân hàng</label>
                                <div class="field-value copy-field">
                                    <input type="text" id="bankName"
                                        value="<?php echo htmlspecialchars($vietqrConfig['bank_name'] ?: 'Chưa cấu hình'); ?>"
                                        readonly>
                                    <button type="button" class="copy-btn"
                                        onclick="copyToClipboard('bankName', this)">Copy</button>
                                </div>
                            </div>

                            <div class="form-field">
                                <label>Tên tài khoản</label>
                                <div class="field-value copy-field">
                                    <input type="text" id="accountName"
                                        value="<?php echo htmlspecialchars($vietqrConfig['account_name'] ?: 'Chưa cấu hình'); ?>"
                                        readonly>
                                    <button type="button" class="copy-btn"
                                        onclick="copyToClipboard('accountName', this)">Copy</button>
                                </div>
                            </div>

                            <div class="form-field">
                                <label>Số tài khoản</label>
                                <div class="field-value copy-field">
                                    <input type="text" id="accountNo"
                                        value="<?php echo htmlspecialchars($vietqrConfig['account_no'] ?: ''); ?>" readonly>
                                    <button type="button" class="copy-btn"
                                        onclick="copyToClipboard('accountNo', this)">Copy</button>
                                </div>
                            </div>

                            <div class="form-field">
                                <label>Nội dung chuyển khoản</label>
                                <div class="field-value copy-field">
                                    <input type="text" id="transferDesc"
                                        value="<?php echo htmlspecialchars($transferContent); ?>" readonly>
                                    <button type="button" class="copy-btn"
                                        onclick="copyToClipboard('transferDesc', this)">Copy</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" onclick="cancelPendingTopup()" class="btn btn-secondary btn-cancel">Huỷ yêu
                            cầu</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* PENDING LAYOUT (New Grid System for PC) */
    .pending-layout {
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 20px !important;
    }

    .qr-col {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .info-col {
        width: 100%;
    }

    .transfer-title {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        font-size: 15px;
    }

    .btn-cancel {
        width: 100%;
        padding: 12px;
        margin-top: 15px;
    }

    /* Desktop Grid Layout (768px+) */
    @media (min-width: 768px) {
        .pending-layout {
            display: grid !important;
            grid-template-columns: 350px 1fr;
            /* QR fixed width, Info flexible */
            gap: 40px;
            align-items: start;
            max-width: 900px !important;
            /* Wider on PC */
        }

        .qr-col {
            border-right: 1px solid #eee;
            padding-right: 20px;
        }

        .qr-code-box {
            margin: 0 !important;
            border: none;
            background: transparent;
            padding: 0;
        }

        .qr-code-box img {
            max-width: 100%;
            border: 1px solid #eee;
        }

        .transfer-form {
            max-width: 100%;
            margin: 0;
            padding: 0;
            border: none;
            background: transparent;
        }

        .btn-cancel {
            width: auto;
            min-width: 150px;
        }
    }

    /* Fix overflow toàn trang trên mobile */
    @media (max-width: 540px) {

        html,
        body {
            overflow-x: hidden !important;
            width: 100% !important;
            max-width: 100vw !important;
        }

        * {
            box-sizing: border-box !important;
        }
    }

    /* QR Code Box */
    .qr-code-box {
        background: #f8f9fa;
        border: 2px dashed #ddd;
        border-radius: 10px;
        padding: 16px;
        margin: 0 0 20px 0;
        text-align: center;
        max-width: 100%;
        width: 100%;
        box-sizing: border-box;
    }

    .qr-code-box img {
        width: 100%;
        height: auto;
        max-width: 100%;
        border-radius: 8px;
        background: white;
        padding: 10px;
        display: block;
        margin: 0 auto;
        object-fit: contain;
    }

    .card.card-pad {
        width: 100%;
        box-sizing: border-box;
        overflow: visible;
    }

    @media (min-width: 768px) {
        .card.card-pad:not(.pending-layout) {
            /* Only limit width for normal form */
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
        }

        .card.card-pad.pending-layout {
            margin-left: auto;
            margin-right: auto;
        }
    }

    .form-field {
        margin-bottom: 12px;
        width: 100%;
        box-sizing: border-box;
    }

    .form-field:last-child {
        margin-bottom: 0;
    }

    .form-field label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #666;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Field Value - RESPONSIVE FIX */
    .field-value {
        display: block;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    .field-value input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 6px 6px 0 0;
        font-size: 13px;
        background: #f8f9fa;
        color: #333;
        box-sizing: border-box;
        word-break: break-all;
        display: block;
        margin: 0;
    }

    #transferDesc {
        font-size: 11px;
    }

    .field-value input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
    }

    .copy-btn {
        width: 100%;
        padding: 10px 12px;
        background: #667eea;
        color: white;
        border: 1px solid #667eea;
        border-radius: 0 0 6px 6px;
        border-top: none;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
        margin-top: 0;
        display: block;
        box-sizing: border-box;
    }

    .copy-btn:hover {
        background: #5568d3;
        border-color: #5568d3;
    }

    .copy-btn:active {
        background: #4557c1;
    }

    /* Desktop - 768px+ */
    @media (min-width: 768px) {
        .form-field {
            margin-bottom: 12px;
        }

        .field-value {
            display: flex;
            gap: 0;
            align-items: stretch;
        }

        .field-value input {
            flex: 1;
            min-width: 0;
            border-radius: 6px 0 0 6px;
        }

        .copy-btn {
            width: auto;
            padding: 8px 14px;
            border-radius: 0 6px 6px 0;
            border-left: none;
            flex-shrink: 0;
        }
    }

    /* Tablet - 541px to 767px */
    @media (min-width: 541px) and (max-width: 767px) {
        .form-field {
            margin-bottom: 10px;
        }

        .field-value {
            display: block;
            gap: 0;
        }

        .field-value input {
            font-size: 12px;
            border-radius: 6px 6px 0 0;
        }

        #transferDesc {
            font-size: 10px;
        }

        .copy-btn {
            padding: 8px 12px;
            font-size: 11px;
            border-radius: 0 0 6px 6px;
            width: 100%;
        }
    }

    /* Mobile - 540px and below */
    @media (max-width: 540px) {
        .main-content {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 10px !important;
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }

        .card.card-pad {
            width: 100% !important;
            max-width: 100% !important;
            padding: 15px !important;
            margin: 0 !important;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-sizing: border-box !important;
            overflow: visible !important;
        }

        /* IMPORTANT: Keep stack layout on mobile */
        .pending-layout {
            display: flex !important;
            flex-direction: column !important;
        }

        .qr-col {
            border-right: none !important;
            padding-right: 0 !important;
        }

        .transfer-form {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 auto !important;
            border: none !important;
            background: transparent !important;
            box-sizing: border-box !important;
            overflow: visible !important;
        }

        /* Allow h4 title on mobile for pending layout context */
        .transfer-title {
            display: block !important;
            margin-bottom: 10px !important;
        }

        .form-field {
            width: 100% !important;
            max-width: 100% !important;
            margin-bottom: 15px !important;
            box-sizing: border-box !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        .form-field:last-child {
            margin-bottom: 0 !important;
        }

        .form-field label {
            font-size: 12px !important;
            margin-bottom: 6px !important;
            display: block !important;
        }

        .copy-btn {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            padding: 10px 12px !important;
            font-size: 13px !important;
            border-radius: 0 0 6px 6px !important;
            border-top: 1px solid #ddd !important;
            margin-top: 0 !important;
            box-sizing: border-box !important;
            border: 1px solid #667eea !important;
            background: #667eea !important;
            color: white !important;
        }

        .field-value {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            gap: 0 !important;
        }

        .field-value input {
            width: 100% !important;
            max-width: 100% !important;
            min-width: auto !important;
            font-size: 14px !important;
            padding: 12px 12px !important;
            box-sizing: border-box !important;
            cursor: pointer !important;
            display: block !important;
            margin: 0 !important;
            border: 1px solid #ddd !important;
            border-radius: 6px 6px 0 0 !important;
            background: #f8f9fa !important;
        }

        .field-value input:focus {
            outline: none !important;
            border-color: #667eea !important;
            background: white !important;
        }

        #transferDesc {
            font-size: 13px !important;
            letter-spacing: 0 !important;
        }

        .card.card-pad>.btn-secondary,
        .card.card-pad>button.btn-secondary,
        .btn-cancel {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            padding: 14px !important;
            font-size: 14px !important;
            border-radius: 8px !important;
            margin-top: 15px !important;
            box-sizing: border-box !important;
            text-align: center !important;
        }

        .table-wrapper {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
        }

        table {
            width: 100% !important;
            font-size: 13px !important;
        }

        table th,
        table td {
            padding: 10px 8px !important;
        }
    }

    /* Amount Highlight */
    .amount-highlight {
        background: linear-gradient(135deg, #fffacd 0%, #fff9e6 100%);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
        border: 1px solid #ffe59a;
    }

    .amount-highlight p {
        margin: 0;
        color: #666;
        font-size: 12px;
        margin-bottom: 8px;
    }

    .amount-highlight .big-amount {
        font-size: 32px;
        font-weight: bold;
        color: #667eea;
        margin: 0;
    }

    /* Pending Status */
    .pending-status {
        background: linear-gradient(135deg, #fff3cd 0%, #fffbf0 100%);
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .pending-header {
        font-weight: bold;
        color: #856404;
        margin-bottom: 10px;
        font-size: 13px;
    }

    .pending-content {
        background: white;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
    }

    .pending-amount {
        font-weight: bold;
        color: #ffc107;
        font-size: 15px;
    }

    .pending-time {
        color: #999;
        font-size: 11px;
    }

    .pending-note {
        margin: 0;
        font-size: 11px;
        color: #856404;
    }

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

    .btn-secondary {
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.3s;
    }

    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-1px);
    }

    .btn-secondary:active {
        transform: translateY(0);
    }

    /* Notification Toast */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 14px 18px;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        font-size: 14px;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        max-width: 400px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .notification.success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border-left: 4px solid #20c997;
    }

    .notification.error {
        background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
        border-left: 4px solid #ff6b6b;
    }

    .notification.info {
        background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%);
        border-left: 4px solid #0dcaf0;
    }

    .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    .close-btn:hover {
        opacity: 1;
    }

    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }

        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    .notification.remove {
        animation: slideOut 0.3s ease-out;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const userId = '<?php echo htmlspecialchars($_SESSION['user_id']); ?>';
        const username = '<?php echo htmlspecialchars($user['username'] ?? 'user'); ?>';
        const hasPending = <?php echo $pendingTopup ? 'true' : 'false'; ?>;
        const pendingAmount = <?php echo $pendingTopup ? intval($pendingTopup['amount']) : 0; ?>;
        const pendingTopupId = <?php echo (int) $pendingTopupId; ?>;
        const bankCode = <?php echo json_encode($vietqrConfig['bank_code'] ?? ''); ?>;
        const bankName = <?php echo json_encode($vietqrConfig['bank_name'] ?? ''); ?>;
        const accountNo = <?php echo json_encode($vietqrConfig['account_no'] ?? ''); ?>;
        const accountName = <?php echo json_encode($vietqrConfig['account_name'] ?? ''); ?>;
        const transferContent = <?php echo json_encode($transferContent); ?>;

        let checkInterval = null;



        // Copy to clipboard with enhanced mobile support
        window.copyToClipboard = function (elementId, button) {
            const element = document.getElementById(elementId);
            const text = element.value || element.textContent;

            // Try modern Clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    handleCopySuccess(element, button, text);
                }).catch(() => {
                    fallbackCopy(text, element, button);
                });
            } else {
                // Fallback for older browsers
                fallbackCopy(text, element, button);
            }
        };

        function handleCopySuccess(element, button, text) {
            if (button) {
                const originalText = button.textContent;
                button.textContent = 'Đã copy ✓';
                button.style.background = '#20c997';
                button.style.color = 'white';

                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '#667eea';
                    button.style.color = 'white';
                }, 1500);
            } else {
                // Mobile: show visual feedback
                element.style.background = '#d4edda';
                element.style.borderColor = '#20c997';
                setTimeout(() => {
                    element.style.background = '#f8f9fa';
                    element.style.borderColor = '#ddd';
                }, 1000);
            }
            showNotification('Đã copy ✓', 'success');
        }

        function fallbackCopy(text, element, button) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                handleCopySuccess(element, button, text);
            } catch (err) {
                showNotification('Copy thất bại, vui lòng thử lại', 'error');
            } finally {
                document.body.removeChild(textarea);
            }
        }

        // Mobile: tap input to copy
        if (window.innerWidth <= 540) {
            document.querySelectorAll('.field-value.copy-field input').forEach(input => {
                input.addEventListener('click', function (e) {
                    e.preventDefault();
                    copyToClipboard(this.id, null);
                });
                // Add visual feedback on touch
                input.addEventListener('touchstart', function () {
                    this.style.opacity = '0.7';
                });
                input.addEventListener('touchend', function () {
                    this.style.opacity = '1';
                });
            });
        }

        // Generate QR code
        function buildTransferContent(amount) {
            if (transferContent) return transferContent;
            const safeUser = (username || 'user').replace(/[^a-z0-9]/g, '').toLowerCase();
            const safeAmount = Number.isFinite(amount) ? Math.round(amount) : 0;
            // Format: shoptoolnro{username}{amount} (no dashes - bank doesn't accept special chars)
            return `shoptoolnro${safeUser}${safeAmount}`;
        }

        function generateQRCode(amount) {
            const safeAmount = Number.isFinite(amount) ? Math.round(amount) : 0;
            if (!safeAmount || !accountNo) return;

            const transferDesc = buildTransferContent(safeAmount);
            const bankId = bankCode || bankName || 'mbbank';
            const cacheBuster = Date.now();
            const qrUrl = `https://img.vietqr.io/image/${bankId}-${accountNo}-compact2.png?amount=${safeAmount}&addInfo=${encodeURIComponent(transferDesc)}&accountName=${encodeURIComponent(accountName || bankName || '')}&t=${cacheBuster}`;

            const qrImage = document.getElementById('qrImage');
            if (qrImage) {
                qrImage.src = qrUrl;
                qrImage.alt = `QR ${bankId} ${accountNo}`;
            }
        }

        // Kiểm tra trạng thái yêu cầu nạp tiền
        function checkTopupStatus() {
            if (!hasPending || pendingAmount <= 0) {
                return;
            }

            const pendingId = <?php echo $pendingTopup ? intval($pendingTopup['id']) : 0; ?>;

            if (!pendingId) {
                return;
            }

            fetch(`/ShopToolNro/api/topup/check_status.php?id=${pendingId}`, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.status === 'approved') {
                            showTopupSuccessModal(data.amount);
                            stopStatusCheck();
                        } else if (data.status === 'rejected') {
                            showNotification('Yêu cầu nạp tiền bị từ chối. Vui lòng liên hệ admin.', 'error');
                            stopStatusCheck();
                            setTimeout(() => location.reload(), 2000);
                        }
                    }
                })
                .catch(error => console.error('Status check error:', error));
        }

        function startStatusCheck() {
            checkTopupStatus();
            checkInterval = setInterval(checkTopupStatus, 10000);
        }

        function stopStatusCheck() {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
        }

        window.cancelPendingTopup = function () {
            if (!hasPending || pendingAmount <= 0) {
                showNotification('Không có yêu cầu nào để hủy', 'error');
                return;
            }

            const pendingId = <?php echo $pendingTopup ? intval($pendingTopup['id']) : 0; ?>;

            if (!pendingId) {
                showNotification('Không tìm thấy ID yêu cầu', 'error');
                return;
            }

            fetch('/ShopToolNro/api/topup/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    topup_id: pendingId
                })
            })
                .then(response => response.json())
                .then(result => {
                    stopStatusCheck();
                    if (result.success) {
                        showNotification('Đã hủy yêu cầu nạp tiền', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Lỗi: ' + (result.message || 'Không thể hủy yêu cầu'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Cancel error:', error);
                    showNotification('Lỗi kết nối: ' + error.message, 'error');
                });
        };

        // Fallback showNotification nếu main.js chưa load
        if (typeof showNotification === 'undefined') {
            window.showNotification = function (message, type = 'success', duration = 3200) {
                console.log('showNotification called:', { message, type, duration });
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

        // Form submit
        const topupForm = document.getElementById('topupForm');
        if (topupForm) {
            topupForm.addEventListener('submit', function (e) {
                e.preventDefault();
                // Parse số từ input đã format
                const amountInput = document.getElementById('amount');
                let rawAmount = amountInput.value;

                // Remove Vietnamese thousand separators (.): "200.000" -> "200000"
                rawAmount = rawAmount.replace(/\./g, '');
                // Replace comma with empty if needed: "200,00" -> "20000"
                rawAmount = rawAmount.replace(/,/g, '');

                const amount = parseInt(rawAmount);

                console.log('Amount input:', amountInput.value, 'Parsed:', amount);

                if (!amount || isNaN(amount) || amount < 10000) {
                    showNotification('Vui lòng nhập số tiền hợp lệ (tối thiểu 10.000)', 'error');
                    return;
                }

                const data = {
                    amount: amount,
                    method: 'vietqr',
                    description: buildTransferContent(amount)
                };

                console.log('Sending data:', data);

                fetch('/ShopToolNro/api/topup/create_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                    .then(response => response.json())
                    .then(result => {
                        console.log('Response:', result);
                        if (result.success) {
                            showNotification('Yêu cầu nạp tiền đã được tạo. Vui lòng chuyển khoản!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showNotification('Lỗi: ' + (result.message || 'Không thể tạo yêu cầu'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('✗ Lỗi: ' + error.message, 'error');
                    });

                amountInput.value = '';
            });
        }

        // Nếu có pending, bắt đầu check và generate QR
        if (hasPending && pendingAmount > 0) {
            const transferDescInput = document.getElementById('transferDesc');
            if (transferDescInput && !transferDescInput.value) {
                transferDescInput.value = buildTransferContent(pendingAmount);
            }

            generateQRCode(pendingAmount);
            startStatusCheck();
        }
    });
</script>

<!-- Success Modal for Topup Approval -->
<div id="topupSuccessModal" class="topup-success-modal">
    <div class="topup-success-content">
        <div class="topup-success-icon">✓</div>
        <h2>Nạp tiền thành công</h2>
        <p id="topupSuccessMessage">Số dư đã được cộng thêm</p>
        <button class="btn btn-primary" onclick="closeTopupSuccessModal()">Đóng</button>
    </div>
</div>

<style>
    .topup-success-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.75);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        transition: all 0.3s ease;
        opacity: 0;
    }

    .topup-success-modal.active {
        display: flex;
        opacity: 1;
    }

    .topup-success-content {
        background: #1e1e1e;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        transform: scale(0.9);
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        max-width: 400px;
        width: 90%;
        position: relative;
    }

    .topup-success-modal.active .topup-success-content {
        transform: scale(1);
    }

    .topup-success-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        color: #10b981;
        background: rgba(16, 185, 129, 0.1);
        border: 2px solid rgba(16, 185, 129, 0.2);
        animation: success-pulse 2s infinite;
    }

    @keyframes success-pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
        }

        70% {
            box-shadow: 0 0 0 15px rgba(16, 185, 129, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
        }
    }

    .topup-success-content h2 {
        margin: 0 0 12px 0;
        font-size: 24px;
        font-weight: 700;
        color: #ffffff;
    }

    .topup-success-content p {
        margin: 0 0 32px 0;
        font-size: 16px;
        color: #a0aec0;
        line-height: 1.5;
    }

    .topup-success-content .btn {
        width: 100%;
        padding: 14px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 12px;
        background: #10b981;
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);
    }

    .topup-success-content .btn:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.4);
    }

    .topup-success-content .btn:active {
        transform: translateY(0);
    }
</style>

<script>
    function showTopupSuccessModal(amount) {
        const modal = document.getElementById('topupSuccessModal');
        const message = document.getElementById('topupSuccessMessage');
        message.textContent = `Nạp thêm ${number_format(amount, 0, ',', '.')} ₫ thành công!`;
        modal.classList.add('active');
    }

    function closeTopupSuccessModal() {
        const modal = document.getElementById('topupSuccessModal');
        modal.classList.remove('active');
        setTimeout(() => location.reload(), 300);
    }

    function number_format(number, decimals, dec_point, thousands_sep) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s = '',
            toFixedFix = function (n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }
</script>

<?php include '../layout/footer.php'; ?>