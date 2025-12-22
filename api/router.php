<?php
/**
 * UniPanel API Router
 * 
 * RESTful API routing sistemi
 * Tüm API istekleri bu dosyadan geçer
 * 
 * Kullanım:
 * - GET /api/v1/communities -> CommunitiesController@index
 * - GET /api/v1/users/me -> UsersController@me
 */

require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/Sanitizer.php';

// CORS Headers
header('Content-Type: application/json; charset=utf-8');
setSecureCORS();
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Request-ID, X-Request-Timestamp');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// OPTIONS request için hemen cevap ver
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// register_2fa.php kaldırıldı - kayıt altyapısı kaldırıldı

/**
 * API Response Helper
 */
class APIResponse {
    public static function success($data = null, $message = null, $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'error' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function error($error, $code = 400, $data = null) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'data' => $data,
            'message' => null,
            'error' => $error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function unauthorized($message = 'Yetkilendirme gerekli') {
        self::error($message, 401);
    }
    
    public static function notFound($message = 'Kaynak bulunamadı') {
        self::error($message, 404);
    }
    
    public static function methodNotAllowed($message = 'Method izin verilmiyor') {
        self::error($message, 405);
    }
}

/**
 * Route Definition
 */
class Route {
    public $method;
    public $path;
    public $controller;
    public $action;
    public $middleware;
    
    public function __construct($method, $path, $controller, $action, $middleware = []) {
        $this->method = $method;
        $this->path = $path;
        $this->controller = $controller;
        $this->action = $action;
        $this->middleware = $middleware;
    }
}

/**
 * Router Class
 */
class Router {
    private $routes = [];
    private $basePath = '/api/v1';
    
    public function addRoute($method, $path, $controller, $action, $middleware = []) {
        $this->routes[] = new Route($method, $path, $controller, $action, $middleware);
    }
    
    public function get($path, $controller, $action, $middleware = []) {
        $this->addRoute('GET', $path, $controller, $action, $middleware);
    }
    
    public function post($path, $controller, $action, $middleware = []) {
        $this->addRoute('POST', $path, $controller, $action, $middleware);
    }
    
    public function put($path, $controller, $action, $middleware = []) {
        $this->addRoute('PUT', $path, $controller, $action, $middleware);
    }
    
    public function delete($path, $controller, $action, $middleware = []) {
        $this->addRoute('DELETE', $path, $controller, $action, $middleware);
    }
    
    private function matchRoute($method, $path) {
        foreach ($this->routes as $route) {
            if ($route->method !== $method) {
                continue;
            }
            
            // Try with base path
            $routePath = $this->basePath . $route->path;
            if ($routePath === $path || $this->matchPath($routePath, $path)) {
                return $route;
            }
            
            // Try without base path (for direct access)
            if ($route->path === $path || $this->matchPath($route->path, $path)) {
                return $route;
            }
            
            // Try with /api/v1 prefix
            $routePathAlt = '/api/v1' . $route->path;
            if ($routePathAlt === $path || $this->matchPath($routePathAlt, $path)) {
                return $route;
            }
        }
        return null;
    }
    
    private function matchPath($pattern, $path) {
        // Convert /users/{id} to regex
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        return preg_match($pattern, $path);
    }
    
    private function extractParams($pattern, $path) {
        $params = [];
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));
        
        foreach ($patternParts as $index => $part) {
            if (preg_match('/\{(\w+)\}/', $part, $matches)) {
                $paramName = $matches[1];
                $params[$paramName] = $pathParts[$index] ?? null;
            }
        }
        
        return $params;
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        
        // register_2fa.php kaldırıldı - kayıt altyapısı kaldırıldı
        
        // Remove base path if exists
        $path = str_replace('/fourkampus', '', $path);
        $path = str_replace('/api/v1', '', $path); // Remove /api/v1 from path
        
        // Debug: Log the path
        error_log("Router: Method=$method, Path=$path");
        
        $route = $this->matchRoute($method, $path);
        
        if (!$route) {
            // Try without base path
            $pathWithoutBase = str_replace($this->basePath, '', $path);
            $route = $this->matchRoute($method, $pathWithoutBase);
        }
        
        if (!$route) {
            APIResponse::notFound('Endpoint bulunamadı: ' . $path . ' (Method: ' . $method . ')');
        }
        
        // Extract route parameters
        // dispatch() içinde /api/v1 prefix'i path'ten çıkarıldığı için
        // param extraction route->path üzerinden yapılmalı (aksi halde index kayar)
        $params = $this->extractParams($route->path, $path);
        
        // Run middleware
        foreach ($route->middleware as $middleware) {
            $this->runMiddleware($middleware);
        }
        
        // Load controller
        $controllerFile = __DIR__ . '/controllers/' . $route->controller . '.php';
        if (!file_exists($controllerFile)) {
            APIResponse::error('Controller bulunamadı: ' . $route->controller, 500);
        }
        
        require_once $controllerFile;
        $controllerClass = $route->controller;
        
        if (!class_exists($controllerClass)) {
            APIResponse::error('Controller class bulunamadı: ' . $controllerClass, 500);
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $route->action)) {
            APIResponse::error('Action bulunamadı: ' . $route->action, 500);
        }
        
        // Call controller action
        try {
            $controller->{$route->action}($params);
        } catch (Exception $e) {
            error_log("Router Error: " . $e->getMessage());
            APIResponse::error('Sunucu hatası: ' . $e->getMessage(), 500);
        }
    }
    
    private function runMiddleware($middleware) {
        $middlewareFile = __DIR__ . '/middleware/' . $middleware . '.php';
        if (!file_exists($middlewareFile)) {
            return; // Middleware optional
        }
        
        require_once $middlewareFile;
        $middlewareClass = $middleware;
        
        if (class_exists($middlewareClass)) {
            $middlewareInstance = new $middlewareClass();
            if (method_exists($middlewareInstance, 'handle')) {
                $middlewareInstance->handle();
            }
        }
    }
}

// Initialize router
$router = new Router();

// ============================================
// ROUTE DEFINITIONS
// ============================================

// Auth Routes
$router->post('/auth/login', 'AuthController', 'login');
// register_2fa route kaldırıldı - kayıt altyapısı kaldırıldı
$router->post('/auth/logout', 'AuthController', 'logout', ['AuthMiddleware']);
$router->get('/auth/me', 'AuthController', 'me', ['AuthMiddleware']);

// Communities Routes
$router->get('/communities', 'CommunitiesController', 'index');
$router->get('/communities/{id}', 'CommunitiesController', 'show');
$router->post('/communities/{id}/join', 'CommunitiesController', 'join', ['AuthMiddleware']);

// Events Routes
$router->get('/events', 'EventsController', 'index');
$router->get('/events/{id}', 'EventsController', 'show');
$router->post('/events/{id}/rsvp', 'EventsController', 'rsvp', ['AuthMiddleware']);

// Users Routes
$router->get('/users/me', 'UsersController', 'me', ['AuthMiddleware']);
$router->put('/users/me', 'UsersController', 'update', ['AuthMiddleware']);

// Dispatch request
$router->dispatch();
