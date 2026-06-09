<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        $stmt = $db->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Set license status if user has active license
            $licenseStmt = $db->prepare("SELECT id FROM licenses WHERE user_id = ? AND status = 'used' AND expires_at > NOW() LIMIT 1");
            $licenseStmt->execute([$user['id']]);
            $license = $licenseStmt->fetch();
            $_SESSION['has_license'] = $license ? true : false;

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - ProxyFF</title>
    <style>
        :root {
            --bg: #0a0e17;
            --card-bg: #121824;
            --border: #2e3a52;
            --accent: #3b82f6;
            --text: #f3f4f6;
            --muted: #9ca3af;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 20px;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
            width: 100%; max-width: 420px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }
        h2 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle { text-align: center; color: var(--muted); margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: var(--muted); }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 12px 16px;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 10px; color: var(--text); font-size: 15px;
            transition: border 0.2s;
        }
        input:focus { outline: none; border-color: var(--accent); }
        .btn {
            width: 100%; padding: 14px;
            background: var(--accent); color: #fff;
            border: none; border-radius: 10px;
            font-size: 16px; font-weight: 700; cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover { background: #2563eb; }
        .error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--danger); padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .links { text-align: center; margin-top: 20px; font-size: 14px; color: var(--muted); }
        .links a { color: var(--accent); text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Đăng Nhập</h2>
        <p class="subtitle">ProxyFF - Hệ Thống Proxy Game</p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" placeholder="Nhập tên đăng nhập" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu" required>
            </div>
            <button type="submit" class="btn">Đăng Nhập</button>
        </form>
        <div class="links">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </div>
    </div>
</body>
</html>
