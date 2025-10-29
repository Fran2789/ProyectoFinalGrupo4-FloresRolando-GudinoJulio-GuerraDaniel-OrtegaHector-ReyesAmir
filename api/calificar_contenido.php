<?php
/*
 * API REST - Calificar Contenido (Películas/Series)
 
 */

// Incluir configuración de API
require_once __DIR__ . '/config_api.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

try {
    // Validar autenticación para todos los métodos
    ApiValidator::requireAuth();
    
    // Inicializar managers
    $contenidoManager = new ContenidoManager();
    $usuarioManager = new UsuarioManager();
    
    // Verificar sesión del usuario
    if (!$usuarioManager->verificarSesion()) {
        ApiResponse::error(
            'Sesión expirada',
            'SESSION_EXPIRED',
            401
        );
    }
    
    $metodo = $_SERVER['REQUEST_METHOD'];
    
    switch ($metodo) {
        
        case 'GET':
            manejarObtenerCalificaciones($contenidoManager, $usuarioManager);
            break;
            
        case 'POST':
            manejarCrearCalificacion($contenidoManager, $usuarioManager);
            break;
            
        case 'PUT':
            manejarActualizarCalificacion($contenidoManager, $usuarioManager);
            break;
            
        case 'DELETE':
            manejarEliminarCalificacion($contenidoManager, $usuarioManager);
            break;
            
        default:
            ApiResponse::error(
                'Método no soportado',
                'METHOD_NOT_SUPPORTED',
                405,
                ['metodos_soportados' => ['GET', 'POST', 'PUT', 'DELETE']]
            );
    }
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en calificar_contenido.php: " . $e->getMessage());
    
    ApiResponse::error(
        'Error interno del servidor',
        'INTERNAL_ERROR',
        500
    );
}

/**
 * Manejar GET - Obtener calificaciones de un contenido
 */
function manejarObtenerCalificaciones($contenidoManager, $usuarioManager) {
    // Validar parámetros requeridos
    if (!isset($_GET['contenido_id'])) {
        ApiResponse::error(
            'contenido_id es requerido',
            'MISSING_CONTENT_ID',
            400
        );
    }
    
    $contenido_id = ApiValidator::validateId($_GET['contenido_id'], 'contenido_id');
    $usuario_id = isset($_GET['usuario_id']) ? ApiValidator::validateId($_GET['usuario_id'], 'usuario_id') : null;
    $limite = isset($_GET['limite']) ? min(50, max(1, intval($_GET['limite']))) : 10;
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $incluir_estadisticas = isset($_GET['incluir_estadisticas']) ? filter_var($_GET['incluir_estadisticas'], FILTER_VALIDATE_BOOLEAN) : true;
    
    // Verificar que el contenido existe
    $contenido = $contenidoManager->obtenerDetalleContenido($contenido_id);
    if (!$contenido) {
        ApiResponse::error(
            'Contenido no encontrado',
            'CONTENT_NOT_FOUND',
            404
        );
    }
    
    // Verificar permisos para usuario específico
    if ($usuario_id && $_SESSION['user_id'] != $usuario_id && $_SESSION['user_role'] !== 'admin') {
        ApiResponse::error(
            'No tienes permiso para ver calificaciones de otro usuario',
            'FORBIDDEN',
            403
        );
    }
    
    // Obtener calificaciones
    $calificaciones = $contenidoManager->obtenerCalificaciones($contenido_id, $limite * 10); // Obtener más para paginación
    
    // Filtrar por usuario si se especifica
    if ($usuario_id) {
        $calificaciones = array_filter($calificaciones, function($cal) use ($usuario_id) {
            return $cal['usuario_id'] == $usuario_id;
        });
    }
    
    // Aplicar paginación
    $total_items = count($calificaciones);
    $offset = ($pagina - 1) * $limite;
    $calificaciones_paginadas = array_slice($calificaciones, $offset, $limite);
    
    // Enriquecer datos de calificaciones
    $calificaciones_enriquecidas = [];
    foreach ($calificaciones_paginadas as $cal) {
        $cal_enriquecida = $cal;
        $cal_enriquecida['tiempo_transcurrido'] = calcularTiempoTranscurrido($cal['fecha_calificacion']);
        $cal_enriquecida['es_del_usuario_actual'] = ($cal['usuario_id'] == $_SESSION['user_id']);
        $calificaciones_enriquecidas[] = $cal_enriquecida;
    }
    
    // Preparar respuesta
    $response_data = [
        'calificaciones' => $calificaciones_enriquecidas,
        'contenido' => [
            'id' => $contenido['id'],
            'titulo' => $contenido['titulo'],
            'tipo' => $contenido['tipo'],
            'calificacion_promedio' => floatval($contenido['calificacion'])
        ],
        'paginacion' => [
            'pagina_actual' => $pagina,
            'limite' => $limite,
            'total_items' => $total_items,
            'total_paginas' => ceil($total_items / $limite)
        ]
    ];
    
    // Agregar estadísticas si se solicita
    if ($incluir_estadisticas) {
        $response_data['estadisticas'] = calcularEstadisticasCalificacion($calificaciones);
        
        // Verificar si el usuario actual ya calificó este contenido
        $calificacion_usuario = $contenidoManager->obtenerCalificacionUsuario($_SESSION['user_id'], $contenido_id);
        $response_data['calificacion_usuario_actual'] = $calificacion_usuario ?: null;
    }
    
    ApiResponse::success($response_data, 'Calificaciones obtenidas correctamente');
}

/**
 * Manejar POST - Crear nueva calificación
 */
function manejarCrearCalificacion($contenidoManager, $usuarioManager) {
    ApiValidator::requireJson();
    $data = getJsonInput();
    
    // Validar parámetros requeridos
    ApiValidator::requireParams($data, ['contenido_id', 'calificacion']);
    
    $contenido_id = ApiValidator::validateId($data['contenido_id'], 'contenido_id');
    $calificacion = intval($data['calificacion']);
    $comentario = isset($data['comentario']) ? sanitize_input($data['comentario']) : '';
    
    // Validar calificación
    if ($calificacion < 1 || $calificacion > 5) {
        ApiResponse::error(
            'La calificación debe estar entre 1 y 5',
            'INVALID_RATING',
            400
        );
    }
    
    // Validar longitud del comentario
    if (strlen($comentario) > 1000) {
        ApiResponse::error(
            'El comentario no puede exceder 1000 caracteres',
            'COMMENT_TOO_LONG',
            400
        );
    }
    
    // Verificar que el contenido existe
    $contenido = $contenidoManager->obtenerDetalleContenido($contenido_id);
    if (!$contenido) {
        ApiResponse::error(
            'Contenido no encontrado',
            'CONTENT_NOT_FOUND',
            404
        );
    }
    
    // Verificar si ya existe una calificación del usuario para este contenido
    $calificacion_existente = $contenidoManager->obtenerCalificacionUsuario($_SESSION['user_id'], $contenido_id);
    if ($calificacion_existente) {
        ApiResponse::error(
            'Ya has calificado este contenido. Usa PUT para actualizarlo',
            'RATING_ALREADY_EXISTS',
            409,
            ['calificacion_existente' => $calificacion_existente]
        );
    }
    
    // Guardar calificación
    $resultado = $contenidoManager->guardarCalificacion(
        $_SESSION['user_id'],
        $contenido_id,
        $calificacion,
        $comentario
    );
    
    if ($resultado) {
        // Obtener contenido actualizado para devolver nueva calificación promedio
        $contenido_actualizado = $contenidoManager->obtenerDetalleContenido($contenido_id);
        $calificacion_guardada = $contenidoManager->obtenerCalificacionUsuario($_SESSION['user_id'], $contenido_id);
        
        // Actualizar estadísticas del usuario
        $estadisticas_usuario = $usuarioManager->obtenerEstadisticasUsuario($_SESSION['user_id']);
        
        ApiResponse::success([
            'calificacion' => $calificacion_guardada,
            'contenido' => [
                'id' => $contenido_actualizado['id'],
                'titulo' => $contenido_actualizado['titulo'],
                'calificacion_promedio_anterior' => floatval($contenido['calificacion']),
                'calificacion_promedio_nueva' => floatval($contenido_actualizado['calificacion'])
            ],
            'usuario' => [
                'calificaciones_totales' => $estadisticas_usuario['calificaciones_dadas'],
                'calificacion_promedio' => $estadisticas_usuario['calificacion_promedio']
            ]
        ], 'Calificación guardada correctamente', 201);
        
    } else {
        ApiResponse::error(
            'Error al guardar la calificación',
            'SAVE_ERROR',
            500
        );
    }
}

/**
 * Manejar PUT - Actualizar calificación existente
 */
function manejarActualizarCalificacion($contenidoManager, $usuarioManager) {
    ApiValidator::requireJson();
    $data = getJsonInput();
    
    // Validar parámetros requeridos
    ApiValidator::requireParams($data, ['contenido_id', 'calificacion']);
    
    $contenido_id = ApiValidator::validateId($data['contenido_id'], 'contenido_id');
    $calificacion = intval($data['calificacion']);
    $comentario = isset($data['comentario']) ? sanitize_input($data['comentario']) : '';
    
    // Validar calificación
    if ($calificacion < 1 || $calificacion > 5) {
        ApiResponse::error(
            'La calificación debe estar entre 1 y 5',
            'INVALID_RATING',
            400
        );
    }
    
    // Validar longitud del comentario
    if (strlen($comentario) > 1000) {
        ApiResponse::error(
            'El comentario no puede exceder 1000 caracteres',
            'COMMENT_TOO_LONG',
            400
        );
    }
    
    // Verificar que el contenido existe
    $contenido = $contenidoManager->obtenerDetalleContenido($contenido_id);
    if (!$contenido) {
        ApiResponse::error(
            'Contenido no encontrado',
            'CONTENT_NOT_FOUND',
            404
        );
    }
    
    // Verificar que existe una calificación previa del usuario
    $calificacion_existente = $contenidoManager->obtenerCalificacionUsuario($_SESSION['user_id'], $contenido_id);
    if (!$calificacion_existente) {
        ApiResponse::error(
            'No tienes una calificación previa para este contenido. Usa POST para crear una nueva',
            'RATING_NOT_FOUND',
            404
        );
    }
    
    // Actualizar calificación
    $resultado = $contenidoManager->guardarCalificacion(
        $_SESSION['user_id'],
        $contenido_id,
        $calificacion,
        $comentario
    );
    
    if ($resultado) {
        // Obtener contenido actualizado
        $contenido_actualizado = $contenidoManager->obtenerDetalleContenido($contenido_id);
        $calificacion_actualizada = $contenidoManager->obtenerCalificacionUsuario($_SESSION['user_id'], $contenido_id);
        
        ApiResponse::success([
            'calificacion_anterior' => $calificacion_existente,
            'calificacion_nueva' => $calificacion_actualizada,
            'contenido' => [
                'id' => $contenido_actualizado['id'],
                'titulo' => $contenido_actualizado['titulo'],
                'calificacion_promedio_anterior' => floatval($contenido['calificacion']),
                'calificacion_promedio_nueva' => floatval($contenido_actualizado['calificacion'])
            ]
        ], 'Calificación actualizada correctamente');
        
    } else {
        ApiResponse::error(
            'Error al actualizar la calificación',
            'UPDATE_ERROR',
            500
        );
    }
}

/**
 * Manejar DELETE - Eliminar calificación
 */
function manejarEliminarCalificacion($contenidoManager, $usuarioManager) {
    ApiValidator::requireJson();
    $data = getJsonInput();
    
    // Validar parámetros requeridos
    ApiValidator::requireParams($data, ['contenido_id']);
    
    $contenido_id = ApiValidator::validateId($data['contenido_id'], 'contenido_id');
    
    // Verificar que el contenido existe
    $contenido = $contenidoManager->obtenerDetalleContenido($contenido_id);
    if (!$contenido) {
        ApiResponse::error(
            'Contenido no encontrado',
            'CONTENT_NOT_FOUND',
            404
        );
    }
    
    // Verificar que existe una calificación del usuario
    $calificacion_existente = $contenidoManager->obtenerCalificacionUsuario($_SESSION['user_id'], $contenido_id);
    if (!$calificacion_existente) {
        ApiResponse::error(
            'No tienes una calificación para este contenido',
            'RATING_NOT_FOUND',
            404
        );
    }
    
    try {
        // Eliminar calificación (implementar en ContenidoManager si no existe)
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "DELETE FROM calificaciones WHERE usuario_id = :usuario_id AND contenido_id = :contenido_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":usuario_id", $_SESSION['user_id']);
        $stmt->bindParam(":contenido_id", $contenido_id);
        
        if ($stmt->execute()) {
            // Recalcular calificación promedio del contenido
            $sql_update = "UPDATE contenido 
                          SET calificacion = (
                              SELECT COALESCE(AVG(calificacion), 0) 
                              FROM calificaciones 
                              WHERE contenido_id = :contenido_id
                          )
                          WHERE id = :contenido_id";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bindParam(":contenido_id", $contenido_id);
            $stmt_update->execute();
            
            // Obtener contenido actualizado
            $contenido_actualizado = $contenidoManager->obtenerDetalleContenido($contenido_id);
            
            ApiResponse::success([
                'calificacion_eliminada' => $calificacion_existente,
                'contenido' => [
                    'id' => $contenido_actualizado['id'],
                    'titulo' => $contenido_actualizado['titulo'],
                    'calificacion_promedio_anterior' => floatval($contenido['calificacion']),
                    'calificacion_promedio_nueva' => floatval($contenido_actualizado['calificacion'])
                ]
            ], 'Calificación eliminada correctamente');
            
        } else {
            ApiResponse::error(
                'Error al eliminar la calificación',
                'DELETE_ERROR',
                500
            );
        }
        
    } catch (Exception $e) {
        error_log("Error al eliminar calificación: " . $e->getMessage());
        ApiResponse::error(
            'Error interno al eliminar calificación',
            'DELETE_ERROR',
            500
        );
    }
}

/**
 * Calcular tiempo transcurrido desde una fecha
 */
function calcularTiempoTranscurrido($fecha) {
    $tiempo = time() - strtotime($fecha);
    
    if ($tiempo < 60) {
        return 'hace un momento';
    } elseif ($tiempo < 3600) {
        $minutos = floor($tiempo / 60);
        return "hace {$minutos} minuto" . ($minutos > 1 ? 's' : '');
    } elseif ($tiempo < 86400) {
        $horas = floor($tiempo / 3600);
        return "hace {$horas} hora" . ($horas > 1 ? 's' : '');
    } elseif ($tiempo < 2592000) { // 30 días
        $dias = floor($tiempo / 86400);
        return "hace {$dias} día" . ($dias > 1 ? 's' : '');
    } else {
        return date('d/m/Y', strtotime($fecha));
    }
}

/**
 * Calcular estadísticas de calificaciones
 */
function calcularEstadisticasCalificacion($calificaciones) {
    if (empty($calificaciones)) {
        return [
            'total_calificaciones' => 0,
            'calificacion_promedio' => 0,
            'distribucion' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'porcentaje_positivas' => 0
        ];
    }
    
    $total = count($calificaciones);
    $suma = array_sum(array_column($calificaciones, 'calificacion'));
    $promedio = $suma / $total;
    
    // Calcular distribución
    $distribucion = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    foreach ($calificaciones as $cal) {
        $distribucion[intval($cal['calificacion'])]++;
    }
    
    // Calcular porcentaje de calificaciones positivas (4+ estrellas)
    $positivas = $distribucion[4] + $distribucion[5];
    $porcentaje_positivas = ($positivas / $total) * 100;
    
    return [
        'total_calificaciones' => $total,
        'calificacion_promedio' => round($promedio, 1),
        'distribucion' => $distribucion,
        'porcentaje_positivas' => round($porcentaje_positivas, 1),
        'calificaciones_con_comentario' => count(array_filter($calificaciones, function($cal) {
            return !empty($cal['comentario']);
        }))
    ];
}
?>