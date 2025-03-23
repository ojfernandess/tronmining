<?php
namespace App\Core;

/**
 * Router Class
 * 
 * Handles URL routing
 */
class Router
{
    /**
     * Routes array
     *
     * @var array
     */
    protected static $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => []
    ];
    
    /**
     * Named routes
     *
     * @var array
     */
    protected static $namedRoutes = [];
    
    /**
     * Current route
     *
     * @var array
     */
    protected static $currentRoute = null;
    
    /**
     * Route parameters
     *
     * @var array
     */
    protected static $params = [];
    
    /**
     * Base path
     *
     * @var string
     */
    protected static $basePath = '';
    
    /**
     * Add a GET route
     *
     * @param string $route Route
     * @param array|string $handler Handler
     * @param string $name Route name
     * @return void
     */
    public static function get($route, $handler, $name = null)
    {
        self::addRoute('GET', $route, $handler, $name);
    }
    
    /**
     * Add a POST route
     *
     * @param string $route Route
     * @param array|string $handler Handler
     * @param string $name Route name
     * @return void
     */
    public static function post($route, $handler, $name = null)
    {
        self::addRoute('POST', $route, $handler, $name);
    }
    
    /**
     * Add a PUT route
     *
     * @param string $route Route
     * @param array|string $handler Handler
     * @param string $name Route name
     * @return void
     */
    public static function put($route, $handler, $name = null)
    {
        self::addRoute('PUT', $route, $handler, $name);
    }
    
    /**
     * Add a DELETE route
     *
     * @param string $route Route
     * @param array|string $handler Handler
     * @param string $name Route name
     * @return void
     */
    public static function delete($route, $handler, $name = null)
    {
        self::addRoute('DELETE', $route, $handler, $name);
    }
    
    /**
     * Add route for multiple methods
     *
     * @param array $methods HTTP methods
     * @param string $route Route
     * @param array|string $handler Handler
     * @param string $name Route name
     * @return void
     */
    public static function match($methods, $route, $handler, $name = null)
    {
        foreach ($methods as $method) {
            self::addRoute(strtoupper($method), $route, $handler, $name);
        }
    }
    
    /**
     * Add a route
     *
     * @param string $method HTTP method
     * @param string $route Route
     * @param array|string $handler Handler
     * @param string $name Route name
     * @return void
     */
    protected static function addRoute($method, $route, $handler, $name = null)
    {
        // Add leading slash if not present
        if ($route !== '/') {
            $route = '/' . ltrim($route, '/');
        }
        
        // Convert route to regex pattern
        $pattern = self::routeToRegex($route);
        
        // Add route to routes array
        self::$routes[$method][$pattern] = [
            'route' => $route,
            'handler' => $handler,
            'name' => $name
        ];
        
        // Add to named routes if name is provided
        if ($name) {
            self::$namedRoutes[$name] = $route;
        }
    }
    
    /**
     * Convert route to regex pattern
     *
     * @param string $route Route
     * @return string Regex pattern
     */
    protected static function routeToRegex($route)
    {
        // Replace route parameters with regex patterns
        $route = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?<$1>[^/]+)', $route);
        $route = preg_replace('/\{([a-zA-Z0-9_]+):([^}]+)\}/', '(?<$1>$2)', $route);
        
        // Escape slashes and add delimiters
        return "#^" . str_replace('/', '\/', $route) . "$#";
    }
    
    /**
     * Match route
     *
     * @param string $method HTTP method
     * @param string $uri URI to match
     * @return bool Whether route matched
     */
    public static function match_route($method, $uri)
    {
        // Clean URI
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');
        
        // Check for routes matching the method
        if (!isset(self::$routes[$method])) {
            return false;
        }
        
        // Check each route
        foreach (self::$routes[$method] as $pattern => $route) {
            if (preg_match($pattern, $uri, $matches)) {
                // Store current route
                self::$currentRoute = $route;
                
                // Extract parameters
                self::$params = array_filter($matches, function($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Dispatch route
     *
     * @return mixed Result of dispatched route
     */
    public static function dispatch()
    {
        // Get request method and URI
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Strip query string if present
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }
        
        // Handle base path
        if (self::$basePath && strpos($uri, self::$basePath) === 0) {
            $uri = substr($uri, strlen(self::$basePath));
        }
        
        // Match route
        if (!self::match_route($method, $uri)) {
            // No route matched
            return self::notFound();
        }
        
        // Get handler
        $handler = self::$currentRoute['handler'];
        
        // Call handler
        if (is_callable($handler)) {
            // Handler is callable
            return call_user_func_array($handler, self::$params);
        } elseif (is_string($handler)) {
            // Handler is string (Controller@method)
            list($controller, $method) = explode('@', $handler);
            
            // Check if controller exists
            $controllerClass = "\\App\\Controllers\\{$controller}";
            
            if (!class_exists($controllerClass)) {
                throw new \Exception("Controller {$controller} not found");
            }
            
            // Create controller instance
            $controllerInstance = new $controllerClass();
            
            // Check if method exists
            if (!method_exists($controllerInstance, $method)) {
                throw new \Exception("Method {$method} not found in controller {$controller}");
            }
            
            // Call controller method with parameters
            return call_user_func_array([$controllerInstance, $method], self::$params);
        } elseif (is_array($handler) && count($handler) === 2) {
            // Handler is array [Controller, method]
            list($controller, $method) = $handler;
            
            // Check controller type
            if (is_string($controller)) {
                // Controller is string, create instance
                $controllerClass = "\\App\\Controllers\\{$controller}";
                
                if (!class_exists($controllerClass)) {
                    throw new \Exception("Controller {$controller} not found");
                }
                
                $controller = new $controllerClass();
            }
            
            // Check if method exists
            if (!method_exists($controller, $method)) {
                throw new \Exception("Method {$method} not found in controller");
            }
            
            // Call controller method with parameters
            return call_user_func_array([$controller, $method], self::$params);
        }
        
        // Invalid handler
        throw new \Exception("Invalid route handler");
    }
    
    /**
     * Handle 404 Not Found
     *
     * @return void
     */
    protected static function notFound()
    {
        header("HTTP/1.0 404 Not Found");
        
        // Check if 404 view exists
        $viewFile = APP_PATH . '/views/errors/404.php';
        
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            echo "<h1>404 Not Found</h1>";
            echo "<p>The page you requested could not be found.</p>";
        }
        
        exit;
    }
    
    /**
     * Generate URL for named route
     *
     * @param string $name Route name
     * @param array $params Route parameters
     * @return string Generated URL
     */
    public static function url($name, $params = [])
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new \Exception("Route {$name} not found");
        }
        
        $url = self::$namedRoutes[$name];
        
        // Replace parameters in URL
        foreach ($params as $key => $value) {
            $url = str_replace("{{$key}}", $value, $url);
            $url = preg_replace("/\{{$key}:[^}]+\}/", $value, $url);
        }
        
        // Add base path
        return self::$basePath . $url;
    }
    
    /**
     * Set base path
     *
     * @param string $path Base path
     * @return void
     */
    public static function setBasePath($path)
    {
        self::$basePath = '/' . trim($path, '/');
        
        if (self::$basePath === '/') {
            self::$basePath = '';
        }
    }
    
    /**
     * Get current route
     *
     * @return array Current route
     */
    public static function getCurrentRoute()
    {
        return self::$currentRoute;
    }
    
    /**
     * Get current route parameters
     *
     * @return array Route parameters
     */
    public static function getParams()
    {
        return self::$params;
    }
} 