<?php
/**
 * API Endpoint para obtener historial de navegación del usuario
 * Método: GET
 * Content-Type: application/json
 * 
 * Parámetros URL esperados:
 * - usuario_id: ID del usuario (integer) - opcional si está en sesión
 * - limite: Número máximo de resultados (integer, default: 20, max: 100)
 * - pagina: Página para paginación (integer, default: 1)
 */

// Headers para API REST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Use GET.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

// Incluir dependencias
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Función para validar que el usuario esté autenticado
function validarAutenticacion() {
    if (!isLoggedIn()) {
        sendJsonResponse([
            'success' => false,
            'error' => 'Usuario no autenticado',
            'code' => 'UNAUTHORIZED'
        ], 401);
    }
}

try {
    // Validar autenticación
    validarAutenticacion();
    
    // Obtener parámetros de la URL
    $usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : $_SESSION['user_id'];
    $limite = isset($_GET['limite']) ? min(100, max(1, intval($_GET['limite']))) : 20;
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    
    // Validar que el usuario solo pueda ver su propio historial (excepto admins)
    if ($_SESSION['user_id'] != $usuario_id && $_SESSION['user_role'] !== 'admin') {
        sendJsonResponse([
            'success' => false,
            'error' => 'No tienes permiso para ver el historial de otro usuario',
            'code' => 'FORBIDDEN'
        ], 403);
    }
    
    // Inicializar managers
    $contenidoManager = new ContenidoManager();
    $usuarioManager = new UsuarioManager();
    
    // Verificar que el usuario existe
    $usuario = $usuarioManager->obtenerUsuario($usuario_id);
    if (!$usuario) {
        sendJsonResponse([
            'success' => false,
            'error' => 'Usuario no encontrado',
            'code' => 'USER_NOT_FOUND'
        ], 404);
    }
    
    // Verificar sesión del usuario
    if (!$usuarioManager->verificarSesion()) {
        sendJsonResponse([
            'success' => false,
            'error' => 'Sesión expirada',
            'code' => 'SESSION_EXPIRED'
        ], 401);
    }
    
    // Obtener historial del usuario
    $historial = $contenidoManager->obtenerHistorialUsuario($usuario_id, $limite);
    
    // Calcular información de paginación (simplificada)
    $total_items = count($historial);
    $offset = ($pagina - 1) * $limite;
    $historial_paginado = array_slice($historial, $offset, $limite);
    
    // Obtener estadísticas del usuario
    $estadisticas = $usuarioManager->obtenerEstadisticasUsuario($usuario_id);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'data' => [
            'historial' => $historial_paginado,
            'usuario' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'estadisticas' => $estadisticas
            ],
            'paginacion' => [
                'pagina_actual' => $pagina,
                'limite' => $limite,
                'total_items' => $total_items,
                'total_paginas' => ceil($total_items / $limite)
            ]
        ],
        'meta' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0'
        ]
    ];
    
    sendJsonResponse($response);
    
} catch (PDOException $e) {
    // Error de base de datos
    error_log("Error PDO en obtener_historial.php: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'error' => 'Error de base de datos',
        'code' => 'DATABASE_ERROR'
    ], 500);
    
} catch (Exception $e) {
    // Error general
    error_log("Error general en obtener_historial.php: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'error' => 'Error interno del servidor',
        'code' => 'INTERNAL_ERROR'
    ], 500);
}
?>