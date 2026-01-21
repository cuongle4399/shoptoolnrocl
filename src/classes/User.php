<?php
require_once __DIR__ . '/../../includes/functions.php';

class User {
    private $db;
    private $table = 'users';
    private static $cache = []; // In-memory cache for this request

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get user by ID (with in-memory caching)
     */
    public function getUserById($id) {
        $id = (int)$id;
        
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
     * Get user by username (with in-memory caching)
     */
    public function getUserByUsername($username) {
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
    public function createUser($data) {
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
     * Update user balance (OPTIMIZED - no need to fetch user first if we just add the amount)
     * Note: For production, use database transaction via Supabase function
     */
    public function updateUserBalance($id, $amount) {
        $id = (int)$id;
        
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
    public function addPendingBalance($id, $amount) {
        return false; // Not implemented
    }

    /**
     * Approve pending balance
     * REMOVED - Not used in schema
     */
    public function approvePendingBalance($id, $amount) {
        return false; // Not implemented
    }

    /**
     * Login user
     */
    public function login($username, $password) {
        $user = $this->getUserByUsername($username);
        if (!$user) return false;

        // Prevent disabled/inactive users from logging in
        if (isset($user['status']) && $user['status'] !== 'active') return 'disabled';

        $storedPassword = $user['password_'] ?? null;
        if (!$storedPassword) return false;

        // Verify password (supports hashed and legacy plaintext)
        if (!verifyPassword($password, $storedPassword)) {
            return false;
        }

        return $user;
    }

    /**
     * Get all users (admin)
     */
    public function getAllUsers($limit = 0, $offset = 0) {
        $endpoint = $this->table . "?order=created_at.desc";
        if ($limit > 0) {
            $endpoint .= "&limit=" . (int)$limit . "&offset=" . (int)$offset;
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
    public function changeUserRole($id, $role) {
        $id = (int)$id;
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
    public function toggleUserStatus($id) {
        $id = (int)$id;
        $user = $this->getUserById($id);
        if (!$user) return false;
        
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
    public function changePassword($id, $oldPassword, $newPassword) {
        $id = (int)$id;
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
     * Clear cache (call this at end of request or when needed)
     */
    public static function clearCache() {
        self::$cache = [];
    }
}
?>