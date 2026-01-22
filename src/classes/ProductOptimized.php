<?php
/**
 * Optimized Product Class using Supabase Functions
 * Giảm từ N+1 queries → 1 query duy nhất
 */
class ProductOptimized {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get all products with durations - 1 API CALL
     * Thay vì: getAllProducts() + loop getDurationsByProductId()
     * Chỉ cần: 1 RPC call
     */
    public function getAllProductsWithDurations($limit = 12, $offset = 0) {
        $endpoint = "rpc/get_products_with_durations";
        $data = [
            'p_limit' => (int)$limit,
            'p_offset' => (int)$offset
        ];
        
        $result = $this->db->callApi($endpoint, 'POST', $data);
        
        if ($result && $result->code == 200 && isset($result->response['products'])) {
            return [
                'products' => $result->response['products'],
                'total' => $result->response['total'] ?? 0,
                'limit' => $result->response['limit'] ?? $limit,
                'offset' => $result->response['offset'] ?? $offset
            ];
        }
        
        return [
            'products' => [],
            'total' => 0,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Get product detail with durations - 1 API CALL
     * Thay vì: getProductById() + getDurationsByProductId()
     */
    public function getProductDetail($product_id) {
        $endpoint = "rpc/get_product_detail";
        $data = ['p_product_id' => (int)$product_id];
        
        $result = $this->db->callApi($endpoint, 'POST', $data);
        
        if ($result && $result->code == 200) {
            return $result->response;
        }
        
        return null;
    }
}
?>
