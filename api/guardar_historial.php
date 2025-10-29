<?php
/**
 * API Endpoint para guardar historial de navegación
 * Método: POST
 * Content-Type: application/json
 * 
 * Parámetros esperados:
 * - usuario_id: ID del usuario (integer)
 * - contenido_id: ID del contenido visitado (integer)
 */

// Incluir configuración de API
require_once __DIR__ . '/config_api.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

try {
    // Validar método y autenticación
    ApiValidator::requireMethod('POST');
    ApiValidator::requireAuth();
    
    // Leer y validar datos JSON
    $data = getJsonInput();
    
    // Validar parámetros requeridos
    ApiValidator::requireParams($data, ['usuario_id', 'contenido_id']);
    
    $usuario_id = ApiValidator::validateId($data['usuario_id'], 'usuario_id');
    $contenido_id = ApiValidator::validateId($data['contenido_id'], 'contenido_id');
    
    // Verificar que el usuario autenticado coincida con el usuario_id del request
    if ($_SESSION['user_id'] != $usuario_id) {
        ApiResponse::error(
            'No tienes permiso para guardar historial de otro usuario',
            'FORBIDDEN',
            403
        );
    }
    
    // Inicializar managers
    $contenidoManager = new ContenidoManager();
    $usuarioManager = new UsuarioManager();
    
    // Verificar que el contenido existe
    $contenido = $contenidoManager->obtenerDetalleContenido($contenido_id);
    if (!$contenido) {
        ApiResponse::error(
            'El contenido especificado no existe',
            'CONTENT_NOT_FOUND',
            404
        );
    }
    
    // Verificar que el usuario existe y está activo
    $usuario = $usuarioManager->obtenerUsuario($usuario_id);
    if (!$usuario || !$usuario['activo']) {
        ApiResponse::error(
            'Usuario no válido o inactivo',
            'USER_INVALID',
            400
        );
    }
    
    // Verificar sesión del usuario
    if (!$usuarioManager->verificarSesion()) {
        ApiResponse::error(
            'Sesión expirada',
            'SESSION_EXPIRED',
            401
        );
    }
    
    // Guardar en historial
    $resultado = $contenidoManager->guardarHistorial($usuario_id, $contenido_id);
    
    if ($resultado) {
        // Obtener estadísticas actualizadas del usuario
        $estadisticas = $usuarioManager->obtenerEstadisticasUsuario($usuario_id);
        
        ApiResponse::success([
            'usuario_id' => $usuario_id,
            'contenido_id' => $contenido_id,
            'contenido_titulo' => $contenido['titulo'],
            'contenido_tipo' => $contenido['tipo'],
            'estadisticas_usuario' => $estadisticas
        ], 'Historial guardado correctamente', 201);
        
    } else {
        ApiResponse::error(
            'Error interno al guardar el historial',
            'SAVE_ERROR',
            500
        );
    }
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en guardar_historial.php: " . $e->getMessage());
    
    ApiResponse::error(
        'Error interno del servidor',
        'INTERNAL_ERROR',
        500
    );
}
?>