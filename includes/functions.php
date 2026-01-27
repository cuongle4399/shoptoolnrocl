<?php
require_once __DIR__ . '/../config/constants.php';

/**
 * Convert UTC timestamp to Vietnam timezone (Asia/Ho_Chi_Minh)
 * @param string $utcTimestamp - UTC timestamp from database (ISO 8601 format)
 * @param string $format - PHP date format (default: 'd/m/Y H:i')
 * @return string - Formatted datetime in Vietnam timezone
 */
function convertToVNTime($utcTimestamp, $format = 'd/m/Y H:i')
{
    if (empty($utcTimestamp)) {
        return '';
    }

    try {
        // Create DateTime object from UTC timestamp
        $dt = new DateTime($utcTimestamp, new DateTimeZone('UTC'));
        // Convert to Vietnam timezone
        $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        // Return formatted string
        return $dt->format($format);
    } catch (Exception $e) {
        // Fallback: return original if conversion fails
        error_log("convertToVNTime error: " . $e->getMessage());
        return date($format, strtotime($utcTimestamp));
    }
}

function response($status, $message, $data = null)
{
    header('Content-Type: application/json');
    $response = [
        'success' => ($status === 'success' ? true : false),
        'status' => $status,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Plaintext passwords: store and compare raw text (NOT RECOMMENDED — use only if you understand the risk)
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $stored)
{
    if ($stored === null)
        return false;

    // Modern password check (Bcrypt)
    if (password_get_info($stored)['algoName'] !== 'unknown') {
        return password_verify($password, $stored);
    }

    // Legacy plaintext check
    return hash_equals((string) $stored, (string) $password);
}

function generateLicenseKey($segments = 4, $segmentLen = 4, $prefix = 'KEY')
{
    // Generates a license like KEY-AB12-C3D4-EF56
    $bytes = random_bytes(intval(ceil($segments * $segmentLen / 2)));
    $hex = strtoupper(bin2hex($bytes));
    $parts = str_split(substr($hex, 0, $segments * $segmentLen), $segmentLen);
    return $prefix . '-' . implode('-', $parts);
}

function generate_uuid_v4()
{
    // Simple UUID v4 generator
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function generateJWT($user_id, $username, $role)
{
    $header = json_encode(['type' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRY
    ]);

    $base64UrlHeader = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
}

function verifyJWT($token)
{
    if (!$token)
        return false;

    $parts = explode('.', $token);
    if (count($parts) !== 3)
        return false;

    $signature = hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    if (!hash_equals($base64UrlSignature, $parts[2]))
        return false;

    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!$payload || $payload['exp'] < time())
        return false;

    return $payload;
}

function getAuthToken()
{
    // First, check Authorization header
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    } else {
        if (!empty($_SERVER['HTTP_AUTHORIZATION']))
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization') {
            $token = preg_replace('/^Bearer\s+/i', '', trim($v));
            return $token;
        }
    }

    // If no token in header, check if user is logged in via session
    // Generate JWT from session data
    if (!session_id()) {
        session_start();
    }

    if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role'])) {
        // Generate JWT token from session data
        return generateJWT($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
    }

    return null;
}

function uploadFile($file)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Tải tệp thất bại'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['status' => false, 'message' => 'Tệp quá lớn'];
    }

    // Validate MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_TYPES)) {
        return ['status' => false, 'message' => 'Định dạng tệp không hợp lệ'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // safe random filename preserving extension
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-z0-9]+/i', '', $extension);
    $filename = bin2hex(random_bytes(8)) . ($safeExt ? '.' . $safeExt : '');
    $filepath = UPLOAD_DIR . $filename;

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['status' => false, 'message' => 'Tải tệp không hợp lệ'];
    }

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['status' => true, 'filename' => $filename, 'url' => UPLOAD_URL . $filename];
    }

    return ['status' => false, 'message' => 'Không thể lưu tệp'];
}



function generateVietQRLink($amount, $orderId)
{
    $accountNo = VIETQR_ACCOUNT_NO;
    $bankCode = VIETQR_BANK_CODE;
    $accountName = VIETQR_ACCOUNT_NAME;
    $description = "Nap tien don hang " . $orderId;

    $url = "https://api.vietqr.io/api/generate?" .
        "accountNo=" . $accountNo .
        "&bankCode=" . $bankCode .
        "&accountName=" . urlencode($accountName) .
        "&amount=" . $amount .
        "&addInfo=" . urlencode($description) .
        "&templateCode=compact";

    return $url;
}

/**
 * Normalize various YouTube URL formats into an embeddable URL
 * Examples accepted:
 * - https://www.youtube.com/watch?v=VIDEO_ID
 * - https://youtu.be/VIDEO_ID
 * - https://www.youtube.com/embed/VIDEO_ID
 */
function normalizeYoutubeEmbedUrl($url)
{
    $url = trim((string) $url);
    if ($url === '')
        return '';

    // Ensure scheme
    if (strpos($url, '://') === false) {
        if (strpos($url, '//') === 0)
            $url = 'https:' . $url;
        else
            $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    $query = $parts['query'] ?? '';

    // youtu.be short link
    if (stripos($host, 'youtu.be') !== false) {
        $id = ltrim($path, '/');
        if ($id)
            return 'https://www.youtube.com/embed/' . $id;
    }

    // standard youtube.com
    if (stripos($host, 'youtube.com') !== false) {
        if (stripos($path, '/embed/') !== false) {
            // already embed form
            return $url;
        }
        if ($query) {
            parse_str($query, $qs);
            if (!empty($qs['v'])) {
                return 'https://www.youtube.com/embed/' . $qs['v'];
            }
        }
    }

    // Fallback: try to regex extract an id
    if (preg_match('/(?:v=|\/)([A-Za-z0-9_-]{6,})/', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }

    // Otherwise return original (best effort)
    return $url;
}

/**
 * Convert a string to a URL-safe slug (ASCII, lowercase, hyphens).
 * Removes diacritics and non-alphanumeric characters.
 */
function slugify($text, $maxLen = 60)
{
    $text = trim((string) $text);
    if ($text === '')
        return '';

    // Try to transliterate to ASCII
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    if ($trans !== false)
        $text = $trans;

    // Replace non alnum with hyphens
    $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
    $text = strtolower(trim($text, '-'));

    if ($maxLen && strlen($text) > $maxLen)
        $text = substr($text, 0, $maxLen);
    // Final cleanup
    $text = preg_replace('/-+/', '-', $text);
    return $text;
}

/**
 * Log API/server errors to a file and return a short log id.
 * Logs are appended as JSON lines to logs/api_errors.log
 */
function api_log_error($message, $context = null)
{
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir))
        @mkdir($logsDir, 0755, true);
    $logFile = $logsDir . '/api_errors.log';

    try {
        $id = date('YmdHis') . '-' . bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $id = date('YmdHis') . '-' . substr(md5(mt_rand()), 0, 12);
    }

    $entry = [
        'id' => $id,
        'timestamp' => date('c'),
        'message' => (string) $message,
        'context' => $context
    ];

    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    return $id;
}

// ============================================
// LICENSE MANAGEMENT WRAPPER FUNCTIONS (OPTIMIZED)
// ============================================

function getLicenseInstance()
{
    static $license = null;
    if ($license === null) {
        $db = new Database();
        $license = new License($db);
    }
    return $license;
}

function getKeyByLicense($license_key)
{
    try {
        return getLicenseInstance()->getKeyByLicense($license_key);
    } catch (Exception $e) {
        error_log("Error in getKeyByLicense: " . $e->getMessage());
        return null;
    }
}

function getAllKeys()
{
    try {
        return getLicenseInstance()->getAllKeys();
    } catch (Exception $e) {
        error_log("Error in getAllKeys: " . $e->getMessage());
        return [];
    }
}

function getLicenseStatus($key)
{
    if (!$key || !isset($key['expires_at'])) {
        return ['text' => 'UNKNOWN', 'color' => '#999', 'remaining_days' => 0];
    }

    $status = $key['status'] ?? 'active';

    if ($status === 'banned') {
        return ['text' => 'BANNED', 'color' => '#dc3545', 'remaining_days' => -1];
    }

    $expires = strtotime($key['expires_at']);
    $now = time();
    $remaining = floor(($expires - $now) / 86400);

    if ($remaining < 0) {
        return ['text' => 'EXPIRED', 'color' => '#dc3545', 'remaining_days' => $remaining];
    } elseif ($remaining <= 3) {
        return ['text' => 'Cảnh báo: ' . $remaining . ' ngày', 'color' => '#ff9800', 'remaining_days' => $remaining];
    } else {
        return ['text' => 'ACTIVE', 'color' => '#28a745', 'remaining_days' => $remaining];
    }
}

function handleCreateLicense($hwid, $user_info, $days)
{
    try {
        $result = getLicenseInstance()->createKey([
            'hwid' => $hwid,
            'user_info' => $user_info,
            'days' => $days
        ]);
        return $result ? ['success' => true, 'message' => 'Tạo License Key thành công!'] : ['success' => false, 'message' => 'Lỗi tạo key'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

function handleBanLicense($license_key)
{
    try {
        $result = getLicenseInstance()->banKey($license_key);
        return $result ? ['success' => true, 'message' => 'Đã BAN License Key!'] : ['success' => false, 'message' => 'Lỗi BAN key'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

function handleUnbanLicense($license_key)
{
    try {
        $result = getLicenseInstance()->unbanKey($license_key);
        return $result ? ['success' => true, 'message' => 'Đã mở BAN License Key!'] : ['success' => false, 'message' => 'Lỗi mở BAN key'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

function handleRenewLicense($license_key, $days)
{
    try {
        $result = getLicenseInstance()->renewKey($license_key, $days);
        return $result ? ['success' => true, 'message' => 'Đã gia hạn License Key ' . $days . ' ngày!'] : ['success' => false, 'message' => 'Lỗi gia hạn key'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

function handleDeleteLicense($license_key)
{
    try {
        $result = getLicenseInstance()->deleteKey($license_key);
        return $result ? ['success' => true, 'message' => 'Đã xóa License Key!'] : ['success' => false, 'message' => 'Lỗi xóa key'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

function handleUpdateHwidAndKey($license_key, $new_hwid, $user_info)
{
    try {
        $license = getLicenseInstance();

        // Get old key info
        $old_key = $license->getKeyByLicense($license_key);
        if (!$old_key) {
            return ['success' => false, 'message' => 'Key không tồn tại'];
        }

        // Delete old key and create new one
        $license->deleteKey($license_key);

        $new_key_data = [
            'hwid' => $new_hwid,
            'user_info' => $user_info,
            'days' => $old_key['remaining_days'] ?? 30
        ];

        $result = $license->createKey($new_key_data);

        if ($result) {
            return ['success' => true, 'message' => 'Đổi HWID thành công!', 'key' => $result['license_key'] ?? ''];
        }

        return ['success' => false, 'message' => 'Lỗi tạo key mới'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}
?>