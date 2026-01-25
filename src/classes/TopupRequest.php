<?php
class TopupRequest
{
    private $db;
    private $table = 'topup_requests';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get user topup requests
     */
    public function getUserTopupRequests($user_id, $limit = 0, $offset = 0)
    {
        $endpoint = $this->table . "?user_id=eq." . (int) $user_id . "&order=created_at.desc";
        if ($limit > 0) {
            $endpoint .= "&limit=" . (int) $limit . "&offset=" . (int) $offset;
        }
        $result = $this->db->callApi($endpoint, 'GET');

        if ($result && $result->code == 200) {
            return $result->response ?? [];
        }
        return [];
    }

    /**
     * Get all topup requests (admin)
     */
    public function getAllTopupRequests($limit = 0, $offset = 0)
    {
        $endpoint = $this->table . "?order=created_at.desc";
        if ($limit > 0) {
            $endpoint .= "&limit=" . (int) $limit . "&offset=" . (int) $offset;
        }
        $result = $this->db->callApi($endpoint, 'GET');

        if ($result && $result->code == 200) {
            return $result->response ?? [];
        }
        return [];
    }

    /**
     * Create topup request
     */
    public function createTopupRequest($data)
    {
        // Validate required fields
        if (!isset($data['user_id']) || !isset($data['amount'])) {
            $this->logDebug('Missing required fields: ' . json_encode($data));
            return null;
        }

        $topupData = [
            'user_id' => (int) $data['user_id'],
            'amount' => (float) $data['amount'],
            'description' => $data['description'] ?? null,
            'status' => 'pending'
        ];

        $this->logDebug('createTopupRequest - START');
        $this->logDebug('Input Data: ' . json_encode($data));
        $this->logDebug('Sending to API: ' . json_encode($topupData));

        try {
            $result = $this->db->callApi($this->table, 'POST', $topupData);

            $this->logDebug('API Response Code: ' . ($result->code ?? 'NULL'));
            $this->logDebug('API Response Data: ' . json_encode($result->response ?? 'NULL'));

            // Check for successful response
            if (!$result) {
                $this->logDebug('Result object is NULL');
                return null;
            }

            if ($result->code == 201 || $result->code == 200) {
                $this->logDebug('Success! Code: ' . $result->code);

                if (!empty($result->response)) {
                    $responseData = is_array($result->response) ? $result->response[0] : $result->response;
                    $this->logDebug('Returning response: ' . json_encode($responseData));
                    return $responseData;
                }

                $this->logDebug('Response empty but success code - returning true');
                return true;
            }

            $this->logDebug('API Error - Code: ' . ($result->code ?? 'NULL'));
            if (isset($result->message)) {
                $this->logDebug('API Message: ' . $result->message);
            }
            return null;

        } catch (Exception $e) {
            $this->logDebug('Exception in createTopupRequest: ' . $e->getMessage());
            $this->logDebug('Trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Get topup request by ID
     */
    public function getTopupById($id)
    {
        $endpoint = $this->table . "?id=eq." . (int) $id . "&limit=1";
        $result = $this->db->callApi($endpoint, 'GET');

        if ($result && $result->code == 200 && !empty($result->response)) {
            return $result->response[0];
        }
        return null;
    }

    /**
     * Update topup status (for admin approval/rejection)
     */
    public function updateTopupStatus($id, $status, $admin_id = null, $rejection_reason = null)
    {
        $endpoint = $this->table . "?id=eq." . (int) $id;
        $data = [
            'status' => $status,
            'approved_by_admin' => ($status === 'approved') ? $admin_id : null,
            // 'approved_at' is handled by DB trigger on_topup_approved to ensure proper timezone
            'rejection_reason' => ($status === 'rejected') ? $rejection_reason : null
        ];

        $result = $this->db->callApi($endpoint, 'PATCH', $data);
        return $result && ($result->code == 200 || $result->code == 204);
    }

    /**
     * Cancel topup request (user only)
     */
    public function cancelTopupRequest($id, $user_id)
    {
        $this->logDebug('cancelTopupRequest - START: ID=' . $id . ', UserID=' . $user_id);

        // Get topup to verify ownership
        $topup = $this->getTopupById($id);

        if (!$topup) {
            $this->logDebug('Topup not found');
            return false;
        }

        // Verify user owns this topup and it's still pending
        if ($topup['user_id'] != $user_id || $topup['status'] !== 'pending') {
            $this->logDebug('Topup ownership check failed or not pending. User: ' . $topup['user_id'] . ' vs ' . $user_id . ', Status: ' . $topup['status']);
            return false;
        }

        // Update status to cancelled
        $endpoint = $this->table . "?id=eq." . (int) $id;
        $data = [
            'status' => 'cancelled'
        ];

        $this->logDebug('Sending PATCH: ' . json_encode($data));
        $result = $this->db->callApi($endpoint, 'PATCH', $data);

        $success = $result && ($result->code == 200 || $result->code == 204);
        $this->logDebug('Result: ' . ($success ? 'SUCCESS' : 'FAILED') . ' (Code: ' . ($result->code ?? 'NULL') . ')');

        return $success;
    }

    /**
     * Get all pending topup requests (admin)
     */
    public function getPendingRequests($limit = 50, $offset = 0)
    {
        $endpoint = $this->table . "?status=eq.pending&order=created_at.asc";
        if ($limit > 0) {
            $endpoint .= "&limit=" . (int) $limit . "&offset=" . (int) $offset;
        }
        $result = $this->db->callApi($endpoint, 'GET');

        if ($result && $result->code == 200) {
            return $result->response ?? [];
        }
        return [];
    }

    /**
     * Log debug info to file
     */
    private function logDebug($message)
    {
        $logFile = __DIR__ . '/../../logs/topup_debug.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        error_log($logMessage, 3, $logFile);
    }
}
?>