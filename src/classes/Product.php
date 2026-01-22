<?php
class Product {
    private $db;
    private $table = 'products';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get all products
     * NOTE: status column has been removed from products table
     */
    public function getAllProducts($onlyActive = true, $limit = 0, $offset = 0) {
        // Since status no longer exists, ignore $onlyActive parameter
        $endpoint = $this->table . "?order=created_at.desc";
        if ($limit > 0) {
            $endpoint .= "&limit=" . (int)$limit . "&offset=" . (int)$offset;
        }
        $result = $this->db->callApi($endpoint, 'GET');
        
        if ($result && $result->code == 200) {
            return $result->response ?? [];
        }
        return [];
    }

    /**
     * Get product by ID
     * NOTE: status column has been removed from products table
     */
    public function getProductById($id, $onlyActive = true) {
        // Since status no longer exists, ignore $onlyActive parameter
        $endpoint = $this->table . "?id=eq." . (int)$id . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        
        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    /**
     * Get many products by id in a single REST call
     */
    public function getProductsByIds(array $ids) {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) return [];

        $idList = implode(',', $ids);
        $endpoint = $this->table . "?id=in.(" . $idList . ")";
        $result = $this->db->callApi($endpoint, 'GET');

        if ($result && $result->code == 200 && !empty($result->response)) {
            $map = [];
            foreach ($result->response as $p) {
                if (isset($p['id'])) {
                    $map[(int)$p['id']] = $p;
                }
            }
            return $map;
        }
        return [];
    }

    /**
     * Get product by code (unique code / sku)
     */
    public function getByCode($code) {
        $endpoint = $this->table . "?code=eq." . urlencode($code) . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    /**
     * Create product
     */
    public function createProduct($data) {
        // Remove status if present in data since column doesn't exist
        if (isset($data['status'])) {
            unset($data['status']);
        }
        
        $result = $this->db->callApi($this->table, 'POST', $data);
        if ($result && ($result->code == 201 || $result->code == 200) && !empty($result->response)) {
            return $result->response[0];
        }
        return false;
    }

    /**
     * Update product
     */
    public function updateProduct($id, $data) {
        // Remove status if present in data since column doesn't exist
        if (isset($data['status'])) {
            unset($data['status']);
        }
        
        $endpoint = $this->table . "?id=eq." . (int)$id;
        $result = $this->db->callApi($endpoint, 'PATCH', $data);
        return $result->code == 200 || $result->code == 204;
    }

    /**
     * Delete product (hard delete)
     */
    public function deleteProduct($id) {
        $endpoint = $this->table . "?id=eq." . (int)$id;
        $result = $this->db->callApi($endpoint, 'DELETE', []);
        return $result->code == 200 || $result->code == 204;
    }
}
?>