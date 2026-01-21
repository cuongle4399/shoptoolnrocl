<?php
class ErrorLogger {
    private $logDir;
    private $logFile;

    public function __construct() {
        $this->logDir = __DIR__ . '/../../logs';
        
        // Tạo folder logs nếu chưa tồn tại
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $this->logFile = $this->logDir . '/error_' . date('Y-m-d') . '.log';
    }

    /**
     * Ghi log lỗi
     * @param string $category Danh mục (product, order, user, etc)
     * @param string $action Hành động (create, update, delete, etc)
     * @param string $message Thông báo
     * @param string|null $trace Stack trace
     * @param array|null $data Dữ liệu liên quan
     */
    public function logError($category, $action, $message, $trace = null, $data = null) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'category' => $category,
            'action' => $action,
            'message' => $message,
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'trace' => $trace,
            'data' => $data
        ];

        $logText = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        
        try {
            file_put_contents($this->logFile, $logText, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log('ErrorLogger: Could not write to log file: ' . $e->getMessage());
        }
    }

    /**
     * Ghi log thành công
     * @param string $category Danh mục
     * @param string $action Hành động
     * @param string $message Thông báo
     * @param array|null $data Dữ liệu liên quan
     */
    public function logSuccess($category, $action, $message, $data = null) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'success',
            'category' => $category,
            'action' => $action,
            'message' => $message,
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'data' => $data
        ];

        $logText = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        
        try {
            file_put_contents($this->logFile, $logText, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log('ErrorLogger: Could not write to log file: ' . $e->getMessage());
        }
    }

    /**
     * Lấy các log gần đây
     * @param int $limit Số lượng log trả về
     * @return array
     */
    public function getRecentLogs($limit = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];

        foreach (array_reverse($lines) as $line) {
            if (count($logs) >= $limit) {
                break;
            }
            
            $decoded = json_decode($line, true);
            if ($decoded) {
                $logs[] = $decoded;
            }
        }

        return $logs;
    }

    /**
     * Lấy logs theo category
     * @param string $category Danh mục
     * @param int $limit Số lượng trả về
     * @return array
     */
    public function getLogsByCategory($category, $limit = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];

        foreach (array_reverse($lines) as $line) {
            if (count($logs) >= $limit) {
                break;
            }
            
            $decoded = json_decode($line, true);
            if ($decoded && $decoded['category'] === $category) {
                $logs[] = $decoded;
            }
        }

        return $logs;
    }

    /**
     * Xóa log cũ (hơn n ngày)
     * @param int $days Số ngày
     */
    public function deleteOldLogs($days = 30) {
        if (!is_dir($this->logDir)) {
            return;
        }

        $files = glob($this->logDir . '/error_*.log');
        $now = time();
        $maxAge = $days * 24 * 60 * 60;

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) >= $maxAge) {
                unlink($file);
            }
        }
    }
}
?>