<?php
class License {
    private $db;
    private $table = 'infokey';
    private static $cache = []; // In-memory cache

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Verify a license key via Supabase REST (OPTIMIZED - no updateLastCheck)
     * Optional: provide product_id to ensure the key belongs to that product
     */
    public function verifyKey($hwid, $license_key, $product_id = null) {
        $endpoint = $this->table . "?license_key=eq." . urlencode($license_key) . "&status=eq.active&limit=1";
        if (!empty($product_id)) {
            $endpoint .= "&product_id=eq." . (int)$product_id;
        }

        $result = $this->db->callApi($endpoint, 'GET');

        if (!($result && $result->code == 200 && !empty($result->response))) {
            return ['valid' => false, 'message' => 'License key not found for product'];
        }

        $key = $result->response[0];

        if (!empty($key['expires_at']) && strtotime($key['expires_at']) < time()) {
            return ['valid' => false, 'message' => 'License expired'];
        }

        if (!empty($key['hwid']) && $key['hwid'] !== $hwid) {
            return ['valid' => false, 'message' => 'HWID mismatch'];
        }

        // REMOVED: updateLastCheck() call - reduces API calls by 1 per verification
        // Checking last_check timestamp is not critical for license verification

        return [
            'valid' => true,
            'message' => 'License valid',
            'user_info' => $key['user_info'] ?? null,
            'expires_at' => $key['expires_at'] ?? null,
            'key_id' => $key['id'] ?? null,
            'product_id' => $key['product_id'] ?? null,
            'hwid' => $key['hwid'] ?? null
        ];
    }

    /**
     * Get all license keys
     */
    public function getAllKeys($limit = 0, $offset = 0) {
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
     * Create a new license key
     */
    public function createKey($data) {
        $payload = [
            'hwid' => $data['hwid'] ?? null,
            'license_key' => $data['license_key'] ?? null,
            'user_info' => $data['user_info'] ?? null,
            'product_id' => isset($data['product_id']) ? (int)$data['product_id'] : null,
            'status' => $data['status'] ?? 'active',
            'created_at' => date('c'),
            'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null
        ];

        $result = $this->db->callApi($this->table, 'POST', $payload);
        if ($result && ($result->code == 201 || $result->code == 200) && !empty($result->response)) {
            return $result->response[0];
        }

        $msg = 'Failed to create license key';
        if ($result && !empty($result->response)) {
            if (is_array($result->response)) {
                if (isset($result->response['message'])) $msg = $result->response['message'];
                elseif (isset($result->response[0]['message'])) $msg = $result->response[0]['message'];
                else $msg = json_encode($result->response);
            } else {
                $msg = (string)$result->response;
            }
        }

        throw new Exception($msg);
    }

    /**
     * Update HWID for a license key
     */
    public function updateKeyHWID($new_hwid, $license_key) {
        if (empty($license_key) || empty($new_hwid)) {
            return (object)['code' => 0, 'response' => null];
        }
        
        $endpoint = $this->table . "?license_key=eq." . urlencode($license_key);
        $data = ['hwid' => $new_hwid];
        
        $result = $this->db->callApi($endpoint, 'PATCH', $data);
        return $result;
    }

    /**
     * Get license key by license_key value (with caching)
     */
    public function getKeyByLicense($license_key, $product_id = null) {
        if (empty($license_key)) return null;
        
        $cache_key = "license_" . md5($license_key . ($product_id ?? ''));
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        $endpoint = $this->table . "?license_key=eq." . urlencode($license_key) . "&limit=1";
        if (!empty($product_id)) {
            $endpoint .= "&product_id=eq." . (int)$product_id;
        }
        
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response)) {
            $key = $result->response[0];
            self::$cache[$cache_key] = $key;
            return $key;
        }
        return null;
    }

    /**
     * Get license key by ID (with caching)
     */
    public function getKeyById($id) {
        if (empty($id)) return null;
        
        $id = (int)$id;
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }
        
        $endpoint = $this->table . "?id=eq." . $id . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        
        if ($result && $result->code == 200 && !empty($result->response)) {
            $key = $result->response[0];
            self::$cache[$id] = $key;
            return $key;
        }
        return null;
    }

    /**
     * Deactivate a license key
     */
    public function deactivateKey($license_key) {
        if (empty($license_key)) return false;
        
        $endpoint = $this->table . "?license_key=eq." . urlencode($license_key);
        $result = $this->db->callApi($endpoint, 'PATCH', ['status' => 'inactive']);
        
        return ($result && ($result->code == 200 || $result->code == 204));
    }

    /**
     * Update license with custom data
     */
    public function updateLicense($license_key, $data) {
        if (empty($license_key)) return false;
        
        $endpoint = $this->table . "?license_key=eq." . urlencode($license_key);
        $result = $this->db->callApi($endpoint, 'PATCH', $data);
        
        // Invalidate cache
        $cache_key = "license_" . md5($license_key);
        unset(self::$cache[$cache_key]);
        
        return ($result && ($result->code == 200 || $result->code == 204));
    }

    /**
     * Clear cache
     */
    public static function clearCache() {
        self::$cache = [];
    }
}
?>