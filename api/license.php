<?php
/**
 * API: License management
 * POST /api/license.php?action=activate - Activate a license key
 * GET  /api/license.php?action=status  - Check license status
 * GET  /api/license.php?action=list    - List all licenses (admin only)
 * POST /api/license.php?action=generate - Generate new licenses (admin only)
 * POST /api/license.php?action=revoke   - Revoke a license (admin only)
 */
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$db = getDbConnection();

// ACTIVATE LICENSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'activate') {
    $input = json_decode(file_get_contents('php://input'), true);
    $licenseKey = trim($input['license_key'] ?? '');

    if (empty($licenseKey)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập mã kích hoạt.'], 400);
    }

    // Find license
    $stmt = $db->prepare("SELECT id, status, duration_days, device_limit, user_id FROM licenses WHERE license_key = ?");
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch();

    if (!$license) {
        jsonResponse(['success' => false, 'message' => 'Mã kích hoạt không tồn tại.'], 404);
    }

    if ($license['status'] === 'used') {
        if ($license['user_id'] == $_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'Bạn đã kích hoạt mã này rồi.']);
        }
        jsonResponse(['success' => false, 'message' => 'Mã kích hoạt đã được sử dụng.']);
    }

    if ($license['status'] === 'inactive') {
        jsonResponse(['success' => false, 'message' => 'Mã kích hoạt đã bị vô hiệu hóa.']);
    }

    // Activate the license
    $durationDays = (int)$license['duration_days'];
    $stmt = $db->prepare("UPDATE licenses SET user_id = ?, status = 'used', activated_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $durationDays, $license['id']]);

    $_SESSION['has_license'] = true;

    jsonResponse([
        'success' => true,
        'message' => "Kích hoạt thành công! License có hiệu lực trong {$durationDays} ngày.",
        'expires_at' => date('Y-m-d H:i:s', strtotime("+{$durationDays} days"))
    ]);
}

// CHECK LICENSE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'status') {
    $stmt = $db->prepare("SELECT l.license_key, l.status, l.activated_at, l.expires_at, l.device_limit, l.duration_days,
        (SELECT COUNT(*) FROM activated_devices ad WHERE ad.license_id = l.id AND ad.is_active = 1) as device_count
        FROM licenses l WHERE l.user_id = ? AND l.status = 'used' AND l.expires_at > NOW() ORDER BY l.expires_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $license = $stmt->fetch();

    if ($license) {
        jsonResponse(['success' => true, 'has_license' => true, 'license' => $license]);
    }
    jsonResponse(['success' => true, 'has_license' => false, 'message' => 'Chưa kích hoạt license.']);
}

// LIST LICENSES (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    requireAdmin();
    $stmt = $db->query("SELECT l.*, u.username FROM licenses l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC");
    jsonResponse(['success' => true, 'licenses' => $stmt->fetchAll()]);
}

// GENERATE LICENSES (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate') {
    requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true);
    $count = min((int)($input['count'] ?? 1), 100);
    $durationDays = (int)($input['duration_days'] ?? 30);
    $deviceLimit = (int)($input['device_limit'] ?? 1);

    $generated = [];
    for ($i = 0; $i < $count; $i++) {
        $key = 'PXFF-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $db->prepare("INSERT INTO licenses (license_key, duration_days, device_limit, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$key, $durationDays, $deviceLimit, $_SESSION['user_id']]);
        $generated[] = $key;
    }

    jsonResponse(['success' => true, 'message' => "Đã tạo {$count} license.", 'licenses' => $generated]);
}

// REVOKE LICENSE (ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'revoke') {
    requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true);
    $licenseId = (int)($input['license_id'] ?? 0);

    if ($licenseId <= 0) {
        jsonResponse(['success' => false, 'message' => 'ID license không hợp lệ.'], 400);
    }

    $stmt = $db->prepare("UPDATE licenses SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$licenseId]);
    jsonResponse(['success' => true, 'message' => 'Đã thu hồi license.']);
}

jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
