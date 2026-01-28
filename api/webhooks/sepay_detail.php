<?php
// Get SePay transaction detail
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (!$id) {
        throw new Exception('Missing transaction ID');
    }

    $database = new Database();
    $db = $database->connect();

    $endpoint = "sepay_transactions?id=eq.{$id}&limit=1";
    $result = $db->callApi($endpoint, 'GET');

    if (!$result || $result->code != 200 || empty($result->response)) {
        throw new Exception('Transaction not found');
    }

    echo json_encode([
        'success' => true,
        'transaction' => $result->response[0]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>