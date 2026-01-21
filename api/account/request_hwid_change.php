<?php
header('Content-Type: application/json');
// Deprecated endpoint — request/approve flow removed
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'Deprecated: HWID request/approve flow removed. Use direct HWID set via /api/hwid/set_hwid.php (or Order page UI).'
]);
exit; 


?>
