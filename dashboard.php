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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ProxyFF</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0e17;
            --bg-secondary: #121824;
            --bg-tertiary: #1b2336;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --text: #f3f4f6;
            --muted: #9ca3af;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #2e3a52;
            --font: 'Outfit', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font); background: var(--bg-primary); color: var(--text); line-height: 1.6; padding: 1rem; min-height: 100vh; }
        .container { max-width: 1000px; margin: 0 auto; }

        /* NAVBAR */
        nav {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.5rem; background: var(--bg-secondary);
            border: 1px solid var(--border); border-radius: 16px;
            margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }
        .nav-brand { font-size: 1.3rem; font-weight: 700; background: linear-gradient(135deg, #60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-links { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .nav-links a, .nav-links span { color: var(--muted); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        .nav-links a:hover { color: var(--accent); }
        .btn-sm {
            padding: 8px 16px; background: var(--accent); color: #fff;
            border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: 0.2s;
        }
        .btn-sm:hover { background: var(--accent-hover); }
        .btn-sm.danger { background: var(--danger); }
        .btn-sm.danger:hover { opacity: 0.9; }
        .user-badge { font-family: var(--font-mono); background: var(--bg-tertiary); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; color: var(--accent); }

        /* HEADER */
        header { text-align: center; margin-bottom: 2rem; }
        header h1 { font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        header p { color: var(--muted); font-size: 1rem; }

        /* LICENSE BANNER */
        .license-banner {
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(59,130,246,0.1));
            border: 1px solid var(--success); border-radius: 12px;
            padding: 1rem 1.5rem; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
        }
        .license-banner .info { display: flex; flex-direction: column; gap: 4px; }
        .license-banner .label { font-size: 0.8rem; color: var(--muted); }
        .license-banner .value { font-family: var(--font-mono); color: var(--success); font-size: 0.9rem; }
        .no-license {
            background: rgba(239,68,68,0.1); border: 1px solid var(--danger);
            border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 2rem;
        }
        .no-license p { color: var(--danger); margin-bottom: 0.5rem; }

        /* ACTIVATE FORM */
        .activate-form { display: flex; gap: 0.5rem; }
        .activate-form input {
            flex: 1; padding: 8px 14px; background: var(--bg-primary);
            border: 1px solid var(--border); border-radius: 8px;
            color: var(--text); font-family: var(--font-mono); font-size: 14px;
        }
        .activate-form input:focus { outline: none; border-color: var(--accent); }

        /* GRID */
        .grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
        @media (min-width: 768px) { .grid { grid-template-columns: 1fr 1fr; } }

        /* CARD */
        .card {
            background: var(--bg-secondary); border: 1px solid var(--border);
            border-radius: 16px; padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
        }
        .card-title {
            font-size: 1.3rem; font-weight: 600; margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border); padding-bottom: 0.5rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .ip-badge {
            font-family: var(--font-mono); font-size: 0.9rem;
            background: var(--bg-tertiary); padding: 0.25rem 0.75rem;
            border-radius: 20px; color: var(--accent);
            border: 1px solid rgba(59,130,246,0.3);
        }

        /* POLICY OPTIONS */
        .policy-options { display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem; }
        .policy-option {
            position: relative; background: var(--bg-tertiary);
            border: 2px solid var(--border); border-radius: 12px;
            padding: 1.25rem; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; gap: 1rem;
        }
        .policy-option input[type="radio"] { display: none; }
        .policy-option::before {
            content: ""; display: inline-block; width: 20px; height: 20px;
            border: 2px solid var(--muted); border-radius: 50%; flex-shrink: 0; transition: all 0.2s;
        }
        .policy-option.selected { border-color: var(--accent); background: rgba(59,130,246,0.05); }
        .policy-option.selected::before { border-color: var(--accent); background: var(--accent); box-shadow: inset 0 0 0 4px var(--bg-tertiary); }
        .policy-name { font-weight: 600; font-size: 1.1rem; }
        .policy-desc { font-size: 0.85rem; color: var(--muted); }
        .badge-normal { color: var(--success); }
        .badge-a { color: var(--warning); }
        .badge-b { color: var(--danger); }

        /* TABLE */
        .table-container { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 1rem; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; }
        .status-pill {
            display: inline-block; padding: 0.25rem 0.6rem; font-size: 0.8rem;
            font-weight: 700; border-radius: 6px; font-family: var(--font-mono);
        }
        .pill-NORMAL { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.2); }
        .pill-FLAG_A, .pill-AIM_HEAD { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        .pill-FLAG_B, .pill-AIM_NECK { background: rgba(245,158,11,0.1); color: var(--warning); border: 1px solid rgba(245,158,11,0.2); }
        .pill-AIM_BODY { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .pill-AIM_LOCK { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .pill-AIM_DRAG { background: rgba(139,92,246,0.1); color: #a78bfa; border: 1px solid rgba(139,92,246,0.2); }

        /* TOAST */
        .toast {
            position: fixed; bottom: 2rem; right: 2rem;
            padding: 1rem 2rem; border-radius: 8px; color: #fff;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
            display: none; z-index: 1000; animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* DISABLED OVERLAY */
        .disabled-overlay {
            position: relative; pointer-events: none; opacity: 0.5;
        }
        .disabled-overlay::after {
            content: "Kích hoạt license để sử dụng";
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8); color: var(--warning);
            padding: 1rem 2rem; border-radius: 8px; font-weight: 700;
            white-space: nowrap; z-index: 10;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- NAVBAR -->
        <nav>
            <span class="nav-brand">ProxyFF Dashboard</span>
            <div class="nav-links">
                <span>👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
                <span class="user-badge"><?= isAdmin() ? 'Admin' : 'User' ?></span>
                <a href="api/mobileconfig.php" class="btn-sm">📱 Tải Proxy Config</a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="btn-sm">⚙️ Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-sm danger">Đăng xuất</a>
            </div>
        </nav>

        <header>
            <h1>UDP Real-Time Middleware</h1>
            <p>Giám sát và kiểm soát dữ liệu nhị phân trung chuyển qua Proxy</p>
        </header>

        <!-- LICENSE SECTION -->
        <?php if ($hasLicense): ?>
            <div class="license-banner">
                <div class="info">
                    <span class="label">License đang hoạt động</span>
                    <span class="value">⏳ Hết hạn: <?= htmlspecialchars($activeLicense['expires_at']) ?> | 📱 Thiết bị: <?= $activeLicense['device_count'] ?>/<?= $activeLicense['device_limit'] ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="no-license">
                <p><strong>⚠️ Bạn chưa kích hoạt license!</strong> Vui lòng nhập mã kích hoạt để sử dụng proxy.</p>
                <div class="activate-form">
                    <input type="text" id="license-input" placeholder="Nhập mã license (VD: PXFF-XXXXXXXX-XXXXXXXX)">
                    <button class="btn-sm" onclick="activateLicense()">Kích hoạt</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- POLICY CONFIGURATION -->
            <div class="card <?= !$hasLicense ? 'disabled-overlay' : '' ?>">
                <div class="card-title">
                    <span>Cấu hình Thiết bị</span>
                    <span class="ip-badge"><?= htmlspecialchars($client_ip) ?></span>
                </div>
                <p style="font-size:0.9rem;color:var(--muted);margin-bottom:1rem;">
                    Chọn chế độ aimbot cho gói tin UDP.
                </p>
                <div class="policy-options">
                    <label class="policy-option <?= $current_policy === 'NORMAL' ? 'selected' : '' ?>">
                        <input type="radio" name="policy" value="NORMAL" <?= $current_policy === 'NORMAL' ? 'checked' : '' ?>>
                        <div>
                            <div class="policy-name badge-normal">🚫 NORMAL</div>
                            <div class="policy-desc">Tắt aimbot, chuyển tiếp gói tin nguyên bản.</div>
                        </div>
                    </label>
                    <label class="policy-option <?= $current_policy === 'AIM_HEAD' ? 'selected' : '' ?>">
                        <input type="radio" name="policy" value="AIM_HEAD" <?= $current_policy === 'AIM_HEAD' ? 'checked' : '' ?>>
                        <div>
                            <div class="policy-name badge-a">🎯 AIM HEAD</div>
                            <div class="policy-desc">Tự động ngắm vào đầu đối thủ. Tỉ lệ headshot cao nhất.</div>
                        </div>
                    </label>
                    <label class="policy-option <?= $current_policy === 'AIM_NECK' ? 'selected' : '' ?>">
                        <input type="radio" name="policy" value="AIM_NECK" <?= $current_policy === 'AIM_NECK' ? 'checked' : '' ?>>
                        <div>
                            <div class="policy-name badge-b">🔫 AIM NECK</div>
                            <div class="policy-desc">Ngắm vào cổ - cân bằng giữa sát thương và độ chính xác.</div>
                        </div>
                    </label>
                    <label class="policy-option <?= $current_policy === 'AIM_BODY' ? 'selected' : '' ?>">
                        <input type="radio" name="policy" value="AIM_BODY" <?= $current_policy === 'AIM_BODY' ? 'checked' : '' ?>>
                        <div>
                            <div class="policy-name" style="color:#f59e0b;">🎯 AIM BODY</div>
                            <div class="policy-desc">Ngắm vào thân - dễ trúng nhất, ít bị report.</div>
                        </div>
                    </label>
                    <label class="policy-option <?= $current_policy === 'AIM_LOCK' ? 'selected' : '' ?>">
                        <input type="radio" name="policy" value="AIM_LOCK" <?= $current_policy === 'AIM_LOCK' ? 'checked' : '' ?>>
                        <div>
                            <div class="policy-name" style="color:#ef4444;">🔒 AIM LOCK</div>
                            <div class="policy-desc">Khóa mục tiêu cứng - không giật, không tản đạn.</div>
                        </div>
                    </label>
                    <label class="policy-option <?= $current_policy === 'AIM_DRAG' ? 'selected' : '' ?>>
                        <input type="radio" name="policy" value="AIM_DRAG" <?= $current_policy === 'AIM_DRAG' ? 'checked' : '' ?>>
                        <div>
                            <div class="policy-name" style="color:#8b5cf6;">🖱️ AIM DRAG</div>
                            <div class="policy-desc">Kéo tâm mượt về phía đối thủ - khó bị phát hiện nhất.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- DEVICE MONITORING -->
            <div class="card">
                <div class="card-title">Thiết bị đang hoạt động</div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Địa chỉ IP</th><?php if (isAdmin()): ?><th>Người dùng</th><?php endif; ?><th>Mục tiêu</th><th>Cập nhật</th></tr>
                        </thead>
                        <tbody id="device-list">
                            <?php foreach ($allDevices as $device): ?>
                            <tr>
                                <td style="font-family:var(--font-mono);"><?= htmlspecialchars($device['client_ip']) ?></td>
                                <?php if (isAdmin()): ?><td><?= htmlspecialchars($device['username'] ?? '-') ?></td><?php endif; ?>
                                <td><span class="status-pill pill-<?= htmlspecialchars($device['routing_target']) ?>"><?= htmlspecialchars($device['routing_target']) ?></span></td>
                                <td style="font-size:0.8rem;color:var(--muted);"><?= htmlspecialchars($device['updated_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allDevices)): ?>
                            <tr><td colspan="<?= isAdmin() ? 4 : 3 ?>" style="text-align:center;color:var(--muted);padding:2rem;">Chưa có thiết bị nào</td></tr>
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
            toast.style.backgroundColor = success ? 'var(--success)' : 'var(--danger)';
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 3000);
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
