<?php
/**
 * Optimized Order Class using Supabase Atomic Function
 * Tránh race condition, duplicate orders
 */
class OrderOptimized {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get user orders with full details - 1 API CALL
     * Thay vì: getAllOrders() + loop để lấy product/license info
     */
    public function getUserOrdersDetailed($user_id, $limit = 50, $offset = 0) {
        $endpoint = "rpc/get_user_orders_detailed";
        $data = [
            'p_user_id' => (int)$user_id,
            'p_limit' => (int)$limit,
            'p_offset' => (int)$offset
        ];
        
        $result = $this->db->callApi($endpoint, 'POST', $data);
        
        if ($result && $result->code == 200 && isset($result->response['orders'])) {
            return [
                'orders' => $result->response['orders'],
                'total' => $result->response['total'] ?? 0
            ];
        }
        
        return ['orders' => [], 'total' => 0];
    }
    
    /**
     * Create order atomically - NO RACE CONDITIONS
     * Sử dụng database transaction, lock rows
     */
    public function createOrderAtomic($user_id, $product_id, $duration_id, $total_price, $promo_code_id = null) {
        // Generate idempotency key to prevent duplicate orders
        $idempotency_key = md5($user_id . '_' . $product_id . '_' . $duration_id . '_' . time());
        
        $endpoint = "rpc/create_order_atomic";
        $data = [
            'p_user_id' => (int)$user_id,
            'p_product_id' => (int)$product_id,
            'p_duration_id' => (int)$duration_id,
            'p_total_price' => (float)$total_price,
            'p_promo_code_id' => $promo_code_id ? (int)$promo_code_id : null,
            'p_idempotency_key' => $idempotency_key
        ];
        
        $result = $this->db->callApi($endpoint, 'POST', $data);
        
        if ($result && $result->code == 200) {
            return $result->response;
        }
        
        return ['success' => false, 'message' => 'Order creation failed'];
    }
}
?>
