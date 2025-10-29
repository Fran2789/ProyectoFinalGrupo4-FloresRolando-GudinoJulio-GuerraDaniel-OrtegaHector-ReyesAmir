<?php
/**
 * API REST - Gestionar Lista de Favoritos
 * Métodos: GET, POST, DELETE
 * Content-Type: application/json
 * 
 * === MÉTODOS SOPORTADOS ===
 * 
 * GET - Obtener lista de favoritos del usuario
 * Parámetros URL:
 * - usuario_id: ID del usuario específico (integer, opcional - solo admins, default: usuario actual)
 * - tipo: Filtrar por tipo de contenido (string: 'pelicula', 'serie', 'todos', default: 'todos')
 * - genero_id: Filtrar por género específico (integer)
 * - orden: Campo para ordenar (string: 'fecha_agregado', 'titulo', 'calificacion', 'año', default: 'fecha_agregado')
 * - direccion: Dirección del orden (string: 'asc', 'desc', default: 'desc')
 * - limite: Número máximo de resultados (integer, default: 20, max: 100)
 * - pagina: Página para paginación (integer, default: 1)
 * - incluir_estadisticas: Incluir estadísticas de favoritos (boolean, default: true)
 * - incluir_recomendaciones: Incluir recomendaciones basadas en favoritos (boolean, default: false)
 * - solo_verificar: Solo verificar si contenido específico está en favoritos (requiere contenido_id)
 * - contenido_id: ID del contenido para verificar (integer, solo con solo_verificar=true)
 * 
 * POST - Agregar contenido a favoritos
 * Body JSON:
 * {
 *   "contenido_id": 1,
 *   "notas": "Mi película favorita de todos los tiempos" (opcional)
 * }
 * 
 * DELETE - Remover contenido de favoritos
 * Body JSON:
 * {
 *   "contenido_id": 1
 * }
 * 
 * === RESPUESTAS JSON ===
 * GET: Lista de favoritos con metadata
 * POST: Confirmación de agregado + estadísticas actualizadas
 * DELETE: Confirmación de eliminación + estadísticas actualizadas
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
            manejarObtenerFavoritos($contenidoManager, $usuarioManager);
            break;
            
        case 'POST':
            manejarAgregarFavorito($contenidoManager, $usuarioManager);
            break;
            
        case 'DELETE':
            manejarEliminarFavorito($contenidoManager, $usuarioManager);
            break;
            
        default:
            ApiResponse::error(
                'Método no soportado',
                'METHOD_NOT_SUPPORTED',
                405,
                ['metodos_soportados' => ['GET', 'POST', 'DELETE']]
            );
    }
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en gestionar_favoritos.php: " . $e->getMessage());
    
    ApiResponse::error(
        'Error interno del servidor',
        'INTERNAL_ERROR',
        500
    );
}

/**
 * Manejar GET - Obtener lista de favoritos
 */
function manejarObtenerFavoritos($contenidoManager, $usuarioManager) {
    // Obtener parámetros
    $usuario_id = isset($_GET['usuario_id']) ? ApiValidator::validateId($_GET['usuario_id'], 'usuario_id') : $_SESSION['user_id'];
    $tipo = isset($_GET['tipo']) ? sanitize_input($_GET['tipo']) : 'todos';
    $genero_id = isset($_GET['genero_id']) ? ApiValidator::validateId($_GET['genero_id'], 'genero_id') : null;
    $orden = isset($_GET['orden']) ? sanitize_input($_GET['orden']) : 'fecha_agregado';
    $direccion = isset($_GET['direccion']) ? sanitize_input($_GET['direccion']) : 'desc';
    $limite = isset($_GET['limite']) ? min(100, max(1, intval($_GET['limite']))) : 20;
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $incluir_estadisticas = isset($_GET['incluir_estadisticas']) ? filter_var($_GET['incluir_estadisticas'], FILTER_VALIDATE_BOOLEAN) : true;
    $incluir_recomendaciones = isset($_GET['incluir_recomendaciones']) ? filter_var($_GET['incluir_recomendaciones'], FILTER_VALIDATE_BOOLEAN) : false;
    $solo_verificar = isset($_GET['solo_verificar']) ? filter_var($_GET['solo_verificar'], FILTER_VALIDATE_BOOLEAN) : false;
    $contenido_id = isset($_GET['contenido_id']) ? ApiValidator::validateId($_GET['contenido_id'], 'contenido_id') : null;
    
    // Validar permisos (usuario solo puede ver sus propios favoritos, excepto admins)
    if ($_SESSION['user_id'] != $usuario_id && $_SESSION['user_role'] !== 'admin') {
        ApiResponse::error(
            'No tienes permiso para ver los favoritos de otro usuario',
            'FORBIDDEN',
            403
        );
    }
    
    // Validar parámetros
    $tipos_validos = ['pelicula', 'serie', 'todos'];
    if (!in_array($tipo, $tipos_validos)) {
        ApiResponse::error(
            'Tipo inválido',
            'INVALID_TYPE',
            400,
            ['tipos_validos' => $tipos_validos]
        );
    }
    
    $ordenes_validos = ['fecha_agregado', 'titulo', 'calificacion', 'año'];
    if (!in_array($orden, $ordenes_validos)) {
        ApiResponse::error(
            'Orden inválido',
            'INVALID_ORDER',
            400,
            ['ordenes_validos' => $ordenes_validos]
        );
    }
    
    // Verificar que el usuario existe
    $usuario = $usuarioManager->obtenerUsuario($usuario_id);
    if (!$usuario) {
        ApiResponse::error(
            'Usuario no encontrado',
            'USER_NOT_FOUND',
            404
        );
    }
    
    // Modo verificación específica
    if ($solo_verificar) {
        if (!$contenido_id) {
            ApiResponse::error(
                'contenido_id es requerido para solo_verificar',
                'MISSING_CONTENT_ID',
                400
            );
        }
        
        $es_favorito = verificarEsFavorito($usuario_id, $contenido_id);
        $favorito_info = $es_favorito ? obtenerDetallesFavorito($usuario_id, $contenido_id) : null;
        
        ApiResponse::success([
            'es_favorito' => $es_favorito,
            'favorito' => $favorito_info,
            'usuario_id' => $usuario_id,
            'contenido_id' => $contenido_id
        ], 'Verificación completada');
        return;
    }
    
    // Obtener favoritos
    $favoritos = obtenerFavoritosUsuario($usuario_id, $tipo, $genero_id, $orden, $direccion, $limite, $pagina);
    
    // Preparar respuesta
    $response_data = [
        'favoritos' => $favoritos['items'],
        'usuario' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre']
        ],
        'filtros_aplicados' => [
            'tipo' => $tipo,
            'genero_id' => $genero_id,
            'orden' => $orden,
            'direccion' => $direccion
        ],
        'paginacion' => [
            'pagina_actual' => $pagina,
            'limite' => $limite,
            'total_items' => $favoritos['total'],
            'total_paginas' => ceil($favoritos['total'] / $limite),
            'tiene_siguiente' => ($pagina * $limite) < $favoritos['total'],
            'tiene_anterior' => $pagina > 1
        ]
    ];
    
    // Agregar estadísticas si se solicita
    if ($incluir_estadisticas) {
        $response_data['estadisticas'] = generarEstadisticasFavoritos($usuario_id);
    }
    
    // Agregar recomendaciones basadas en favoritos si se solicita
    if ($incluir_recomendaciones && !empty($favoritos['items'])) {
        $response_data['recomendaciones'] = generarRecomendacionesBasadasEnFavoritos($contenidoManager, $usuario_id, 6);
    }
    
    ApiResponse::success($response_data, 'Favoritos obtenidos correctamente');
}

/**
 * Manejar POST - Agregar contenido a favoritos
 */
function manejarAgregarFavorito($contenidoManager, $usuarioManager) {
    ApiValidator::requireJson();
    $data = getJsonInput();
    
    // Validar parámetros requeridos
    ApiValidator::requireParams($data, ['contenido_id']);
    
    $contenido_id = ApiValidator::validateId($data['contenido_id'], 'contenido_id');
    $notas = isset($data['notas']) ? sanitize_input($data['notas']) : '';
    
    // Validar longitud de notas
    if (strlen($notas) > 500) {
        ApiResponse::error(
            'Las notas no pueden exceder 500 caracteres',
            'NOTES_TOO_LONG',
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
    
    // Verificar si ya está en favoritos
    if (verificarEsFavorito($_SESSION['user_id'], $contenido_id)) {
        ApiResponse::error(
            'Este contenido ya está en tus favoritos',
            'ALREADY_IN_FAVORITES',
            409,
            ['contenido' => [
                'id' => $contenido['id'],
                'titulo' => $contenido['titulo']
            ]]
        );
    }
    
    // Agregar a favoritos
    $favorito_id = agregarAFavoritos($_SESSION['user_id'], $contenido_id, $notas);
    
    if ($favorito_id) {
        // Obtener el favorito recién creado
        $favorito_creado = obtenerDetallesFavorito($_SESSION['user_id'], $contenido_id);
        
        // Obtener estadísticas actualizadas
        $estadisticas_actualizadas = generarEstadisticasFavoritos($_SESSION['user_id']);
        
        ApiResponse::success([
            'favorito' => $favorito_creado,
            'contenido' => [
                'id' => $contenido['id'],
                'titulo' => $contenido['titulo'],
                'tipo' => $contenido['tipo'],
                'genero_nombre' => $contenido['genero_nombre']
            ],
            'estadisticas' => $estadisticas_actualizadas,
            'mensaje_usuario' => "\"" . $contenido['titulo'] . "\" agregado a tus favoritos"
        ], 'Contenido agregado a favoritos correctamente', 201);
        
    } else {
        ApiResponse::error(
            'Error al agregar a favoritos',
            'ADD_ERROR',
            500
        );
    }
}

/**
 * Manejar DELETE - Eliminar contenido de favoritos
 */
function manejarEliminarFavorito($contenidoManager, $usuarioManager) {
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
    
    // Verificar que está en favoritos
    if (!verificarEsFavorito($_SESSION['user_id'], $contenido_id)) {
        ApiResponse::error(
            'Este contenido no está en tus favoritos',
            'NOT_IN_FAVORITES',
            404,
            ['contenido' => [
                'id' => $contenido['id'],
                'titulo' => $contenido['titulo']
            ]]
        );
    }
    
    // Obtener datos del favorito antes de eliminarlo
    $favorito_eliminado = obtenerDetallesFavorito($_SESSION['user_id'], $contenido_id);
    
    // Eliminar de favoritos
    $resultado = eliminarDeFavoritos($_SESSION['user_id'], $contenido_id);
    
    if ($resultado) {
        // Obtener estadísticas actualizadas
        $estadisticas_actualizadas = generarEstadisticasFavoritos($_SESSION['user_id']);
        
        ApiResponse::success([
            'favorito_eliminado' => $favorito_eliminado,
            'contenido' => [
                'id' => $contenido['id'],
                'titulo' => $contenido['titulo'],
                'tipo' => $contenido['tipo']
            ],
            'estadisticas' => $estadisticas_actualizadas,
            'mensaje_usuario' => "\"" . $contenido['titulo'] . "\" eliminado de tus favoritos"
        ], 'Contenido eliminado de favoritos correctamente');
        
    } else {
        ApiResponse::error(
            'Error al eliminar de favoritos',
            'DELETE_ERROR',
            500
        );
    }
}

/**
 * Verificar si un contenido está en favoritos del usuario
 */
function verificarEsFavorito($usuario_id, $contenido_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "SELECT id FROM favoritos WHERE usuario_id = :usuario_id AND contenido_id = :contenido_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':contenido_id', $contenido_id);
        $stmt->execute();
        
        return $stmt->fetch() !== false;
        
    } catch (Exception $e) {
        error_log("Error verificando favorito: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener detalles específicos de un favorito
 */
function obtenerDetallesFavorito($usuario_id, $contenido_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "SELECT f.*, c.titulo, c.tipo, c.poster, c.calificacion, c.año_lanzamiento,
                       g.nombre as genero_nombre
                FROM favoritos f
                JOIN contenido c ON f.contenido_id = c.id
                LEFT JOIN generos g ON c.genero_id = g.id
                WHERE f.usuario_id = :usuario_id AND f.contenido_id = :contenido_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':contenido_id', $contenido_id);
        $stmt->execute();
        
        $favorito = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($favorito) {
            $favorito['tiempo_en_favoritos'] = calcularTiempoEnFavoritos($favorito['fecha_agregado']);
            $favorito['poster_url'] = 'uploads/' . ($favorito['poster'] ?: 'no-image.svg');
        }
        
        return $favorito;
        
    } catch (Exception $e) {
        error_log("Error obteniendo detalles favorito: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener lista completa de favoritos del usuario con filtros
 */
function obtenerFavoritosUsuario($usuario_id, $tipo, $genero_id, $orden, $direccion, $limite, $pagina) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Construir WHERE dinámico
        $where_conditions = ["f.usuario_id = :usuario_id"];
        $params = [':usuario_id' => $usuario_id];
        
        if ($tipo !== 'todos') {
            $where_conditions[] = "c.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        
        if ($genero_id) {
            $where_conditions[] = "c.genero_id = :genero_id";
            $params[':genero_id'] = $genero_id;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        // Construir ORDER BY
        $order_map = [
            'fecha_agregado' => 'f.fecha_agregado',
            'titulo' => 'c.titulo',
            'calificacion' => 'c.calificacion',
            'año' => 'c.año_lanzamiento'
        ];
        
        $order_field = isset($order_map[$orden]) ? $order_map[$orden] : 'f.fecha_agregado';
        $order_dir = strtoupper($direccion) === 'ASC' ? 'ASC' : 'DESC';
        $order_clause = "ORDER BY {$order_field} {$order_dir}";
        
        // Query principal para obtener favoritos
        $sql = "SELECT f.*, c.titulo, c.tipo, c.poster, c.calificacion, c.año_lanzamiento,
                       c.descripcion, g.nombre as genero_nombre
                FROM favoritos f
                JOIN contenido c ON f.contenido_id = c.id
                LEFT JOIN generos g ON c.genero_id = g.id
                {$where_clause}
                {$order_clause}
                LIMIT :offset, :limite";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parámetros
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $offset = ($pagina - 1) * $limite;
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        
        $stmt->execute();
        $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Enriquecer datos
        foreach ($favoritos as &$favorito) {
            $favorito['tiempo_en_favoritos'] = calcularTiempoEnFavoritos($favorito['fecha_agregado']);
            $favorito['poster_url'] = 'uploads/' . ($favorito['poster'] ?: 'no-image.svg');
            $favorito['url_detalle'] = "detalle_contenido.php?id=" . $favorito['contenido_id'];
            $favorito['tipo_badge'] = $favorito['tipo'] === 'pelicula' ? '🎬 Película' : '📺 Serie';
        }
        
        // Query para contar total
        $sql_count = "SELECT COUNT(*) as total
                      FROM favoritos f
                      JOIN contenido c ON f.contenido_id = c.id
                      {$where_clause}";
        
        $stmt_count = $conn->prepare($sql_count);
        foreach ($params as $key => $value) {
            if (strpos($sql_count, $key) !== false) {
                $stmt_count->bindValue($key, $value);
            }
        }
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'items' => $favoritos,
            'total' => intval($total)
        ];
        
    } catch (Exception $e) {
        error_log("Error obteniendo favoritos: " . $e->getMessage());
        return ['items' => [], 'total' => 0];
    }
}

/**
 * Agregar contenido a favoritos
 */
function agregarAFavoritos($usuario_id, $contenido_id, $notas = '') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "INSERT INTO favoritos (usuario_id, contenido_id, notas, fecha_agregado) 
                VALUES (:usuario_id, :contenido_id, :notas, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':contenido_id', $contenido_id);
        $stmt->bindParam(':notas', $notas);
        
        if ($stmt->execute()) {
            return $conn->lastInsertId();
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error agregando favorito: " . $e->getMessage());
        return false;
    }
}

/**
 * Eliminar contenido de favoritos
 */
function eliminarDeFavoritos($usuario_id, $contenido_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "DELETE FROM favoritos WHERE usuario_id = :usuario_id AND contenido_id = :contenido_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':contenido_id', $contenido_id);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error eliminando favorito: " . $e->getMessage());
        return false;
    }
}

/**
 * Generar estadísticas de favoritos del usuario
 */
function generarEstadisticasFavoritos($usuario_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Estadísticas básicas
        $sql = "SELECT 
                    COUNT(*) as total_favoritos,
                    SUM(CASE WHEN c.tipo = 'pelicula' THEN 1 ELSE 0 END) as peliculas,
                    SUM(CASE WHEN c.tipo = 'serie' THEN 1 ELSE 0 END) as series,
                    AVG(c.calificacion) as calificacion_promedio,
                    MIN(f.fecha_agregado) as primer_favorito,
                    MAX(f.fecha_agregado) as ultimo_favorito
                FROM favoritos f
                JOIN contenido c ON f.contenido_id = c.id
                WHERE f.usuario_id = :usuario_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $stats_basicas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Género más común
        $sql_genero = "SELECT g.nombre, COUNT(*) as cantidad
                       FROM favoritos f
                       JOIN contenido c ON f.contenido_id = c.id
                       JOIN generos g ON c.genero_id = g.id
                       WHERE f.usuario_id = :usuario_id
                       GROUP BY g.id, g.nombre
                       ORDER BY cantidad DESC
                       LIMIT 1";
        
        $stmt_genero = $conn->prepare($sql_genero);
        $stmt_genero->bindParam(':usuario_id', $usuario_id);
        $stmt_genero->execute();
        $genero_favorito = $stmt_genero->fetch(PDO::FETCH_ASSOC);
        
        // Año más común
        $sql_año = "SELECT c.año_lanzamiento, COUNT(*) as cantidad
                    FROM favoritos f
                    JOIN contenido c ON f.contenido_id = c.id
                    WHERE f.usuario_id = :usuario_id
                    GROUP BY c.año_lanzamiento
                    ORDER BY cantidad DESC
                    LIMIT 1";
        
        $stmt_año = $conn->prepare($sql_año);
        $stmt_año->bindParam(':usuario_id', $usuario_id);
        $stmt_año->execute();
        $año_favorito = $stmt_año->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_favoritos' => intval($stats_basicas['total_favoritos']),
            'peliculas' => intval($stats_basicas['peliculas']),
            'series' => intval($stats_basicas['series']),
            'calificacion_promedio' => $stats_basicas['calificacion_promedio'] ? round(floatval($stats_basicas['calificacion_promedio']), 1) : 0,
            'genero_favorito' => $genero_favorito ? $genero_favorito['nombre'] : null,
            'año_favorito' => $año_favorito ? intval($año_favorito['año_lanzamiento']) : null,
            'primer_favorito' => $stats_basicas['primer_favorito'],
            'ultimo_favorito' => $stats_basicas['ultimo_favorito'],
            'tiempo_coleccionando' => $stats_basicas['primer_favorito'] ? calcularTiempoColeccionando($stats_basicas['primer_favorito']) : null
        ];
        
    } catch (Exception $e) {
        error_log("Error generando estadísticas favoritos: " . $e->getMessage());
        return [
            'total_favoritos' => 0,
            'peliculas' => 0,
            'series' => 0,
            'calificacion_promedio' => 0,
            'genero_favorito' => null,
            'año_favorito' => null
        ];
    }
}

/**
 * Generar recomendaciones basadas en favoritos del usuario
 */
function generarRecomendacionesBasadasEnFavoritos($contenidoManager, $usuario_id, $limite) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Obtener géneros más frecuentes en favoritos
        $sql = "SELECT c.genero_id, COUNT(*) as frecuencia
                FROM favoritos f
                JOIN contenido c ON f.contenido_id = c.id
                WHERE f.usuario_id = :usuario_id
                GROUP BY c.genero_id
                ORDER BY frecuencia DESC
                LIMIT 3";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $generos_favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($generos_favoritos)) {
            return [];
        }
        
        // Buscar contenido similar que no esté en favoritos
        $generos_ids = array_column($generos_favoritos, 'genero_id');
        $placeholders = str_repeat('?,', count($generos_ids) - 1) . '?';
        
        $sql_recomendaciones = "SELECT c.*, g.nombre as genero_nombre
                                FROM contenido c
                                JOIN generos g ON c.genero_id = g.id
                                WHERE c.genero_id IN ({$placeholders})
                                AND c.id NOT IN (
                                    SELECT contenido_id 
                                    FROM favoritos 
                                    WHERE usuario_id = ?
                                )
                                ORDER BY c.calificacion DESC
                                LIMIT ?";
        
        $stmt_rec = $conn->prepare($sql_recomendaciones);
        
        // Bind géneros IDs
        $bind_index = 1;
        foreach ($generos_ids as $genero_id) {
            $stmt_rec->bindValue($bind_index++, $genero_id, PDO::PARAM_INT);
        }
        $stmt_rec->bindValue($bind_index++, $usuario_id, PDO::PARAM_INT);
        $stmt_rec->bindValue($bind_index, $limite, PDO::PARAM_INT);
        
        $stmt_rec->execute();
        $recomendaciones = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);
        
        // Enriquecer recomendaciones
        foreach ($recomendaciones as &$rec) {
            $rec['razon_recomendacion'] = "Te gusta " . $rec['genero_nombre'];
            $rec['poster_url'] = 'uploads/' . ($rec['poster'] ?: 'no-image.svg');
            $rec['url_detalle'] = "detalle_contenido.php?id=" . $rec['id'];
        }
        
        return $recomendaciones;
        
    } catch (Exception $e) {
        error_log("Error generando recomendaciones basadas en favoritos: " . $e->getMessage());
        return [];
    }
}

/**
 * Calcular tiempo que ha estado en favoritos
 */
function calcularTiempoEnFavoritos($fecha_agregado) {
    $tiempo = time() - strtotime($fecha_agregado);
    
    if ($tiempo < 3600) {
        $minutos = floor($tiempo / 60);
        return "hace {$minutos} minuto" . ($minutos > 1 ? 's' : '');
    } elseif ($tiempo < 86400) {
        $horas = floor($tiempo / 3600);
        return "hace {$horas} hora" . ($horas > 1 ? 's' : '');
    } elseif ($tiempo < 2592000) { // 30 días
        $dias = floor($tiempo / 86400);
        return "hace {$dias} día" . ($dias > 1 ? 's' : '');
    } else {
        $meses = floor($tiempo / 2592000);
        return "hace {$meses} mes" . ($meses > 1 ? 'es' : '');
    }
}

/**
 * Calcular tiempo total coleccionando favoritos
 */
function calcularTiempoColeccionando($primer_favorito) {
    $tiempo = time() - strtotime($primer_favorito);
    $dias = floor($tiempo / 86400);
    
    if ($dias < 30) {
        return "{$dias} día" . ($dias > 1 ? 's' : '');
    } elseif ($dias < 365) {
        $meses = floor($dias / 30);
        return "{$meses} mes" . ($meses > 1 ? 'es' : '');
    } else {
        $años = floor($dias / 365);
        return "{$años} año" . ($años > 1 ? 's' : '');
    }
}

/**
 * Crear tabla de favoritos si no existe (función helper)
 */
function crearTablaFavoritos() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS favoritos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            usuario_id INT NOT NULL,
            contenido_id INT NOT NULL,
            notas TEXT,
            fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_content (usuario_id, contenido_id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (contenido_id) REFERENCES contenido(id) ON DELETE CASCADE,
            INDEX idx_usuario_fecha (usuario_id, fecha_agregado)
        )";
        
        $conn->exec($sql);
        return true;
        
    } catch (Exception $e) {
        error_log("Error creando tabla favoritos: " . $e->getMessage());
        return false;
    }
}

// Crear tabla si no existe (llamada automática)
crearTablaFavoritos();
?>