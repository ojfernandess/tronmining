<?php
/**
 * Tronmining - HYIP Script
 * Application entry point
 */

// Define base paths
define('ROOT_PATH', __DIR__);
define('APP_PATH', __DIR__ . '/app');
define('PUBLIC_PATH', __DIR__ . '/public');

// Definir modo de depuração - TRUE para desenvolvimento, FALSE para produção
define('DEBUG_MODE', true);

// Set error reporting based on environment
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set error and exception handlers
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    error_log($exception->getTraceAsString());
    
    http_response_code(500);
    if (file_exists(APP_PATH . '/views/errors/500.php')) {
        require APP_PATH . '/views/errors/500.php';
    } else {
        echo "<h1>500 Internal Server Error</h1>";
        echo "<p>An unexpected error occurred. Please try again later.</p>";
        echo "<p>Error: " . $exception->getMessage() . "</p>";
    }
    exit;
});

// Load environment variables
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv = parse_ini_file(ROOT_PATH . '/.env');
    foreach ($dotenv as $key => $value) {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// Check if the application needs installation
if (!file_exists(ROOT_PATH . '/.env') && !isset($_GET['install'])) {
    header('Location: install/index.php');
    exit;
}

// Autoload classes
spl_autoload_register(function ($className) {
    // Convert namespace separators to directory separators
    $className = str_replace('\\', '/', $className);
    
    // Remove 'App/' from beginning since it's already part of APP_PATH
    $className = str_replace('App/', '', $className);
    
    // Create the file path
    $filePath = APP_PATH . '/' . $className . '.php';
    
    // Debug log para verificar qual arquivo está tentando carregar
    error_log("Tentando carregar classe: $className -> $filePath");
    
    // Check if file exists
    if (file_exists($filePath)) {
        require_once $filePath;
        return true;
    }
    
    error_log("Arquivo não encontrado: $filePath");
    return false;
});

// Adicione esta linha para depuração do roteamento
error_log("URI solicitada: " . $_SERVER['REQUEST_URI']);

// Load core classes explicitly to ensure proper loading order
require_once APP_PATH . '/core/Router.php';
require_once APP_PATH . '/core/App.php';

// Initialize the application
$app = \App\Core\App::getInstance();
$app->run(); 