<?php
/**
 * Configuración general para APIs REST
 * Este archivo contiene funciones y configuraciones comunes para todos los endpoints
 */

// Configuración de API
define('API_VERSION', '1.0');
define('API_MAX_REQUESTS_PER_MINUTE', 60);
define('API_MAX_PAYLOAD_SIZE', 1048576); // 1MB

/**
 * Clase para manejo centralizado de respuestas API
 */
class ApiResponse {
    
    /**
     * Enviar respuesta JSON estándar
     */
    public static function send($data, $httpCode = 200) {
        http_response_code($httpCode);
        
        // Añadir metadatos estándar
        if (!isset($data['meta'])) {
            $data['meta'] = [
                'timestamp' => date('c'), // ISO 8601
                'version' => API_VERSION,
                'execution_time' => self::getExecutionTime()
            ];
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Respuesta de éxito
     */
    public static function success($data = [], $message = null, $httpCode = 200) {
        $response = [
            'success' => true,
            'data' => $data
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        self::send($response, $httpCode);
    }
    
    /**
     * Respuesta de error
     */
    public static function error($message, $code = 'GENERIC_ERROR', $httpCode = 400, $details = null) {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code
            ]
        ];
        
        if ($details) {
            $response['error']['details'] = $details;
        }
        
        self::send($response, $httpCode);
    }
    
    /**
     * Calcular tiempo de ejecución
     */
    private static function getExecutionTime() {
        if (defined('API_START_TIME')) {
            return round((microtime(true) - API_START_TIME) * 1000, 2) . 'ms';
        }
        return null;
    }
}

/**
 * Clase para validaciones comunes
 */
class ApiValidator {
    
    /**
     * Validar autenticación de usuario
     */
    public static function requireAuth() {
        if (!isLoggedIn()) {
            ApiResponse::error('Usuario no autenticado', 'UNAUTHORIZED', 401);
        }
    }
    
    /**
     * Validar rol de administrador
     */
    public static function requireAdmin() {
        self::requireAuth();
        if (!isAdmin()) {
            ApiResponse::error('Permisos de administrador requeridos', 'FORBIDDEN', 403);
        }
    }
    
    /**
     * Validar método HTTP
     */
    public static function requireMethod($method) {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
            ApiResponse::error(
                "Método no permitido. Use {$method}.", 
                'METHOD_NOT_ALLOWED', 
                405
            );
        }
    }
    
    /**
     * Validar Content-Type para JSON
     */
    public static function requireJson() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false) {
            ApiResponse::error(
                'Content-Type debe ser application/json', 
                'INVALID_CONTENT_TYPE', 
                415
            );
        }
    }
    
    /**
     * Validar tamaño del payload
     */
    public static function validatePayloadSize() {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ($contentLength > API_MAX_PAYLOAD_SIZE) {
            ApiResponse::error(
                'Payload demasiado grande', 
                'PAYLOAD_TOO_LARGE', 
                413
            );
        }
    }
    
    /**
     * Validar parámetros requeridos
     */
    public static function requireParams($data, $requiredParams) {
        $missing = [];
        
        foreach ($requiredParams as $param) {
            if (!isset($data[$param]) || empty($data[$param])) {
                $missing[] = $param;
            }
        }
        
        if (!empty($missing)) {
            ApiResponse::error(
                'Parámetros requeridos faltantes',
                'MISSING_PARAMETERS',
                400,
                ['missing_params' => $missing]
            );
        }
    }
    
    /**
     * Validar ID numérico
     */
    public static function validateId($id, $paramName = 'id') {
        if (!is_numeric($id) || $id <= 0) {
            ApiResponse::error(
                "El parámetro {$paramName} debe ser un número entero positivo",
                'INVALID_ID',
                400
            );
        }
        return intval($id);
    }
}

/**
 * Clase para rate limiting básico
 */
class ApiRateLimit {
    
    /**
     * Verificar rate limit por IP
     */
    public static function check() {
        $ip = self::getClientIp();
        $key = "api_rate_limit_{$ip}";
        
        // Usar sesión para rate limiting simple (en producción usar Redis/Memcached)
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'window_start' => time()
            ];
        }
        
        $current_time = time();
        $rate_data = $_SESSION[$key];
        
        // Reset si pasó 1 minuto
        if ($current_time - $rate_data['window_start'] >= 60) {
            $_SESSION[$key] = [
                'count' => 1,
                'window_start' => $current_time
            ];
            return true;
        }
        
        // Incrementar contador
        $_SESSION[$key]['count']++;
        
        // Verificar límite
        if ($_SESSION[$key]['count'] > API_MAX_REQUESTS_PER_MINUTE) {
            ApiResponse::error(
                'Demasiadas peticiones. Intenta de nuevo en un minuto.',
                'RATE_LIMIT_EXCEEDED',
                429,
                [
                    'limit' => API_MAX_REQUESTS_PER_MINUTE,
                    'window' => 60,
                    'reset_time' => $rate_data['window_start'] + 60
                ]
            );
        }
        
        return true;
    }
    
    /**
     * Obtener IP del cliente
     */
    private static function getClientIp() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_FORWARDED_FOR',    // Proxy/Load Balancer
            'HTTP_X_REAL_IP',          // Nginx
            'REMOTE_ADDR'              // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return '0.0.0.0';
    }
}

/**
 * Función para leer JSON del request body
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        ApiResponse::error('No se recibieron datos JSON', 'EMPTY_PAYLOAD', 400);
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        ApiResponse::error(
            'JSON inválido: ' . json_last_error_msg(),
            'INVALID_JSON',
            400
        );
    }
    
    return $data;
}

/**
 * Configurar headers CORS estándar
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400'); // 24 horas
}

/**
 * Manejar preflight OPTIONS request
 */
function handlePreflight() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCorsHeaders();
        http_response_code(200);
        exit();
    }
}

/**
 * Inicialización de API
 */
function initApi() {
    // Marcar tiempo de inicio
    define('API_START_TIME', microtime(true));
    
    // Configurar headers
    header('Content-Type: application/json; charset=utf-8');
    setCorsHeaders();
    
    // Manejar preflight
    handlePreflight();
    
    // Verificar rate limit
    ApiRateLimit::check();
    
    // Validar tamaño del payload
    ApiValidator::validatePayloadSize();
}

/**
 * Middleware de logging para APIs
 */
function logApiRequest() {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'ip' => ApiRateLimit::getClientIp(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'user_id' => $_SESSION['user_id'] ?? null
    ];
    
    error_log("API Request: " . json_encode($log_data));
}

// Auto-inicialización si este archivo es incluido
if (!defined('API_MANUAL_INIT')) {
    initApi();
    logApiRequest();
}
?>