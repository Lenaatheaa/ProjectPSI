/**
 * JavaScript Configuration
 * Frontend Settings for AI Assistant
 */

const AppConfig = {
    // API Endpoints
    api: {
        base: '/api/',
        ai: {
            chat: '/api/ai/chat',
            settings: '/api/ai/settings',
            suggestions: '/api/ai/suggestions',
            history: '/api/ai/history'
        },
        users: '/api/users',
        transactions: '/api/transactions',
        analytics: '/api/analytics'
    },
    
    // AI Assistant Settings
    ai: {
        maxMessageLength: 1000,
        typingDelay: 1000,
        retryAttempts: 3,
        retryDelay: 2000,
        autoSave: true,
        autoSaveInterval: 30000, // 30 seconds
        maxHistoryItems: 100
    },
    
    // UI Settings
    ui: {
        theme: 'light',
        sidebarCollapsed: false,
        notificationDuration: 3000,
        chartRefreshInterval: 30000,
        tablePageSize: 20,
        modalAnimationDuration: 300
    },
    
    // Chart Colors
    colors: {
        primary: '#000000',
        secondary: '#2d3748',
        accent: '#4a5568',
        success: '#28a745',
        warning: '#ffc107',
        danger: '#dc3545',
        info: '#17a2b8'
    },
    
    // Validation Rules
    validation: {
        apiKey: {
            minLength: 20,
            pattern: /^sk-[a-zA-Z0-9]+$/
        },
        message: {
            maxLength: 1000,
            minLength: 1
        }
    },
    
    // Error Messages
    messages: {
        errors: {
            network: 'Terjadi masalah koneksi. Silakan coba lagi.',
            apiKey: 'API Key tidak valid. Silakan periksa pengaturan.',
            rateLimit: 'Terlalu banyak permintaan. Silakan tunggu sebentar.',
            general: 'Terjadi kesalahan. Silakan coba lagi.'
        },
        success: {
            saved: 'Data berhasil disimpan.',
            deleted: 'Data berhasil dihapus.',
            updated: 'Data berhasil diperbarui.'
        }
    },
    
    // Local Storage Keys
    storage: {
        apiKey: 'mis_ai_api_key',
        chatHistory: 'mis_chat_history',
        userPreferences: 'mis_user_preferences',
        theme: 'mis_theme'
    },
    
    // Feature Flags
    features: {
        aiAssistant: true,
        analytics: true,
        reports: true,
        notifications: true,
        darkMode: false
    },
    
    // Development Settings
    debug: true,
    logLevel: 'debug' // debug, info, warn, error
};

// Utility Functions
const Utils = {
    // Format currency
    formatCurrency: (amount) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(amount);
    },
    
    // Format date
    formatDate: (date) => {
        return new Intl.DateTimeFormat('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    },
    
    // Validate API Key
    validateApiKey: (apiKey) => {
        return apiKey && 
               apiKey.length >= AppConfig.validation.apiKey.minLength &&
               AppConfig.validation.apiKey.pattern.test(apiKey);
    },
    
    // Show notification
    showNotification: (message, type = 'info') => {
        // Implementation will be in main.js
        console.log(`[${type.toUpperCase()}] ${message}`);
    },
    
    // Log message
    log: (level, message, data = null) => {
        if (!AppConfig.debug) return;
        
        const levels = ['error', 'warn', 'info', 'debug'];
        const currentLevel = levels.indexOf(AppConfig.logLevel);
        const messageLevel = levels.indexOf(level);
        
        if (messageLevel <= currentLevel) {
            console[level](`[MIS] ${message}`, data || '');
        }
    },
    
    // Debounce function
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Throttle function
    throttle: (func, limit) => {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AppConfig, Utils };
}
