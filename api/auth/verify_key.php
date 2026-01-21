<?php
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../src/classes/License.php';

$database = new Database();
$db = $database->connect();
$license = new License($db);

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->hwid) || !isset($data->license_key) || !isset($data->product_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields (hwid, license_key, product_id)']);
    exit;
}

$result = $license->verifyKey($data->hwid, $data->license_key, (int)$data->product_id);
http_response_code($result['valid'] ? 200 : 401);
echo json_encode($result);
?>
