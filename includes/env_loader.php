<?php
if (!function_exists('loadEnv')) {
    function loadEnv($path)
    {
        if (!file_exists($path)) {
            // Check if .env is in parent directory (common when including from config subdirectory)
            $parentPath = dirname($path) . '/../.env';
            if (file_exists($parentPath)) {
                $path = $parentPath;
            } else {
                return;
            }
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0)
                continue;
            if (strpos($line, '=') === false)
                continue;

            list($key, $value) = array_map('trim', explode('=', $line, 2));
            $value = trim($value, '"\''); // Remove quotes

            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}