<?php
require_once 'config.php';
requireAdmin();

$db = getDbConnection();
$tab = $_GET['tab'] ?? 'licenses';

// Fetch data based on tab
$licenses = $db->query("SELECT l.*, u.username FROM licenses l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC")->fetchAll();
$users = $db->query("SELECT u.*, (SELECT COUNT(*) FROM licenses l WHERE l.user_id = u.id AND l.status = 'used') as license_count FROM users u ORDER BY u.created_at DESC")->fetchAll();
$settings = $db->query("SELECT * FROM system_settings ORDER BY id")->fetchAll();
$devices = $db->query("SELECT df.*, u.username FROM device_filters df LEFT JOIN users u ON df.user_id = u.id ORDER BY df.updated_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - ProxyFF</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0e17; --card-bg: #121824; --tertiary: #1b2336;
            --accent: #3b82f6; --accent-hover: #2563eb; --text: #f3f4f6;
            --muted: #9ca3af; --success: #10b981; --warning: #f59e0b;
            --danger: #ef4444; --border: #2e3a52;
            --font: 'Outfit', sans-serif; --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 240px; background: var(--card-bg); border-right: 1px solid var(--border);
            padding: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem;
            position: fixed; height: 100vh; left: 0; top: 0;
        }
        .sidebar h3 { font-size: 1.1rem; margin-bottom: 1rem; background: linear-gradient(135deg, #60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .sidebar a {
            color: var(--muted); text-decoration: none; padding: 10px 14px;
            border-radius: 8px; font-size: 0.9rem; transition: all 0.2s;
        }
        .sidebar a:hover, .sidebar a.active { background: var(--tertiary); color: var(--text); }
        .main { margin-left: 240px; flex: 1; padding: 2rem; }
        h1 { font-size: 1.8rem; margin-bottom: 1.5rem; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); text-align: left; font-size: 0.9rem; }
        th { color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .badge { padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; font-family: var(--font-mono); }
        .badge-active { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
        .badge-used { background: rgba(59,130,246,0.1); color: var(--accent); border: 1px solid rgba(59,130,246,0.3); }
        .badge-inactive { background: rgba(239,68,68,0.1); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
        .badge-aim { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .badge-lock { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .badge-drag { background: rgba(139,92,246,0.1); color: #a78bfa; border: 1px solid rgba(139,92,246,0.2); }

        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 4px; font-size: 0.85rem; color: var(--muted); }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; background: var(--bg);
            border: 1px solid var(--border); border-radius: 8px;
            color: var(--text); font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .btn {
            padding: 10px 20px; background: var(--accent); color: #fff;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer;
            font-size: 14px; transition: 0.2s;
        }
        .btn:hover { background: var(--accent-hover); }
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; border-radius: 6px; }
        .btn-danger { background: var(--danger); }
        .btn-success { background: var(--success); }
        .btn-warning { background: var(--warning); color: #000; }

        .toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 1rem 2rem; border-radius: 8px; color: #fff; display: none; z-index: 1000; box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
        .generate-result { margin-top: 1rem; background: var(--bg); padding: 1rem; border-radius: 8px; font-family: var(--font-mono); font-size: 0.85rem; max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <h3>⚙️ Admin Panel</h3>
            <a href="?tab=licenses" class="<?= $tab === 'licenses' ? 'active' : '' ?>">🎫 Quản lý License</a>
            <a href="?tab=users" class="<?= $tab === 'users' ? 'active' : '' ?>">👥 Quản lý Users</a>
            <a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>">🔧 Cài đặt Hệ thống</a>
            <a href="?tab=devices" class="<?= $tab === 'devices' ? 'active' : '' ?>">📱 Thiết bị</a>
            <a href="dashboard.php" style="margin-top:auto;">← Quay lại Dashboard</a>
        </aside>

        <main class="main">
            <!-- LICENSES TAB -->
            <div class="tab-content <?= $tab === 'licenses' ? 'active' : '' ?>" id="tab-licenses">
                <h1>🎫 Quản lý License</h1>
                <div class="card">
                    <h3 style="margin-bottom:1rem;">Tạo License Mới</h3>
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
                    <button class="btn" onclick="generateLicenses()">🎫 Tạo License</button>
                    <div class="generate-result" id="generate-result" style="display:none;"></div>
                </div>
                <table>
                    <thead>
                        <tr><th>License Key</th><th>Người dùng</th><th>Trạng thái</th><th>Thời hạn</th><th>Hết hạn</th><th>Hành động</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licenses as $l): ?>
                        <tr>
                            <td style="font-family:var(--font-mono);font-size:0.8rem;"><?= htmlspecialchars($l['license_key']) ?></td>
                            <td><?= htmlspecialchars($l['username'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= $l['status'] ?>"><?= $l['status'] ?></span></td>
                            <td><?= $l['duration_days'] ?> ngày</td>
                            <td style="font-size:0.8rem;"><?= $l['expires_at'] ?? '-' ?></td>
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

            <!-- USERS TAB -->
            <div class="tab-content <?= $tab === 'users' ? 'active' : '' ?>" id="tab-users">
                <h1>👥 Quản lý Người dùng</h1>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Licenses</th><th>Trạng thái</th><th>Ngày tạo</th><th>Hành động</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= $u['role'] === 'admin' ? 'active' : 'used' ?>"><?= $u['role'] ?></span></td>
                            <td><?= $u['license_count'] ?></td>
                            <td><span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Banned' ?></span></td>
                            <td style="font-size:0.8rem;"><?= $u['created_at'] ?></td>
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

            <!-- SETTINGS TAB -->
            <div class="tab-content <?= $tab === 'settings' ? 'active' : '' ?>" id="tab-settings">
                <h1>🔧 Cài đặt Hệ thống</h1>
                <div class="card">
                    <form id="settings-form">
                        <?php foreach ($settings as $s): ?>
                        <div class="form-group">
                            <label><?= htmlspecialchars($s['setting_label'] ?? $s['setting_key']) ?></label>
                            <?php if (strlen($s['setting_value']) > 100): ?>
                                <textarea name="<?= htmlspecialchars($s['setting_key']) ?>" style="width:100%;padding:10px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;resize:vertical;min-height:60px;"><?= htmlspecialchars($s['setting_value']) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= htmlspecialchars($s['setting_key']) ?>" value="<?= htmlspecialchars($s['setting_value']) ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn">💾 Lưu cài đặt</button>
                    </form>
                </div>
            </div>

            <!-- DEVICES TAB -->
            <div class="tab-content <?= $tab === 'devices' ? 'active' : '' ?>" id="tab-devices">
                <h1>📱 Thiết bị đang kết nối</h1>
                <table>
                    <thead>
                        <tr><th>IP</th><th>Người dùng</th><th>Policy</th><th>Cập nhật</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $d): ?>
                        <tr>
                            <td style="font-family:var(--font-mono);"><?= htmlspecialchars($d['client_ip']) ?></td>
                            <td><?= htmlspecialchars($d['username'] ?? '-') ?></td>
                            <td><span class="badge <?php 
                                $mode = $d['routing_target'];
                                if ($mode === 'NORMAL') echo 'badge-active';
                                elseif (in_array($mode, ['AIM_HEAD', 'FLAG_A', 'AIM_NECK', 'FLAG_B', 'AIM_BODY'])) echo 'badge-aim';
                                elseif ($mode === 'AIM_LOCK') echo 'badge-lock';
                                elseif ($mode === 'AIM_DRAG') echo 'badge-drag';
                                else echo 'badge-used';
                            ?>"><?= $d['routing_target'] ?></span></td>
                            <td style="font-size:0.8rem;"><?= $d['updated_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        function showToast(msg, ok = true) {
            const t = document.getElementById('toast');
            t.innerText = msg; t.style.backgroundColor = ok ? 'var(--success)' : 'var(--danger)';
            t.style.display = 'block'; setTimeout(() => t.style.display = 'none', 3000);
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
                    div.innerHTML = '<strong>✅ Đã tạo ' + data.licenses.length + ' license(s):</strong><br>' + data.licenses.join('<br>');
                    showToast(data.message);
                    setTimeout(() => location.reload(), 2000);
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
