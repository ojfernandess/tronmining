<?php
namespace App\Core;

/**
 * Base Controller Class
 * 
 * Base controller for all controllers in the application
 */
class Controller
{
    /**
     * Load model
     *
     * @param string $model Model name
     * @return object Model instance
     */
    protected function model($model)
    {
        $modelClass = "App\\Models\\{$model}";
        
        if (class_exists($modelClass)) {
            return new $modelClass();
        } else {
            throw new \Exception("Model {$model} not found");
        }
    }
    
    /**
     * Load view
     *
     * @param string $view View name
     * @param array $data Data to pass to view
     * @return void
     */
    protected function view($view, $data = [])
    {
        // Check if view exists
        $viewFile = APP_PATH . "/views/{$view}.php";
        
        if (file_exists($viewFile)) {
            // Extract data to make variables available in view
            extract($data);
            
            // Start output buffering
            ob_start();
            
            // Include view
            include_once $viewFile;
            
            // Get contents of buffer and clean it
            $content = ob_get_clean();
            
            // Output content
            echo $content;
        } else {
            throw new \Exception("View {$view} not found");
        }
    }
    
    /**
     * Render view with layout
     *
     * @param string $view View name
     * @param array $data Data to pass to view
     * @param string $layout Layout name
     * @return void
     */
    protected function render($view, $data = [], $layout = 'default')
    {
        // Add view content to data
        $data['content'] = $this->getViewContent($view, $data);
        
        // Render layout
        $this->view("layouts/{$layout}", $data);
    }
    
    /**
     * Get view content without rendering
     *
     * @param string $view View name
     * @param array $data Data to pass to view
     * @return string View content
     */
    protected function getViewContent($view, $data = [])
    {
        // Check if view exists
        $viewFile = APP_PATH . "/views/{$view}.php";
        
        if (file_exists($viewFile)) {
            // Extract data to make variables available in view
            extract($data);
            
            // Start output buffering
            ob_start();
            
            // Include view
            include_once $viewFile;
            
            // Get contents of buffer and clean it
            return ob_get_clean();
        } else {
            throw new \Exception("View {$view} not found");
        }
    }
    
    /**
     * Redirect to URL
     *
     * @param string $url URL to redirect to
     * @return void
     */
    protected function redirect($url)
    {
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Redirect to route
     *
     * @param string $route Route name
     * @param array $params Route parameters
     * @return void
     */
    protected function redirectToRoute($route, $params = [])
    {
        $url = Router::url($route, $params);
        $this->redirect($url);
    }
    
    /**
     * JSON response
     *
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @return void
     */
    protected function json($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
    
    /**
     * Get request method
     *
     * @return string Request method
     */
    protected function getMethod()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }
    
    /**
     * Check if request method is GET
     *
     * @return bool Whether request method is GET
     */
    protected function isGet()
    {
        return $this->getMethod() === 'GET';
    }
    
    /**
     * Check if request method is POST
     *
     * @return bool Whether request method is POST
     */
    protected function isPost()
    {
        return $this->getMethod() === 'POST';
    }
    
    /**
     * Check if request method is PUT
     *
     * @return bool Whether request method is PUT
     */
    protected function isPut()
    {
        return $this->getMethod() === 'PUT';
    }
    
    /**
     * Check if request method is DELETE
     *
     * @return bool Whether request method is DELETE
     */
    protected function isDelete()
    {
        return $this->getMethod() === 'DELETE';
    }
    
    /**
     * Check if request is AJAX
     *
     * @return bool Whether request is AJAX
     */
    protected function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get request data
     *
     * @param string $method Request method
     * @return array Request data
     */
    protected function getRequestData($method = null)
    {
        $method = $method ?: $this->getMethod();
        
        switch ($method) {
            case 'GET':
                return $_GET;
            
            case 'POST':
                return $_POST;
            
            case 'PUT':
            case 'DELETE':
                parse_str(file_get_contents('php://input'), $data);
                return $data;
            
            default:
                return [];
        }
    }
    
    /**
     * Get request body as JSON
     *
     * @return array JSON data
     */
    protected function getJsonBody()
    {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?: [];
    }
    
    /**
     * Upload file
     *
     * @param string $fieldName The name of the file field
     * @param string $directory The directory to upload to
     * @param array $allowedTypes Allowed file types
     * @param int $maxSize Maximum file size in bytes
     * @return string|false The file path or false on failure
     */
    public function uploadFile($fieldName, $directory = 'uploads', $allowedTypes = [], $maxSize = 2097152) {
        // Check if file was uploaded
        if(!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        $file = $_FILES[$fieldName];
        
        // Check file size
        if($file['size'] > $maxSize) {
            return false;
        }
        
        // Check file type if specified
        if(!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if(!in_array($fileType, $allowedTypes)) {
                return false;
            }
        }
        
        // Create directory if it doesn't exist
        $uploadDir = PUBLIC_PATH . '/' . $directory;
        if(!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . $file['name'];
        $destination = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if(move_uploaded_file($file['tmp_name'], $destination)) {
            return $directory . '/' . $filename;
        }
        
        return false;
    }
} 