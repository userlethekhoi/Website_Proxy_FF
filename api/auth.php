<?php
/**
 * API: Authentication endpoints
 */
require_once '../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin.'], 400);
    }

    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        jsonResponse(['success' => true, 'message' => 'Đăng nhập thành công.', 'user' => ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]]);
    }
    jsonResponse(['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'check') {
    if (isLoggedIn()) {
        jsonResponse(['success' => true, 'logged_in' => true, 'user' => ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'role' => $_SESSION['role']]]);
    }
    jsonResponse(['success' => true, 'logged_in' => false]);
}

jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
