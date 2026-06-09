<?php
header('Content-Type: application/json');
require_once 'config.php';
requireLogin();

// Decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['client_ip']) || !isset($input['routing_target'])) {
    jsonResponse(['success' => false, 'message' => 'Dữ liệu yêu cầu không hợp lệ.'], 400);
}

$client_ip = trim($input['client_ip']);
$routing_target = trim($input['routing_target']);

// Validate routing targets (legacy + aimbot modes)
$allowed_targets = ['NORMAL', 'FLAG_A', 'FLAG_B', 'AIM_HEAD', 'AIM_NECK', 'AIM_BODY', 'AIM_LOCK', 'AIM_DRAG'];
if (!in_array($routing_target, $allowed_targets)) {
    jsonResponse(['success' => false, 'message' => 'Trạng thái định tuyến không hợp lệ.'], 400);
}

// Check license (skip for admin)
if (!isAdmin()) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id FROM licenses WHERE user_id = ? AND status = 'used' AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Bạn cần kích hoạt license để thay đổi chính sách.'], 403);
    }
}

try {
    $db = getDbConnection();

    // Perform Upsert with user_id
    $stmt = $db->prepare("
        INSERT INTO device_filters (client_ip, user_id, routing_target, is_active) 
        VALUES (:client_ip, :user_id, :routing_target, 1) 
        ON DUPLICATE KEY UPDATE routing_target = :routing_target_update, user_id = :user_id_update, is_active = 1, updated_at = NOW()
    ");

    $stmt->execute([
        ':client_ip' => $client_ip,
        ':user_id' => $_SESSION['user_id'],
        ':routing_target' => $routing_target,
        ':routing_target_update' => $routing_target,
        ':user_id_update' => $_SESSION['user_id']
    ]);

    jsonResponse([
        'success' => true,
        'message' => "Đã cập nhật chính sách cho thiết bị $client_ip thành $routing_target."
    ]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()], 500);
}
?>
