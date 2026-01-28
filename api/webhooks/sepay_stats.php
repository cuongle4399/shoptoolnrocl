<?php
// Get SePay transaction statistics
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->connect();

    // Today's transactions count
    $today = date('Y-m-d');
    $endpoint = "sepay_transactions?transaction_date=gte.{$today}T00:00:00&select=count";
    $result = $db->callApi($endpoint, 'GET');
    $today_count = $result->response[0]['count'] ?? 0;

    // Today's amount in
    $endpoint = "sepay_transactions?transaction_date=gte.{$today}T00:00:00&select=amount_in";
    $result = $db->callApi($endpoint, 'GET');
    $today_amount_in = 0;
    if ($result && $result->code == 200) {
        foreach ($result->response as $trans) {
            $today_amount_in += $trans['amount_in'] ?? 0;
        }
    }

    // Processed count
    $endpoint = "sepay_transactions?processed=eq.true&select=count";
    $result = $db->callApi($endpoint, 'GET');
    $processed_count = $result->response[0]['count'] ?? 0;

    // Pending count
    $endpoint = "sepay_transactions?processed=eq.false&select=count";
    $result = $db->callApi($endpoint, 'GET');
    $pending_count = $result->response[0]['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'today_count' => $today_count,
            'today_amount_in' => $today_amount_in,
            'processed_count' => $processed_count,
            'pending_count' => $pending_count
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>