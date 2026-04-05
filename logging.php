<?php
/**
 * Logging Configuration
 * Error and Activity Logging
 */

class LoggingConfig {
    public const LOG_LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7
    ];
    
    public const LOG_CHANNELS = [
        'app' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'level' => 'debug',
            'max_files' => 30
        ],
        'ai' => [
            'driver' => 'file',
            'path' => 'logs/ai.log',
            'level' => 'info',
            'max_files' => 30
        ],
        'security' => [
            'driver' => 'file',
            'path' => 'logs/security.log',
            'level' => 'warning',
            'max_files' => 90
        ],
        'database' => [
            'driver' => 'file',
            'path' => 'logs/database.log',
            'level' => 'error',
            'max_files' => 30
        ]
    ];
    
    public const LOG_FORMAT = '[%datetime%] %channel%.%level_name%: %message% %context% %extra%';
    public const DATE_FORMAT = 'Y-m-d H:i:s';
    
    // Log rotation settings
    public const MAX_LOG_SIZE = 10485760; // 10MB
    public const LOG_RETENTION_DAYS = 30;
    
    public static function getLogPath($channel = 'app') {
        $config = self::LOG_CHANNELS[$channel] ?? self::LOG_CHANNELS['app'];
        return AppConfig::BASE_PATH . $config['path'];
    }
    
    public static function shouldLog($level, $channel = 'app') {
        $config = self::LOG_CHANNELS[$channel] ?? self::LOG_CHANNELS['app'];
        $channelLevel = self::LOG_LEVELS[$config['level']] ?? 7;
        $messageLevel = self::LOG_LEVELS[$level] ?? 7;
        
        return $messageLevel <= $channelLevel;
    }
}
?>
