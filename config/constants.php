<?php
// Load .env file - improved parser
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) continue;
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            // Only set if not already set
            if (!empty($key) && !getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

define('SITE_URL', 'http://localhost/ShopToolNro');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// JWT Secret - REQUIRED from environment (fail fast if not set)
if (!getenv('JWT_SECRET')) {
    die('Fatal Error: JWT_SECRET environment variable not configured. Set it in .env file.');
}
define('JWT_SECRET', getenv('JWT_SECRET'));
define('JWT_EXPIRY', 7 * 24 * 60 * 60); // 7 days

// VietQR Config - Load from environment (no hardcoded defaults)
define('VIETQR_BANK_CODE', getenv('VIETQR_BANK_CODE') ?: null);
define('VIETQR_BANK_NAME', getenv('VIETQR_BANK_NAME') ?: null);
define('VIETQR_BANK_FULL_NAME', getenv('VIETQR_BANK_FULL_NAME') ?: null);
define('VIETQR_ACCOUNT_NO', getenv('VIETQR_ACCOUNT_NO') ?: null);
define('VIETQR_ACCOUNT_NAME', getenv('VIETQR_ACCOUNT_NAME') ?: null);

// Supabase Storage Config (load from .env)
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://your-project.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');
define('SUPABASE_BUCKET', 'products');

// Roles
define('ROLE_CUSTOMER', 'customer');
define('ROLE_ADMIN', 'admin');

// Statuses
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_PENDING', 'pending');
?>
