<?php
/**
 * API: Admin management
 * GET  /api/admin.php?action=settings       - Get all settings
 * POST /api/admin.php?action=settings       - Update settings
 * GET  /api/admin.php?action=users          - List all users
 * POST /api/admin.php?action=toggle_user    - Enable/disable user
 * GET  /api/admin.php?action=devices        - List all active devices
 */
require_once '../config.php';
requireAdmin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$db = getDbConnection();

// GET SETTINGS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'settings') {
    $stmt = $db->query("SELECT setting_key, setting_value, setting_label FROM system_settings ORDER BY id");
    jsonResponse(['success' => true, 'settings' => $stmt->fetchAll()]);
}

// UPDATE SETTINGS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'settings') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['success' => false, 'message' => 'Dữ liệu không hợp lệ.'], 400);
    }

    foreach ($input as $key => $value) {
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }

    jsonResponse(['success' => true, 'message' => 'Đã cập nhật cài đặt.']);
}

// LIST USERS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'users') {
    $stmt = $db->query("SELECT u.id, u.username, u.email, u.role, u.is_active, u.created_at,
        (SELECT COUNT(*) FROM licenses l WHERE l.user_id = u.id AND l.status = 'used') as license_count
        FROM users u ORDER BY u.created_at DESC");
    jsonResponse(['success' => true, 'users' => $stmt->fetchAll()]);
}

// TOGGLE USER STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_user') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = (int)($input['user_id'] ?? 0);
    $isActive = (int)($input['is_active'] ?? 0);

    if ($userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'ID người dùng không hợp lệ.'], 400);
    }

    // Prevent self-deactivation
    if ($userId == $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Không thể thay đổi trạng thái của chính mình.'], 400);
    }

    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$isActive, $userId]);
    jsonResponse(['success' => true, 'message' => 'Đã cập nhật trạng thái người dùng.']);
}

// LIST DEVICES
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'devices') {
    $stmt = $db->query("SELECT df.*, u.username FROM device_filters df LEFT JOIN users u ON df.user_id = u.id WHERE df.is_active = 1 ORDER BY df.updated_at DESC");
    jsonResponse(['success' => true, 'devices' => $stmt->fetchAll()]);
}

jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
