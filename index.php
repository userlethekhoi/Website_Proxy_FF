<?php
/**
 * Landing Page - Dark Theme
 * Trang chủ hiển thị link tải proxy, hướng dẫn, cộng đồng
 */
require_once 'config.php';

$isLoggedIn = isLoggedIn();

$siteTitle = getSetting('site_title', 'Proxy Free Fire');
$siteSubtitle = getSetting('site_subtitle', 'Proxy Free Fire Made By Kaydus');
$proxyVersion = getSetting('proxy_version', 'V16');
$videoGuideUrl = getSetting('video_guide_url', '#');
$videoFixUrl = getSetting('video_fix_url', '#');
$telegramUrl = getSetting('telegram_url', '#');
$discordUrl = getSetting('discord_url', '#');
$maintenanceMode = getSetting('maintenance_mode', '0');
$announcement = getSetting('announcement_text', '');
?>
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= htmlspecialchars($siteTitle) ?> — Premium Space</title>
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
            --nav-bg:        rgba(9, 10, 16, 0.85);
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
        body.loading { overflow: hidden; }
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; cursor: pointer; border: none; background: none; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg2); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

        /* Splash Loading Screen */
        #introSplash {
            position: fixed; inset: 0;
            background: #06070a;
            display: flex; align-items: center; justify-content: center;
            z-index: 9000;
            transition: opacity 0.5s, visibility 0.5s;
        }
        #introSplash.hide { opacity: 0; visibility: hidden; pointer-events: none; }
        .splash-inner { text-align: center; position: relative; }
        .splash-ring {
            width: 120px; height: 120px;
            border-radius: 50%;
            border: 2px solid transparent;
            border-top-color: rgba(255, 255, 255, 0.3);
            border-right-color: rgba(255, 255, 255, 0.1);
            animation: spinRing 1s linear infinite;
            position: absolute;
            top: -10px; left: 50%; transform: translateX(-50%);
        }
        @keyframes spinRing { to { transform: translateX(-50%) rotate(360deg); } }
        .splash-icon {
            width: 100px; height: 100px;
            border-radius: 50%;
            margin: 0 auto 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: popIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative; z-index: 1;
            background: var(--bg2);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .splash-title {
            font-size: 22px; font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
            animation: fadeUp 0.5s 0.2s both;
        }
        .splash-sub {
            font-size: 13px; color: var(--text2);
            margin-top: 8px;
            animation: fadeUp 0.5s 0.3s both;
            font-weight: 500;
        }
        .splash-loader {
            margin-top: 24px;
            display: flex; justify-content: center;
            animation: fadeUp 0.5s 0.4s both;
        }
        .splash-loader span {
            width: 50px; height: 2px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 99px;
            position: relative;
            overflow: hidden;
        }
        .splash-loader span::after {
            content: '';
            position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 1.2s infinite;
        }
        @keyframes shimmer { to { left: 100%; } }
        @keyframes fadeUp { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

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

        .music-btn {
            width: 36px; height: 36px; border-radius: 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex; justify-content: center; align-items: center;
            font-size: 14px; color: var(--text2);
            transition: all 0.2s ease;
        }
        .music-btn:hover { background: var(--surface2); color: var(--text); border-color: rgba(255, 255, 255, 0.15); }
        .music-btn.playing { color: var(--text); border-color: rgba(255, 255, 255, 0.2); box-shadow: 0 0 15px rgba(255, 255, 255, 0.05); animation: spin 4s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* Profile Container */
        .profile-container {
            max-width: 440px; width: 100%; text-align: center;
            padding: 110px 20px 40px;
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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

        .announcement {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 24px;
            font-size: 13px; font-weight: 500;
            color: var(--text);
            text-align: left;
            display: flex; align-items: flex-start; gap: 8px;
        }
        .maintenance-overlay {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.15);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 24px;
            color: var(--danger); font-weight: 500; font-size: 13px;
            text-align: left;
        }

        .section-label {
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.15em; text-transform: uppercase;
            color: var(--text3);
            margin: 32px 0 12px;
            display: flex; align-items: center;
            width: 100%;
            text-align: left;
        }

        /* Link Cards */
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
        .link-card.proxy-featured:hover {
            border-color: rgba(255, 255, 255, 0.25);
        }

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
            color: var(--accent2);
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.18);
        }

        .card-body { flex: 1; text-align: left; }
        .card-title { color: var(--text); font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .card-desc { color: var(--text2); font-size: 12px; font-weight: 500; }
        .card-arrow { color: var(--text3); font-size: 16px; transition: transform 0.2s ease; }
        .link-card:hover .card-arrow { transform: translateX(2px); color: var(--text2); }

        /* Toast notifications */
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

        /* Footer */
        .footer {
            text-align: center;
            padding: 32px 20px 48px;
            color: var(--text3);
            font-size: 12px; font-weight: 500;
            max-width: 440px; width: 100%;
        }

        @media (min-width: 600px) {
            .title { font-size: 32px; }
        }

        /* Terms Modal */
        .terms-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            display: flex; align-items: center; justify-content: center;
            z-index: 9500;
            padding: 20px;
            opacity: 0; visibility: hidden;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .terms-modal-overlay.show { opacity: 1; visibility: visible; }
        .terms-modal-box {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px 24px;
            width: 100%; max-width: 380px;
            text-align: center;
            box-shadow: var(--shadow);
            transform: scale(0.95);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .terms-modal-overlay.show .terms-modal-box { transform: scale(1); }
        .terms-icon {
            width: 50px; height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: var(--accent);
            margin: 0 auto 16px;
        }
        .terms-title { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 12px; }
        .terms-text { font-size: 13px; color: var(--text2); line-height: 1.6; text-align: justify; margin-bottom: 24px; }
        .terms-actions { display: flex; gap: 12px; }
        .terms-btn {
            flex: 1; padding: 12px 16px; border-radius: 12px;
            font-size: 13px; font-weight: 600; text-align: center; cursor: pointer;
            transition: all 0.2s ease;
        }
        .terms-btn.btn-accept { background: #fff; color: #000; }
        .terms-btn.btn-accept:hover { background: rgba(255,255,255,0.9); transform: translateY(-1px); }
        .terms-btn.btn-decline { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06); color: var(--text2); }
        .terms-btn.btn-decline:hover { background: rgba(255,255,255,0.06); color: var(--text); border-color: rgba(255, 255, 255, 0.15); transform: translateY(-1px); }
    </style>
</head>
<body class="loading">

    <div id="introSplash">
        <div class="splash-inner">
            <div class="splash-ring"></div>
            <div class="splash-icon"><img src="logo.png" style="width:100%; height:100%; border-radius:50%; object-fit:cover;"></div>
            <h1 class="splash-title">Proxy Free Fire</h1>
            <p class="splash-sub">Đang khởi động...</p>
            <div class="splash-loader"><span></span></div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div id="termsModal" class="terms-modal-overlay">
        <div class="terms-modal-box">
            <div class="terms-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <h2 class="terms-title">Điều Khoản & Chính Sách</h2>
            <p class="terms-text">
                Vui lòng chấp nhận điều khoản và chính sách bảo mật trước khi dùng sản phẩm của chúng tôi. Chúng tôi không chịu trách nhiệm với các hành vi sử dụng sản phẩm của chúng tôi dưới các hình thức vi phạm pháp luật. Mọi người dùng sử dụng sản phẩm của chúng tôi đều phải tự chịu trách nhiệm cho hành vi của mình, chúng tôi cam kết không chống phá nhà nước hay vi phạm pháp luật.
            </p>
            <div class="terms-actions">
                <button class="terms-btn btn-decline" id="btnDecline">Từ chối</button>
                <button class="terms-btn btn-accept" id="btnAccept">Đồng ý & Tiếp tục</button>
            </div>
        </div>
    </div>

    <audio id="bg-music" loop autoplay playsinline style="display:none;">
        <source src="nhac.mp3" type="audio/mpeg">
    </audio>

    <div class="top-bar">
        <span class="top-bar-brand">
            <img src="logo.png" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.15);">
            <?= htmlspecialchars($siteTitle) ?>
        </span>
        <div class="music-btn" id="musicToggle">
            <i class="bi bi-music-note"></i>
        </div>
    </div>

    <div class="profile-container">
        <div class="avatar-wrap">
            <div class="avatar"><img src="logo.png" style="width:100%; height:100%; border-radius:50%; object-fit:cover;"></div>
        </div>

        <h1 class="title"><?= htmlspecialchars($siteTitle) ?></h1>
        <p class="subtitle"><?= htmlspecialchars($siteSubtitle) ?></p>

        <?php if ($isLoggedIn): ?>
            <div class="user-info">
                <span class="user-pill">👤 <?= htmlspecialchars($_SESSION["username"]) ?></span>
                <a href="dashboard.php" class="user-pill">📊 Dashboard</a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="user-pill">⚙️ Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="user-pill">Thoát</a>
            </div>
        <?php else: ?>
            <div class="user-info">
                <a href="login.php" class="user-pill">Đăng Nhập</a>
                <a href="register.php" class="user-pill">Đăng Ký</a>
            </div>
        <?php endif; ?>

        <?php if ($maintenanceMode === "1"): ?>
            <div class="maintenance-overlay">
                Hệ thống đang trong chế độ bảo trì. Một số tính năng có thể không khả dụng.
            </div>
        <?php endif; ?>

        <?php if (!empty($announcement)): ?>
            <div class="announcement">📢 <?= htmlspecialchars($announcement) ?></div>
        <?php endif; ?>

        <div class="section-label">Proxy</div>

        <?php if ($isLoggedIn): ?>
            <a href="api/mobileconfig.php" class="link-card proxy-featured">
                <div class="card-icon"><i class="bi bi-download"></i></div>
                <div class="card-body">
                    <div class="card-title">CÀI ĐẶT PROXY</div>
                    <div class="card-desc">Phiên bản <?= htmlspecialchars($proxyVersion) ?> Mới Nhất</div>
                </div>
                <i class="bi bi-arrow-right card-arrow"></i>
            </a>
        <?php else: ?>
            <a href="login.php" class="link-card proxy-featured" onclick="showToast('Vui lòng đăng nhập để tải cấu hình proxy.', false)">
                <div class="card-icon"><i class="bi bi-download"></i></div>
                <div class="card-body">
                    <div class="card-title">CÀI ĐẶT PROXY</div>
                    <div class="card-desc">Đăng nhập để tải — Phiên bản <?= htmlspecialchars($proxyVersion) ?></div>
                </div>
                <i class="bi bi-arrow-right card-arrow"></i>
            </a>
        <?php endif; ?>

        <div class="section-label">Hướng Dẫn & Sửa Lỗi</div>

        <a href="<?= htmlspecialchars($videoGuideUrl) ?>" class="link-card" target="_blank">
            <div class="card-icon"><i class="bi bi-play-fill"></i></div>
            <div class="card-body">
                <div class="card-title">CÁCH CÀI ĐẶT PROXY</div>
                <div class="card-desc">Video hướng dẫn chi tiết</div>
            </div>
            <i class="bi bi-arrow-right card-arrow"></i>
        </a>

        <a href="<?= htmlspecialchars($videoFixUrl) ?>" class="link-card" target="_blank">
            <div class="card-icon"><i class="bi bi-wrench"></i></div>
            <div class="card-body">
                <div class="card-title">FIX LỖI 32MB & LOADING</div>
                <div class="card-desc">Khắc phục sự cố nhanh chóng</div>
            </div>
            <i class="bi bi-arrow-right card-arrow"></i>
        </a>

        <div class="section-label">Cộng Đồng</div>

        <a href="<?= htmlspecialchars($telegramUrl) ?>" class="link-card" target="_blank">
            <div class="card-icon"><i class="bi bi-telegram"></i></div>
            <div class="card-body">
                <div class="card-title">NHÓM TELEGRAM</div>
                <div class="card-desc">Cập nhật & Hỗ trợ</div>
            </div>
            <i class="bi bi-arrow-right card-arrow"></i>
        </a>

        <a href="<?= htmlspecialchars($discordUrl) ?>" class="link-card" target="_blank">
            <div class="card-icon"><i class="bi bi-discord"></i></div>
            <div class="card-body">
                <div class="card-title">BOX DISCORD</div>
                <div class="card-desc">Cộng đồng & Giao lưu</div>
            </div>
            <i class="bi bi-arrow-right card-arrow"></i>
        </a>
    </div>

    <div class="footer">© 2026 <strong><?= htmlspecialchars($siteTitle) ?></strong> — Made with ❤️ in Việt Nam</div>

    <div class="toast success" id="toast"></div>

    <script>
        window.addEventListener("load", () => {
            setTimeout(() => {
                document.getElementById("introSplash").classList.add("hide");
                document.body.classList.remove("loading");

                // Show terms modal after loading finishes if not accepted before
                if (!localStorage.getItem("terms_accepted")) {
                    document.getElementById("termsModal").classList.add("show");
                }
            }, 1500);
        });

        document.getElementById("btnAccept").addEventListener("click", () => {
            localStorage.setItem("terms_accepted", "true");
            document.getElementById("termsModal").classList.remove("show");
        });

        document.getElementById("btnDecline").addEventListener("click", () => {
            window.location.href = "https://www.google.com";
        });

        const music = document.getElementById("bg-music");
        const musicBtn = document.getElementById("musicToggle");

        function tryAutoplay() {
            music.play().then(() => {
                musicBtn.classList.add("playing");
                musicBtn.innerHTML = '<i class="bi bi-disc"></i>';
            }).catch(() => {});
        }
        tryAutoplay();

        function unmuteOnInteraction() {
            if (music.paused) {
                music.play();
                musicBtn.classList.add("playing");
                musicBtn.innerHTML = '<i class="bi bi-disc"></i>';
            }
            document.removeEventListener("click", unmuteOnInteraction);
            document.removeEventListener("touchstart", unmuteOnInteraction);
        }
        document.addEventListener("click", unmuteOnInteraction);
        document.addEventListener("touchstart", unmuteOnInteraction);

        musicBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            if (music.paused) {
                music.play();
                musicBtn.classList.add("playing");
                musicBtn.innerHTML = '<i class="bi bi-disc"></i>';
            } else {
                music.pause();
                musicBtn.classList.remove("playing");
                musicBtn.innerHTML = '<i class="bi bi-music-note"></i>';
            }
        });

        // Notification Toast function

        function showToast(message, success = true) {
            const toast = document.getElementById("toast");
            toast.innerText = message;
            toast.className = "toast " + (success ? "success" : "error");
            toast.style.display = "block";
            clearTimeout(toast._t);
            toast._t = setTimeout(() => { toast.style.display = "none"; }, 3000);
        }
    </script>
</body>
</html>