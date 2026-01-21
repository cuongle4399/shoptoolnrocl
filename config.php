<?php
// ========== CONFIG.PHP - LICENSE MANAGEMENT SYSTEM ==========
// INFINITYFREE COMPATIBLE VERSION

// ===== SESSION CONFIGURATION & SECURITY (BEFORE session_start) =====
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 1800); // 30 phút
ini_set('session.use_strict_mode', 1);

// ===== ERROR REPORTING (PRODUCTION MODE) =====
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ===== LOAD ENV FILE =====
loadEnv(__DIR__ . '/.env');

// ===== START SESSION (AFTER ini_set) =====
session_start();

// ===== CONFIG CONSTANTS - HARDCODED FOR INFINITYFREE =====
define('SUPABASE_URL', rtrim((string)getenv('SUPABASE_URL'), '/'));
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY'));
define('ADMIN_USER', getenv('ADMIN_USER'));
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD'));
define('AES_ENCRYPTION_KEY', getenv('AES_ENCRYPTION_KEY'));
define('TABLE_NAME', 'infokey');

// ===== REQUIRE ESSENTIAL CLASSES & FUNCTIONS =====
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/classes/License.php';
require_once __DIR__ . '/includes/functions.php';

// ===== VALIDATE CONFIG =====
if (!SUPABASE_URL) die('Error: SUPABASE_URL not configured');
if (!SUPABASE_ANON_KEY) die('Error: SUPABASE_ANON_KEY not configured');
if (!ADMIN_PASSWORD) die('Error: ADMIN_PASSWORD not configured');
if (strlen(AES_ENCRYPTION_KEY) !== 32) die('Error: AES_ENCRYPTION_KEY must be 32 characters');

if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            $value = trim($value, '"');
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// ===== RATE LIMITING =====
function checkRateLimit($action, $identifier, $maxAttempts = 5, $timeWindow = 3600) {
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    $key = hash('sha256', $action . $identifier);
    $now = time();
    
    if (isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = array_filter(
            $_SESSION['rate_limits'][$key],
            fn($t) => $t > ($now - $timeWindow)
        );
    } else {
        $_SESSION['rate_limits'][$key] = [];
    }
    
    if (count($_SESSION['rate_limits'][$key]) >= $maxAttempts) {
        return false;
    }
    $_SESSION['rate_limits'][$key][$now] = $now;
    return true;
}

// ===== AUDIT LOGGING =====
function logAudit($action, $user, $details, $status = 'success') {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user' => $user,
        'details' => $details,
        'status' => $status,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
    ];
    $logFile = $logDir . '/audit_' . date('Y-m-d') . '.log';
    @file_put_contents($logFile, json_encode($log) . PHP_EOL, FILE_APPEND | LOCK_EX);
    @chmod($logFile, 0644);
}

// ===== CSRF TOKEN =====
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || time() - ($_SESSION['csrf_time'] ?? 0) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ===== INPUT SANITIZE =====
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ===== VALIDATE HWID =====
function validateHWID($hwid) {
    return preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $hwid);
}

// ===== SUPABASE API CALL =====
function callSupabaseApi($endpoint, $method, $data = []) {
    if (substr($endpoint, -1) === '?') {
        $endpoint = rtrim($endpoint, '?');
    }
    $endpoint = ltrim($endpoint, '/');
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;
    $apiKey = SUPABASE_ANON_KEY;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if ($method === 'POST' || $method === 'PATCH') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $headers = [
        "Content-Type: application/json",
        "Accept: application/json",
        "apikey: " . $apiKey,
        "Authorization: Bearer " . $apiKey,
        "Prefer: return=representation"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($curl_error) {
        throw new Exception('API error: ' . $curl_error);
    }

    $decoded = json_decode($response, true);
    if ($http_code == 204) {
        return ['code' => 204, 'response' => null, 'raw' => $response];
    }
    return [
        'code' => $http_code,
        'response' => $decoded,
        'raw' => $response
    ];
}

// ===== ENCRYPT AES-256-CBC =====
function encryptAES($data, $key) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

// ===== DECRYPT AES-256-CBC =====
function decryptAES($encryptedData, $key) {
    $data = base64_decode($encryptedData, true);
    if ($data === false || strlen($data) < 16) {
        throw new Exception('Invalid encrypted data');
    }
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    if ($decrypted === false) {
        throw new Exception('Decryption failed');
    }
    return $decrypted;
}

// ===== VERIFY LICENSE KEY =====
function verifyLicenseKey($encryptedKey) {
    try {
        $decrypted = decryptAES($encryptedKey, AES_ENCRYPTION_KEY);
        $data = json_decode($decrypted, true);
        
        if (!$data || !isset($data['checksum'], $data['expires_at'])) {
            return false;
        }
        
        $checksum = $data['checksum'];
        unset($data['checksum']);
        $expected = hash_hmac('sha256', json_encode($data), AES_ENCRYPTION_KEY);
        
        if (!hash_equals($checksum, $expected)) {
            return false;
        }

        if (strtotime($data['expires_at']) < time()) {
            return false;
        }

        return $data;
    } catch (Exception $e) {
        return false;
    }
}

// ===== SAFE LOGOUT =====
function safeLogout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// ===== INIT SESSION - SECURITY =====
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['created_at'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}

// ===== SESSION VALIDATION =====
if (isset($_SESSION['created_at']) && 
    (time() - $_SESSION['created_at'] > 86400 || 
     (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']))) {
    safeLogout();
    header('Location: index.php');
    exit;
}

// ===== USER HELPERS =====
function getUserByEmail($email) {
    $email = sanitizeInput($email);
    $res = callSupabaseApi("users?select=*&email=eq." . rawurlencode($email), 'GET');
    if (isset($res['code']) && $res['code'] >= 200 && $res['code'] < 300) {
        return $res['response'][0] ?? null;
    }
    return null;
}

if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        // Return raw password (plaintext)
        return $password;
    }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $userRow) {
        if (!$userRow) return false;
        $stored = $userRow['password_'] ?? ($userRow['password'] ?? ($userRow['password_hash'] ?? null));
        if ($stored === null) return false;
        return hash_equals((string)$stored, (string)$password);
    }
}

function registerUser($username, $email, $password) {
    $username = sanitizeInput($username);
    $email = sanitizeInput($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email'];
    }
    if (strlen($username) < 3) {
        return ['success' => false, 'error' => 'Username too short'];
    }

    if (!checkRateLimit('register', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10, 3600)) {
        return ['success' => false, 'error' => 'Too many registration attempts. Try later.'];
    }

    if (getUserByEmail($email)) {
        return ['success' => false, 'error' => 'Email already registered'];
    }

    $pwHash = hashPassword($password);
    $payload = [
        'username' => $username,
        'email' => $email,
        'password_' => $pwHash
    ];

    try {
        $res = callSupabaseApi('users', 'POST', $payload);
    } catch (Exception $e) {
        logAudit('register', $email, 'exception: '.$e->getMessage(), 'error');
        return ['success' => false, 'error' => 'Registration failed (API error)'];
    }

    if (isset($res['code']) && $res['code'] >= 200 && $res['code'] < 300) {
        logAudit('register', $email, 'user created', 'success');
        return ['success' => true, 'user' => $res['response'][0] ?? $res['response']];
    }

    $errMsg = is_array($res['response']) ? json_encode($res['response']) : ($res['raw'] ?? '');
    if (stripos($errMsg, 'password_') !== false || stripos($errMsg, 'column') !== false) {
        $payload2 = [
            'username' => $username,
            'email' => $email,
            'password' => $pwHash
        ];
        try {
            $res2 = callSupabaseApi('users', 'POST', $payload2);
        } catch (Exception $e) {
            logAudit('register', $email, 'exception fallback: '.$e->getMessage(), 'error');
            return ['success' => false, 'error' => 'Registration failed (fallback API error)'];
        }

        if (isset($res2['code']) && $res2['code'] >= 200 && $res2['code'] < 300) {
            logAudit('register', $email, 'user created (password field)', 'success');
            return ['success' => true, 'user' => $res2['response'][0] ?? $res2['response']];
        }

        $err2 = is_array($res2['response']) ? json_encode($res2['response']) : ($res2['raw'] ?? '');
        logAudit('register', $email, 'fallback failed: '.$err2, 'error');
        return ['success' => false, 'error' => 'Registration failed: '.$err2];
    }

    logAudit('register', $email, 'create failed: '.$errMsg, 'error');
    return ['success' => false, 'error' => 'Registration failed: '.$errMsg];
}
?>