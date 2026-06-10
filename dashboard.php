<?php
require_once 'config.php';
requireLogin();

$db = getDbConnection();
$client_ip = getClientIP();

// Check license status
$licenseStmt = $db->prepare("SELECT l.*, (SELECT COUNT(*) FROM activated_devices ad WHERE ad.license_id = l.id AND ad.is_active = 1) as device_count FROM licenses l WHERE l.user_id = ? AND l.status = 'used' AND l.expires_at > NOW() ORDER BY l.expires_at DESC LIMIT 1");
$licenseStmt->execute([$_SESSION['user_id']]);
$activeLicense = $licenseStmt->fetch();
$hasLicense = $activeLicense ? true : false;

// Fetch current policy
$current_policy = 'NORMAL';
if ($hasLicense) {
    $stmt = $db->prepare("SELECT routing_target FROM device_filters WHERE client_ip = ? AND is_active = 1");
    $stmt->execute([$client_ip]);
    $row = $stmt->fetch();
    if ($row) {
        $current_policy = $row['routing_target'];
    } else {
        $stmt_insert = $db->prepare("INSERT INTO device_filters (client_ip, user_id, routing_target) VALUES (?, ?, 'NORMAL')");
        $stmt_insert->execute([$client_ip, $_SESSION['user_id']]);
    }
}

// Fetch all devices (for monitoring - admin only or own devices for users)
if (isAdmin()) {
    $allDevicesStmt = $db->query("SELECT df.*, u.username FROM device_filters df LEFT JOIN users u ON df.user_id = u.id ORDER BY df.updated_at DESC");
} else {
    $allDevicesStmt = $db->prepare("SELECT df.*, u.username FROM device_filters df LEFT JOIN users u ON df.user_id = u.id WHERE df.user_id = ? ORDER BY df.updated_at DESC");
    $allDevicesStmt->execute([$_SESSION['user_id']]);
}
$allDevices = $allDevicesStmt->fetchAll();

$siteTitle = getSetting('site_title', 'Proxy Free Fire');
?>
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Bảng Điều Khiển — <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --accent:        #cbd5e1;
            --accent2:       #ffffff;
            --success:       #10b981;
            --warn:          #f59e0b;
            --danger:        #ef4444;
            --bg:            #090a10;
            --bg2:           #0e1017;
            --surface:       rgba(255, 255, 255, 0.025);
            --surface2:      rgba(255, 255, 255, 0.05);
            --surface3:      rgba(255, 255, 255, 0.01);
            --border:        rgba(255, 255, 255, 0.06);
            --text:          #f8fafc;
            --text2:         #94a3b8;
            --text3:         #475569;
            --shadow:        0 20px 40px -15px rgba(0, 0, 0, 0.7);
            --shadow-sm:     0 4px 20px -5px rgba(0, 0, 0, 0.4);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; font-size: 16px; }
        body {
            font-family: 'Plus Jakarta Sans', 'Be Vietnam Pro', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center;
            background-image: radial-gradient(circle at 50% -10%, rgba(99, 102, 241, 0.08) 0%, transparent 50%);
        }
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; cursor: pointer; border: none; background: none; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg2); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

        /* Cursor Glow Follower */
        #cursor-glow {
            position: fixed;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.035) 0%, transparent 70%);
            pointer-events: none;
            transform: translate(-50%, -50%);
            z-index: 0;
            top: -9999px; left: -9999px;
            transition: opacity 0.5s ease;
            opacity: 0;
        }
        body:hover #cursor-glow { opacity: 1; }

        /* Staggered load animations */
        @keyframes revealUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .reveal {
            opacity: 0;
            animation: revealUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .reveal-1 { animation-delay: 0.05s; }
        .reveal-2 { animation-delay: 0.12s; }
        .reveal-3 { animation-delay: 0.18s; }
        .reveal-4 { animation-delay: 0.24s; }
        .reveal-5 { animation-delay: 0.3s; }
        .reveal-6 { animation-delay: 0.36s; }
        .reveal-7 { animation-delay: 0.42s; }
        .reveal-8 { animation-delay: 0.48s; }

        /* Top Bar */
        .top-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px;
            background: rgba(9, 10, 16, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .top-bar-brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 700; font-size: 15px; color: var(--text);
            letter-spacing: -0.2px;
        }

        /* Profile Container */
        .profile-container {
            max-width: 520px; width: 100%; text-align: center;
            padding: 110px 20px 40px;
            position: relative;
            z-index: 2;
        }

        .avatar-wrap { position: relative; width: 100px; margin: 0 auto 24px; }
        .avatar {
            width: 100px; height: 100px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex; align-items: center; justify-content: center;
            position: relative; z-index: 1;
            margin: 0 auto;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .avatar:hover { transform: translateY(-2px); border-color: rgba(255, 255, 255, 0.25); }

        .title {
            font-size: 28px; font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
            color: #fff;
        }
        .subtitle {
            font-size: 13px; color: var(--text2);
            font-weight: 500;
            margin-bottom: 24px;
        }

        .user-info {
            display: flex; justify-content: center; gap: 8px;
            flex-wrap: wrap; margin-bottom: 28px;
        }
        .user-pill {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 13px; font-weight: 600;
            color: var(--text2);
            background: var(--surface);
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }
        .user-pill:hover { background: var(--surface2); color: var(--text); border-color: rgba(255, 255, 255, 0.15); }

        .section-label {
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.15em; text-transform: uppercase;
            color: var(--text3);
            margin: 32px 0 12px;
            display: flex; align-items: center;
            width: 100%;
            text-align: left;
        }

        /* Card System */
        .card {
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }
        .card-title {
            font-size: 14px; font-weight: 700; color: #fff;
            margin-bottom: 16px;
        }

        /* Link Cards / Interactive Option Cards */
        .link-card {
            position: relative;
            display: flex; align-items: center; gap: 16px;
            padding: 16px 20px;
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 16px;
            text-decoration: none; margin-bottom: 12px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }
        .link-card:hover {
            transform: translateY(-2px);
            background: var(--surface2);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: var(--shadow);
        }
        .link-card:active { transform: translateY(0); }
        .link-card.proxy-featured {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0.015) 100%);
            border-color: rgba(255, 255, 255, 0.09);
        }
        .link-card.proxy-featured:hover { border-color: rgba(255, 255, 255, 0.25); }

        .card-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; justify-content: center; align-items: center;
            font-size: 20px; color: var(--accent);
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            flex-shrink: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .link-card:hover .card-icon {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.18);
        }

        .card-body { flex: 1; text-align: left; }
        .card-title { color: var(--text); font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .card-desc { color: var(--text2); font-size: 12px; font-weight: 500; line-height: 1.4; }
        .card-arrow { color: var(--text3); font-size: 16px; transition: transform 0.2s ease; }
        .link-card:hover .card-arrow { transform: translateX(2px); color: var(--text2); }

        /* policy selections indicators */
        .policy-indicator {
            width: 18px; height: 18px; border-radius: 50%;
            border: 2px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            transition: all 0.25s ease;
            position: relative;
        }
        .policy-option.selected {
            border-color: rgba(255, 255, 255, 0.25);
            background: var(--surface2);
        }
        .policy-option.selected .policy-indicator {
            border-color: #fff;
            background: #fff;
        }
        .policy-option.selected .policy-indicator::after {
            content: '';
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--bg);
            position: absolute;
        }

        /* Setup guide code blocks */
        code {
            font-family: monospace;
            background: rgba(255, 255, 255, 0.05);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--accent);
        }

        /* Dynamic Table design */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .device-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }
        .device-table th {
            padding: 12px 8px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .device-table td {
            padding: 14px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            color: var(--text2);
            font-weight: 500;
        }
        .device-table tr:last-child td { border-bottom: none; }

        /* Custom Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 700;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .pill-NORMAL { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: var(--text2); }
        .pill-AIM_HEAD { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.15); color: #ef4444; }
        .pill-AIM_NECK { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.15); color: #f59e0b; }
        .pill-AIM_BODY { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.15); color: #10b981; }
        .pill-AIM_LOCK { background: rgba(236,72,153,0.08); border: 1px solid rgba(236,72,153,0.15); color: #ec4899; }
        .pill-AIM_DRAG { background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.15); color: #a78bfa; }

        /* Disabled overlay class for license lock */
        .disabled-overlay {
            position: relative;
            pointer-events: none;
            opacity: 0.35;
        }
        .disabled-overlay::after {
            content: "KÍCH HOẠT LICENSE ĐỂ CẤU HÌNH";
            position: absolute;
            inset: -4px;
            background: rgba(9, 10, 16, 0.82);
            color: var(--warn);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            border-radius: 20px;
            border: 1px dashed var(--border);
            z-index: 10;
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 24px; left: 50%; transform: translateX(-50%);
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600; font-size: 13px;
            display: none;
            z-index: 8000;
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow);
            max-width: 90vw;
            text-align: center;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .toast.success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981; }
        .toast.error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; }
        @keyframes slideUp {
            from { transform: translateX(-50%) translateY(30px); opacity: 0; }
            to { transform: translateX(-50%) translateY(0); opacity: 1; }
        }

        /* Terms Modal Button styling */
        .terms-btn {
            padding: 12px 16px; border-radius: 12px;
            font-size: 13px; font-weight: 600; text-align: center; cursor: pointer;
            transition: all 0.2s ease;
        }
        .terms-btn.btn-accept { background: #fff; color: #000; }
        .terms-btn.btn-accept:hover { background: rgba(255,255,255,0.9); transform: translateY(-1px); }

        /* Hide elements on mobile */
        @media (max-width: 500px) {
            .d-none-mobile { display: none !important; }
            .title { font-size: 24px; }
            .top-bar { padding: 12px 16px; }
        }
    </style>
</head>
<body>
    <div id="cursor-glow"></div>

    <div class="top-bar">
        <a href="index.php" class="top-bar-brand">
            <img src="logo.png" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.15);">
            <?= htmlspecialchars($siteTitle) ?> — Dashboard
        </a>
        <div class="nav-links" style="display: flex; gap: 8px; align-items: center;">
            <a href="index.php" class="user-pill" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-house-door-fill"></i> <span class="d-none-mobile">Trang Chủ</span></a>
            <?php if (isAdmin()): ?>
                <a href="admin.php" class="user-pill" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-gear-fill"></i> <span class="d-none-mobile">Admin</span></a>
            <?php endif; ?>
            <a href="logout.php" class="user-pill" style="padding: 6px 12px; font-size: 12px; border-color: rgba(239, 68, 68, 0.2); color: #ef4444;"><i class="bi bi-box-arrow-right"></i> <span class="d-none-mobile">Thoát</span></a>
        </div>
    </div>

    <div class="profile-container">
        <div class="avatar-wrap reveal reveal-1">
            <div class="avatar"><img src="logo.png" style="width:100%; height:100%; border-radius:50%; object-fit:cover;"></div>
        </div>

        <h1 class="title reveal reveal-2">Bảng Điều Khiển</h1>
        <p class="subtitle reveal reveal-3">UDP Real-Time Middleware — Giám sát & cấu hình</p>

        <div class="user-info reveal reveal-4">
            <span class="user-pill">👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
            <span class="user-pill"><?= isAdmin() ? '🛡️ Admin' : '👤 Thành viên' ?></span>
            <span class="user-pill">🌐 IP: <?= htmlspecialchars($client_ip) ?></span>
        </div>

        <!-- LICENSE SECTION -->
        <div class="reveal reveal-5">
            <?php if ($hasLicense): ?>
                <div class="link-card proxy-featured" style="cursor: default; margin-bottom: 24px;">
                    <div class="card-icon" style="color: var(--success); border-color: rgba(16, 185, 129, 0.15); background: rgba(16, 185, 129, 0.03);"><i class="bi bi-shield-check"></i></div>
                    <div class="card-body">
                        <div class="card-title" style="color: var(--success); margin-bottom: 4px;">LICENSE ĐANG HOẠT ĐỘNG</div>
                        <div class="card-desc" style="font-size: 12px; font-weight: 600;">⏳ Hết hạn: <?= htmlspecialchars($activeLicense['expires_at']) ?></div>
                        <div class="card-desc" style="font-size: 11px; margin-top: 4px; color: var(--text3);">📱 Thiết bị: <?= $activeLicense['device_count'] ?> / <?= $activeLicense['device_limit'] ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card" style="margin-bottom: 24px; text-align: left; border-color: rgba(239, 68, 68, 0.2); background: rgba(239, 68, 68, 0.01);">
                    <div class="card-title" style="color: var(--danger); font-size: 15px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; border-bottom: none; padding-bottom: 0;">
                        <i class="bi bi-exclamation-triangle-fill"></i> Chưa Kích Hoạt License
                    </div>
                    <p style="font-size: 13px; color: var(--text2); line-height: 1.6; margin-bottom: 16px;">
                        Vui lòng nhập mã kích hoạt để sử dụng cấu hình proxy và aimbot.
                    </p>
                    <div class="activate-form" style="display: flex; gap: 8px; width: 100%;">
                        <input type="text" id="license-input" placeholder="Mã kích hoạt (VD: PXFF-XXXXXXXX-XXXXXXXX)" style="flex: 1; padding: 12px 14px; background: rgba(0, 0, 0, 0.2); border: 1px solid var(--border); border-radius: 12px; color: #fff; font-family: monospace; font-size: 13px;">
                        <button class="terms-btn btn-accept" style="flex: 0 0 auto; width: auto; padding: 12px 18px;" onclick="activateLicense()">Kích hoạt</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- IPHONE SETUP GUIDE -->
        <div class="reveal reveal-6">
            <div class="section-label">Hướng Dẫn Thiết Lập</div>
            <div class="card" style="text-align: left; margin-bottom: 24px;">
                <div class="card-title" onclick="toggleSetup()" style="font-size: 15px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">
                    <span style="display: flex; align-items: center; gap: 8px;"><i class="bi bi-phone"></i> Cài đặt trên iPhone</span>
                    <i id="setup-chevron" class="bi bi-chevron-down" style="font-size: 14px; color: var(--text3); transition: transform 0.3s ease;"></i>
                </div>
                <div id="setup-guide" style="max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.16, 1, 0.3, 1); margin-top: 0;">
                    <div style="padding-top: 16px; display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <h4 style="font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                                <span style="width: 18px; height: 18px; border-radius: 50%; background: rgba(255,255,255,0.06); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 10px;">1</span>
                                CẤU HÌNH PROXY WIFI
                            </h4>
                            <ol style="font-size: 12px; color: var(--text2); line-height: 1.8; padding-left: 20px;">
                                <li>Vào Cài đặt (Settings) → Wi-Fi → Chọn biểu tượng chữ ⓘ bên cạnh Wi-Fi đang dùng</li>
                                <li>Cuộn xuống chọn Định cấu hình Proxy (Configure Proxy) → Chọn <strong>Thủ công (Manual)</strong></li>
                                <li>Nhập <strong>Máy chủ (Server)</strong>: <code style="color: var(--accent);">103.129.127.244</code></li>
                                <li>Nhập <strong>Cổng (Port)</strong>: <code>8082</code></li>
                                <li>Nhấn <strong>Lưu (Save)</strong> ở góc trên bên phải</li>
                            </ol>
                        </div>
                        <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                            <h4 style="font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                                <span style="width: 18px; height: 18px; border-radius: 50%; background: rgba(255,255,255,0.06); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 10px;">2</span>
                                TẢI & TIN CẬY CHỨNG CHỈ
                            </h4>
                            <ol style="font-size: 12px; color: var(--text2); line-height: 1.8; padding-left: 20px; margin-bottom: 12px;">
                                <li>Dùng Safari truy cập: <a href="/api/ca-cert.pem" style="color: #fff; text-decoration: underline;">Tải Chứng Chỉ CA</a></li>
                                <li>Vào Cài đặt (Settings) → Nhấn vào <strong>Đã tải về hồ sơ (Profile Downloaded)</strong> ở đầu màn hình → Chọn Cài đặt</li>
                                <li>Cài đặt chung (General) → Giới thiệu (About) → Cài đặt tin cậy chứng chỉ (Certificate Trust Settings) ở dưới cùng</li>
                                <li>Tìm mục <strong>mitmproxy</strong> và gạt công tắc màu xanh để kích hoạt tin cậy ✅</li>
                            </ol>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="/api/ca-cert.pem" class="user-pill" style="font-size: 11px; padding: 6px 12px;"><i class="bi bi-file-earmark-lock-fill"></i> CA Certificate</a>
                            </div>
                            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 6px;">
                                <span style="font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase;">Tải cấu hình Proxy tự động (.mobileconfig):</span>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;">
                                    <a href="api/mobileconfig.php?port=8082" class="user-pill" style="font-size: 10px; padding: 6px 8px; justify-content: center; background: rgba(255,255,255,0.02);"><i class="bi bi-download"></i> Cổng 8082 (Normal)</a>
                                    <a href="api/mobileconfig.php?port=8083" class="user-pill" style="font-size: 10px; padding: 6px 8px; justify-content: center; background: rgba(239,68,68,0.04); color: #ef4444; border-color: rgba(239,68,68,0.15);"><i class="bi bi-download"></i> Cổng 8083 (Head)</a>
                                    <a href="api/mobileconfig.php?port=8084" class="user-pill" style="font-size: 10px; padding: 6px 8px; justify-content: center; background: rgba(245,158,11,0.04); color: #f59e0b; border-color: rgba(245,158,11,0.15);"><i class="bi bi-download"></i> Cổng 8084 (Neck)</a>
                                    <a href="api/mobileconfig.php?port=8085" class="user-pill" style="font-size: 10px; padding: 6px 8px; justify-content: center; background: rgba(16,185,129,0.04); color: #10b981; border-color: rgba(16,185,129,0.15);"><i class="bi bi-download"></i> Cổng 8085 (Body)</a>
                                    <a href="api/mobileconfig.php?port=8086" class="user-pill" style="font-size: 10px; padding: 6px 8px; justify-content: center; background: rgba(236,72,153,0.04); color: #ec4899; border-color: rgba(236,72,153,0.15);"><i class="bi bi-download"></i> Cổng 8086 (Lock)</a>
                                    <a href="api/mobileconfig.php?port=8087" class="user-pill" style="font-size: 10px; padding: 6px 8px; justify-content: center; background: rgba(139,92,246,0.04); color: #a78bfa; border-color: rgba(139,92,246,0.15);"><i class="bi bi-download"></i> Cổng 8087 (Drag)</a>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 12px; background: rgba(245, 158, 11, 0.02); border: 1px solid rgba(245, 158, 11, 0.08); border-radius: 12px; font-size: 11px; color: var(--warn); line-height: 1.6;">
                            ⚠️ <strong>Chú ý:</strong> Đảm bảo bạn bật Certificate Trust Settings để tránh gặp lỗi bảo mật SSL khi duyệt game. Cổng proxy sẽ tương ứng với chức năng aimbot bạn muốn sử dụng.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- POLICY SELECTION -->
        <div class="reveal reveal-7">
            <div class="section-label">Cấu Hình Aimbot</div>
            <div class="policy-options-container <?= !$hasLicense ? 'disabled-overlay' : '' ?>" style="margin-bottom: 24px;">
                <div class="policy-options" style="display: flex; flex-direction: column; gap: 10px;">
                    <label class="link-card policy-option <?= $current_policy === 'NORMAL' ? 'selected' : '' ?>" style="margin-bottom: 0;">
                        <input type="radio" name="policy" value="NORMAL" <?= $current_policy === 'NORMAL' ? 'checked' : '' ?> style="display: none;">
                        <div class="card-icon" style="color: var(--text3); border-color: rgba(255,255,255,0.04);"><i class="bi bi-x-circle-fill"></i></div>
                        <div class="card-body">
                            <div class="card-title" style="font-size: 13px; font-weight: 700; color: var(--text3); display: flex; justify-content: space-between; align-items: center;">
                                <span>NORMAL</span>
                                <span style="font-size: 10px; opacity: 0.8; font-weight: 500; background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 4px;">Cổng 8082</span>
                            </div>
                            <div class="card-desc">Tắt aimbot, chuyển tiếp gói tin nguyên bản.</div>
                        </div>
                        <div class="policy-indicator"></div>
                    </label>
                    <label class="link-card policy-option <?= $current_policy === 'AIM_HEAD' ? 'selected' : '' ?>" style="margin-bottom: 0;">
                        <input type="radio" name="policy" value="AIM_HEAD" <?= $current_policy === 'AIM_HEAD' ? 'checked' : '' ?> style="display: none;">
                        <div class="card-icon" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.12); background: rgba(239, 68, 68, 0.02);"><i class="bi bi-crosshair"></i></div>
                        <div class="card-body">
                            <div class="card-title" style="font-size: 13px; font-weight: 700; color: #ef4444; display: flex; justify-content: space-between; align-items: center;">
                                <span>🎯 AIM HEAD</span>
                                <span style="font-size: 10px; opacity: 0.8; font-weight: 500; background: rgba(239,68,68,0.15); padding: 2px 6px; border-radius: 4px;">Cổng 8083</span>
                            </div>
                            <div class="card-desc">Tự động ngắm vào đầu đối thủ. Tỉ lệ headshot cao nhất.</div>
                        </div>
                        <div class="policy-indicator"></div>
                    </label>
                    <label class="link-card policy-option <?= $current_policy === 'AIM_NECK' ? 'selected' : '' ?>" style="margin-bottom: 0;">
                        <input type="radio" name="policy" value="AIM_NECK" <?= $current_policy === 'AIM_NECK' ? 'checked' : '' ?> style="display: none;">
                        <div class="card-icon" style="color: #f59e0b; border-color: rgba(245, 158, 11, 0.12); background: rgba(245, 158, 11, 0.02);"><i class="bi bi-shield-fill"></i></div>
                        <div class="card-body">
                            <div class="card-title" style="font-size: 13px; font-weight: 700; color: #f59e0b; display: flex; justify-content: space-between; align-items: center;">
                                <span>🔫 AIM NECK</span>
                                <span style="font-size: 10px; opacity: 0.8; font-weight: 500; background: rgba(245,158,11,0.15); padding: 2px 6px; border-radius: 4px;">Cổng 8084</span>
                            </div>
                            <div class="card-desc">Ngắm vào cổ - cân bằng giữa sát thương và độ chính xác.</div>
                        </div>
                        <div class="policy-indicator"></div>
                    </label>
                    <label class="link-card policy-option <?= $current_policy === 'AIM_BODY' ? 'selected' : '' ?>" style="margin-bottom: 0;">
                        <input type="radio" name="policy" value="AIM_BODY" <?= $current_policy === 'AIM_BODY' ? 'checked' : '' ?> style="display: none;">
                        <div class="card-icon" style="color: #10b981; border-color: rgba(16, 185, 129, 0.12); background: rgba(16, 185, 129, 0.02);"><i class="bi bi-shield-fill-check"></i></div>
                        <div class="card-body">
                            <div class="card-title" style="font-size: 13px; font-weight: 700; color: #10b981; display: flex; justify-content: space-between; align-items: center;">
                                <span>🛡️ AIM BODY</span>
                                <span style="font-size: 10px; opacity: 0.8; font-weight: 500; background: rgba(16,185,129,0.15); padding: 2px 6px; border-radius: 4px;">Cổng 8085</span>
                            </div>
                            <div class="card-desc">Ngắm vào thân - dễ trúng nhất, ít bị report.</div>
                        </div>
                        <div class="policy-indicator"></div>
                    </label>
                    <label class="link-card policy-option <?= $current_policy === 'AIM_LOCK' ? 'selected' : '' ?>" style="margin-bottom: 0;">
                        <input type="radio" name="policy" value="AIM_LOCK" <?= $current_policy === 'AIM_LOCK' ? 'checked' : '' ?> style="display: none;">
                        <div class="card-icon" style="color: #ec4899; border-color: rgba(236, 72, 153, 0.12); background: rgba(236, 72, 153, 0.02);"><i class="bi bi-lock-fill"></i></div>
                        <div class="card-body">
                            <div class="card-title" style="font-size: 13px; font-weight: 700; color: #ec4899; display: flex; justify-content: space-between; align-items: center;">
                                <span>🔒 AIM LOCK</span>
                                <span style="font-size: 10px; opacity: 0.8; font-weight: 500; background: rgba(236,72,153,0.15); padding: 2px 6px; border-radius: 4px;">Cổng 8086</span>
                            </div>
                            <div class="card-desc">Khóa mục tiêu cứng - không giật, không tản đạn.</div>
                        </div>
                        <div class="policy-indicator"></div>
                    </label>
                    <label class="link-card policy-option <?= $current_policy === 'AIM_DRAG' ? 'selected' : '' ?>" style="margin-bottom: 0;">
                        <input type="radio" name="policy" value="AIM_DRAG" <?= $current_policy === 'AIM_DRAG' ? 'checked' : '' ?> style="display: none;">
                        <div class="card-icon" style="color: #8b5cf6; border-color: rgba(139, 92, 246, 0.12); background: rgba(139, 92, 246, 0.02);"><i class="bi bi-mouse3-fill"></i></div>
                        <div class="card-body">
                            <div class="card-title" style="font-size: 13px; font-weight: 700; color: #8b5cf6; display: flex; justify-content: space-between; align-items: center;">
                                <span>🖱️ AIM DRAG</span>
                                <span style="font-size: 10px; opacity: 0.8; font-weight: 500; background: rgba(139,92,246,0.15); padding: 2px 6px; border-radius: 4px;">Cổng 8087</span>
                            </div>
                            <div class="card-desc">Kéo tâm mượt về phía đối thủ - cực kì tự nhiên.</div>
                        </div>
                        <div class="policy-indicator"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- DEVICE MONITORING -->
        <div class="reveal reveal-8" style="width: 100%;">
            <div class="section-label">Thiết Bị Đang Hoạt Động</div>
            <div class="card" style="padding: 16px; margin-bottom: 24px;">
                <div class="table-responsive">
                    <table class="device-table">
                        <thead>
                            <tr>
                                <th>Địa Chỉ IP</th>
                                <?php if (isAdmin()): ?><th>Người dùng</th><?php endif; ?>
                                <th>Mục Tiêu</th>
                                <th>Cập nhật</th>
                            </tr>
                        </thead>
                        <tbody id="device-list">
                            <?php foreach ($allDevices as $device): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: #fff;"><?= htmlspecialchars($device['client_ip']) ?></td>
                                <?php if (isAdmin()): ?><td style="color: #60a5fa; font-weight: 600;"><?= htmlspecialchars($device['username'] ?? '-') ?></td><?php endif; ?>
                                <td>
                                    <?php
                                    $tgt = $device['routing_target'];
                                    $pillClass = '';
                                    if ($tgt === 'NORMAL') $pillClass = 'pill-NORMAL';
                                    elseif ($tgt === 'AIM_HEAD') $pillClass = 'pill-AIM_HEAD';
                                    elseif ($tgt === 'AIM_NECK') $pillClass = 'pill-AIM_NECK';
                                    elseif ($tgt === 'AIM_BODY') $pillClass = 'pill-AIM_BODY';
                                    elseif ($tgt === 'AIM_LOCK') $pillClass = 'pill-AIM_LOCK';
                                    elseif ($tgt === 'AIM_DRAG') $pillClass = 'pill-AIM_DRAG';
                                    ?>
                                    <span class="status-pill <?= $pillClass ?>"><?= htmlspecialchars($tgt) ?></span>
                                </td>
                                <td style="font-size: 11px; color: var(--text3);"><?= htmlspecialchars($device['updated_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allDevices)): ?>
                            <tr>
                                <td colspan="<?= isAdmin() ? 4 : 3 ?>" style="text-align: center; color: var(--text3); padding: 24px 0;">Chưa có thiết bị nào kết nối</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        const clientIp = <?= json_encode($client_ip) ?>;

        document.addEventListener("DOMContentLoaded", () => {
            // Cursor glow follower
            const glow = document.getElementById("cursor-glow");
            document.addEventListener("mousemove", (e) => {
                glow.style.left = e.clientX + "px";
                glow.style.top = e.clientY + "px";
            });
        });

        // Toggle setup guide
        function toggleSetup() {
            const guide = document.getElementById('setup-guide');
            const chevron = document.getElementById('setup-chevron');
            if (guide.style.maxHeight === '0px' || !guide.style.maxHeight) {
                guide.style.maxHeight = guide.scrollHeight + 'px';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                guide.style.maxHeight = '0px';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        // Policy selection
        document.querySelectorAll('.policy-option').forEach(option => {
            option.addEventListener('click', () => {
                if (option.parentElement.parentElement.classList.contains('disabled-overlay')) return;
                document.querySelectorAll('.policy-option').forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                const radio = option.querySelector('input[type="radio"]');
                radio.checked = true;
                updatePolicy(radio.value);
            });
        });

        async function updatePolicy(policyValue) {
            try {
                const res = await fetch('update_policy.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ client_ip: clientIp, routing_target: policyValue })
                });
                const data = await res.json();
                showToast(data.message || 'Cập nhật thành công!', data.success);
                refreshDeviceTable();
            } catch (e) {
                showToast('Không thể kết nối đến máy chủ.', false);
            }
        }

        async function activateLicense() {
            const key = document.getElementById('license-input').value.trim();
            if (!key) { showToast('Vui lòng nhập mã license.', false); return; }
            try {
                const res = await fetch('api/license.php?action=activate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ license_key: key })
                });
                const data = await res.json();
                showToast(data.message, data.success);
                if (data.success) setTimeout(() => location.reload(), 1500);
            } catch (e) {
                showToast('Lỗi kết nối.', false);
            }
        }

        function showToast(message, success = true) {
            const toast = document.getElementById('toast');
            toast.innerText = message;
            toast.className = 'toast ' + (success ? 'success' : 'error');
            toast.style.display = 'block';
            clearTimeout(toast._t);
            toast._t = setTimeout(() => toast.style.display = 'none', 3000);
        }

        async function refreshDeviceTable() {
            try {
                const res = await fetch(window.location.href);
                const text = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(text, 'text/html');
                const newBody = doc.getElementById('device-list');
                if (newBody) document.getElementById('device-list').innerHTML = newBody.innerHTML;
            } catch (e) { console.error(e); }
        }

        setInterval(refreshDeviceTable, 5000);

        // Enter key for license activation
        document.getElementById('license-input')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') activateLicense();
        });
    </script>
</body>
</html>
