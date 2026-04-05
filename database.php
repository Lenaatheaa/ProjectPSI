<?php
/**
 * AI Assistant Configuration
 * OpenAI API and Chat Settings
 */

class AIConfig {
    // OpenAI API Settings
    public const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    public const OPENAI_MODEL_DEFAULT = 'gpt-3.5-turbo';
    public const OPENAI_MODEL_ADVANCED = 'gpt-4';
    
    // Chat Settings
    public const MAX_TOKENS_DEFAULT = 500;
    public const MAX_TOKENS_ADVANCED = 1000;
    public const TEMPERATURE_DEFAULT = 0.7;
    public const TEMPERATURE_CREATIVE = 0.9;
    public const TEMPERATURE_PRECISE = 0.3;
    
    // Rate Limiting
    public const DAILY_MESSAGE_LIMIT = 100;
    public const HOURLY_MESSAGE_LIMIT = 20;
    public const MESSAGE_COOLDOWN_SECONDS = 2;
    
    // Session Settings
    public const SESSION_TIMEOUT_MINUTES = 30;
    public const MAX_MESSAGES_PER_SESSION = 50;
    public const MAX_MESSAGE_LENGTH = 1000;
    
    // Security Settings
    public const API_KEY_ENCRYPTION_METHOD = 'AES-256-CBC';
    public const API_KEY_MIN_LENGTH = 20;
    public const ALLOWED_ADMIN_IPS = []; // Empty = allow all
    
    // System Prompts
    public const SYSTEM_PROMPTS = [
        'default' => 'Anda adalah AI Assistant untuk sistem MIS (Management Information System) admin travel. Anda membantu admin dalam mengelola user management, grup tabungan, target travel, analytics, dan transaksi keuangan digital. Berikan jawaban yang profesional, informatif, dan membantu dalam bahasa Indonesia.',
        
        'analytics' => 'Anda adalah AI Assistant khusus untuk analisis data MIS Travel. Fokus pada interpretasi data, tren, dan memberikan insight bisnis yang actionable. Gunakan data statistik yang tersedia untuk memberikan rekomendasi strategis.',
        
        'support' => 'Anda adalah AI Assistant untuk customer support MIS Travel. Bantu admin dalam menyelesaikan masalah teknis, memberikan panduan penggunaan sistem, dan troubleshooting.',
        
        'financial' => 'Anda adalah AI Assistant untuk analisis keuangan MIS Travel. Fokus pada analisis transaksi, laporan keuangan, dan memberikan insight tentang performa finansial platform.'
    ];
    
    // Response Templates
    public const RESPONSE_TEMPLATES = [
        'error_api_key' => 'Maaf, terjadi masalah dengan konfigurasi AI. Silakan periksa pengaturan API Key Anda.',
        'error_rate_limit' => 'Anda telah mencapai batas penggunaan. Silakan coba lagi dalam beberapa menit.',
        'error_network' => 'Maaf, terjadi masalah koneksi. Silakan coba lagi nanti.',
        'error_general' => 'Maaf, terjadi kesalahan saat memproses permintaan Anda. Silakan coba lagi.',
        'welcome' => 'Halo! Saya AI Assistant Anda. Bagaimana saya bisa membantu Anda hari ini?'
    ];
    
    // Suggestion Categories
    public const SUGGESTION_CATEGORIES = [
        'statistics' => [
            'icon' => 'fas fa-chart-bar',
            'color' => '#3498db',
            'description' => 'Statistik dan Data'
        ],
        'analysis' => [
            'icon' => 'fas fa-analytics',
            'color' => '#e74c3c',
            'description' => 'Analisis Bisnis'
        ],
        'reports' => [
            'icon' => 'fas fa-file-alt',
            'color' => '#2ecc71',
            'description' => 'Laporan'
        ],
        'insights' => [
            'icon' => 'fas fa-lightbulb',
            'color' => '#f39c12',
            'description' => 'Insight & Rekomendasi'
        ]
    ];
    
    // Validation Rules
    public static function validateApiKey($apiKey) {
        return !empty($apiKey) && 
               strlen($apiKey) >= self::API_KEY_MIN_LENGTH && 
               str_starts_with($apiKey, 'sk-');
    }
    
    public static function validateMessage($message) {
        return !empty(trim($message)) && 
               strlen($message) <= self::MAX_MESSAGE_LENGTH;
    }
    
    // Get model configuration
    public static function getModelConfig($model = null) {
        $model = $model ?: self::OPENAI_MODEL_DEFAULT;
        
        $configs = [
            'gpt-3.5-turbo' => [
                'max_tokens' => self::MAX_TOKENS_DEFAULT,
                'temperature' => self::TEMPERATURE_DEFAULT,
                'cost_per_token' => 0.000002
            ],
            'gpt-4' => [
                'max_tokens' => self::MAX_TOKENS_ADVANCED,
                'temperature' => self::TEMPERATURE_DEFAULT,
                'cost_per_token' => 0.00003
            ]
        ];
        
        return $configs[$model] ?? $configs[self::OPENAI_MODEL_DEFAULT];
    }
    
    // Encrypt API Key
    public static function encryptApiKey($apiKey, $encryptionKey) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::API_KEY_ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($apiKey, self::API_KEY_ENCRYPTION_METHOD, $encryptionKey, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    // Decrypt API Key
    public static function decryptApiKey($encryptedApiKey, $encryptionKey) {
        $data = base64_decode($encryptedApiKey);
        list($encrypted_data, $iv) = explode('::', $data, 2);
        return openssl_decrypt($encrypted_data, self::API_KEY_ENCRYPTION_METHOD, $encryptionKey, 0, $iv);
    }
}
?>
