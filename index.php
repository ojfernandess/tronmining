<?php
/**
 * Tronmining - HYIP Script
 * Application entry point
 */

// Define base paths
define('ROOT_PATH', __DIR__);
define('APP_PATH', __DIR__ . '/app');
define('PUBLIC_PATH', __DIR__ . '/public');

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

// Set error reporting based on environment
if (getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Autoload classes
spl_autoload_register(function ($className) {
    // Convert namespace separators to directory separators
    $className = str_replace('\\', '/', $className);
    
    // Remove 'App/' from beginning since it's already part of APP_PATH
    $className = str_replace('App/', '', $className);
    
    // Create the file path
    $filePath = APP_PATH . '/' . $className . '.php';
    
    // Check if file exists
    if (file_exists($filePath)) {
        require_once $filePath;
        return true;
    }
    
    return false;
});

// Initialize the application
require_once APP_PATH . '/core/App.php';
$app = \App\Core\App::getInstance();
$app->run(); 