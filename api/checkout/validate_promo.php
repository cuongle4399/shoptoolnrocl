<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../src/classes/PromotionCode.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Database connection failed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['code']) || empty($data['order_amount'])) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'Missing required fields']);
    exit;
}

$promoClass = new PromotionCode($db);
$promo = $promoClass->validateCode($data['code'], $data['order_amount']);

if ($promo) {
    echo json_encode([
        'valid' => true,
        'discount' => $promo
    ]);
} else {
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'Mã khuyến mãi không hợp lệ hoặc hết hạn']);
}
?>
