<?php
// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration settings for MySQL database connection
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'ioscruxcom');
define('DB_PASS', 'ioscruxcom');
define('DB_NAME', 'ioscruxcom');
define('DB_PORT', '3306');

// Base URL (change this to your actual domain)
define('BASE_URL', 'http://localhost/proxyff');

/**
 * Returns a PDO connection instance.
 */
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Return JSON error if connection fails in an API context, or terminate cleanly.
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Check if user is logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user is admin.
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require authentication. Redirects to login if not logged in.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require admin role. Redirects if not admin.
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Get a system setting value by key.
 */
function getSetting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = getDbConnection();
            $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
            $rows = $stmt->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Get client's real IP address.
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($ip === '::1' || $ip === 'localhost') {
            $ip = '127.0.0.1';
        }
        return $ip;
    }
}

/**
 * Generate a CSRF token.
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token.
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Send JSON response and exit.
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Automatically check and update the Garena Free Fire logo from Google Play Store.
 * Runs in the background once every 24 hours.
 */
function updateLogoAuto() {
    $logoFile = __DIR__ . '/logo.png';
    if (!file_exists($logoFile) || (time() - filemtime($logoFile) > 86400)) {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                    'timeout' => 5
                ]
            ]);
            $html = @file_get_contents('https://play.google.com/store/apps/details?id=com.dts.freefireth', false, $ctx);
            if ($html && preg_match('/https:\/\/play-lh\.googleusercontent\.com\/[a-zA-Z0-9_=-]+/i', $html, $matches)) {
                $imgUrl = $matches[0] . '=s256-rw';
                $imgData = @file_get_contents($imgUrl, false, $ctx);
                if ($imgData) {
                    file_put_contents($logoFile, $imgData);
                }
            }
        } catch (Exception $e) {
            // Fail silently
        }
    }
}

// Run logo update check
updateLogoAuto();
?>
