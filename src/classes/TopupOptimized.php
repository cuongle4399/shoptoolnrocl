<?php
/**
 * Optimized Topup Request Class
 */
class TopupOptimized {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get topup requests with user/admin details - 1 API CALL
     */
    public function getTopupRequestsDetailed($status = null, $limit = 50, $offset = 0) {
        $endpoint = "rpc/get_topup_requests_detailed";
        $data = [
            'p_status' => $status,
            'p_limit' => (int)$limit,
            'p_offset' => (int)$offset
        ];
        
        $result = $this->db->callApi($endpoint, 'POST', $data);
        
        if ($result && $result->code == 200 && isset($result->response['requests'])) {
            return [
                'requests' => $result->response['requests'],
                'total' => $result->response['total'] ?? 0
            ];
        }
        
        return ['requests' => [], 'total' => 0];
    }
}
?>
