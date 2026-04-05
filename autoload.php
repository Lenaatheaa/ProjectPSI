<?php
/**
 * Autoloader Configuration
 * Class and File Loading
 */

// Register autoloader
spl_autoload_register(function ($className) {
    $directories = [
        __DIR__ . '/../classes/',
        __DIR__ . '/../models/',
        __DIR__ . '/../controllers/',
        __DIR__ . '/../services/',
        __DIR__ . '/../helpers/',
        __DIR__ . '/../config/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load configuration files
require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/ai_config.php';
require_once __DIR__ . '/logging.php';

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

// Initialize error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE'
    ];
    
    $errorType = $errorTypes[$severity] ?? 'UNKNOWN';
    $logMessage = "[$errorType] $message in $file on line $line";
    
    error_log($logMessage, 3, LoggingConfig::getLogPath('app'));
    
    if (AppConfig::getConfig('debug')) {
        echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
        echo "<strong>$errorType:</strong> $message<br>";
        echo "<small>File: $file, Line: $line</small>";
        echo "</div>";
    }
    
    return true;
});

// Initialize exception handler
set_exception_handler(function($exception) {
    $message = "Uncaught exception: " . $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $trace = $exception->getTraceAsString();
    
    $logMessage = "$message in $file on line $line\nStack trace:\n$trace";
    error_log($logMessage, 3, LoggingConfig::getLogPath('app'));
    
    if (AppConfig::getConfig('debug')) {
        echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
        echo "<h3>Uncaught Exception</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($message) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($file) . "</p>";
        echo "<p><strong>Line:</strong> $line</p>";
        echo "<details><summary>Stack Trace</summary><pre>" . htmlspecialchars($trace) . "</pre></details>";
        echo "</div>";
    } else {
        echo "<h1>Oops! Something went wrong.</h1>";
        echo "<p>We're sorry, but something went wrong. Please try again later.</p>";
    }
});

// Check system requirements
function checkSystemRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'cURL Extension' => extension_loaded('curl'),
        'JSON Extension' => extension_loaded('json'),
        'Writable Logs Directory' => is_writable(AppConfig::LOGS_PATH),
        'Writable Cache Directory' => is_writable(AppConfig::CACHE_PATH)
    ];
    
    $failed = [];
    foreach ($requirements as $requirement => $met) {
        if (!$met) {
            $failed[] = $requirement;
        }
    }
    
    if (!empty($failed)) {
        die("System requirements not met:\n" . implode("\n", $failed));
    }
}

// Run system check
checkSystemRequirements();

// Set default headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
