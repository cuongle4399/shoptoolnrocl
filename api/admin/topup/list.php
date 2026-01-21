<?php
// ====================================================================
// API: List Topup Requests (Admin)
// Using Supabase REST API
// ====================================================================

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/classes/TopupRequest.php';

$database = new Database();
$db = $database->connect();
$topupClass = new TopupRequest($db);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET method is allowed']);
    exit;
}

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $status = $_GET['status'] ?? 'pending';  // default to 'pending'
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Get topup requests with limit/offset
    $endpoint = 'topup_requests?status=eq.' . urlencode($status) . '&order=created_at.desc&limit=' . $limit . '&offset=' . $offset;
    $result = $db->callApi($endpoint, 'GET');
    
    if (!$result || $result->code != 200) {
        throw new Exception('Failed to fetch topup requests');
    }
    
    $topups = $result->response ?? [];
    
    // Get total count for pagination
    $countEndpoint = 'topup_requests?status=eq.' . urlencode($status) . '&select=id';
    $countResult = $db->callApi($countEndpoint, 'GET');
    $total = ($countResult && $countResult->code == 200) ? count($countResult->response ?? []) : 0;
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Topup requests fetched',
        'topups' => $topups,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch topup requests: ' . $e->getMessage()
    ]);
}
?>
