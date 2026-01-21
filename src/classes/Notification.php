<?php

class Notification {
    private $db;
    private $pdo;
    private $table = 'public.shop_notification';

    public function __construct($db) {
        $this->db = $db;
        // prefer PDO if available
        if ($db instanceof PDO) {
            $this->pdo = $db;
        } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $this->pdo = $GLOBALS['pdo'];
        } else {
            $this->pdo = null; // fallback to API via $db->callApi
        }
    }

    /**
     * Get the latest notification
     * Note: Schema now only has 'id' and 'message' fields
     */
    public function getActiveNotification() {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("SELECT id, message FROM public.shop_notification ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            return $row ?: null;
        }

        // Fallback to API
        $endpoint = 'shop_notification?order=id.desc&limit=1';
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200 && !empty($result->response)) {
            $row = $result->response[0];
            return $row;
        }
        return null;
    }

    /**
     * Get all notifications
     * Note: Schema now only has 'id' and 'message' fields
     */
    public function getAllNotifications($limit = 0, $offset = 0) {
        if ($this->pdo) {
            $sql = "SELECT id, message FROM public.shop_notification ORDER BY id DESC";
            if ($limit > 0) {
                $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return $rows;
        }

        $endpoint = 'shop_notification?order=id.desc';
        if ($limit > 0) $endpoint .= "&limit=" . (int)$limit . "&offset=" . (int)$offset;
        $result = $this->db->callApi($endpoint, 'GET');
        if ($result && $result->code == 200) {
            $rows = $result->response ?? [];
            return $rows;
        }
        return [];
    }

    /**
     * Create notification
     * Note: Only 'message' field is stored
     */
    public function createNotification($data) {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("INSERT INTO public.shop_notification (message) VALUES (?) RETURNING id");
            $stmt->execute([$data['message']]);
            $row = $stmt->fetch();
            return (bool)$row;
        }

        $payload = [
            'message' => $data['message']
        ];
        $result = $this->db->callApi('shop_notification', 'POST', $payload);
        return $result->code == 201 || $result->code == 200;
    }

    /**
     * Update notification message
     * Note: Only 'message' field can be updated
     */
    public function updateNotification($id, $data) {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("UPDATE public.shop_notification SET message = ? WHERE id = ?");
            return $stmt->execute([$data['message'], $id]);
        }

        $endpoint = 'shop_notification?id=eq.' . (int)$id;
        $result = $this->db->callApi($endpoint, 'PATCH', ['message' => $data['message']]);
        return $result->code == 200 || $result->code == 204;
    }

    /**
     * Delete notification
     */
    public function deleteNotification($id) {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("DELETE FROM public.shop_notification WHERE id = ?");
            return $stmt->execute([$id]);
        }

        $endpoint = 'shop_notification?id=eq.' . (int)$id;
        $result = $this->db->callApi($endpoint, 'DELETE', []);
        return $result->code == 200 || $result->code == 204;
    }
}
?>
