<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$promoId = (int)($input['promo_id'] ?? 0);
$code = strtoupper(trim($input['code'] ?? ''));
$discount_percent = isset($input['discount_percent']) && $input['discount_percent'] !== '' ? (int)$input['discount_percent'] : null;
$discount_amount = isset($input['discount_amount']) && $input['discount_amount'] !== '' ? (float)$input['discount_amount'] : null;
$max_uses = isset($input['max_uses']) && $input['max_uses'] !== '' ? (int)$input['max_uses'] : null;
$min_order_amount = isset($input['min_order_amount']) && $input['min_order_amount'] !== '' ? (float)$input['min_order_amount'] : null;
$expires_at = $input['expires_at'] ?? null;

if (!$promoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid promo id']);
    exit;
}

if (!$code) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Promotion code required']);
    exit;
}

if ($discount_percent === null && $discount_amount === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Discount percent or amount required']);
    exit;
}

try {
    $database = new Database(); $db = $database->connect();
    $endpoint = "promotion_codes?id=eq." . $promoId;

    $payload = [];
    $payload['code'] = $code;
    $payload['discount_percent'] = $discount_percent ?: null;
    $payload['discount_amount'] = $discount_amount ?: null;
    $payload['max_uses'] = $max_uses ?: null;
    $payload['min_order_amount'] = $min_order_amount ?: null;
    $payload['expires_at'] = $expires_at ?: null;
    // Note: updated_by_admin field removed from schema

    $result = $db->callApi($endpoint, 'PATCH', $payload);
    if ($result && ($result->code == 200 || $result->code == 204)) {
        echo json_encode(['success' => true, 'message' => 'Updated']);
    } else {
        throw new Exception('Failed to update promotion');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>