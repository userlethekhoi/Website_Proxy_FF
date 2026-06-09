-- ============================================================
-- BẢNG NGƯỜI DÙNG
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BẢNG LICENSE KEY
-- ============================================================
CREATE TABLE IF NOT EXISTS `licenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `license_key` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'used') NOT NULL DEFAULT 'active',
    `duration_days` INT NOT NULL DEFAULT 30,
    `device_limit` INT NOT NULL DEFAULT 1,
    `activated_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BẢNG THIẾT BỊ ĐÃ KÍCH HOẠT (liên kết user + device)
-- ============================================================
CREATE TABLE IF NOT EXISTS `activated_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `license_id` INT NOT NULL,
    `device_name` VARCHAR(100) DEFAULT NULL,
    `device_ip` VARCHAR(45) DEFAULT NULL,
    `device_udid` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `activated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`license_id`) REFERENCES `licenses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BẢNG DEVICE FILTERS (đã có - cập nhật thêm user_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS `device_filters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_ip` VARCHAR(45) NOT NULL UNIQUE,
    `user_id` INT DEFAULT NULL,
    `routing_target` VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BẢNG CÀI ĐẶT HỆ THỐNG (admin config)
-- ============================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `setting_label` VARCHAR(200) DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu mặc định cho system_settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_label`) VALUES
('proxy_host', '127.0.0.1', 'Địa chỉ Proxy Server'),
('proxy_port', '9999', 'Cổng Proxy UDP'),
('proxy_ssl_port', '8080', 'Cổng Proxy SSL/HTTPS'),
('video_guide_url', 'https://drive.google.com/file/d/1iDPmcazn3t4nEANk3f0_tbUwlIrIiUEQ/view', 'Link video hướng dẫn cài đặt'),
('video_fix_url', 'https://drive.google.com/file/d/12PFQOgiXMF1wuM660vlKuqw6tf2T_CLT/view', 'Link video fix lỗi'),
('telegram_url', 'https://t.me/solitudeproxy', 'Link nhóm Telegram'),
('discord_url', 'https://discord.gg/4CuEwNCEC', 'Link Discord'),
('site_title', 'Proxy Free Fire', 'Tiêu đề trang web'),
('site_subtitle', 'Proxy Free Fire', 'Phụ đề trang web'),
('proxy_version', 'V16', 'Phiên bản Proxy'),
('maintenance_mode', '0', 'Chế độ bảo trì (0=tắt, 1=bật)'),
('announcement_text', '', 'Thông báo hệ thống');

-- ============================================================
-- Tài khoản admin mặc định (password: admin123)
-- ============================================================
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`) VALUES
('admin', 'admin@proxy.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert some initial dummy data for testing
INSERT INTO `device_filters` (`client_ip`, `routing_target`) VALUES
('127.0.0.1', 'NORMAL')
ON DUPLICATE KEY UPDATE `routing_target` = `routing_target`;
