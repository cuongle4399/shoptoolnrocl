<?php
class ErrorHandler {
    public static function setErrorHandler() {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            error_log("[ERROR] $errstr in $errfile:$errline");
            return true;
        });
    }

    public static function logError($message, $context = []) {
        $log_file = __DIR__ . '/../../logs/error.log';
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0777, true);
        }
        
        $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $log_message .= ' - ' . json_encode($context);
        }
        $log_message .= PHP_EOL;
        
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }

    public static function apiError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    public static function apiSuccess($data = [], $message = 'Success') {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}
?>
