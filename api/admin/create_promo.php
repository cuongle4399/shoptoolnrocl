<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/PromotionCode.php';

$database = new Database();
$db = $database->connect();
$promo = new PromotionCode($db);

$data = json_decode(file_get_contents("php://input"), true);
// Note: created_by_admin field removed from schema

try {
    if ($promo->createPromoCode($data)) {
        echo json_encode(['success' => true, 'message' => 'Mã đã được tạo']);
    } else {
        throw new Exception('Không thể tạo mã');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
