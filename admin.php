<?php
require_once 'config.php';
requireAdmin();

$db = getDbConnection();
$tab = $_GET['tab'] ?? 'licenses';

// Fetch data
$licenses = $db->query("SELECT l.*, u.username FROM licenses l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC")->fetchAll();
$users = $db->query("SELECT u.*, (SELECT COUNT(*) FROM licenses l WHERE l.user_id = u.id AND l.status = 'used') as license_count FROM users u ORDER BY u.created_at DESC")->fetchAll();
$settings = $db->query("SELECT * FROM system_settings ORDER BY id")->fetchAll();
$devices = $db->query("SELECT df.*, u.username FROM device_filters df LEFT JOIN users u ON df.user_id = u.id ORDER BY df.updated_at DESC")->fetchAll();

$siteTitle = getSetting('site_title', 'Proxy Free Fire');

// Calculate statistics
$totalUsers = count($users);
$totalDevices = count($devices);
$totalLicenses = count($licenses);

$activeLicensesCount = 0;
foreach ($licenses as $l) {
    if ($l['status'] === 'used' && strtotime($l['expires_at']) > time()) {
        $activeLicensesCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Admin Panel — <?= htmlspecialchars($siteTitle) ?></title>
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

        .container {
            max-width: 820px; width: 100%;
            padding: 110px 20px 40px;
            position: relative;
            z-index: 2;
            text-align: center;
        }

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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
            width: 100%;
        }
        @media (min-width: 600px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
        }

        /* Tab Switcher */
        .tab-switcher {
            display: flex; gap: 4px; margin-bottom: 24px;
            border-bottom: 1px solid var(--border); padding-bottom: 12px;
            overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;
        }
        .tab-btn {
            padding: 10px 18px; border-radius: 12px;
            font-size: 13px; font-weight: 600; color: var(--text2);
            border: 1px solid transparent; transition: all 0.2s ease;
            white-space: nowrap; background: none; cursor: pointer;
        }
        .tab-btn:hover { color: var(--text); background: var(--surface); }
        .tab-btn.active { color: #fff; background: var(--surface2); border-color: var(--border); }

        /* Card System */
        .card {
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        /* Form Controls */
        .form-row {
            display: grid; grid-template-columns: 1fr; gap: 16px;
            margin-bottom: 20px;
        }
        @media (min-width: 600px) {
            .form-row { grid-template-columns: repeat(3, 1fr); }
        }
        .form-group {
            display: flex; flex-direction: column; gap: 6px;
            text-align: left;
        }
        .form-group label {
            font-size: 11px; font-weight: 700; color: var(--text3);
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .form-group input, .form-group select, .form-group textarea {
            padding: 12px 14px; background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border); border-radius: 12px;
            color: #fff; font-size: 13px; transition: border-color 0.2s;
            width: 100%;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: rgba(255, 255, 255, 0.2);
        }

        /* Search input bar */
        .search-bar {
            width: 100%; padding: 12px 16px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border); border-radius: 12px;
            color: #fff; font-size: 13px; margin-bottom: 16px;
        }
        .search-bar:focus { outline: none; border-color: rgba(255, 255, 255, 0.15); }

        /* Buttons styling */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 12px 20px; border-radius: 12px; font-size: 13px; font-weight: 600;
            transition: all 0.2s ease; cursor: pointer; border: none; text-align: center;
            gap: 8px;
        }
        .btn-primary { background: #fff; color: #000; }
        .btn-primary:hover { background: rgba(255,255,255,0.9); transform: translateY(-1px); }
        .btn-sm { padding: 6px 12px; font-size: 11px; border-radius: 8px; }
        .btn-danger { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.15); color: #ef4444; }
        .btn-danger:hover { background: rgba(239,68,68,0.15); transform: translateY(-1px); }
        .btn-success { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.15); color: var(--success); }
        .btn-success:hover { background: rgba(16,185,129,0.15); transform: translateY(-1px); }
        .btn-warning { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.15); color: #f59e0b; }
        .btn-warning:hover { background: rgba(245,158,11,0.15); transform: translateY(-1px); }

        /* Tables layout styling */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }
        .admin-table th {
            padding: 12px 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .admin-table td {
            padding: 14px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            color: var(--text2);
            font-weight: 500;
        }
        .admin-table tr:last-child td { border-bottom: none; }

        /* Custom Status Badges */
        .badge {
            display: inline-flex; align-items: center;
            padding: 4px 10px; border-radius: 8px;
            font-size: 10px; font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-active { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.15); color: var(--success); }
        .badge-used { background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.15); color: #60a5fa; }
        .badge-inactive { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.15); color: #ef4444; }

        .badge-aim { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.15); color: #f59e0b; }
        .badge-lock { background: rgba(236,72,153,0.08); border: 1px solid rgba(236,72,153,0.15); color: #ec4899; }
        .badge-drag { background: rgba(139,92,246,0.08); border: 1px solid rgba(139,92,246,0.15); color: #a78bfa; }

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

        .generate-result {
            margin-top: 16px; background: rgba(0, 0, 0, 0.25);
            padding: 16px; border-radius: 12px; border: 1px solid var(--border);
            font-family: monospace; font-size: 12px; max-height: 200px;
            overflow-y: auto; text-align: left; line-height: 1.6;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

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
            <?= htmlspecialchars($siteTitle) ?> — Admin
        </a>
        <div class="nav-links" style="display: flex; gap: 8px; align-items: center;">
            <a href="dashboard.php" class="user-pill" style="padding: 6px 12px; font-size: 12px;"><i class="bi bi-arrow-left-short"></i> <span class="d-none-mobile">Dashboard</span></a>
            <a href="logout.php" class="user-pill" style="padding: 6px 12px; font-size: 12px; border-color: rgba(239, 68, 68, 0.2); color: #ef4444;"><i class="bi bi-box-arrow-right"></i> <span class="d-none-mobile">Thoát</span></a>
        </div>
    </div>

    <div class="container">
        <h1 class="title">Admin Control Panel</h1>
        <p class="subtitle">Quản trị hệ thống, cấp phát license & quản lý kết nối</p>

        <!-- STATS OVERVIEW -->
        <div class="stats-grid">
            <div class="card" style="padding: 16px; text-align: center; margin-bottom: 0; display: flex; flex-direction: column; justify-content: center;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.5px;">Tổng User</div>
                <div style="font-size: 24px; font-weight: 800; color: #fff; margin-top: 4px;"><?= $totalUsers ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center; margin-bottom: 0; display: flex; flex-direction: column; justify-content: center;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.5px;">Thiết bị online</div>
                <div style="font-size: 24px; font-weight: 800; color: #fff; margin-top: 4px;"><?= $totalDevices ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center; margin-bottom: 0; display: flex; flex-direction: column; justify-content: center;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.5px;">License Hoạt Động</div>
                <div style="font-size: 24px; font-weight: 800; color: var(--success); margin-top: 4px;"><?= $activeLicensesCount ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center; margin-bottom: 0; display: flex; flex-direction: column; justify-content: center;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: 0.5px;">Tổng License</div>
                <div style="font-size: 24px; font-weight: 800; color: #fff; margin-top: 4px;"><?= $totalLicenses ?></div>
            </div>
        </div>

        <!-- TAB NAVIGATION -->
        <div class="tab-switcher">
            <button class="tab-btn <?= $tab === 'licenses' ? 'active' : '' ?>" onclick="switchTab('licenses')">🎫 Licenses</button>
            <button class="tab-btn <?= $tab === 'users' ? 'active' : '' ?>" onclick="switchTab('users')">👥 Người Dùng</button>
            <button class="tab-btn <?= $tab === 'settings' ? 'active' : '' ?>" onclick="switchTab('settings')">🔧 Cài Đặt</button>
            <button class="tab-btn <?= $tab === 'devices' ? 'active' : '' ?>" onclick="switchTab('devices')">📱 Thiết Bị</button>
        </div>

        <!-- LICENSES TAB -->
        <div class="tab-content <?= $tab === 'licenses' ? 'active' : '' ?>" id="tab-licenses">
            <div class="card" style="text-align: left;">
                <h3 style="font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 16px;">Tạo License Mới</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Số lượng</label>
                        <input type="number" id="gen-count" value="1" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>Thời hạn (ngày)</label>
                        <input type="number" id="gen-days" value="30" min="1" max="365">
                    </div>
                    <div class="form-group">
                        <label>Giới hạn thiết bị</label>
                        <input type="number" id="gen-devices" value="1" min="1" max="10">
                    </div>
                </div>
                <button class="btn btn-primary" onclick="generateLicenses()"><i class="bi bi-plus-circle-fill"></i> Tạo License</button>
                <div class="generate-result" id="generate-result" style="display:none;"></div>
            </div>

            <div class="card" style="padding: 16px;">
                <input type="text" id="license-search" placeholder="🔍 Tìm kiếm license key hoặc người dùng..." oninput="filterLicenses()" class="search-bar">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>License Key</th>
                                <th>Người dùng</th>
                                <th>Trạng thái</th>
                                <th>Thời hạn</th>
                                <th>Hết hạn</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="license-table-body">
                            <?php foreach ($licenses as $l): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: #fff;"><?= htmlspecialchars($l['license_key']) ?></td>
                                <td style="font-weight: 600; color: var(--text2);"><?= htmlspecialchars($l['username'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $st = $l['status'];
                                    $badge = 'badge-inactive';
                                    if ($st === 'used') $badge = 'badge-used';
                                    elseif ($st === 'active') $badge = 'badge-active';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($st) ?></span>
                                </td>
                                <td><?= $l['duration_days'] ?> ngày</td>
                                <td style="font-size: 11px; color: var(--text3);"><?= $l['expires_at'] ?? '-' ?></td>
                                <td>
                                    <?php if ($l['status'] === 'used'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="revokeLicense(<?= $l['id'] ?>)">Thu hồi</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- USERS TAB -->
        <div class="tab-content <?= $tab === 'users' ? 'active' : '' ?>" id="tab-users">
            <div class="card" style="padding: 16px;">
                <input type="text" id="user-search" placeholder="🔍 Tìm kiếm tên tài khoản hoặc email..." oninput="filterUsers()" class="search-bar">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Quyền</th>
                                <th>Licenses</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-body">
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td style="font-family: monospace; color: var(--text3);"><?= $u['id'] ?></td>
                                <td style="font-weight: 700; color: #fff;"><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                                <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-active' : 'badge-used' ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                                <td style="font-weight: 600; text-align: center;"><?= $u['license_count'] ?></td>
                                <td><span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Banned' ?></span></td>
                                <td style="font-size: 11px; color: var(--text3);"><?= $u['created_at'] ?></td>
                                <td>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>" onclick="toggleUser(<?= $u['id'] ?>, <?= $u['is_active'] ? 0 : 1 ?>)">
                                            <?= $u['is_active'] ? 'Ban' : 'Unban' ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SETTINGS TAB -->
        <div class="tab-content <?= $tab === 'settings' ? 'active' : '' ?>" id="tab-settings">
            <div class="card" style="text-align: left;">
                <form id="settings-form" style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach ($settings as $s): ?>
                    <div class="form-group">
                        <label><?= htmlspecialchars($s['setting_label'] ?? $s['setting_key']) ?></label>
                        <?php if (strlen($s['setting_value']) > 100): ?>
                            <textarea name="<?= htmlspecialchars($s['setting_key']) ?>" style="min-height: 80px; resize: vertical; font-family: inherit; font-size: 13px; line-height: 1.5;"><?= htmlspecialchars($s['setting_value']) ?></textarea>
                        <?php else: ?>
                            <input type="text" name="<?= htmlspecialchars($s['setting_key']) ?>" value="<?= htmlspecialchars($s['setting_value']) ?>">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary" style="align-self: flex-start;"><i class="bi bi-save-fill"></i> Lưu cấu hình</button>
                </form>
            </div>
        </div>

        <!-- DEVICES TAB -->
        <div class="tab-content <?= $tab === 'devices' ? 'active' : '' ?>" id="tab-devices">
            <div class="card" style="padding: 16px;">
                <input type="text" id="device-search" placeholder="🔍 Tìm kiếm IP hoặc tên tài khoản..." oninput="filterDevices()" class="search-bar">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Địa Chỉ IP</th>
                                <th>Người Dùng</th>
                                <th>Chế Độ Policy</th>
                                <th>Cập Nhật</th>
                            </tr>
                        </thead>
                        <tbody id="device-table-body">
                            <?php foreach ($devices as $d): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: #fff;"><?= htmlspecialchars($d['client_ip']) ?></td>
                                <td style="font-weight: 600; color: var(--text2);"><?= htmlspecialchars($d['username'] ?? '-') ?></td>
                                <td>
                                    <?php 
                                    $mode = $d['routing_target'];
                                    $badge = 'badge-used';
                                    if ($mode === 'NORMAL') $badge = 'badge-active';
                                    elseif (in_array($mode, ['AIM_HEAD', 'FLAG_A', 'AIM_BODY'])) $badge = 'badge-aim';
                                    elseif ($mode === 'AIM_LOCK') $badge = 'badge-lock';
                                    elseif ($mode === 'AIM_DRAG') $badge = 'badge-drag';
                                    elseif ($mode === 'AIM_NECK') $badge = 'badge-aim';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($mode) ?></span>
                                </td>
                                <td style="font-size: 11px; color: var(--text3);"><?= $d['updated_at'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($devices)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text3); padding: 24px 0;">Chưa có thiết bị nào</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Cursor glow follower
            const glow = document.getElementById("cursor-glow");
            document.addEventListener("mousemove", (e) => {
                glow.style.left = e.clientX + "px";
                glow.style.top = e.clientY + "px";
            });
        });

        // Tab Switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            const content = document.getElementById('tab-' + tabName);
            if (content) content.classList.add('active');
            
            const btn = document.querySelector(`[onclick="switchTab('${tabName}')"]`);
            if (btn) btn.classList.add('active');
            
            const newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + tabName;
            window.history.pushState({ path: newurl }, '', newurl);
        }

        // Live Table Filters
        function filterLicenses() {
            const q = document.getElementById('license-search').value.toLowerCase();
            document.querySelectorAll('#license-table-body tr').forEach(row => {
                const key = row.children[0].innerText.toLowerCase();
                const user = row.children[1].innerText.toLowerCase();
                row.style.display = (key.includes(q) || user.includes(q)) ? '' : 'none';
            });
        }

        function filterUsers() {
            const q = document.getElementById('user-search').value.toLowerCase();
            document.querySelectorAll('#user-table-body tr').forEach(row => {
                const username = row.children[1].innerText.toLowerCase();
                const email = row.children[2].innerText.toLowerCase();
                row.style.display = (username.includes(q) || email.includes(q)) ? '' : 'none';
            });
        }

        function filterDevices() {
            const q = document.getElementById('device-search').value.toLowerCase();
            document.querySelectorAll('#device-table-body tr').forEach(row => {
                const ip = row.children[0].innerText.toLowerCase();
                const user = row.children[1].innerText.toLowerCase();
                row.style.display = (ip.includes(q) || user.includes(q)) ? '' : 'none';
            });
        }

        function showToast(msg, ok = true) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.className = 'toast ' + (ok ? 'success' : 'error');
            t.style.display = 'block';
            clearTimeout(t._t);
            t._t = setTimeout(() => t.style.display = 'none', 3000);
        }

        async function generateLicenses() {
            const count = document.getElementById('gen-count').value;
            const days = document.getElementById('gen-days').value;
            const devices = document.getElementById('gen-devices').value;
            try {
                const res = await fetch('api/license.php?action=generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ count: parseInt(count), duration_days: parseInt(days), device_limit: parseInt(devices) })
                });
                const data = await res.json();
                if (data.success) {
                    const div = document.getElementById('generate-result');
                    div.style.display = 'block';
                    div.innerHTML = '<strong>✅ Đã tạo ' + data.licenses.length + ' mã:</strong><br>' + data.licenses.join('<br>');
                    showToast(data.message);
                    setTimeout(() => location.reload(), 2500);
                } else { showToast(data.message, false); }
            } catch (e) { showToast('Lỗi kết nối.', false); }
        }

        async function revokeLicense(id) {
            if (!confirm('Bạn có chắc muốn thu hồi license này?')) return;
            try {
                const res = await fetch('api/license.php?action=revoke', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ license_id: id })
                });
                const data = await res.json();
                showToast(data.message, data.success);
                if (data.success) setTimeout(() => location.reload(), 1000);
            } catch (e) { showToast('Lỗi kết nối.', false); }
        }

        async function toggleUser(id, isActive) {
            try {
                const res = await fetch('api/admin.php?action=toggle_user', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: id, is_active: isActive })
                });
                const data = await res.json();
                showToast(data.message, data.success);
                if (data.success) setTimeout(() => location.reload(), 1000);
            } catch (e) { showToast('Lỗi kết nối.', false); }
        }

        // Settings form submission
        document.getElementById('settings-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = {};
            formData.forEach((v, k) => data[k] = v);
            try {
                const res = await fetch('api/admin.php?action=settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                showToast(result.message, result.success);
            } catch (err) { showToast('Lỗi kết nối.', false); }
        });
    </script>
</body>
</html>
