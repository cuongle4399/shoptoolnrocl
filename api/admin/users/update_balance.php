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
$user_id = (int)($input['user_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0);
$action = $input['action'] ?? 'add'; // add, subtract

if (!$user_id || !$amount) {
    response('error', 'User ID and amount required');
}

try {
    $database = new Database(); $db = $database->connect();

    $userRes = $db->callApi('users?id=eq.' . (int)$user_id, 'GET');
    if (!($userRes && $userRes->code == 200 && !empty($userRes->response))) response('error', 'User not found');
    $user = $userRes->response[0];

    $current = isset($user['balance']) ? (float)$user['balance'] : 0;
    $new_balance = ($action === 'add') ? ($current + $amount) : ($current - $amount);

    $db->callApi('users?id=eq.' . (int)$user_id, 'PATCH', ['balance' => $new_balance]);

    $db->callApi('audit_logs', 'POST', ['action' => 'BALANCE_UPDATED', 'username' => $auth['username'], 'details' => "User: $user_id, Amount: $amount, Action: $action", 'status' => 'success', 'created_at' => date('c')]);

    response('success', 'Balance updated', ['new_balance' => $new_balance]);

} catch (Exception $e) {
    try { $database = new Database(); $db = $database->connect(); $db->callApi('audit_logs','POST', ['action'=>'BALANCE_UPDATE_ERROR','username'=>$auth['username'],'details'=>$e->getMessage(),'status'=>'error','created_at'=>date('c')]); } catch (Exception $__){}
    response('error', 'Failed to update balance');
}
?>
