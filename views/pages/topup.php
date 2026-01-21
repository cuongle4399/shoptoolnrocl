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
?>

<div class="main-content fade-in">
    <h1>Nạp tiền</h1> 
    
    <div class="grid-2 mb-40">
        <!-- Cột trái: Form nạp tiền hoặc QR Code -->
        <div>
            <h3><?php echo $pendingTopup ? 'Chuyển khoản nạp tiền' : 'Nạp tiền'; ?></h3>
            
            <div class="card card-pad mt-15">
                <p><strong>Số dư hiện tại:</strong> <span class="accent-amount"><?php echo number_format($user['balance'], 0, ',', '.'); ?> ₫</span></p>
            </div>
            
            <?php if (!$pendingTopup): ?>
                <!-- FORM NẠP TIỀN (khi không có pending) -->
                <form id="topupForm" class="card card-pad mt-15" style="padding: 20px;">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="amount" style="display: block; margin-bottom: 8px; font-weight: bold;">Nhập số tiền (VNĐ)</label>
                        <input 
                            type="text" 
                            id="amount" 
                            name="amount" 
                            class="format-currency" 
                            placeholder="Tối thiểu 10.000 VNĐ" 
                            required
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                        >
                        <small style="display: block; color: #666; margin-top: 5px;">Nhập số tiền muốn nạp vào tài khoản</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px; font-weight: bold;">
                        Tạo yêu cầu nạp tiền
                    </button>
                </form>
            <?php else: ?>
                <!-- QR CODE + FORM CHUYỂN KHOẢN (khi có pending) -->
                <div class="card card-pad mt-15">
                    <!-- QR CODE -->
                    <div class="qr-code-box">
                        <img id="qrImage" src="" alt="QR Code" style="max-width: 100%; border-radius: 8px;">
                        <p style="font-size: 12px; color: #999; margin-top: 8px;">Quét mã QR để thanh toán</p>
                    </div>
                    
                    <!-- FORM THÔNG TIN CHUYỂN KHOẢN -->
                    <div class="transfer-form">
                        <h4 style="margin-top: 0; margin-bottom: 15px; color: #333; font-size: 15px;">Hoặc nhập thông tin chuyển khoản:</h4>
                        
                        <div class="form-field">
                            <label>Ngân hàng</label>
                            <div class="field-value">
                                <input type="text" value="MB - Ngân hàng Quân Đội" readonly>
                            </div>
                        </div>
                        
                        <div class="form-field">
                            <label>Tên tài khoản</label>
                            <div class="field-value">
                                <input type="text" value="LE QUOC CUONG" readonly>
                            </div>
                        </div>
                        
                        <div class="form-field">
                            <label>Số tài khoản</label>
                            <div class="field-value copy-field">
                                <input type="text" id="accountNo" value="0865134328" readonly>
                                <button type="button" class="copy-btn" onclick="copyToClipboard('accountNo', this)">Copy</button>
                            </div>
                        </div>
                        
                        <div class="form-field">
                            <label>Nội dung chuyển khoản</label>
                            <div class="field-value copy-field">
                                <input type="text" id="transferDesc" value="shoptoolnro_<?php echo htmlspecialchars($user['username']); ?>_<?php echo $pendingTopup['amount']; ?>" readonly style="font-size: 12px;">
                                <button type="button" class="copy-btn" onclick="copyToClipboard('transferDesc', this)">Copy</button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" onclick="cancelPendingTopup()" class="btn btn-secondary" style="width: 100%; padding: 12px; margin-top: 15px;">Huỷ yêu cầu</button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cột phải: Lịch sử nạp tiền -->
        <div>
            <h3>Lịch sử nạp tiền</h3>
            <table class="mt-15">
                <thead>
                    <tr>
                        <th>Số tiền</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody id="topupHistory">
                    <?php if (empty($topupRequests)): ?>
                        <tr id="emptyRow">
                            <td colspan="3" class="text-center">Chưa có yêu cầu nạp tiền nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topupRequests as $req): ?>
                            <tr>
                                <td><?php echo number_format($req['amount'], 0, ',', '.'); ?> ₫</td>
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
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div id="paginationContainer" style="margin-top: 15px; text-align: center;"></div>
        </div>
    </div>
</div>

<style>
/* QR Code Box */
.qr-code-box {
    background: #f8f9fa;
    border: 2px dashed #ddd;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    text-align: center;
}

.qr-code-box img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    background: white;
    padding: 10px;
}

/* Transfer Form */
.transfer-form {
    background: #fff;
    padding: 15px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
}

.form-field {
    margin-bottom: 12px;
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

.field-value {
    display: flex;
    gap: 8px;
    align-items: center;
}

.field-value input {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    background: #f8f9fa;
    color: #333;
}

.field-value input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
}

.copy-btn {
    padding: 8px 14px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
}

.copy-btn:hover {
    background: #5568d3;
    transform: translateY(-1px);
}

.copy-btn:active {
    transform: translateY(0);
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
}/* Pending Status */
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
document.addEventListener('DOMContentLoaded', function() {
    const userId = '<?php echo htmlspecialchars($_SESSION['user_id']); ?>';
    const username = '<?php echo htmlspecialchars($user['username'] ?? 'user'); ?>';
    const hasPending = <?php echo $pendingTopup ? 'true' : 'false'; ?>;
    const pendingAmount = <?php echo $pendingTopup ? intval($pendingTopup['amount']) : 0; ?>;
    
    let checkInterval = null;
    
    // Pagination
    const rowsPerPage = 7;
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
    
    // Copy to clipboard
    window.copyToClipboard = function(elementId, button) {
        const element = document.getElementById(elementId);
        const text = element.value || element.textContent;
        
        navigator.clipboard.writeText(text).then(() => {
            const originalText = button.textContent;
            button.textContent = 'Đã copy';
            button.style.background = '#20c997';
            button.style.color = 'white';
            
            setTimeout(() => {
                button.textContent = originalText;
                button.style.background = '#667eea';
                button.style.color = 'white';
            }, 1500);
        }).catch(() => {
            showAlert('Copy thất bại', 'error');
        });
    };
    
    // Generate QR code
    function generateQRCode(amount) {
        const transferDesc = `shoptoolnro_${username}_${amount}`;
        const bankId = 'mbbank';
        const accountNo = '0865134328';
        const accountName = 'LE QUOC CUONG';
        const qrUrl = `https://img.vietqr.io/image/${bankId}-${accountNo}-compact2.png?amount=${amount}&addInfo=${encodeURIComponent(transferDesc)}&accountName=${encodeURIComponent(accountName)}`;
        
        const qrImage = document.getElementById('qrImage');
        if (qrImage) {
            qrImage.src = qrUrl;
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
            headers: {'Content-Type': 'application/json'}
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
    
    window.cancelPendingTopup = function() {
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
            headers: {'Content-Type': 'application/json'},
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
    
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.classList.add('remove');
                setTimeout(() => {
                    if (notification.parentElement) notification.remove();
                }, 300);
            }
        }, 3000);
    }
    
    // Form submit
    const topupForm = document.getElementById('topupForm');
    if (topupForm) {
        topupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // Parse số từ input đã format
            const amountInput = document.getElementById('amount');
            const rawAmount = parseFormattedNumber(amountInput.value);
            const amount = parseInt(rawAmount);
            
            if (!amount || amount < 10000) {
                showNotification('Vui lòng nhập số tiền hợp lệ (tối thiểu 10.000)', 'error');
                return;
            }
            
            const data = {
                amount: amount,
                method: 'vietqr'
            };
            
            fetch('/ShopToolNro/api/topup/create_request.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
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
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
}

.topup-success-modal.active {
    display: flex;
    animation: fadeIn 0.3s ease-in-out;
}

.topup-success-content {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
    border-radius: 16px;
    padding: 50px 40px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(16, 185, 129, 0.15),
                0 0 1px rgba(0, 0, 0, 0.1);
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-width: 420px;
    position: relative;
    overflow: hidden;
}

.topup-success-content::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.topup-success-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 30px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 56px;
    color: white;
    animation: bounceIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    position: relative;
    z-index: 1;
}

.topup-success-content h2 {
    margin: 0 0 12px 0;
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    letter-spacing: -0.5px;
    position: relative;
    z-index: 1;
}

.topup-success-content p {
    margin: 0 0 35px 0;
    font-size: 18px;
    color: #059669;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

.topup-success-content .btn {
    padding: 14px 40px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    position: relative;
    z-index: 1;
}

.topup-success-content .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(16, 185, 129, 0.4);
}

.topup-success-content .btn:active {
    transform: translateY(0);
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        transform: translateY(40px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes bounceIn {
    0% {
        transform: scale(0) rotateZ(-45deg);
        opacity: 0;
    }
    50% {
        transform: scale(1.15) rotateZ(10deg);
    }
    100% {
        transform: scale(1) rotateZ(0);
        opacity: 1;
    }
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
        toFixedFix = function(n, prec) {
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