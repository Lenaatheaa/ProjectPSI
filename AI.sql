-- Database untuk AI Assistant - MIS Travel Admin
-- Dibuat untuk PHPMyAdmin

-- 1. Tabel untuk menyimpan sesi chat AI
CREATE TABLE `ai_chat_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `session_name` varchar(255) DEFAULT 'Chat Session',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT 1,
  `total_messages` int(11) DEFAULT 0,
  PRIMARY KEY (`session_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel untuk menyimpan pesan chat
CREATE TABLE `ai_chat_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `sender_type` enum('user','ai') NOT NULL,
  `message_content` text NOT NULL,
  `message_formatted` text,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_time_ms` int(11) DEFAULT NULL,
  `token_count` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`message_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_sender_type` (`sender_type`),
  FOREIGN KEY (`session_id`) REFERENCES `ai_chat_sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel untuk pengaturan AI Assistant
CREATE TABLE `ai_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `openai_api_key` varchar(500) DEFAULT NULL,
  `model_name` varchar(100) DEFAULT 'gpt-3.5-turbo',
  `max_tokens` int(11) DEFAULT 500,
  `temperature` decimal(3,2) DEFAULT 0.70,
  `status` enum('online','offline') DEFAULT 'offline',
  `daily_usage_limit` int(11) DEFAULT 100,
  `current_daily_usage` int(11) DEFAULT 0,
  `usage_reset_date` date DEFAULT (CURRENT_DATE),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `unique_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel untuk template/suggestion pesan
CREATE TABLE `ai_message_suggestions` (
  `suggestion_id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `suggestion_text` text NOT NULL,
  `icon_class` varchar(100) DEFAULT 'fas fa-comment',
  `order_index` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `usage_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`suggestion_id`),
  KEY `idx_category` (`category`),
  KEY `idx_order` (`order_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel untuk analytics dan tracking penggunaan AI
CREATE TABLE `ai_usage_analytics` (
  `analytics_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_messages_sent` int(11) DEFAULT 0,
  `total_ai_responses` int(11) DEFAULT 0,
  `total_tokens_used` int(11) DEFAULT 0,
  `average_response_time_ms` decimal(10,2) DEFAULT NULL,
  `session_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`analytics_id`),
  UNIQUE KEY `unique_admin_date` (`admin_id`, `date`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel untuk menyimpan context/sistem prompt AI
CREATE TABLE `ai_system_prompts` (
  `prompt_id` int(11) NOT NULL AUTO_INCREMENT,
  `prompt_name` varchar(255) NOT NULL,
  `system_content` text NOT NULL,
  `description` text,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`prompt_id`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel untuk log aktivitas AI
CREATE TABLE `ai_activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action_type` enum('api_key_saved','chat_started','message_sent','error_occurred','settings_updated') NOT NULL,
  `description` text,
  `ip_address` varchar(45),
  `user_agent` text,
  `additional_data` json DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert data awal untuk suggestion messages
INSERT INTO `ai_message_suggestions` (`category`, `title`, `suggestion_text`, `icon_class`, `order_index`) VALUES
('statistics', 'Statistik User', 'Berapa total user aktif bulan ini?', 'fas fa-users', 1),
('performance', 'Performa Transaksi', 'Bagaimana performa transaksi hari ini?', 'fas fa-chart-line', 2),
('analysis', 'Analisis Target', 'Analisis target travel yang tercapai', 'fas fa-bullseye', 3),
('reports', 'Laporan Keuangan', 'Buatkan ringkasan laporan keuangan minggu ini', 'fas fa-file-alt', 4),
('insights', 'Insight Bisnis', 'Berikan insight untuk meningkatkan engagement user', 'fas fa-lightbulb', 5),
('trends', 'Tren Tabungan', 'Analisis tren grup tabungan yang sedang populer', 'fas fa-trending-up', 6);

-- Insert system prompt default
INSERT INTO `ai_system_prompts` (`prompt_name`, `system_content`, `description`, `is_default`) VALUES
('MIS Travel Assistant', 
'Anda adalah AI Assistant untuk sistem MIS (Management Information System) admin travel. Anda membantu admin dalam mengelola:
- User management (Total users saat ini: 2,847 dengan pertumbuhan +12.5%)
- Grup tabungan aktif (1,256 grup dengan pertumbuhan +8.2%)
- Total tabungan (Rp 45.2M dengan pertumbuhan +15.3%)
- Target travel tercapai (847 target dengan pertumbuhan +23.1%)
- Analytics dan laporan
- Transaksi keuangan digital

Berikan jawaban yang profesional, informatif, dan membantu dalam bahasa Indonesia. Fokus pada analisis data, memberikan insight, dan saran actionable untuk meningkatkan performa bisnis travel.',
'System prompt default untuk AI Assistant MIS Travel',
1);

-- Index untuk optimasi performa
CREATE INDEX idx_ai_messages_content ON ai_chat_messages(message_content(100));
CREATE INDEX idx_ai_analytics_compound ON ai_usage_analytics(admin_id, date, total_messages_sent);
CREATE INDEX idx_ai_sessions_admin_active ON ai_chat_sessions(admin_id, is_active);

-- Trigger untuk update counter pesan di session
DELIMITER $$
CREATE TRIGGER update_session_message_count 
AFTER INSERT ON ai_chat_messages
FOR EACH ROW
BEGIN
    UPDATE ai_chat_sessions 
    SET total_messages = total_messages + 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE session_id = NEW.session_id;
END$$
DELIMITER ;

-- Trigger untuk update daily usage analytics
DELIMITER $$
CREATE TRIGGER update_daily_analytics
AFTER INSERT ON ai_chat_messages
FOR EACH ROW
BEGIN
    DECLARE admin_id_val INT;
    
    SELECT admin_id INTO admin_id_val 
    FROM ai_chat_sessions 
    WHERE session_id = NEW.session_id;
    
    INSERT INTO ai_usage_analytics (admin_id, date, total_messages_sent, total_ai_responses)
    VALUES (admin_id_val, CURDATE(), 
            CASE WHEN NEW.sender_type = 'user' THEN 1 ELSE 0 END,
            CASE WHEN NEW.sender_type = 'ai' THEN 1 ELSE 0 END)
    ON DUPLICATE KEY UPDATE
        total_messages_sent = total_messages_sent + CASE WHEN NEW.sender_type = 'user' THEN 1 ELSE 0 END,
        total_ai_responses = total_ai_responses + CASE WHEN NEW.sender_type = 'ai' THEN 1 ELSE 0 END,
        updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Procedure untuk cleanup data lama (optional)
DELIMITER $$
CREATE PROCEDURE CleanupOldChatData(IN days_to_keep INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Delete old chat messages
    DELETE FROM ai_chat_messages 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Delete empty sessions
    DELETE FROM ai_chat_sessions 
    WHERE total_messages = 0 
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Delete old analytics (keep 1 year)
    DELETE FROM ai_usage_analytics 
    WHERE date < DATE_SUB(CURDATE(), INTERVAL 365 DAY);
    
    COMMIT;
END$$
DELIMITER ;

-- View untuk statistik AI usage
CREATE VIEW ai_usage_summary AS
SELECT 
    DATE(timestamp) as date,
    COUNT(*) as total_interactions,
    COUNT(CASE WHEN sender_type = 'user' THEN 1 END) as user_messages,
    COUNT(CASE WHEN sender_type = 'ai' THEN 1 END) as ai_responses,
    AVG(response_time_ms) as avg_response_time,
    COUNT(DISTINCT session_id) as unique_sessions
FROM ai_chat_messages 
WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(timestamp)
ORDER BY date DESC;

-- Tampilkan struktur yang sudah dibuat
SHOW TABLES LIKE 'ai_%';
