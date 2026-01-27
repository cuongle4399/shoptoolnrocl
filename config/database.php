<?php



require_once __DIR__ . '/../includes/env_loader.php';
loadEnv(__DIR__ . '/../.env');

class Database
{
    private $supabase_url;
    private $supabase_key;

    public function __construct()
    {
        $this->supabase_url = getenv('SUPABASE_URL');
        // Prefer a service role / server-side key when available for admin ops
        $serviceKey = getenv('SUPABASE_SERVICE_KEY');
        $anonKey = getenv('SUPABASE_ANON_KEY');
        $this->supabase_key = $serviceKey ?: $anonKey;

        // Controlled logging - enable by setting APP_DEBUG=1 in environment
        $debug = getenv('APP_DEBUG');
        if ($debug) {
            error_log("=== DATABASE INIT ===");
            error_log("SUPABASE_URL: " . ($this->supabase_url ? "SET" : "NOT SET"));
            if ($serviceKey) {
                error_log("SUPABASE_SERVICE_KEY: SET (using service key for server-side requests)");
            } else {
                error_log("SUPABASE_SERVICE_KEY: NOT SET");
                error_log("SUPABASE_ANON_KEY: " . ($anonKey ? "SET (length: " . strlen($anonKey) . ") - WARNING: running server-side requests with ANON key may be limited by RLS policies" : "NOT SET"));
            }
        }
    }


    public function connect()
    {
        return $this;
    }

    /**
     * Call Supabase REST API (OPTIMIZED)
     * - Optimized curl settings for faster connection
     * - Removed debug error logging for production speed
     */
    public function callApi($endpoint, $method = 'GET', $data = [])
    {
        if (!$this->supabase_url || !$this->supabase_key) {
            return (object) ['code' => 0, 'response' => null];
        }

        // Clean endpoint
        if (substr($endpoint, -1) === '?') {
            $endpoint = rtrim($endpoint, '?');
        }

        $url = $this->supabase_url . "/rest/v1/" . $endpoint;

        $ch = curl_init($url);

        // OPTIMIZED curl settings for speed (safe on hosts without FASTOPEN)
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 10,              // Reduced from 15
            CURLOPT_CONNECTTIMEOUT => 5,        // Added: faster connection timeout
            CURLOPT_SSL_VERIFYPEER => false,    // DEVELOPMENT ONLY: Disable SSL verification
            CURLOPT_SSL_VERIFYHOST => 0,        // DEVELOPMENT ONLY: Disable SSL verification
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1  // HTTP/1.1 is faster than 2.0 for REST
        ];

        // Some PHP/cURL builds (notably on Windows) lack this constant; guard it to avoid fatal errors
        if (defined('CURLOPT_TCP_FASTOPEN')) {
            $curlOptions[CURLOPT_TCP_FASTOPEN] = true;
        }

        curl_setopt_array($ch, $curlOptions);

        if ($method === 'POST' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $headers = [
            "Content-Type: application/json",
            "apikey: " . $this->supabase_key,
            "Authorization: Bearer " . $this->supabase_key
        ];

        if ($method === 'POST' || $method === 'PATCH') {
            $headers[] = "Prefer: return=representation";
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Minimal error handling - no logging in production
        if ($curl_error) {
            return (object) ['code' => 0, 'response' => null];
        }

        if ($http_code == 204 || empty($response)) {
            return (object) ['code' => $http_code, 'response' => null];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return (object) ['code' => $http_code, 'response' => null];
        }

        return (object) [
            'code' => $http_code,
            'response' => $decoded
        ];
    }

    public function beginTransaction()
    {
        return true;
    }

    public function commit()
    {
        return true;
    }

    public function rollBack()
    {
        return true;
    }
}

/**
 * Backwards-compatible PDO connection for legacy code that expects $pdo
 * Creates a global $pdo variable when DB_HOST/DB_USER/DB_PASSWORD are set.
 * This is optional and will silently leave $pdo null if connection fails.
 */
if (!isset($GLOBALS['pdo'])) {
    $dbHost = getenv('DB_HOST');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASSWORD');
    $dbName = getenv('DB_NAME') ?: 'postgres';

    if ($dbHost && $dbUser) {
        try {
            $dsn = "pgsql:host={$dbHost};port=5432;dbname={$dbName}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $GLOBALS['pdo'] = new PDO($dsn, $dbUser, $dbPass, $options);
            if (getenv('APP_DEBUG'))
                error_log('PDO connection established');
        } catch (Exception $e) {
            error_log('PDO connection failed: ' . $e->getMessage());
            $GLOBALS['pdo'] = null;
        }
    } else {
        // no DB config provided, leave $pdo null
        $GLOBALS['pdo'] = null;
    }
}

// Make $pdo available as a regular variable for backward compatibility
$pdo = $GLOBALS['pdo'] ?? null;

?>