<?php
/*
 * API REST - Búsqueda Avanzada de Contenido

 */

// Incluir configuración de API
require_once __DIR__ . '/config_api.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';

try {
    // No requiere autenticación para búsquedas básicas
    // Pero puede incluir funcionalidades extra si está logueado
    $usuario_logueado = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Validar método
    ApiValidator::requireMethod('GET');
    
    // Inicializar manager
    $contenidoManager = new ContenidoManager();
    
    // Obtener y validar parámetros de búsqueda
    $parametros = obtenerParametrosBusqueda();
    
    // Validar parámetros
    $errores_validacion = validarParametrosBusqueda($parametros);
    if (!empty($errores_validacion)) {
        ApiResponse::error(
            'Parámetros de búsqueda inválidos',
            'INVALID_SEARCH_PARAMS',
            400,
            ['errores' => $errores_validacion]
        );
    }
    
    // Ejecutar búsqueda según el tipo solicitado
    if ($parametros['autocompletar']) {
        // Modo autocompletado - solo títulos
        $resultados = ejecutarAutocompletado($contenidoManager, $parametros);
        ApiResponse::success([
            'sugerencias' => $resultados,
            'tipo' => 'autocompletado'
        ], 'Sugerencias obtenidas correctamente');
        
    } else {
        // Búsqueda completa
        $resultado_busqueda = ejecutarBusquedaCompleta($contenidoManager, $parametros, $usuario_logueado);
        ApiResponse::success($resultado_busqueda, 'Búsqueda completada exitosamente');
    }
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en buscar_contenido.php: " . $e->getMessage());
    
    ApiResponse::error(
        'Error interno del servidor',
        'INTERNAL_ERROR',
        500
    );
}

/**
 * Obtener y limpiar parámetros de búsqueda de la URL
 */
function obtenerParametrosBusqueda() {
    return [
        // Búsqueda de texto
        'q' => isset($_GET['q']) ? sanitize_input(trim($_GET['q'])) : '',
        'titulo' => isset($_GET['titulo']) ? sanitize_input(trim($_GET['titulo'])) : '',
        'descripcion' => isset($_GET['descripcion']) ? sanitize_input(trim($_GET['descripcion'])) : '',
        'autocompletar' => isset($_GET['autocompletar']) ? filter_var($_GET['autocompletar'], FILTER_VALIDATE_BOOLEAN) : false,
        
        // Filtros
        'tipo' => isset($_GET['tipo']) ? sanitize_input($_GET['tipo']) : 'todos',
        'genero_id' => isset($_GET['genero_id']) ? intval($_GET['genero_id']) : null,
        'genero_nombre' => isset($_GET['genero_nombre']) ? sanitize_input($_GET['genero_nombre']) : '',
        'año_min' => isset($_GET['año_min']) ? intval($_GET['año_min']) : null,
        'año_max' => isset($_GET['año_max']) ? intval($_GET['año_max']) : null,
        'calificacion_min' => isset($_GET['calificacion_min']) ? floatval($_GET['calificacion_min']) : null,
        'calificacion_max' => isset($_GET['calificacion_max']) ? floatval($_GET['calificacion_max']) : null,
        'duracion_min' => isset($_GET['duracion_min']) ? intval($_GET['duracion_min']) : null,
        'duracion_max' => isset($_GET['duracion_max']) ? intval($_GET['duracion_max']) : null,
        
        // Ordenamiento
        'orden' => isset($_GET['orden']) ? sanitize_input($_GET['orden']) : 'relevancia',
        'direccion' => isset($_GET['direccion']) ? sanitize_input($_GET['direccion']) : 'desc',
        
        // Paginación
        'limite' => isset($_GET['limite']) ? min(100, max(1, intval($_GET['limite']))) : 20,
        'pagina' => isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1,
        'offset' => isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : null,
        
        // Opciones
        'incluir_estadisticas' => isset($_GET['incluir_estadisticas']) ? filter_var($_GET['incluir_estadisticas'], FILTER_VALIDATE_BOOLEAN) : true,
        'incluir_sugerencias' => isset($_GET['incluir_sugerencias']) ? filter_var($_GET['incluir_sugerencias'], FILTER_VALIDATE_BOOLEAN) : true,
        'incluir_filtros_disponibles' => isset($_GET['incluir_filtros_disponibles']) ? filter_var($_GET['incluir_filtros_disponibles'], FILTER_VALIDATE_BOOLEAN) : false,
        'solo_ids' => isset($_GET['solo_ids']) ? filter_var($_GET['solo_ids'], FILTER_VALIDATE_BOOLEAN) : false
    ];
}

/**
 * Validar parámetros de búsqueda
 */
function validarParametrosBusqueda($params) {
    $errores = [];
    
    // Validar tipo
    $tipos_validos = ['pelicula', 'serie', 'todos'];
    if (!in_array($params['tipo'], $tipos_validos)) {
        $errores['tipo'] = 'Tipo debe ser: ' . implode(', ', $tipos_validos);
    }
    
    // Validar años
    $año_actual = date('Y');
    if ($params['año_min'] && ($params['año_min'] < 1900 || $params['año_min'] > $año_actual)) {
        $errores['año_min'] = 'Año mínimo debe estar entre 1900 y ' . $año_actual;
    }
    if ($params['año_max'] && ($params['año_max'] < 1900 || $params['año_max'] > $año_actual)) {
        $errores['año_max'] = 'Año máximo debe estar entre 1900 y ' . $año_actual;
    }
    if ($params['año_min'] && $params['año_max'] && $params['año_min'] > $params['año_max']) {
        $errores['años'] = 'Año mínimo no puede ser mayor que año máximo';
    }
    
    // Validar calificaciones
    if ($params['calificacion_min'] !== null && ($params['calificacion_min'] < 0 || $params['calificacion_min'] > 5)) {
        $errores['calificacion_min'] = 'Calificación mínima debe estar entre 0 y 5';
    }
    if ($params['calificacion_max'] !== null && ($params['calificacion_max'] < 0 || $params['calificacion_max'] > 5)) {
        $errores['calificacion_max'] = 'Calificación máxima debe estar entre 0 y 5';
    }
    if ($params['calificacion_min'] !== null && $params['calificacion_max'] !== null && $params['calificacion_min'] > $params['calificacion_max']) {
        $errores['calificaciones'] = 'Calificación mínima no puede ser mayor que máxima';
    }
    
    // Validar duración
    if ($params['duracion_min'] && $params['duracion_min'] < 1) {
        $errores['duracion_min'] = 'Duración mínima debe ser mayor a 0';
    }
    if ($params['duracion_max'] && $params['duracion_max'] < 1) {
        $errores['duracion_max'] = 'Duración máxima debe ser mayor a 0';
    }
    if ($params['duracion_min'] && $params['duracion_max'] && $params['duracion_min'] > $params['duracion_max']) {
        $errores['duracion'] = 'Duración mínima no puede ser mayor que máxima';
    }
    
    // Validar orden
    $ordenes_validos = ['titulo', 'año', 'calificacion', 'fecha_agregado', 'popularidad', 'relevancia'];
    if (!in_array($params['orden'], $ordenes_validos)) {
        $errores['orden'] = 'Orden debe ser: ' . implode(', ', $ordenes_validos);
    }
    
    // Validar dirección
    $direcciones_validas = ['asc', 'desc'];
    if (!in_array($params['direccion'], $direcciones_validas)) {
        $errores['direccion'] = 'Dirección debe ser: asc o desc';
    }
    
    return $errores;
}

/**
 * Ejecutar autocompletado de títulos
 */
function ejecutarAutocompletado($contenidoManager, $params) {
    if (empty($params['q']) && empty($params['titulo'])) {
        return [];
    }
    
    $termino = !empty($params['q']) ? $params['q'] : $params['titulo'];
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Búsqueda optimizada para autocompletado
        $sql = "SELECT DISTINCT titulo, tipo, año_lanzamiento, poster 
                FROM contenido 
                WHERE titulo LIKE :termino 
                ORDER BY 
                    CASE WHEN titulo LIKE :termino_exacto THEN 1 ELSE 2 END,
                    calificacion DESC,
                    titulo ASC
                LIMIT :limite";
        
        $stmt = $conn->prepare($sql);
        $termino_like = '%' . $termino . '%';
        $termino_exacto = $termino . '%';
        $stmt->bindParam(':termino', $termino_like);
        $stmt->bindParam(':termino_exacto', $termino_exacto);
        $stmt->bindParam(':limite', $params['limite'], PDO::PARAM_INT);
        
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear para autocompletado
        $sugerencias = [];
        foreach ($resultados as $item) {
            $sugerencias[] = [
                'titulo' => $item['titulo'],
                'tipo' => $item['tipo'],
                'año' => $item['año_lanzamiento'],
                'poster' => $item['poster'],
                'label' => $item['titulo'] . ' (' . $item['año_lanzamiento'] . ')',
                'value' => $item['titulo']
            ];
        }
        
        return $sugerencias;
        
    } catch (Exception $e) {
        error_log("Error en autocompletado: " . $e->getMessage());
        return [];
    }
}

/**
 * Ejecutar búsqueda completa con todos los filtros
 */
function ejecutarBusquedaCompleta($contenidoManager, $params, $usuario_logueado) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Construir query base
        $query_parts = construirQueryBusqueda($params);
        
        // Query principal para resultados
        $sql = $query_parts['select'] . $query_parts['from'] . $query_parts['where'] . $query_parts['order'] . $query_parts['limit'];
        
        $stmt = $conn->prepare($sql);
        
        // Bind parámetros
        foreach ($query_parts['params'] as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $resultados_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Query para contar total (sin LIMIT)
        $sql_count = "SELECT COUNT(*) as total " . $query_parts['from'] . $query_parts['where'];
        $stmt_count = $conn->prepare($sql_count);
        foreach ($query_parts['params'] as $key => $value) {
            if (strpos($sql_count, $key) !== false) {
                $stmt_count->bindValue($key, $value);
            }
        }
        $stmt_count->execute();
        $total_resultados = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Procesar resultados
        $resultados = procesarResultadosBusqueda($resultados_raw, $params, $usuario_logueado);
        
        // Preparar respuesta completa
        $response = [
            'resultados' => $resultados,
            'total_resultados' => intval($total_resultados),
            'parametros_busqueda' => $params,
            'filtros_aplicados' => obtenerFiltrosAplicados($params)
        ];
        
        // Agregar paginación
        $response['paginacion'] = [
            'pagina_actual' => $params['pagina'],
            'limite' => $params['limite'],
            'total_items' => intval($total_resultados),
            'total_paginas' => ceil($total_resultados / $params['limite']),
            'tiene_siguiente' => ($params['pagina'] * $params['limite']) < $total_resultados,
            'tiene_anterior' => $params['pagina'] > 1,
            'offset' => ($params['pagina'] - 1) * $params['limite']
        ];
        
        // Agregar estadísticas si se solicita
        if ($params['incluir_estadisticas']) {
            $response['estadisticas'] = generarEstadisticasBusqueda($resultados_raw, $params);
        }
        
        // Agregar sugerencias si se solicita
        if ($params['incluir_sugerencias'] && empty($resultados)) {
            $response['sugerencias'] = generarSugerenciasBusqueda($params, $contenidoManager);
        }
        
        // Agregar filtros disponibles si se solicita
        if ($params['incluir_filtros_disponibles']) {
            $response['filtros_disponibles'] = obtenerFiltrosDisponibles($contenidoManager);
        }
        
        return $response;
        
    } catch (Exception $e) {
        error_log("Error en búsqueda completa: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Construir query SQL dinámico basado en parámetros
 */
function construirQueryBusqueda($params) {
    $conditions = [];
    $query_params = [];
    $joins = [];
    
    // SELECT base
    if ($params['solo_ids']) {
        $select = "SELECT DISTINCT c.id ";
    } else {
        $select = "SELECT DISTINCT c.*, g.nombre as genero_nombre ";
    }
    
    // FROM base
    $from = "FROM contenido c LEFT JOIN generos g ON c.genero_id = g.id ";
    
    // Construir WHERE dinámicamente
    
    // Búsqueda de texto
    if (!empty($params['q'])) {
        $conditions[] = "(c.titulo LIKE :busqueda OR c.descripcion LIKE :busqueda)";
        $query_params[':busqueda'] = '%' . $params['q'] . '%';
    }
    
    if (!empty($params['titulo'])) {
        $conditions[] = "c.titulo LIKE :titulo";
        $query_params[':titulo'] = '%' . $params['titulo'] . '%';
    }
    
    if (!empty($params['descripcion'])) {
        $conditions[] = "c.descripcion LIKE :descripcion";
        $query_params[':descripcion'] = '%' . $params['descripcion'] . '%';
    }
    
    // Filtro por tipo
    if ($params['tipo'] !== 'todos') {
        $conditions[] = "c.tipo = :tipo";
        $query_params[':tipo'] = $params['tipo'];
    }
    
    // Filtro por género
    if ($params['genero_id']) {
        $conditions[] = "c.genero_id = :genero_id";
        $query_params[':genero_id'] = $params['genero_id'];
    } elseif (!empty($params['genero_nombre'])) {
        $conditions[] = "g.nombre LIKE :genero_nombre";
        $query_params[':genero_nombre'] = '%' . $params['genero_nombre'] . '%';
    }
    
    // Filtros de año
    if ($params['año_min']) {
        $conditions[] = "c.año_lanzamiento >= :año_min";
        $query_params[':año_min'] = $params['año_min'];
    }
    if ($params['año_max']) {
        $conditions[] = "c.año_lanzamiento <= :año_max";
        $query_params[':año_max'] = $params['año_max'];
    }
    
    // Filtros de calificación
    if ($params['calificacion_min'] !== null) {
        $conditions[] = "c.calificacion >= :calificacion_min";
        $query_params[':calificacion_min'] = $params['calificacion_min'];
    }
    if ($params['calificacion_max'] !== null) {
        $conditions[] = "c.calificacion <= :calificacion_max";
        $query_params[':calificacion_max'] = $params['calificacion_max'];
    }
    
    // Filtros de duración (solo para películas)
    if ($params['duracion_min'] && $params['tipo'] !== 'serie') {
        $conditions[] = "(c.tipo = 'pelicula' AND c.duracion >= :duracion_min)";
        $query_params[':duracion_min'] = $params['duracion_min'];
    }
    if ($params['duracion_max'] && $params['tipo'] !== 'serie') {
        $conditions[] = "(c.tipo = 'pelicula' AND c.duracion <= :duracion_max)";
        $query_params[':duracion_max'] = $params['duracion_max'];
    }
    
    // Construir WHERE clause
    $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) . " " : "";
    
    // Construir ORDER BY
    $order = construirOrderBy($params);
    
    // Construir LIMIT
    $offset = $params['offset'] !== null ? $params['offset'] : ($params['pagina'] - 1) * $params['limite'];
    $limit = "LIMIT " . $offset . ", " . $params['limite'];
    
    return [
        'select' => $select,
        'from' => $from,
        'where' => $where,
        'order' => $order,
        'limit' => $limit,
        'params' => $query_params
    ];
}

/**
 * Construir ORDER BY dinámico
 */
function construirOrderBy($params) {
    $order_map = [
        'titulo' => 'c.titulo',
        'año' => 'c.año_lanzamiento',
        'calificacion' => 'c.calificacion',
        'fecha_agregado' => 'c.fecha_agregado',
        'popularidad' => 'c.calificacion', // Simplificado
        'relevancia' => 'c.calificacion' // Simplificado
    ];
    
    $campo = isset($order_map[$params['orden']]) ? $order_map[$params['orden']] : 'c.calificacion';
    $direccion = strtoupper($params['direccion']);
    
    // Para relevancia, agregar lógica especial si hay búsqueda de texto
    if ($params['orden'] === 'relevancia' && !empty($params['q'])) {
        return "ORDER BY 
                CASE WHEN c.titulo LIKE '" . $params['q'] . "%' THEN 1 ELSE 2 END,
                c.calificacion DESC ";
    }
    
    return "ORDER BY {$campo} {$direccion} ";
}

/**
 * Procesar resultados de búsqueda
 */
function procesarResultadosBusqueda($resultados_raw, $params, $usuario_logueado) {
    if ($params['solo_ids']) {
        return array_column($resultados_raw, 'id');
    }
    
    $resultados_procesados = [];
    
    foreach ($resultados_raw as $item) {
        $resultado = $item;
        
        // Agregar información adicional
        $resultado['url_detalle'] = "detalle_contenido.php?id=" . $item['id'];
        $resultado['poster_url'] = "uploads/" . ($item['poster'] ?: 'no-image.svg');
        $resultado['tipo_badge'] = $item['tipo'] === 'pelicula' ? '🎬 Película' : '📺 Serie';
        
        // Información de coincidencia si hay búsqueda de texto
        if (!empty($params['q'])) {
            $resultado['relevancia_score'] = calcularRelevancia($item, $params['q']);
            $resultado['coincidencias'] = encontrarCoincidencias($item, $params['q']);
        }
        
        $resultados_procesados[] = $resultado;
    }
    
    return $resultados_procesados;
}

/**
 * Calcular score de relevancia para búsquedas de texto
 */
function calcularRelevancia($item, $termino) {
    $score = 0;
    $termino_lower = strtolower($termino);
    $titulo_lower = strtolower($item['titulo']);
    $descripcion_lower = strtolower($item['descripcion']);
    
    // Coincidencia exacta en título
    if (strpos($titulo_lower, $termino_lower) === 0) {
        $score += 100;
    } elseif (strpos($titulo_lower, $termino_lower) !== false) {
        $score += 50;
    }
    
    // Coincidencia en descripción
    if (strpos($descripcion_lower, $termino_lower) !== false) {
        $score += 20;
    }
    
    // Bonus por calificación alta
    $score += $item['calificacion'] * 10;
    
    return $score;
}

/**
 * Encontrar coincidencias específicas para destacar
 */
function encontrarCoincidencias($item, $termino) {
    $coincidencias = [];
    
    if (stripos($item['titulo'], $termino) !== false) {
        $coincidencias[] = 'titulo';
    }
    
    if (stripos($item['descripcion'], $termino) !== false) {
        $coincidencias[] = 'descripcion';
    }
    
    return $coincidencias;
}

/**
 * Obtener filtros aplicados actualmente
 */
function obtenerFiltrosAplicados($params) {
    $filtros = [];
    
    if (!empty($params['q'])) $filtros['busqueda'] = $params['q'];
    if ($params['tipo'] !== 'todos') $filtros['tipo'] = $params['tipo'];
    if ($params['genero_id']) $filtros['genero_id'] = $params['genero_id'];
    if ($params['año_min']) $filtros['año_minimo'] = $params['año_min'];
    if ($params['año_max']) $filtros['año_maximo'] = $params['año_max'];
    if ($params['calificacion_min'] !== null) $filtros['calificacion_minima'] = $params['calificacion_min'];
    if ($params['calificacion_max'] !== null) $filtros['calificacion_maxima'] = $params['calificacion_max'];
    
    return $filtros;
}

/**
 * Generar estadísticas de la búsqueda
 */
function generarEstadisticasBusqueda($resultados, $params) {
    if (empty($resultados)) {
        return [
            'total_resultados' => 0,
            'peliculas' => 0,
            'series' => 0,
            'calificacion_promedio' => 0,
            'año_mas_comun' => null
        ];
    }
    
    $peliculas = array_filter($resultados, function($r) { return $r['tipo'] === 'pelicula'; });
    $series = array_filter($resultados, function($r) { return $r['tipo'] === 'serie'; });
    
    $calificaciones = array_column($resultados, 'calificacion');
    $años = array_column($resultados, 'año_lanzamiento');
    $año_mas_comun = array_count_values($años);
    arsort($año_mas_comun);
    
    return [
        'total_resultados' => count($resultados),
        'peliculas' => count($peliculas),
        'series' => count($series),
        'calificacion_promedio' => !empty($calificaciones) ? round(array_sum($calificaciones) / count($calificaciones), 1) : 0,
        'año_mas_comun' => !empty($año_mas_comun) ? array_key_first($año_mas_comun) : null,
        'rango_años' => [
            'minimo' => !empty($años) ? min($años) : null,
            'maximo' => !empty($años) ? max($años) : null
        ]
    ];
}

/**
 * Generar sugerencias cuando no hay resultados
 */
function generarSugerenciasBusqueda($params, $contenidoManager) {
    $sugerencias = [];
    
    // Sugerir búsquedas similares
    if (!empty($params['q'])) {
        // Búsquedas más flexibles
        $terminos_similares = generarTerminosSimilares($params['q']);
        $sugerencias['terminos_alternativos'] = $terminos_similares;
        
        // Sugerir remover filtros
        $sugerencias['remover_filtros'] = 'Intenta remover algunos filtros para obtener más resultados';
    }
    
    // Sugerir contenido popular
    try {
        $popular = $contenidoManager->obtenerContenidoDestacado(null, 5);
        $sugerencias['contenido_popular'] = array_map(function($item) {
            return [
                'id' => $item['id'],
                'titulo' => $item['titulo'],
                'tipo' => $item['tipo']
            ];
        }, $popular);
    } catch (Exception $e) {
        // Ignorar error
    }
    
    return $sugerencias;
}

/**
 * Generar términos similares para sugerencias
 */
function generarTerminosSimilares($termino) {
    // Implementación básica - en un proyecto real usarías algoritmos más sofisticados
    $terminos = [];
    
    // Quitar caracteres especiales
    $termino_limpio = preg_replace('/[^a-zA-Z0-9\s]/', '', $termino);
    if ($termino_limpio !== $termino) {
        $terminos[] = $termino_limpio;
    }
    
    // Palabras individuales si es una frase
    if (strpos($termino, ' ') !== false) {
        $palabras = explode(' ', $termino);
        foreach ($palabras as $palabra) {
            if (strlen($palabra) > 3) {
                $terminos[] = $palabra;
            }
        }
    }
    
    return array_unique($terminos);
}

/**
 * Obtener filtros disponibles para la interfaz
 */
function obtenerFiltrosDisponibles($contenidoManager) {
    try {
        return [
            'generos' => $contenidoManager->obtenerGeneros(),
            'tipos' => [
                ['valor' => 'todos', 'nombre' => 'Todos'],
                ['valor' => 'pelicula', 'nombre' => 'Películas'],
                ['valor' => 'serie', 'nombre' => 'Series']
            ],
            'años' => [
                'minimo' => 1900,
                'maximo' => date('Y'),
                'populares' => [2024, 2023, 2022, 2021, 2020]
            ],
            'calificaciones' => [
                'minimo' => 0,
                'maximo' => 5,
                'incremento' => 0.5
            ]
        ];
    } catch (Exception $e) {
        return [];
    }
}
?>