<?php
class Order {
    private $db;
    private $table = 'orders';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Create order (OPTIMIZED - removed duplicate check before creation)
     * The database trigger handles duplicate prevention
     */
    public function createOrder($data) {
        // Remove status if present since column doesn't exist
        if (isset($data['status'])) {
            unset($data['status']);
        }
        
        $idempotency_key = !empty($data['idempotency_key']) ? trim($data['idempotency_key']) : null;

        // Attempt to create via Supabase REST API
        $result = $this->db->callApi($this->table, 'POST', $data);
        if ($result && ($result->code == 201 || $result->code == 200) && !empty($result->response)) {
            return $result->response[0];
        }

        // If creation failed but idempotency_key was provided, try to fetch the existing order
        if ($idempotency_key) {
            $get = $this->db->callApi($this->table . "?idempotency_key=eq." . urlencode($idempotency_key) . "&limit=1", 'GET');
            if ($get && $get->code == 200 && !empty($get->response)) {
                return $get->response[0];
            }
        }

        return false;
    }

    /**
     * Update arbitrary fields on an order
     */
    public function updateOrder($id, $data) {
        // Remove status if present since column doesn't exist
        if (isset($data['status'])) {
            unset($data['status']);
        }
        
        $endpoint = $this->table . "?id=eq." . (int)$id;
        $result = $this->db->callApi($endpoint, 'PATCH', $data);
        return ($result && ($result->code == 200 || $result->code == 204));
    }

    /**
     * Get orders by user ID
     */
    public function getOrdersByUserId($user_id, $limit = 0, $offset = 0) {
        $user_id = (int)$user_id;
        $endpoint = $this->table . "?user_id=eq." . $user_id . "&order=created_at.desc";
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
     * Get orders by product id
     */
    public function getOrdersByProductId($product_id, $user_id = null) {
        $product_id = (int)$product_id;
        $endpoint = $this->table . "?product_id=eq." . $product_id;
        if (!empty($user_id)) {
            $endpoint .= "&user_id=eq." . (int)$user_id;
        }
        $endpoint .= "&order=created_at.desc";

        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200) {
            return $result->response ?? [];
        }
        return [];
    }

    /**
     * Get all orders (admin dashboard)
     */
    public function getAllOrders($limit = 0, $offset = 0) {
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
     * Get order by ID
     */
    public function getOrderById($id) {
        $id = (int)$id;
        $endpoint = $this->table . "?id=eq." . $id . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        
        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    /**
     * Mark order as completed
     */
    public function completeOrder($id) {
        $id = (int)$id;
        $endpoint = $this->table . "?id=eq." . $id;
        $result = $this->db->callApi($endpoint, 'PATCH', ['completed_at' => date('c')]);
        return $result && ($result->code == 200 || $result->code == 204);
    }

    /**
     * Check if order is completed
     */
    public function isOrderCompleted($order) {
        return !empty($order['completed_at']);
    }

    /**
     * Get total revenue (sum of all completed order totals)
     */
    public function getTotalRevenue() {
        $endpoint = $this->table . "?completed_at=not.is.null";
        $result = $this->db->callApi($endpoint, 'GET');
        
        if ($result && $result->code == 200) {
            $orders = $result->response ?? [];
            $total = 0;
            foreach ($orders as $order) {
                $total += (float)($order['total_price'] ?? 0);
            }
            return $total;
        }
        
        return 0;
    }

    /**
     * Get pending orders (orders without completed_at)
     */
    public function getPendingOrders($limit = 0, $offset = 0) {
        $endpoint = $this->table . "?completed_at=is.null&order=created_at.desc";
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
     * Get completed orders
     */
    public function getCompletedOrders($limit = 0, $offset = 0) {
        $endpoint = $this->table . "?completed_at=not.is.null&order=completed_at.desc";
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
     * Get order count
     */
    public function getOrderCount() {
        $orders = $this->getAllOrders();
        return count($orders);
    }

    /**
     * Get completed order count
     */
    public function getCompletedOrderCount() {
        $orders = $this->getCompletedOrders();
        return count($orders);
    }
}
?>