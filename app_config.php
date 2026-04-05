<?php
/**
 * Main Application Configuration
 * MIS Travel Admin Dashboard
 */

class AppConfig {
    // Application Info
    public const APP_NAME = 'MIS Travel Admin';
    public const APP_VERSION = '1.0.0';
    public const APP_DESCRIPTION = 'Management Information System for Travel Business';
    public const APP_AUTHOR = 'MIS Travel Team';
    
    // Environment Settings
    public const ENVIRONMENT = 'development'; // development, staging, production
    public const DEBUG_MODE = true;
    public const LOG_LEVEL = 'debug'; // debug, info, warning, error
    
    // Security Settings
    public const SESSION_LIFETIME = 3600; // 1 hour
    public const CSRF_TOKEN_LIFETIME = 1800; // 30 minutes
    public const PASSWORD_MIN_LENGTH = 8;
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOGIN_LOCKOUT_TIME = 900; // 15 minutes
    
    // File Upload Settings
    public const MAX_FILE_SIZE = 5242880; // 5MB
    public const ALLOWED_FILE_TYPES = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    public const UPLOAD_PATH = 'uploads/';
    
    // Pagination Settings
    public const ITEMS_PER_PAGE = 20;
    public const MAX_ITEMS_PER_PAGE = 100;
    
    // Cache Settings
    public const CACHE_ENABLED = true;
    public const CACHE_LIFETIME = 3600; // 1 hour
    public const CACHE_PATH = 'cache/';
    
    // Email Settings
    public const SMTP_HOST = 'localhost';
    public const SMTP_PORT = 587;
    public const SMTP_USERNAME = '';
    public const SMTP_PASSWORD = '';
    public const SMTP_ENCRYPTION = 'tls';
    public const FROM_EMAIL = 'admin@mistravel.com';
    public const FROM_NAME = 'MIS Travel Admin';
    
    // API Settings
    public const API_RATE_LIMIT = 1000; // requests per hour
    public const API_VERSION = 'v1';
    public const API_TIMEOUT = 30; // seconds
    
    // Timezone and Locale
    public const DEFAULT_TIMEZONE = 'Asia/Jakarta';
    public const DEFAULT_LOCALE = 'id_ID';
    public const DATE_FORMAT = 'Y-m-d H:i:s';
    public const DISPLAY_DATE_FORMAT = 'd/m/Y H:i';
    
    // Currency Settings
    public const DEFAULT_CURRENCY = 'IDR';
    public const CURRENCY_SYMBOL = 'Rp';
    public const CURRENCY_DECIMAL_PLACES = 0;
    
    // Notification Settings
    public const NOTIFICATION_TYPES = [
        'success' => ['icon' => 'fas fa-check-circle', 'color' => '#28a745'],
        'error' => ['icon' => 'fas fa-exclamation-circle', 'color' => '#dc3545'],
        'warning' => ['icon' => 'fas fa-exclamation-triangle', 'color' => '#ffc107'],
        'info' => ['icon' => 'fas fa-info-circle', 'color' => '#17a2b8']
    ];
    
    // Dashboard Settings
    public const DASHBOARD_REFRESH_INTERVAL = 30000; // 30 seconds
    public const CHART_COLORS = [
        'primary' => '#000000',
        'secondary' => '#2d3748',
        'accent' => '#4a5568',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger' => '#dc3545',
        'info' => '#17a2b8'
    ];
    
    // System Paths
    public const BASE_PATH = __DIR__ . '/../';
    public const CONFIG_PATH = __DIR__ . '/';
    public const LOGS_PATH = __DIR__ . '/../logs/';
    public const TEMP_PATH = __DIR__ . '/../temp/';
    
    // Database Table Prefixes
    public const DB_PREFIX = '';
    
    // Feature Flags
    public const FEATURES = [
        'ai_assistant' => true,
        'analytics' => true,
        'reports' => true,
        'notifications' => true,
        'file_upload' => true,
        'export_data' => true,
        'backup_restore' => false
    ];
    
    // Get configuration by environment
    public static function getConfig($key = null) {
        $configs = [
            'development' => [
                'debug' => true,
                'log_level' => 'debug',
                'cache_enabled' => false,
                'minify_assets' => false
            ],
            'staging' => [
                'debug' => true,
                'log_level' => 'info',
                'cache_enabled' => true,
                'minify_assets' => true
            ],
            'production' => [
                'debug' => false,
                'log_level' => 'error',
                'cache_enabled' => true,
                'minify_assets' => true
            ]
        ];
        
        $envConfig = $configs[self::ENVIRONMENT] ?? $configs['development'];
        
        return $key ? ($envConfig[$key] ?? null) : $envConfig;
    }
    
    // Initialize application
    public static function init() {
        // Set timezone
        date_default_timezone_set(self::DEFAULT_TIMEZONE);
        
        // Set locale
        setlocale(LC_ALL, self::DEFAULT_LOCALE);
        
        // Error reporting based on environment
        if (self::getConfig('debug')) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
        
        // Session configuration
        ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
        ini_set('session.cookie_lifetime', self::SESSION_LIFETIME);
        
        // Create necessary directories
        self::createDirectories();
    }
    
    // Create necessary directories
    private static function createDirectories() {
        $directories = [
            self::LOGS_PATH,
            self::TEMP_PATH,
            self::UPLOAD_PATH,
            self::CACHE_PATH
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    // Get database configuration
    public static function getDatabaseConfig() {
        return [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => $_ENV['DB_NAME'] ?? 'mis_travel_db',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'pass' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'port' => $_ENV['DB_PORT'] ?? 3306
        ];
    }
}

// Initialize application
AppConfig::init();
?>
