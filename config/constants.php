<?php
require_once __DIR__ . '/../includes/env_loader.php';
loadEnv(__DIR__ . '/../.env');

// SITE_URL - Load from environment or use default
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/ShopToolNro');
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
if (!defined('SUPABASE_URL'))
    define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://your-project.supabase.co');
if (!defined('SUPABASE_ANON_KEY'))
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