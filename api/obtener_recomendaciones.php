<?php
/**
 * API REST - Obtener Recomendaciones Personalizadas
 * Método: GET
 * Content-Type: application/json
 * 
 * Parámetros URL opcionales:
 * - usuario_id: ID del usuario (integer, opcional si está en sesión)
 * - tipo: Tipo de recomendaciones (string: 'personalizadas', 'genero', 'similares', 'trending', 'todas')
 * - genero_id: ID del género específico (integer, solo para tipo='genero')
 * - contenido_id: ID del contenido base (integer, solo para tipo='similares')
 * - limite: Número máximo de resultados (integer, default: 12, max: 50)
 * - pagina: Página para paginación (integer, default: 1)
 * - incluir_metadata: Incluir metadatos adicionales (boolean, default: true)
 * 
 * Respuesta JSON:
 * {
 *   "success": true,
 *   "data": {
 *     "recomendaciones": [...],
 *     "usuario": {...},
 *     "algoritmo": {...},
 *     "paginacion": {...}
 *   }
 * }
 */

// Incluir configuración de API
require_once __DIR__ . '/config_api.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

try {
    // Validar método y autenticación
    ApiValidator::requireMethod('GET');
    ApiValidator::requireAuth();
    
    // Obtener parámetros de la URL
    $usuario_id = isset($_GET['usuario_id']) ? ApiValidator::validateId($_GET['usuario_id'], 'usuario_id') : $_SESSION['user_id'];
    $tipo = isset($_GET['tipo']) ? sanitize_input($_GET['tipo']) : 'personalizadas';
    $genero_id = isset($_GET['genero_id']) ? ApiValidator::validateId($_GET['genero_id'], 'genero_id') : null;
    $contenido_id = isset($_GET['contenido_id']) ? ApiValidator::validateId($_GET['contenido_id'], 'contenido_id') : null;
    $limite = isset($_GET['limite']) ? min(50, max(1, intval($_GET['limite']))) : 12;
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $incluir_metadata = isset($_GET['incluir_metadata']) ? filter_var($_GET['incluir_metadata'], FILTER_VALIDATE_BOOLEAN) : true;
    
    // Validar tipos de recomendaciones permitidos
    $tipos_permitidos = ['personalizadas', 'genero', 'similares', 'trending', 'todas'];
    if (!in_array($tipo, $tipos_permitidos)) {
        ApiResponse::error(
            'Tipo de recomendación no válido',
            'INVALID_TYPE',
            400,
            ['tipos_permitidos' => $tipos_permitidos]
        );
    }
    
    // Validar permisos (usuario solo puede ver sus propias recomendaciones, excepto admins)
    if ($_SESSION['user_id'] != $usuario_id && $_SESSION['user_role'] !== 'admin') {
        ApiResponse::error(
            'No tienes permiso para ver las recomendaciones de otro usuario',
            'FORBIDDEN',
            403
        );
    }
    
    // Inicializar managers
    $contenidoManager = new ContenidoManager();
    $usuarioManager = new UsuarioManager();
    
    // Verificar que el usuario existe y está activo
    $usuario = $usuarioManager->obtenerUsuario($usuario_id);
    if (!$usuario || !$usuario['activo']) {
        ApiResponse::error(
            'Usuario no encontrado o inactivo',
            'USER_NOT_FOUND',
            404
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
    
    // Obtener datos base del usuario para el algoritmo
    $preferencias = $usuarioManager->obtenerPreferencias($usuario_id);
    $estadisticas = $usuarioManager->obtenerEstadisticasUsuario($usuario_id);
    $historial_reciente = $contenidoManager->obtenerHistorialUsuario($usuario_id, 10);
    
    // Variable para almacenar recomendaciones
    $recomendaciones = [];
    $algoritmo_info = [];
    
    // Calcular recomendaciones según el tipo solicitado
    switch ($tipo) {
        case 'personalizadas':
            $recomendaciones = obtenerRecomendacionesPersonalizadas($contenidoManager, $usuario_id, $preferencias, $historial_reciente, $limite);
            $algoritmo_info = [
                'tipo' => 'personalizadas',
                'factores' => ['preferencias_usuario', 'historial_navegacion', 'calificaciones_previas'],
                'preferencias_count' => count($preferencias),
                'historial_count' => count($historial_reciente)
            ];
            break;
            
        case 'genero':
            if (!$genero_id) {
                ApiResponse::error(
                    'genero_id es requerido para tipo="genero"',
                    'MISSING_GENRE_ID',
                    400
                );
            }
            $recomendaciones = $contenidoManager->buscarContenido('', $genero_id, '', $limite);
            $algoritmo_info = [
                'tipo' => 'por_genero',
                'genero_id' => $genero_id,
                'factores' => ['genero_especifico', 'calificacion_alta']
            ];
            break;
            
        case 'similares':
            if (!$contenido_id) {
                ApiResponse::error(
                    'contenido_id es requerido para tipo="similares"',
                    'MISSING_CONTENT_ID',
                    400
                );
            }
            $recomendaciones = $contenidoManager->obtenerContenidoRelacionado($contenido_id, $limite);
            $algoritmo_info = [
                'tipo' => 'contenido_similar',
                'contenido_base_id' => $contenido_id,
                'factores' => ['mismo_genero', 'calificacion_similar']
            ];
            break;
            
        case 'trending':
            $recomendaciones = $contenidoManager->obtenerContenidoDestacado(null, $limite);
            $algoritmo_info = [
                'tipo' => 'trending',
                'factores' => ['calificacion_alta', 'popularidad_reciente']
            ];
            break;
            
        case 'todas':
            $recomendaciones = obtenerRecomendacionesMixtas($contenidoManager, $usuario_id, $preferencias, $historial_reciente, $limite);
            $algoritmo_info = [
                'tipo' => 'mixtas',
                'factores' => ['personalizadas', 'trending', 'por_genero'],
                'distribucion' => ['40% personalizadas', '30% trending', '30% géneros favoritos']
            ];
            break;
    }
    
    // Aplicar paginación si es necesario
    $total_items = count($recomendaciones);
    $offset = ($pagina - 1) * $limite;
    $recomendaciones_paginadas = array_slice($recomendaciones, $offset, $limite);
    
    // Enriquecer datos de recomendaciones
    $recomendaciones_enriquecidas = [];
    foreach ($recomendaciones_paginadas as $item) {
        $item_enriquecido = $item;
        
        // Calcular score de compatibilidad
        $item_enriquecido['compatibilidad'] = calcularCompatibilidad($item, $preferencias, $historial_reciente);
        
        // Agregar razones de recomendación
        $item_enriquecido['razones'] = obtenerRazonesRecomendacion($item, $preferencias, $historial_reciente);
        
        // Información adicional si se solicita
        if ($incluir_metadata) {
            $item_enriquecido['metadata'] = [
                'fecha_agregado' => $item['fecha_agregado'],
                'popularidad_score' => calcularPopularidad($item),
                'tendencia' => esTendencia($item),
                'nuevo' => esNuevo($item)
            ];
        }
        
        $recomendaciones_enriquecidas[] = $item_enriquecido;
    }
    
    // Preparar respuesta
    $response_data = [
        'recomendaciones' => $recomendaciones_enriquecidas,
        'count' => count($recomendaciones_enriquecidas),
        'algoritmo' => $algoritmo_info
    ];
    
    // Agregar información del usuario si se solicita
    if ($incluir_metadata) {
        $response_data['usuario'] = [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'estadisticas' => $estadisticas,
            'preferencias_count' => count($preferencias),
            'historial_count' => count($historial_reciente)
        ];
        
        $response_data['paginacion'] = [
            'pagina_actual' => $pagina,
            'limite' => $limite,
            'total_items' => $total_items,
            'total_paginas' => ceil($total_items / $limite),
            'tiene_siguiente' => ($pagina * $limite) < $total_items,
            'tiene_anterior' => $pagina > 1
        ];
        
        // Sugerencias para mejorar recomendaciones
        $response_data['sugerencias'] = generarSugerenciasMejora($preferencias, $estadisticas, $historial_reciente);
    }
    
    ApiResponse::success($response_data, 'Recomendaciones obtenidas correctamente');
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en obtener_recomendaciones.php: " . $e->getMessage());
    
    ApiResponse::error(
        'Error interno del servidor',
        'INTERNAL_ERROR',
        500
    );
}

/**
 * Obtener recomendaciones personalizadas basadas en preferencias e historial
 */
function obtenerRecomendacionesPersonalizadas($contenidoManager, $usuario_id, $preferencias, $historial, $limite) {
    // Si no tiene preferencias, devolver contenido popular
    if (empty($preferencias)) {
        return $contenidoManager->obtenerContenidoDestacado(null, $limite);
    }
    
    try {
        // Usar el método ya implementado en ContenidoManager
        return $contenidoManager->obtenerRecomendacionesPersonalizadas($usuario_id, $limite);
    } catch (Exception $e) {
        error_log("Error en recomendaciones personalizadas: " . $e->getMessage());
        // Fallback a contenido destacado
        return $contenidoManager->obtenerContenidoDestacado(null, $limite);
    }
}

/**
 * Obtener recomendaciones mixtas (combinación de diferentes tipos)
 */
function obtenerRecomendacionesMixtas($contenidoManager, $usuario_id, $preferencias, $historial, $limite) {
    $recomendaciones = [];
    
    try {
        // 40% personalizadas
        $personalizadas = obtenerRecomendacionesPersonalizadas($contenidoManager, $usuario_id, $preferencias, $historial, ceil($limite * 0.4));
        $recomendaciones = array_merge($recomendaciones, $personalizadas);
        
        // 30% trending
        $trending = $contenidoManager->obtenerContenidoDestacado(null, ceil($limite * 0.3));
        $recomendaciones = array_merge($recomendaciones, $trending);
        
        // 30% por géneros favoritos
        if (!empty($preferencias)) {
            $genero_favorito = $preferencias[0]['genero_id'];
            $por_genero = $contenidoManager->buscarContenido('', $genero_favorito, '', ceil($limite * 0.3));
            $recomendaciones = array_merge($recomendaciones, $por_genero);
        }
        
        // Eliminar duplicados y limitar
        $recomendaciones = array_unique($recomendaciones, SORT_REGULAR);
        return array_slice($recomendaciones, 0, $limite);
        
    } catch (Exception $e) {
        error_log("Error en recomendaciones mixtas: " . $e->getMessage());
        return $contenidoManager->obtenerContenidoDestacado(null, $limite);
    }
}

/**
 * Calcular porcentaje de compatibilidad del contenido con el usuario
 */
function calcularCompatibilidad($contenido, $preferencias, $historial) {
    $score = 50; // Score base
    
    // Bonus por género favorito
    if (!empty($preferencias)) {
        $generos_favoritos = array_column($preferencias, 'genero_id');
        if (in_array($contenido['genero_id'], $generos_favoritos)) {
            $score += 30;
        }
    }
    
    // Bonus por calificación alta
    if ($contenido['calificacion'] >= 4.5) {
        $score += 15;
    } elseif ($contenido['calificacion'] >= 4.0) {
        $score += 10;
    } elseif ($contenido['calificacion'] >= 3.5) {
        $score += 5;
    }
    
    // Bonus por contenido reciente
    $años_desde_lanzamiento = date('Y') - $contenido['año_lanzamiento'];
    if ($años_desde_lanzamiento <= 2) {
        $score += 10;
    } elseif ($años_desde_lanzamiento <= 5) {
        $score += 5;
    }
    
    // Penalty si ya lo vió
    if (!empty($historial)) {
        $contenido_visto = array_column($historial, 'id');
        if (in_array($contenido['id'], $contenido_visto)) {
            $score -= 20;
        }
    }
    
    // Asegurar que esté en rango 0-100
    return max(0, min(100, $score));
}

/**
 * Obtener razones específicas de por qué se recomienda este contenido
 */
function obtenerRazonesRecomendacion($contenido, $preferencias, $historial) {
    $razones = [];
    
    // Verificar género favorito
    if (!empty($preferencias)) {
        $generos_favoritos = array_column($preferencias, 'genero_id');
        if (in_array($contenido['genero_id'], $generos_favoritos)) {
            $razones[] = "Coincide con tu género favorito";
        }
    }
    
    // Verificar calificación
    if ($contenido['calificacion'] >= 4.5) {
        $razones[] = "Excelente calificación (" . $contenido['calificacion'] . "/5)";
    } elseif ($contenido['calificacion'] >= 4.0) {
        $razones[] = "Muy buena calificación";
    }
    
    // Verificar si es reciente
    $años_desde_lanzamiento = date('Y') - $contenido['año_lanzamiento'];
    if ($años_desde_lanzamiento <= 1) {
        $razones[] = "Lanzamiento reciente";
    }
    
    // Verificar popularidad
    if ($contenido['calificacion'] >= 4.3) {
        $razones[] = "Popular entre usuarios";
    }
    
    // Si no hay razones específicas
    if (empty($razones)) {
        $razones[] = "Contenido destacado";
    }
    
    return $razones;
}

/**
 * Calcular score de popularidad
 */
function calcularPopularidad($contenido) {
    $score = $contenido['calificacion'] * 20; // Base: calificación * 20
    
    // Bonus por año reciente
    $años_desde_lanzamiento = date('Y') - $contenido['año_lanzamiento'];
    if ($años_desde_lanzamiento <= 1) {
        $score += 20;
    } elseif ($años_desde_lanzamiento <= 3) {
        $score += 10;
    }
    
    return min(100, $score);
}

/**
 * Verificar si es tendencia
 */
function esTendencia($contenido) {
    return $contenido['calificacion'] >= 4.2 && (date('Y') - $contenido['año_lanzamiento']) <= 3;
}

/**
 * Verificar si es nuevo
 */
function esNuevo($contenido) {
    return (date('Y') - $contenido['año_lanzamiento']) <= 1;
}

/**
 * Generar sugerencias para mejorar las recomendaciones
 */
function generarSugerenciasMejora($preferencias, $estadisticas, $historial) {
    $sugerencias = [];
    
    if (count($preferencias) < 3) {
        $sugerencias[] = [
            'tipo' => 'preferencias',
            'mensaje' => 'Agrega más géneros favoritos para mejores recomendaciones',
            'accion' => 'Configurar preferencias'
        ];
    }
    
    if ($estadisticas['calificaciones_dadas'] < 5) {
        $sugerencias[] = [
            'tipo' => 'calificaciones',
            'mensaje' => 'Califica más contenido para recomendaciones personalizadas',
            'accion' => 'Ir al catálogo'
        ];
    }
    
    if ($estadisticas['contenido_visto'] < 10) {
        $sugerencias[] = [
            'tipo' => 'exploracion',
            'mensaje' => 'Explora más contenido para crear tu perfil de gustos',
            'accion' => 'Explorar catálogo'
        ];
    }
    
    return $sugerencias;
}
?>