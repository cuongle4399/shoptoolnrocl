<?php
require_once __DIR__ . '/../../includes/functions.php';

class User
{
    private $db;
    private $table = 'users';
    private static $cache = []; // In-memory cache for this request

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get user by ID (with in-memory caching)
     */
    public function getUserById($id)
    {
        $id = (int) $id;

        // Check cache first
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }

        $endpoint = $this->table . "?id=eq." . $id . "&select=*&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response)) {
            $user = $result->response[0];
            self::$cache[$id] = $user; // Cache the result
            return $user;
        }
        return null;
    }

    /**
     * Get many users by id in a single REST call (helps avoid N+1)
     */
    public function getUsersByIds(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids))
            return [];

        // Serve from cache where possible
        $resultMap = [];
        $missingIds = [];
        foreach ($ids as $id) {
            if (isset(self::$cache[$id])) {
                $resultMap[$id] = self::$cache[$id];
            } else {
                $missingIds[] = $id;
            }
        }

        if (!empty($missingIds)) {
            $idList = implode(',', $missingIds);
            $endpoint = $this->table . "?id=in.(" . $idList . ")";
            $resp = $this->db->callApi($endpoint, 'GET');
            if ($resp && $resp->code == 200 && !empty($resp->response)) {
                foreach ($resp->response as $u) {
                    if (isset($u['id'])) {
                        $uid = (int) $u['id'];
                        self::$cache[$uid] = $u;
                        $resultMap[$uid] = $u;
                    }
                }
            }
        }

        return $resultMap;
    }

    /**
     * Get user by username (with in-memory caching)
     */
    public function getUserByUsername($username)
    {
        $cache_key = "username_" . md5($username);

        // Check cache first
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $encoded_username = urlencode($username);
        $endpoint = $this->table . "?username=eq." . $encoded_username . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response) && is_array($result->response)) {
            $user = $result->response[0];
            self::$cache[$cache_key] = $user;
            if (isset($user['id'])) {
                self::$cache[$user['id']] = $user;
            }
            return $user;
        }
        return null;
    }

    /**
     * Create user
     */
    public function createUser($data)
    {
        // Store password as plaintext (per project decision)
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_' => $data['password'],
            'role' => $data['role'] ?? 'customer',
            'status' => 'active'
        ];

        $result = $this->db->callApi($this->table, 'POST', $userData);
        return ($result && ($result->code == 201 || $result->code == 200));
    }

    /**
     * Get user by Google ID
     */
    public function getUserByGoogleId($google_id)
    {
        $endpoint = $this->table . "?google_id=eq." . $google_id . "&select=*&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    /**
     * Create or Link Google User
     * Returns ['user' => user_data, 'status' => 'login'|'linked'|'setup_required']
     */
    public function createOrLinkGoogleUser($email, $google_id, $name, $avatar)
    {
        // 1. Check if user exists by Google ID
        $user = $this->getUserByGoogleId($google_id);
        if ($user) {
            // Update avatar if currently empty and Google provided one
            if (empty($user['avatar_url']) && !empty($avatar)) {
                $endpoint = $this->table . "?id=eq." . $user['id'];
                $this->db->callApi($endpoint, 'PATCH', ['avatar_url' => $avatar]);
                $user['avatar_url'] = $avatar;
            }
            return ['user' => $user, 'status' => 'login'];
        }

        // 2. Check if user exists by Email
        $user = $this->getUserByEmail($email);
        if ($user) {
            $endpoint = $this->table . "?id=eq." . $user['id'];
            $updateData = ['google_id' => $google_id, 'login_type' => 'google'];
            if (empty($user['avatar_url']) && !empty($avatar)) {
                $updateData['avatar_url'] = $avatar;
                $user['avatar_url'] = $avatar;
            }
            $this->db->callApi($endpoint, 'PATCH', $updateData);
            return ['user' => $user, 'status' => 'linked'];
        }

        // 3. Setup Required - User doesn't exist at all
        return ['status' => 'setup_required', 'google_info' => ['email' => $email, 'google_id' => $google_id, 'name' => $name, 'avatar' => $avatar]];
    }

    /**
     * Complete Google Registration with Password
     */
    public function completeGoogleRegistration($email, $google_id, $name, $avatar, $password)
    {
        $username = $this->generateUniqueUsername($email);

        $userData = [
            'username' => $username,
            'email' => $email,
            'password_' => $password,
            'google_id' => $google_id,
            'login_type' => 'google',
            'avatar_url' => $avatar,
            'role' => 'customer',
            'status' => 'active',
            'balance' => 0
        ];

        $result = $this->db->callApi($this->table, 'POST', $userData);

        if ($result && ($result->code == 201 || $result->code == 200)) {
            return $this->getUserByEmail($email);
        }

        return false;
    }

    private function generateUniqueUsername($email)
    {
        // Extract username from email (part before @)
        $emailParts = explode('@', $email);
        $baseName = $emailParts[0] ?? 'user';

        // Clean up: only keep alphanumeric characters
        $baseName = preg_replace('/[^a-zA-Z0-9]/', '', $baseName);
        if (empty($baseName))
            $baseName = 'user';

        $username = $baseName;
        $counter = 1;

        while ($this->getUserByUsername($username)) {
            $username = $baseName . $counter;
            $counter++;
        }
        return $username;
    }

    /**
     * Update user balance (OPTIMIZED - no need to fetch user first if we just add the amount)
     * Note: For production, use database transaction via Supabase function
     */
    public function updateUserBalance($id, $amount)
    {
        $id = (int) $id;

        // Direct API call - Supabase will handle the increment
        $endpoint = $this->table . "?id=eq." . $id;

        // For Supabase REST API, we need to fetch, calculate, and update
        // But we can optimize by only fetching if the balance matters
        $user = $this->getUserById($id);
        if (!$user) {
            return false;
        }

        $new_balance = ($user['balance'] ?? 0) + $amount;

        $result = $this->db->callApi($endpoint, 'PATCH', ['balance' => $new_balance]);

        if ($result && ($result->code == 200 || $result->code == 204)) {
            // Invalidate cache
            unset(self::$cache[$id]);
            return true;
        }

        return false;
    }

    /**
     * Add pending balance (for topup requests)
     * REMOVED - Not used in schema, use topup_requests table instead
     */
    public function addPendingBalance($id, $amount)
    {
        return false; // Not implemented
    }

    /**
     * Approve pending balance
     * REMOVED - Not used in schema
     */
    public function approvePendingBalance($id, $amount)
    {
        return false; // Not implemented
    }

    /**
     * Login user
     */
    public function login($username, $password)
    {
        $user = $this->getUserByUsername($username);
        if (!$user)
            return false;

        // Prevent disabled/inactive users from logging in
        if (isset($user['status']) && $user['status'] !== 'active')
            return 'disabled';

        $storedPassword = $user['password_'] ?? null;
        if (!$storedPassword)
            return false;

        // Verify password (supports hashed and legacy plaintext)
        if (!verifyPassword($password, $storedPassword)) {
            return false;
        }

        return $user;
    }

    /**
     * Get all users (admin)
     */
    public function getAllUsers($limit = 0, $offset = 0)
    {
        $endpoint = $this->table . "?order=created_at.desc";
        if ($limit > 0) {
            $endpoint .= "&limit=" . (int) $limit . "&offset=" . (int) $offset;
        }
        $result = $this->db->callApi($endpoint, 'GET');

        if ($result && $result->code == 200 && is_array($result->response)) {
            return $result->response;
        }

        return [];
    }

    /**
     * Change user role (admin)
     */
    public function changeUserRole($id, $role)
    {
        $id = (int) $id;
        $endpoint = $this->table . "?id=eq." . $id;
        $result = $this->db->callApi($endpoint, 'PATCH', ['role' => $role]);

        if ($result && ($result->code == 200 || $result->code == 204)) {
            unset(self::$cache[$id]);
            return true;
        }
        return false;
    }

    /**
     * Toggle user status (admin)
     */
    public function toggleUserStatus($id)
    {
        $id = (int) $id;
        $user = $this->getUserById($id);
        if (!$user)
            return false;

        $new_status = ($user['status'] === 'active') ? 'inactive' : 'active';

        $endpoint = $this->table . "?id=eq." . $id;
        $result = $this->db->callApi($endpoint, 'PATCH', ['status' => $new_status]);

        if ($result && ($result->code == 200 || $result->code == 204)) {
            unset(self::$cache[$id]);
            return true;
        }
        return false;
    }

    /**
     * Change user password
     * @param int $id User ID
     * @param string $oldPassword Old password (for self-change, empty string for admin change)
     * @param string $newPassword New password
     * @return bool True if successful
     */
    public function changePassword($id, $oldPassword, $newPassword)
    {
        $id = (int) $id;
        $user = $this->getUserById($id);
        if (!$user) {
            return false;
        }

        // If oldPassword is provided (not empty), verify it
        if (!empty($oldPassword)) {
            $storedPassword = $user['password_'] ?? null;
            if (!verifyPassword($oldPassword, $storedPassword)) {
                return false;
            }
        }

        // Update password - plaintext per project design
        $endpoint = $this->table . "?id=eq." . $id;
        $result = $this->db->callApi($endpoint, 'PATCH', ['password_' => $newPassword]);

        if ($result && ($result->code == 200 || $result->code == 204)) {
            unset(self::$cache[$id]);
            return true;
        }

        return false;
    }

    /**
     * Initiate password reset
     */
    public function initiatePasswordReset($email)
    {
        $user = $this->getUserByEmail($email);
        if (!$user) {
            // Return true to prevent email enumeration
            return true;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 hour expiry

        $endpoint = $this->table . "?id=eq." . $user['id'];
        $result = $this->db->callApi($endpoint, 'PATCH', [
            'reset_token' => $token,
            'reset_token_expires_at' => $expires
        ]);

        if ($result && ($result->code == 200 || $result->code == 204)) {
            $mailer = new Mailer();
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/ShopToolNro/views/pages/reset_password.php?token=" . $token;
            $siteName = getenv('SMTP_FROM_NAME') ?: 'ShopToolNro';

            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 12px; background-color: #f9f9f9;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h1 style='color: #00bcd4; margin: 0;'>$siteName</h1>
                </div>
                <div style='background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                    <h2 style='color: #333; margin-top: 0;'>Yêu cầu đặt lại mật khẩu</h2>
                    <p style='color: #666; line-height: 1.6;'>Chào bạn,</p>
                    <p style='color: #666; line-height: 1.6;'>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn tại <strong>$siteName</strong>. Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email này.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$resetLink' style='background-color: #00bcd4; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 4px 12px rgba(0, 188, 212, 0.3); transition: all 0.3s ease;'>Đặt lại mật khẩu</a>
                    </div>
                    <p style='color: #999; font-size: 13px; line-height: 1.6;'>Liên kết này sẽ hết hạn sau <strong>1 giờ</strong>. Vì lý do bảo mật, vui lòng không chia sẻ liên kết này với bất kỳ ai.</p>
                </div>
                <div style='text-align: center; margin-top: 20px; color: #999; font-size: 12px;'>
                    <p>&copy; " . date('Y') . " $siteName. All rights reserved.</p>
                </div>
            </div>";

            return $mailer->send($email, "[$siteName] Yêu cầu đặt lại mật khẩu", $body);
        }

        return "Database error: Unable to generate reset token.";
    }

    /**
     * Verify reset token
     */
    public function verifyResetToken($token)
    {
        if (empty($token))
            return false;

        // Select user with this token
        $endpoint = $this->table . "?reset_token=eq." . $token . "&select=*&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');

        if ($result && $result->code == 200 && !empty($result->response)) {
            $user = $result->response[0];

            // Check expiry
            if (strtotime($user['reset_token_expires_at']) > time()) {
                return $user;
            }
        }

        return false;
    }

    /**
     * Reset password using token
     */
    public function resetPasswordUsingToken($token, $newPassword)
    {
        $user = $this->verifyResetToken($token);
        if (!$user)
            return false;

        $endpoint = $this->table . "?id=eq." . $user['id'];
        $result = $this->db->callApi($endpoint, 'PATCH', [
            'password_' => $newPassword,
            'reset_token' => null, // Clear token
            'reset_token_expires_at' => null
        ]);

        if ($result && ($result->code == 200 || $result->code == 204)) {
            return true;
        }
        return false;
    }

    /**
     * Update Email
     */
    public function updateEmail($id, $newEmail)
    {
        $id = (int) $id;

        // Check if email already used (by another user)
        $existing = $this->getUserByEmail($newEmail);
        if ($existing && (int) $existing['id'] !== $id) {
            return "Email đã được sử dụng bởi một tài khoản khác.";
        }

        $endpoint = $this->table . "?id=eq." . $id;
        $result = $this->db->callApi($endpoint, 'PATCH', ['email' => $newEmail]);

        if ($result && ($result->code == 200 || $result->code == 204)) {
            unset(self::$cache[$id]);
            return true;
        }
        return false;
    }

    /**
     * Get user by email (helper)
     */
    public function getUserByEmail($email)
    {
        $endpoint = $this->table . "?email=eq." . urlencode($email) . "&select=*&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    /**
     * Clear cache (call this at end of request or when needed)
     */
    public static function clearCache()
    {
        self::$cache = [];
    }
}