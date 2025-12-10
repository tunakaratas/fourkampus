<?php
/**
 * UniPanel Autoloader
 * PSR-4 compatible autoloader
 */

// Autoloader function for namespaces
spl_autoload_register(function ($class) {
    // Base directory
    $baseDir = __DIR__ . '/';
    
    // Convert namespace to directory path
    if (strpos($class, '\\') !== false) {
        $parts = explode('\\', $class);
        
        // UniPanel namespace mapping
        if (isset($parts[0]) && $parts[0] === 'UniPanel') {
            array_shift($parts); // Remove 'UniPanel'
            
            if (isset($parts[0])) {
                $type = strtolower($parts[0]); // core, models, general
                
                // Build file path
                if (isset($parts[1])) {
                    $file = $baseDir . $type . '/' . $parts[1] . '.php';
                } else {
                    $file = $baseDir . $type . '.php';
                }
            } else {
                $file = $baseDir . implode('/', $parts) . '.php';
            }
        } else {
            $file = $baseDir . str_replace('\\', '/', $class) . '.php';
        }
    } else {
        // Simple class name
        $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    }
    
    // If file exists, require it
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

/**
 * Namespace mapping for old-style includes
 */
class UniPanelAutoloader {
    private static $map = [
        'Database' => 'core/Database.php',
        'Event' => 'models/Event.php',
        'Member' => 'models/Member.php',
        'Notification' => 'models/Notification.php',
        'SecurityHelper' => 'general/security_helper.php',
        'PasswordManager' => 'general/password_manager.php',
        'InputValidator' => 'general/input_validator.php',
        'SessionSecurity' => 'general/session_security.php',
        'PHPMailer' => 'general/PHPMailer.php',
    ];
    
    public static function getPath($class) {
        return isset(self::$map[$class]) ? __DIR__ . '/' . self::$map[$class] : null;
    }
    
    public static function load($class) {
        $path = self::getPath($class);
        if ($path && file_exists($path)) {
            require_once $path;
            return true;
        }
        return false;
    }
}

// Register manual loader for backward compatibility
spl_autoload_register(function ($class) {
    return UniPanelAutoloader::load($class);
});

// Helper function to load all core classes
function load_unipanel_core($dbPath = 'unipanel.sqlite') {
    // Load database
    require_once __DIR__ . '/core/Database.php';
    
    // Load models
    require_once __DIR__ . '/models/Event.php';
    require_once __DIR__ . '/models/Member.php';
    require_once __DIR__ . '/models/Notification.php';
    
    // Return database instance
    return \UniPanel\Core\Database::getInstance($dbPath);
}

// Helper function to load security helpers
function load_unipanel_security() {
    require_once __DIR__ . '/general/security_helper.php';
    require_once __DIR__ . '/general/password_manager.php';
    require_once __DIR__ . '/general/input_validator.php';
    require_once __DIR__ . '/general/session_security.php';
}

// Helper function to create model instances
function create_model_instance($modelName, $database, $clubId = 1) {
    $classMap = [
        'event' => \UniPanel\Models\Event::class,
        'member' => \UniPanel\Models\Member::class,
        'notification' => \UniPanel\Models\Notification::class,
    ];
    
    $className = isset($classMap[strtolower($modelName)]) 
        ? $classMap[strtolower($modelName)] 
        : '\\UniPanel\\Models\\' . ucfirst($modelName);
    
    if (class_exists($className)) {
        return new $className($database->getDb(), $clubId);
    }
    
    throw new \Exception("Model class not found: $className");
}

