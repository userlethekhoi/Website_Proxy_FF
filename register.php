<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Tên đăng nhập phải từ 3-50 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } else {
        try {
            $db = getDbConnection();

            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Tên đăng nhập đã tồn tại.';
            } else {
                // Create user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email ?: null, $password_hash]);

                $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - ProxyFF</title>
    <style>
        :root {
            --bg: #0a0e17;
            --card-bg: #121824;
            --border: #2e3a52;
            --accent: #10b981;
            --accent-hover: #059669;
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
            background: linear-gradient(135deg, #34d399, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle { text-align: center; color: var(--muted); margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: var(--muted); }
        input[type="text"], input[type="password"], input[type="email"] {
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
        .btn:hover { background: var(--accent-hover); }
        .error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--danger); padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--accent); padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .links { text-align: center; margin-top: 20px; font-size: 14px; color: var(--muted); }
        .links a { color: var(--accent); text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .optional { color: var(--muted); font-weight: 400; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Đăng Ký</h2>
        <p class="subtitle">Tạo tài khoản ProxyFF miễn phí</p>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Tên đăng nhập *</label>
                <input type="text" name="username" placeholder="Nhập tên đăng nhập" required>
            </div>
            <div class="form-group">
                <label>Email <span class="optional">(tùy chọn)</span></label>
                <input type="email" name="email" placeholder="example@gmail.com">
            </div>
            <div class="form-group">
                <label>Mật khẩu *</label>
                <input type="password" name="password" placeholder="Ít nhất 6 ký tự" required>
            </div>
            <div class="form-group">
                <label>Xác nhận mật khẩu *</label>
                <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
            </div>
            <button type="submit" class="btn">Đăng Ký</button>
        </form>
        <div class="links">
            Đã có tài khoản? <a href="login.php">Đăng nhập</a>
        </div>
    </div>
</body>
</html>
