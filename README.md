# ShopToolNro - License Management System

A comprehensive license key management and e-commerce platform built with PHP and Supabase.

## 🚀 Features

### Core Features
- **User Management**: Registration, login, Google OAuth integration
- **Product Management**: Create and manage software products with multiple pricing tiers
- **License Key System**: Automated license key assignment and HWID binding
- **Payment Processing**: 
  - Manual topup requests with admin approval
  - **SePay Webhook Integration** for automatic payment processing
- **Order Management**: Complete order lifecycle with atomic transactions
- **Promotion Codes**: Discount system with usage tracking
- **Admin Dashboard**: Comprehensive admin panel for managing all aspects

### SePay Integration (NEW!)
- ✅ Automatic topup approval when bank transfer detected
- ✅ Real-time webhook processing
- ✅ Transaction logging and audit trail
- ✅ Manual processing fallback
- ✅ IP whitelist security

## 📋 Requirements

- PHP 7.4 or higher
- Supabase account
- Composer (for dependencies)
- Web server (Apache/Nginx)

## 🔧 Installation

### 1. Clone the repository
```bash
git clone https://github.com/yourusername/ShopToolNro.git
cd ShopToolNro
```

### 2. Install dependencies
```bash
composer install
```

### 3. Configure environment
```bash
cp .env.example .env
```

Edit `.env` and add your credentials:
```env
# Supabase Configuration
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_KEY=your-service-key

# Admin Account
ADMIN_USER=admin
ADMIN_PASSWORD=your-secure-password

# Encryption
AES_ENCRYPTION_KEY=your-32-character-key-here

# SePay Configuration (Optional)
SEPAY_ENABLE_IP_CHECK=false
SEPAY_WHITELIST_IPS=103.124.92.0/24,171.244.50.0/24
SEPAY_WEBHOOK_SECRET=your-secret-key
```

### 4. Setup Database
1. Open Supabase SQL Editor
2. Run the entire `database.sql` file
3. This will create all tables, functions, and triggers

### 5. Configure VietQR (Optional)
Edit `config/constants.php` to add your bank details for QR code generation.

## 🎯 SePay Webhook Setup

### 1. Configure Webhook in SePay Dashboard
1. Login to https://my.sepay.vn
2. Go to **Webhooks** → **Add New**
3. URL: `https://your-domain.com/ShopToolNro/api/webhooks/sepay_receiver.php`
4. Authentication: None (or API Key)
5. Content Type: `application/json`

### 2. Transfer Content Format
Users must transfer with this format:
```
shoptoolnro-{username}-{amount}
```

**Examples:**
- `shoptoolnro-admin-50000` → Topup 50,000 for user "admin"
- `shoptoolnro-john-100000` → Topup 100,000 for user "john"

### 3. How It Works
1. User creates topup request
2. User transfers money with correct format
3. SePay detects transaction → calls webhook
4. System automatically:
   - Saves transaction to database
   - Finds matching topup request
   - Adds balance to user account
   - Updates status to "approved"

## 📁 Project Structure

```
ShopToolNro/
├── api/                    # API endpoints
│   ├── admin/             # Admin APIs
│   ├── auth/              # Authentication APIs
│   └── webhooks/          # Webhook receivers (SePay)
├── assets/                # Static assets (CSS, JS, images)
├── config/                # Configuration files
├── docs/                  # Documentation
├── src/                   # Source code
│   └── classes/           # PHP classes
├── views/                 # View templates
│   ├── admin/             # Admin pages
│   ├── layout/            # Layout components
│   └── pages/             # Public pages
├── vendor/                # Composer dependencies
├── .env.example           # Environment template
├── database.sql           # Database schema
└── README.md              # This file
```

## 🔒 Security Features

- **Environment Variables**: Sensitive data stored in `.env` (not committed)
- **CSRF Protection**: Token-based CSRF prevention
- **Rate Limiting**: Prevent brute force attacks
- **Session Security**: Secure session configuration
- **IP Whitelist**: SePay webhook IP validation
- **Audit Logging**: Complete audit trail for all actions
- **Password Hashing**: Bcrypt password hashing

## 📚 Documentation

- **SePay Integration**: See `docs/SEPAY_INTEGRATION.md`
- **API Documentation**: Coming soon
- **Admin Guide**: Coming soon

## 🛠️ Development

### Local Development
```bash
# Start local server
php -S localhost:8000
```

### Production Deployment
1. Set `SEPAY_ENABLE_IP_CHECK=true` in `.env`
2. Configure proper IP whitelist
3. Enable HTTPS
4. Set proper file permissions

## 📝 License

This project is proprietary software. All rights reserved.

## 👥 Author

Developed by Cuong Le

## 🐛 Issues & Support

For issues and support, please contact: cuong01697072089@gmail.com

---

**⚠️ IMPORTANT SECURITY NOTES:**
- Never commit `.env` file to version control
- Always use HTTPS in production
- Keep Supabase keys secure
- Regularly update dependencies
- Enable IP whitelist for webhooks in production
