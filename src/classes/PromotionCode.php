<?php

class PromotionCode {
    private $db;
    private $table = 'promotion_codes';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get promotion code by code string
     */
    public function getByCode($code) {
        if (empty($code)) return null;
        
        $endpoint = $this->table . "?code=eq." . urlencode(strtoupper(trim($code))) . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        
        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    /**
     * Validate promotion code
     */
    public function validateCode($code, $order_amount) {
        $endpoint = $this->table . "?code=eq." . urlencode($code) . 
                   "&or=(expires_at.is.null,expires_at.gt." . date('Y-m-d') . ")" .
                   "&order=id.desc&limit=1";
        
        $result = $this->db->callApi($endpoint, 'GET');
        
        if ($result && $result->code == 200 && !empty($result->response)) {
            $promo = $result->response[0];
            
            if ($promo['max_uses'] && $promo['usage_count'] >= $promo['max_uses']) {
                return null;
            }
            
            if ($promo['min_order_amount'] && $order_amount < $promo['min_order_amount']) {
                return null;
            }
            
            return $promo;
        }
        return null;
    }

    /**
     * Increment usage count
     */
    public function incrementUsage($code) {
        $promo = $this->validateCode($code, 0);
        if (!$promo) return false;

        $endpoint = $this->table . "?id=eq." . $promo['id'];
        $data = ['usage_count' => $promo['usage_count'] + 1];

        $result = $this->db->callApi($endpoint, 'PATCH', $data);
        return ($result && ($result->code == 200 || $result->code == 204));
    }

    /**
     * Get all promotion codes (admin list)
     */
    public function getAllPromoCodes($limit = 0, $offset = 0) {
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
     * Create a promotion code (admin)
     */
    public function createPromoCode($data) {
        $code = strtoupper(trim($data['code'] ?? ''));
        $discount_percent = isset($data['discount_percent']) && $data['discount_percent'] !== '' ? (int)$data['discount_percent'] : null;
        $discount_amount = isset($data['discount_amount']) && $data['discount_amount'] !== '' ? (float)$data['discount_amount'] : null;

        if (!$code) {
            throw new Exception('Promotion code required');
        }

        if ($discount_percent === null && $discount_amount === null) {
            throw new Exception('Discount percent or amount required');
        }

        // check duplicate
        $check = $this->db->callApi($this->table . "?code=eq." . urlencode($code), 'GET');
        if ($check && $check->code == 200 && !empty($check->response)) {
            throw new Exception('Promotion code already exists');
        }

        $payload = [
            'code' => $code,
            'discount_percent' => $discount_percent ?: null,
            'discount_amount' => $discount_amount ?: null,
            'max_uses' => isset($data['max_uses']) && $data['max_uses'] !== '' ? (int)$data['max_uses'] : null,
            'min_order_amount' => isset($data['min_order_amount']) && $data['min_order_amount'] !== '' ? (float)$data['min_order_amount'] : null,
            'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null
        ];

        $result = $this->db->callApi($this->table, 'POST', $payload);
        if ($result && ($result->code == 201 || $result->code == 200)) {
            return true;
        }
        return false;
    }

    /**
     * Update promo helper (optional)
     */
    public function updatePromoCode($id, $data) {
        $endpoint = $this->table . "?id=eq." . (int)$id;
        $payload = [];
        if (isset($data['code'])) $payload['code'] = strtoupper(trim($data['code']));
        $payload['discount_percent'] = isset($data['discount_percent']) && $data['discount_percent'] !== '' ? (int)$data['discount_percent'] : null;
        $payload['discount_amount'] = isset($data['discount_amount']) && $data['discount_amount'] !== '' ? (float)$data['discount_amount'] : null;
        $payload['max_uses'] = isset($data['max_uses']) && $data['max_uses'] !== '' ? (int)$data['max_uses'] : null;
        $payload['min_order_amount'] = isset($data['min_order_amount']) && $data['min_order_amount'] !== '' ? (float)$data['min_order_amount'] : null;
        $payload['expires_at'] = isset($data['expires_at']) && $data['expires_at'] !== '' ? $data['expires_at'] : null;

        $result = $this->db->callApi($endpoint, 'PATCH', $payload);
        return ($result && ($result->code == 200 || $result->code == 204));
    }

    /**
     * Delete promo helper (optional)
     */
    public function deletePromoCode($id) {
        $endpoint = $this->table . "?id=eq." . (int)$id;
        $result = $this->db->callApi($endpoint, 'DELETE');
        return ($result && ($result->code == 200 || $result->code == 204));
    }
}
?>
