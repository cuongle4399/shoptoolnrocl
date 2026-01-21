<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents("php://input"), true);

try {
    $endpoint = "promotion_codes?id=eq." . (int)$data['promo_id'];
    $result = $db->callApi($endpoint, 'DELETE');

    if ($result && ($result->code == 200 || $result->code == 204)) {
        echo json_encode(['success' => true, 'message' => 'Đã xóa']);
    } else {
        throw new Exception('Xóa thất bại');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
