<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response('error', 'Method not allowed');
}

$auth = requireRole(ROLE_ADMIN);

$input = json_decode(file_get_contents('php://input'), true);
$code = strtoupper($input['code'] ?? '');
$discount_percent = (int)($input['discount_percent'] ?? 0);
$discount_amount = (float)($input['discount_amount'] ?? 0);
$max_uses = (int)($input['max_uses'] ?? 0);
$min_order_amount = (float)($input['min_order_amount'] ?? 0);
$expires_at = $input['expires_at'] ?? null;

if (!$code) {
    response('error', 'Promotion code required');
}

if (!$discount_percent && !$discount_amount) {
    response('error', 'Discount percent or amount required');
}

try {
    $database = new Database(); $db = $database->connect();
    $payload = [
        'code' => $code,
        'discount_percent' => $discount_percent ?: null,
        'discount_amount' => $discount_amount ?: null,
        'max_uses' => $max_uses ?: null,
        'min_order_amount' => $min_order_amount ?: null,
        'expires_at' => $expires_at ?: null,
        'created_by_admin' => $auth['user_id'] ?? null,
        'created_at' => date('c')
    ];

    $result = $db->callApi('promotion_codes', 'POST', $payload);
    if (!($result && ($result->code == 201 || $result->code == 200) && !empty($result->response))) {
        throw new Exception('Failed to create promotion');
    }

    $promo = $result->response[0];
    $db->callApi('audit_logs', 'POST', ['action' => 'PROMOTION_CREATED', 'username' => $auth['username'], 'details' => "Code: $code", 'status' => 'success', 'created_at' => date('c')]);
    response('success', 'Promotion code created', ['promo' => $promo]);

} catch (Exception $e) {
    try { $database = new Database(); $db = $database->connect(); $db->callApi('audit_logs','POST', ['action'=>'PROMOTION_ERROR','username'=>$auth['username'],'details'=>$e->getMessage(),'status'=>'error','created_at'=>date('c')]); } catch (Exception $__){}
    response('error', 'Failed to create promotion code');
} 
?>
