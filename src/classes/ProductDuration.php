<?php
class ProductDuration {
    private $db;
    private $table = 'product_durations';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getDurationsByProductId($product_id, $activeOnly = true) {
        $endpoint = $this->table . "?product_id=eq." . (int)$product_id;
        if ($activeOnly) {
            $endpoint .= "&status=eq.active";
        }
        $endpoint .= "&order=duration_days.asc,price.asc";
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200) {
            return $result->response ?? [];
        }
        return [];
    }

    public function getById($id) {
        $endpoint = $this->table . "?id=eq." . (int)$id . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    public function create($data) {
        $result = $this->db->callApi($this->table, 'POST', $data);
        if ($result && ($result->code == 201 || $result->code == 200)) {
            // Return true on successful creation, even if response is empty
            return !empty($result->response) ? $result->response[0] : true;
        }
        error_log('ProductDuration create failed - Code: ' . ($result->code ?? 'N/A') . ', Response: ' . json_encode($result->response ?? null));
        return false;
    }

    public function update($id, $data) {
        $endpoint = $this->table . "?id=eq." . (int)$id;
        $result = $this->db->callApi($endpoint, 'PATCH', $data);
        return ($result && ($result->code == 200 || $result->code == 204));
    }

    public function deleteByProductId($product_id) {
        $endpoint = $this->table . "?product_id=eq." . (int)$product_id;
        $result = $this->db->callApi($endpoint, 'DELETE');
        return ($result && ($result->code == 200 || $result->code == 204));
    }

    public function getProductPrices($product_id) {
        // Returns all durations for a product ordered by duration_days and price
        return $this->getDurationsByProductId($product_id, true);
    }
}
?>