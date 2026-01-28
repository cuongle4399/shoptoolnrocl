<?php
// Manually process a SePay transaction
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $transaction_id = isset($input['transaction_id']) ? (int) $input['transaction_id'] : 0;

    if (!$transaction_id) {
        throw new Exception('Missing transaction ID');
    }

    $database = new Database();
    $db = $database->connect();

    // Call the process function
    $result = $db->callApi(
        'rpc/process_sepay_transaction',
        'POST',
        ['p_transaction_id' => $transaction_id]
    );

    if (!$result || $result->code != 200) {
        throw new Exception('Failed to process transaction');
    }

    $processData = $result->response;

    if ($processData['success']) {
        echo json_encode([
            'success' => true,
            'message' => $processData['message'],
            'data' => $processData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $processData['message']
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>