<?php
/**
 * API: Generate iOS .mobileconfig profile
 * GET /api/mobileconfig.php - Download proxy configuration
 *
 * Generates a signed .mobileconfig file for automatic iOS proxy setup.
 */

require_once '../config.php';
requireLogin();

// Check license
$db = getDbConnection();
$stmt = $db->prepare("SELECT l.id, l.expires_at FROM licenses l WHERE l.user_id = ? AND l.status = 'used' AND l.expires_at > NOW() LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$license = $stmt->fetch();

if (!$license) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bạn cần kích hoạt license để tải cấu hình proxy.']);
    exit;
}

// Get system settings
$proxyHost = getSetting('proxy_host', '127.0.0.1');
$proxyPort = (int)getSetting('proxy_port', '9999');
$sslPort = (int)getSetting('proxy_ssl_port', '8080');
$siteTitle = getSetting('site_title', 'ProxyFF');
$proxyVersion = getSetting('proxy_version', 'V1');

// Load CA certificate (mitmproxy)
$caCertPath = __DIR__ . '/ca-cert.pem';
$caCertData = '';
if (file_exists($caCertPath)) {
    $caPem = file_get_contents($caCertPath);
    // Extract base64 content between BEGIN/END markers
    preg_match('/-----BEGIN CERTIFICATE-----\s*(.*?)\s*-----END CERTIFICATE-----/s', $caPem, $matches);
    if (isset($matches[1])) {
        $caCertData = base64_encode(base64_decode($matches[1])); // normalize
    }
}

// Generate UUIDs
$payloadUUID = strtoupper(sprintf('%s-%s-%s-%s-%s',
    bin2hex(random_bytes(4)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(6))
));
$dnsUUID = strtoupper(sprintf('%s-%s-%s-%s-%s',
    bin2hex(random_bytes(4)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(6))
));
$caUUID = strtoupper(sprintf('%s-%s-%s-%s-%s',
    bin2hex(random_bytes(4)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(2)),
    bin2hex(random_bytes(6))
));

$content = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadContent</key>
    <array>
        <!-- MITM SSL CA Certificate -->
        <dict>
            <key>PayloadDisplayName</key>
            <string>' . $siteTitle . ' CA</string>
            <key>PayloadIdentifier</key>
            <string>com.' . strtolower($siteTitle) . '.ca</string>
            <key>PayloadType</key>
            <string>com.apple.security.root</string>
            <key>PayloadUUID</key>
            <string>' . $caUUID . '</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
            <key>PayloadContent</key>
            <data>' . ($caCertData ?: '') . '</data>
        </dict>
    </array>
    <key>PayloadDisplayName</key>
    <string>' . $siteTitle . ' ' . $proxyVersion . '</string>
    <key>PayloadIdentifier</key>
    <string>com.' . strtolower($siteTitle) . '.config</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>' . $payloadUUID . '</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
</dict>
</plist>';

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="' . strtolower($siteTitle) . '.mobileconfig"');
header('Content-Length: ' . strlen($content));
echo $content;
exit;
