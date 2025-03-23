<?php
namespace App\Core;

/**
 * App Class
 * 
 * Main application class
 */
class App {
    /**
     * Application instance (singleton)
     *
     * @var App
     */
    private static $instance = null;
    
    /**
     * Application configuration
     *
     * @var array
     */
    private $config = [];
    
    /**
     * Application constructor
     */
    private function __construct() {
        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Set default timezone
        date_default_timezone_set('UTC');
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Load configuration
        $this->loadConfig();
        
        // Define path constants
        $this->definePathConstants();
        
        // Set router base path
        if (isset($this->config['app']['base_path'])) {
            Router::setBasePath($this->config['app']['base_path']);
        }
    }
    
    /**
     * Get App instance (singleton)
     *
     * @return App
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Run the application
     *
     * @return void
     */
    public function run() {
        // Load routes
        $this->loadRoutes();
        
        // Dispatch request
        Router::dispatch();
    }
    
    /**
     * Load application configuration
     *
     * @return void
     */
    private function loadConfig() {
        // Load main configuration file
        $configFile = dirname(__DIR__) . '/config/config.php';
        
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = [];
        }
    }
    
    /**
     * Define path constants
     *
     * @return void
     */
    private function definePathConstants() {
        // Only define constants if they don't already exist
        if (!defined('APP_PATH')) {
            define('APP_PATH', dirname(__DIR__));
        }
        
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(APP_PATH));
        }
        
        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', ROOT_PATH . '/public');
        }
        
        if (!defined('VIEWS_PATH')) {
            define('VIEWS_PATH', APP_PATH . '/views');
        }
        
        if (!defined('UPLOADS_PATH')) {
            define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
        }
    }
    
    /**
     * Load application routes
     *
     * @return void
     */
    private function loadRoutes() {
        // Load routes file
        $routesFile = APP_PATH . '/routes.php';
        
        if (file_exists($routesFile)) {
            require_once $routesFile;
        }
    }
    
    /**
     * Get configuration value
     *
     * @param string $key Configuration key (dot notation)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function config($key, $default = null) {
        $app = self::getInstance();
        
        // Split key into parts
        $keys = explode('.', $key);
        
        // Get config array
        $config = $app->config;
        
        // Traverse config array
        foreach ($keys as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            
            $config = $config[$part];
        }
        
        return $config;
    }
    
    /**
     * Handle uncaught exceptions
     *
     * @param \Throwable $exception Exception
     * @return void
     */
    public static function handleException($exception) {
        // Log exception
        error_log($exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
        
        // Show error page in production
        if (self::config('app.env') === 'production') {
            // Display friendly error page
            header('HTTP/1.1 500 Internal Server Error');
            
            // Check if 500 view exists
            $errorFile = APP_PATH . '/views/errors/500.php';
            
            if (file_exists($errorFile)) {
                require_once $errorFile;
            } else {
                echo "<h1>500 Internal Server Error</h1>";
                echo "<p>Sorry, something went wrong on the server.</p>";
            }
        } else {
            // Show detailed error in development
            header('HTTP/1.1 500 Internal Server Error');
            echo "<h1>500 Internal Server Error</h1>";
            echo "<p><strong>Type:</strong> " . get_class($exception) . "</p>";
            echo "<p><strong>Message:</strong> " . $exception->getMessage() . "</p>";
            echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
            echo "<h2>Stack Trace:</h2>";
            echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        }
        
        exit;
    }
    
    /**
     * Handle fatal errors
     *
     * @return void
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            // Convert error to exception
            self::handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }
} 