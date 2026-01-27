<?php
// Test Google Login API directly
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== GOOGLE LOGIN DEBUG TEST ===\n\n";

// 1. Check autoload
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("ERROR: vendor/autoload.php not found. Run 'composer install' first.\n");
}
require_once __DIR__ . '/vendor/autoload.php';
echo "[OK] Autoload loaded\n";

// 2. Check Google Client class
if (!class_exists('Google\Client')) {
    die("ERROR: Google\\Client class not found.\n");
}
echo "[OK] Google\\Client class exists\n";

// 3. Load environment
require_once __DIR__ . '/config/constants.php';
$clientId = getenv('GOOGLE_CLIENT_ID');
if (!$clientId) {
    die("ERROR: GOOGLE_CLIENT_ID not set in .env\n");
}
echo "[OK] GOOGLE_CLIENT_ID loaded: " . substr($clientId, 0, 20) . "...\n";

// 4. Test creating Google Client
try {
    $client = new Google\Client(['client_id' => $clientId]);
    echo "[OK] Google Client created successfully\n";
} catch (Exception $e) {
    die("ERROR creating Google Client: " . $e->getMessage() . "\n");
}

// 5. Test with SSL disabled
try {
    $httpClient = new GuzzleHttp\Client(['verify' => false]);
    $client->setHttpClient($httpClient);
    echo "[OK] HTTP Client configured (SSL verification disabled)\n";
} catch (Exception $e) {
    die("ERROR configuring HTTP Client: " . $e->getMessage() . "\n");
}

// 6. Test token verification (you need a real token to test this)
echo "\n=== READY FOR TESTING ===\n";
echo "To test token verification:\n";
echo "1. Click 'Sign in with Google' button on login page\n";
echo "2. Open Browser Console (F12)\n";
echo "3. Copy the 'credential' value from the console log\n";
echo "4. Add ?token=YOUR_TOKEN to this URL\n\n";

if (isset($_GET['token']) && !empty($_GET['token'])) {
    echo "\n=== TESTING TOKEN VERIFICATION ===\n";
    try {
        $payload = $client->verifyIdToken($_GET['token']);
        if ($payload) {
            echo "[SUCCESS] Token verified!\n";
            echo "User ID: " . $payload['sub'] . "\n";
            echo "Email: " . $payload['email'] . "\n";
            echo "Name: " . $payload['name'] . "\n";
        } else {
            echo "[FAIL] Token verification returned null\n";
        }
    } catch (Exception $e) {
        echo "[ERROR] Token verification failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

echo "\n=== END TEST ===\n";
?>