<?php
define('SITE_URL', 'http://localhost/ShopToolNro');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// JWT Secret - prefer environment variable (DO NOT commit production secret into VCS)
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'dev_jwt_secret_change_me');
define('JWT_EXPIRY', 7 * 24 * 60 * 60); // 7 days

// VietQR Config - MB Bank (vietinbank)
define('VIETQR_BANK_CODE', 'vietinbank');
define('VIETQR_BANK_NAME', 'MBBANK');
define('VIETQR_BANK_FULL_NAME', 'MB NGÂN HÀNG QUÂN ĐỘI');
define('VIETQR_ACCOUNT_NO', '0865134328');
define('VIETQR_ACCOUNT_NAME', 'LE QUOC CUONG');

// Roles
define('ROLE_CUSTOMER', 'customer');
define('ROLE_ADMIN', 'admin');

// Statuses
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_PENDING', 'pending');
?>
