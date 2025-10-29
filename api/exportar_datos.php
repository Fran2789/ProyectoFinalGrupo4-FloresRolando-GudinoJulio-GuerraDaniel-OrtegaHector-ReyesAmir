<?php
/**
 * API REST - Exportar/Importar Datos del Sistema
 * Métodos: GET (exportar), POST (importar)
 * Content-Type: application/json, application/xml, text/xml
 * 
 * === REQUISITO DEL PROYECTO ===
 * "Consumo de Webservices: Usar al menos un archivo XML o JSON para alimentar 
 * o intercambiar información con la aplicación (ej: cargar contenido o guardar/exportar datos)"
 * 
 * === MÉTODOS SOPORTADOS ===
 * 
 * GET - Exportar datos del sistema
 * Parámetros URL:
 * - tipo: Tipo de datos a exportar (string: 'contenido', 'usuarios', 'calificaciones', 'favoritos', 'estadisticas', 'completo')
 * - formato: Formato de exportación (string: 'json', 'xml', default: 'json')
 * - filtro_usuario: ID del usuario específico (integer, solo para admin o datos propios)
 * - filtro_genero: ID del género específico (integer)
 * - filtro_tipo: Tipo de contenido (string: 'pelicula', 'serie', 'todos', default: 'todos')
 * - filtro_fecha_desde: Fecha desde (string: YYYY-MM-DD)
 * - filtro_fecha_hasta: Fecha hasta (string: YYYY-MM-DD)
 * - incluir_metadatos: Incluir metadatos de exportación (boolean, default: true)
 * - nivel_detalle: Nivel de detalle (string: 'basico', 'completo', 'estadisticas', default: 'completo')
 * - limite: Número máximo de registros (integer, default: 1000, max: 10000)
 * - descargar: Forzar descarga como archivo (boolean, default: false)
 * - nombre_archivo: Nombre personalizado del archivo (string)
 * 
 * POST - Importar datos al sistema
 * Body: Archivo XML/JSON o datos en bruto
 * Headers: Content-Type apropiado
 * Parámetros:
 * - tipo_importacion: Tipo de datos a importar (string: 'contenido', 'generos', 'usuarios')
 * - modo: Modo de importación (string: 'insertar', 'actualizar', 'upsert', default: 'upsert')
 * - validar_solo: Solo validar sin importar (boolean, default: false)
 * - sobrescribir: Permitir sobrescribir datos existentes (boolean, default: false, solo admin)
 * 
 * === TIPOS DE EXPORTACIÓN ===
 * - contenido: Películas y series con detalles completos
 * - usuarios: Información de usuarios (solo admin)
 * - calificaciones: Reseñas y calificaciones
 * - favoritos: Listas de favoritos de usuarios
 * - estadisticas: Métricas y estadísticas del sistema
 * - completo: Exportación completa del sistema (solo admin)
 */

// Incluir configuración de API
require_once __DIR__ . '/config_api.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

try {
    // Validar autenticación
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
            manejarExportarDatos($contenidoManager, $usuarioManager);
            break;
            
        case 'POST':
            manejarImportarDatos($contenidoManager, $usuarioManager);
            break;
            
        default:
            ApiResponse::error(
                'Método no soportado',
                'METHOD_NOT_SUPPORTED',
                405,
                ['metodos_soportados' => ['GET', 'POST']]
            );
    }
    
} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en exportar_datos.php: " . $e->getMessage());
    
    ApiResponse::error(
        'Error interno del servidor',
        'INTERNAL_ERROR',
        500
    );
}

/**
 * Manejar GET - Exportar datos del sistema
 */
function manejarExportarDatos($contenidoManager, $usuarioManager) {
    // Obtener parámetros
    $tipo = isset($_GET['tipo']) ? sanitize_input($_GET['tipo']) : 'contenido';
    $formato = isset($_GET['formato']) ? sanitize_input($_GET['formato']) : 'json';
    $filtro_usuario = isset($_GET['filtro_usuario']) ? ApiValidator::validateId($_GET['filtro_usuario'], 'filtro_usuario') : null;
    $filtro_genero = isset($_GET['filtro_genero']) ? ApiValidator::validateId($_GET['filtro_genero'], 'filtro_genero') : null;
    $filtro_tipo = isset($_GET['filtro_tipo']) ? sanitize_input($_GET['filtro_tipo']) : 'todos';
    $filtro_fecha_desde = isset($_GET['filtro_fecha_desde']) ? sanitize_input($_GET['filtro_fecha_desde']) : null;
    $filtro_fecha_hasta = isset($_GET['filtro_fecha_hasta']) ? sanitize_input($_GET['filtro_fecha_hasta']) : null;
    $incluir_metadatos = isset($_GET['incluir_metadatos']) ? filter_var($_GET['incluir_metadatos'], FILTER_VALIDATE_BOOLEAN) : true;
    $nivel_detalle = isset($_GET['nivel_detalle']) ? sanitize_input($_GET['nivel_detalle']) : 'completo';
    $limite = isset($_GET['limite']) ? min(10000, max(1, intval($_GET['limite']))) : 1000;
    $descargar = isset($_GET['descargar']) ? filter_var($_GET['descargar'], FILTER_VALIDATE_BOOLEAN) : false;
    $nombre_archivo = isset($_GET['nombre_archivo']) ? sanitize_input($_GET['nombre_archivo']) : null;
    
    // Validar parámetros
    $tipos_validos = ['contenido', 'usuarios', 'calificaciones', 'favoritos', 'estadisticas', 'completo'];
    if (!in_array($tipo, $tipos_validos)) {
        ApiResponse::error(
            'Tipo de exportación inválido',
            'INVALID_EXPORT_TYPE',
            400,
            ['tipos_validos' => $tipos_validos]
        );
    }
    
    $formatos_validos = ['json', 'xml'];
    if (!in_array($formato, $formatos_validos)) {
        ApiResponse::error(
            'Formato de exportación inválido',
            'INVALID_FORMAT',
            400,
            ['formatos_validos' => $formatos_validos]
        );
    }
    
    $niveles_validos = ['basico', 'completo', 'estadisticas'];
    if (!in_array($nivel_detalle, $niveles_validos)) {
        ApiResponse::error(
            'Nivel de detalle inválido',
            'INVALID_DETAIL_LEVEL',
            400,
            ['niveles_validos' => $niveles_validos]
        );
    }
    
    // Validar permisos
    $tipos_admin_solo = ['usuarios', 'completo'];
    if (in_array($tipo, $tipos_admin_solo) && $_SESSION['user_role'] !== 'admin') {
        ApiResponse::error(
            'No tienes permisos para exportar este tipo de datos',
            'INSUFFICIENT_PERMISSIONS',
            403
        );
    }
    
    // Validar filtro de usuario
    if ($filtro_usuario && $_SESSION['user_id'] != $filtro_usuario && $_SESSION['user_role'] !== 'admin') {
        ApiResponse::error(
            'No tienes permiso para exportar datos de otro usuario',
            'FORBIDDEN',
            403
        );
    }
    
    // Validar fechas
    if ($filtro_fecha_desde && !validarFecha($filtro_fecha_desde)) {
        ApiResponse::error(
            'Formato de fecha inválido para filtro_fecha_desde. Use YYYY-MM-DD',
            'INVALID_DATE_FORMAT',
            400
        );
    }
    
    if ($filtro_fecha_hasta && !validarFecha($filtro_fecha_hasta)) {
        ApiResponse::error(
            'Formato de fecha inválido para filtro_fecha_hasta. Use YYYY-MM-DD',
            'INVALID_DATE_FORMAT',
            400
        );
    }
    
    // Obtener datos según el tipo solicitado
    $datos_exportar = [];
    $metadatos_exportacion = [];
    
    switch ($tipo) {
        case 'contenido':
            $resultado = exportarContenido($contenidoManager, $filtro_genero, $filtro_tipo, $nivel_detalle, $limite);
            $datos_exportar = $resultado['datos'];
            $metadatos_exportacion = $resultado['metadatos'];
            break;
            
        case 'usuarios':
            $resultado = exportarUsuarios($usuarioManager, $nivel_detalle, $limite);
            $datos_exportar = $resultado['datos'];
            $metadatos_exportacion = $resultado['metadatos'];
            break;
            
        case 'calificaciones':
            $resultado = exportarCalificaciones($contenidoManager, $filtro_usuario, $filtro_fecha_desde, $filtro_fecha_hasta, $nivel_detalle, $limite);
            $datos_exportar = $resultado['datos'];
            $metadatos_exportacion = $resultado['metadatos'];
            break;
            
        case 'favoritos':
            $resultado = exportarFavoritos($filtro_usuario ?: $_SESSION['user_id'], $filtro_tipo, $nivel_detalle, $limite);
            $datos_exportar = $resultado['datos'];
            $metadatos_exportacion = $resultado['metadatos'];
            break;
            
        case 'estadisticas':
            $resultado = exportarEstadisticas($usuarioManager, $contenidoManager, $nivel_detalle);
            $datos_exportar = $resultado['datos'];
            $metadatos_exportacion = $resultado['metadatos'];
            break;
            
        case 'completo':
            $resultado = exportarCompleto($contenidoManager, $usuarioManager, $nivel_detalle, $limite);
            $datos_exportar = $resultado['datos'];
            $metadatos_exportacion = $resultado['metadatos'];
            break;
    }
    
    // Preparar exportación final
    $exportacion_final = [];
    
    if ($incluir_metadatos) {
        $exportacion_final['metadatos'] = [
            'version' => '1.0',
            'sistema' => 'CineRecomendaciones',
            'fecha_exportacion' => date('Y-m-d H:i:s'),
            'tipo_exportacion' => $tipo,
            'formato' => $formato,
            'nivel_detalle' => $nivel_detalle,
            'usuario_exportador' => $_SESSION['user_name'],
            'total_registros' => count($datos_exportar),
            'filtros_aplicados' => array_filter([
                'usuario' => $filtro_usuario,
                'genero' => $filtro_genero,
                'tipo' => $filtro_tipo !== 'todos' ? $filtro_tipo : null,
                'fecha_desde' => $filtro_fecha_desde,
                'fecha_hasta' => $filtro_fecha_hasta
            ]),
            'detalles_exportacion' => $metadatos_exportacion
        ];
    }
    
    $exportacion_final['datos'] = $datos_exportar;
    
    // Generar nombre de archivo si no se proporciona
    if (!$nombre_archivo) {
        $timestamp = date('Y-m-d_H-i-s');
        $nombre_archivo = "cinerecomendaciones_{$tipo}_{$timestamp}";
    }
    
    // Exportar en el formato solicitado
    if ($formato === 'xml') {
        exportarComoXML($exportacion_final, $nombre_archivo, $descargar);
    } else {
        exportarComoJSON($exportacion_final, $nombre_archivo, $descargar);
    }
}

/**
 * Manejar POST - Importar datos al sistema
 */
function manejarImportarDatos($contenidoManager, $usuarioManager) {
    // Solo administradores pueden importar
    if ($_SESSION['user_role'] !== 'admin') {
        ApiResponse::error(
            'Solo los administradores pueden importar datos',
            'INSUFFICIENT_PERMISSIONS',
            403
        );
    }
    
    // Obtener parámetros
    $tipo_importacion = isset($_POST['tipo_importacion']) ? sanitize_input($_POST['tipo_importacion']) : 'contenido';
    $modo = isset($_POST['modo']) ? sanitize_input($_POST['modo']) : 'upsert';
    $validar_solo = isset($_POST['validar_solo']) ? filter_var($_POST['validar_solo'], FILTER_VALIDATE_BOOLEAN) : false;
    $sobrescribir = isset($_POST['sobrescribir']) ? filter_var($_POST['sobrescribir'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Validar parámetros
    $tipos_importacion_validos = ['contenido', 'generos', 'usuarios'];
    if (!in_array($tipo_importacion, $tipos_importacion_validos)) {
        ApiResponse::error(
            'Tipo de importación inválido',
            'INVALID_IMPORT_TYPE',
            400,
            ['tipos_validos' => $tipos_importacion_validos]
        );
    }
    
    $modos_validos = ['insertar', 'actualizar', 'upsert'];
    if (!in_array($modo, $modos_validos)) {
        ApiResponse::error(
            'Modo de importación inválido',
            'INVALID_IMPORT_MODE',
            400,
            ['modos_validos' => $modos_validos]
        );
    }
    
    // Obtener datos a importar
    $datos_importar = null;
    $formato_detectado = null;
    
    // Verificar si se subió un archivo
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo_contenido = file_get_contents($_FILES['archivo']['tmp_name']);
        $extension = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
        
        if (in_array(strtolower($extension), ['json'])) {
            $datos_importar = json_decode($archivo_contenido, true);
            $formato_detectado = 'json';
        } elseif (in_array(strtolower($extension), ['xml'])) {
            $datos_importar = convertirXMLaArray($archivo_contenido);
            $formato_detectado = 'xml';
        } else {
            ApiResponse::error(
                'Formato de archivo no soportado. Use JSON o XML',
                'UNSUPPORTED_FILE_FORMAT',
                400
            );
        }
    } else {
        // Intentar obtener datos del cuerpo de la petición
        $raw_input = file_get_contents('php://input');
        
        if (!empty($raw_input)) {
            // Intentar JSON primero
            $datos_json = json_decode($raw_input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $datos_importar = $datos_json;
                $formato_detectado = 'json';
            } else {
                // Intentar XML
                $datos_xml = convertirXMLaArray($raw_input);
                if ($datos_xml !== false) {
                    $datos_importar = $datos_xml;
                    $formato_detectado = 'xml';
                } else {
                    ApiResponse::error(
                        'No se pudieron parsear los datos. Verifique el formato JSON o XML',
                        'PARSE_ERROR',
                        400
                    );
                }
            }
        }
    }
    
    if ($datos_importar === null) {
        ApiResponse::error(
            'No se proporcionaron datos para importar',
            'NO_DATA_PROVIDED',
            400
        );
    }
    
    // Validar estructura de datos
    $errores_validacion = validarEstructuraDatos($datos_importar, $tipo_importacion);
    if (!empty($errores_validacion)) {
        ApiResponse::error(
            'Estructura de datos inválida',
            'INVALID_DATA_STRUCTURE',
            400,
            ['errores' => $errores_validacion]
        );
    }
    
    // Si solo es validación, devolver resultado
    if ($validar_solo) {
        ApiResponse::success([
            'validacion' => 'exitosa',
            'formato_detectado' => $formato_detectado,
            'tipo_importacion' => $tipo_importacion,
            'total_registros' => count($datos_importar['datos']),
            'modo' => $modo
        ], 'Validación completada correctamente');
        return;
    }
    
    // Ejecutar importación
    $resultado_importacion = ejecutarImportacion($datos_importar, $tipo_importacion, $modo, $sobrescribir, $contenidoManager, $usuarioManager);
    
    ApiResponse::success($resultado_importacion, 'Importación completada correctamente');
}

/**
 * Exportar contenido (películas y series)
 */
function exportarContenido($contenidoManager, $filtro_genero, $filtro_tipo, $nivel_detalle, $limite) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Construir query base
        $where_conditions = [];
        $params = [];
        
        if ($filtro_genero) {
            $where_conditions[] = "c.genero_id = :genero_id";
            $params[':genero_id'] = $filtro_genero;
        }
        
        if ($filtro_tipo !== 'todos') {
            $where_conditions[] = "c.tipo = :tipo";
            $params[':tipo'] = $filtro_tipo;
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Seleccionar campos según nivel de detalle
        if ($nivel_detalle === 'basico') {
            $select_fields = "c.id, c.titulo, c.tipo, c.año_lanzamiento, c.calificacion, g.nombre as genero";
        } elseif ($nivel_detalle === 'estadisticas') {
            $select_fields = "c.id, c.titulo, c.tipo, c.calificacion, COUNT(cal.id) as total_calificaciones, AVG(cal.calificacion) as calificacion_promedio_calculada";
        } else {
            $select_fields = "c.*, g.nombre as genero, g.descripcion as genero_descripcion";
        }
        
        $sql = "SELECT {$select_fields}
                FROM contenido c
                LEFT JOIN generos g ON c.genero_id = g.id";
        
        if ($nivel_detalle === 'estadisticas') {
            $sql .= " LEFT JOIN calificaciones cal ON c.id = cal.contenido_id";
        }
        
        $sql .= " {$where_clause}";
        
        if ($nivel_detalle === 'estadisticas') {
            $sql .= " GROUP BY c.id";
        }
        
        $sql .= " ORDER BY c.titulo ASC LIMIT :limite";
        
        $stmt = $conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        
        $stmt->execute();
        $contenido = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener estadísticas adicionales si es nivel completo
        if ($nivel_detalle === 'completo') {
            foreach ($contenido as &$item) {
                // Obtener calificaciones
                $sql_cal = "SELECT calificacion, comentario, u.nombre as usuario_nombre
                           FROM calificaciones cal
                           JOIN usuarios u ON cal.usuario_id = u.id
                           WHERE cal.contenido_id = :contenido_id
                           ORDER BY cal.fecha_calificacion DESC
                           LIMIT 5";
                
                $stmt_cal = $conn->prepare($sql_cal);
                $stmt_cal->bindValue(':contenido_id', $item['id']);
                $stmt_cal->execute();
                $item['calificaciones_recientes'] = $stmt_cal->fetchAll(PDO::FETCH_ASSOC);
                
                // Agregar URL del poster
                $item['poster_url'] = 'uploads/' . ($item['poster'] ?: 'no-image.svg');
            }
        }
        
        $metadatos = [
            'total_exportado' => count($contenido),
            'filtros' => [
                'genero' => $filtro_genero,
                'tipo' => $filtro_tipo
            ],
            'nivel_detalle' => $nivel_detalle
        ];
        
        return ['datos' => $contenido, 'metadatos' => $metadatos];
        
    } catch (Exception $e) {
        error_log("Error exportando contenido: " . $e->getMessage());
        return ['datos' => [], 'metadatos' => ['error' => $e->getMessage()]];
    }
}

/**
 * Exportar usuarios (solo admin)
 */
function exportarUsuarios($usuarioManager, $nivel_detalle, $limite) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($nivel_detalle === 'basico') {
            $select_fields = "id, nombre, email, rol, activo, fecha_registro";
        } elseif ($nivel_detalle === 'estadisticas') {
            $select_fields = "u.id, u.nombre, u.rol, u.fecha_registro, 
                             COUNT(DISTINCT c.id) as total_calificaciones,
                             COUNT(DISTINCT f.id) as total_favoritos,
                             AVG(c.calificacion) as calificacion_promedio";
        } else {
            $select_fields = "u.*, COUNT(DISTINCT c.id) as total_calificaciones, COUNT(DISTINCT f.id) as total_favoritos";
        }
        
        $sql = "SELECT {$select_fields}
                FROM usuarios u";
        
        if ($nivel_detalle !== 'basico') {
            $sql .= " LEFT JOIN calificaciones c ON u.id = c.usuario_id
                     LEFT JOIN favoritos f ON u.id = f.usuario_id";
        }
        
        $sql .= " GROUP BY u.id
                 ORDER BY u.fecha_registro DESC
                 LIMIT :limite";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remover información sensible
        foreach ($usuarios as &$usuario) {
            unset($usuario['password']); // No exportar contraseñas
            
            if ($nivel_detalle === 'completo') {
                // Agregar estadísticas adicionales
                $estadisticas = $usuarioManager->obtenerEstadisticasUsuario($usuario['id']);
                $usuario['estadisticas_detalladas'] = $estadisticas;
            }
        }
        
        $metadatos = [
            'total_exportado' => count($usuarios),
            'nivel_detalle' => $nivel_detalle,
            'informacion_sensible_excluida' => ['password', 'tokens_sesion']
        ];
        
        return ['datos' => $usuarios, 'metadatos' => $metadatos];
        
    } catch (Exception $e) {
        error_log("Error exportando usuarios: " . $e->getMessage());
        return ['datos' => [], 'metadatos' => ['error' => $e->getMessage()]];
    }
}

/**
 * Exportar calificaciones
 */
function exportarCalificaciones($contenidoManager, $filtro_usuario, $filtro_fecha_desde, $filtro_fecha_hasta, $nivel_detalle, $limite) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Construir WHERE
        $where_conditions = [];
        $params = [];
        
        if ($filtro_usuario) {
            $where_conditions[] = "cal.usuario_id = :usuario_id";
            $params[':usuario_id'] = $filtro_usuario;
        }
        
        if ($filtro_fecha_desde) {
            $where_conditions[] = "DATE(cal.fecha_calificacion) >= :fecha_desde";
            $params[':fecha_desde'] = $filtro_fecha_desde;
        }
        
        if ($filtro_fecha_hasta) {
            $where_conditions[] = "DATE(cal.fecha_calificacion) <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtro_fecha_hasta;
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        if ($nivel_detalle === 'basico') {
            $select_fields = "cal.calificacion, cal.fecha_calificacion, c.titulo, u.nombre as usuario";
        } else {
            $select_fields = "cal.*, c.titulo, c.tipo, c.año_lanzamiento, u.nombre as usuario, g.nombre as genero";
        }
        
        $sql = "SELECT {$select_fields}
                FROM calificaciones cal
                JOIN contenido c ON cal.contenido_id = c.id
                JOIN usuarios u ON cal.usuario_id = u.id
                LEFT JOIN generos g ON c.genero_id = g.id
                {$where_clause}
                ORDER BY cal.fecha_calificacion DESC
                LIMIT :limite";
        
        $stmt = $conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        
        $stmt->execute();
        $calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $metadatos = [
            'total_exportado' => count($calificaciones),
            'filtros' => [
                'usuario' => $filtro_usuario,
                'fecha_desde' => $filtro_fecha_desde,
                'fecha_hasta' => $filtro_fecha_hasta
            ],
            'rango_fechas' => [
                'primera_calificacion' => !empty($calificaciones) ? end($calificaciones)['fecha_calificacion'] : null,
                'ultima_calificacion' => !empty($calificaciones) ? reset($calificaciones)['fecha_calificacion'] : null
            ]
        ];
        
        return ['datos' => $calificaciones, 'metadatos' => $metadatos];
        
    } catch (Exception $e) {
        error_log("Error exportando calificaciones: " . $e->getMessage());
        return ['datos' => [], 'metadatos' => ['error' => $e->getMessage()]];
    }
}

/**
 * Exportar favoritos
 */
function exportarFavoritos($usuario_id, $filtro_tipo, $nivel_detalle, $limite) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $where_conditions = ["f.usuario_id = :usuario_id"];
        $params = [':usuario_id' => $usuario_id];
        
        if ($filtro_tipo !== 'todos') {
            $where_conditions[] = "c.tipo = :tipo";
            $params[':tipo'] = $filtro_tipo;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        if ($nivel_detalle === 'basico') {
            $select_fields = "f.fecha_agregado, c.titulo, c.tipo, c.año_lanzamiento";
        } else {
            $select_fields = "f.*, c.titulo, c.tipo, c.descripcion, c.calificacion, c.año_lanzamiento, g.nombre as genero";
        }
        
        $sql = "SELECT {$select_fields}
                FROM favoritos f
                JOIN contenido c ON f.contenido_id = c.id
                LEFT JOIN generos g ON c.genero_id = g.id
                {$where_clause}
                ORDER BY f.fecha_agregado DESC
                LIMIT :limite";
        
        $stmt = $conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        
        $stmt->execute();
        $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $metadatos = [
            'total_exportado' => count($favoritos),
            'usuario_id' => $usuario_id,
            'filtros' => ['tipo' => $filtro_tipo]
        ];
        
        return ['datos' => $favoritos, 'metadatos' => $metadatos];
        
    } catch (Exception $e) {
        error_log("Error exportando favoritos: " . $e->getMessage());
        return ['datos' => [], 'metadatos' => ['error' => $e->getMessage()]];
    }
}

/**
 * Exportar estadísticas del sistema
 */
function exportarEstadisticas($usuarioManager, $contenidoManager, $nivel_detalle) {
    try {
        $estadisticas = [
            'sistema' => [
                'version' => '1.0',
                'fecha_reporte' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Estadísticas básicas
        $estadisticas['resumen'] = $usuarioManager->obtenerEstadisticasGenerales();
        
        if ($nivel_detalle === 'completo' || $nivel_detalle === 'estadisticas') {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Estadísticas de contenido por género
            $sql = "SELECT g.nombre, COUNT(c.id) as cantidad, AVG(c.calificacion) as calificacion_promedio
                   FROM generos g
                   LEFT JOIN contenido c ON g.id = c.genero_id
                   GROUP BY g.id, g.nombre
                   ORDER BY cantidad DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $estadisticas['contenido_por_genero'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top contenido mejor calificado
            $sql = "SELECT c.titulo, c.tipo, c.calificacion, COUNT(cal.id) as total_calificaciones
                   FROM contenido c
                   LEFT JOIN calificaciones cal ON c.id = cal.contenido_id
                   GROUP BY c.id
                   HAVING total_calificaciones >= 3
                   ORDER BY c.calificacion DESC, total_calificaciones DESC
                   LIMIT 10";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $estadisticas['top_contenido'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Actividad por mes
            $sql = "SELECT 
                       DATE_FORMAT(fecha_calificacion, '%Y-%m') as mes,
                       COUNT(*) as calificaciones,
                       AVG(calificacion) as calificacion_promedio
                   FROM calificaciones
                   WHERE fecha_calificacion >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                   GROUP BY DATE_FORMAT(fecha_calificacion, '%Y-%m')
                   ORDER BY mes DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $estadisticas['actividad_mensual'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $metadatos = [
            'nivel_detalle' => $nivel_detalle,
            'fecha_generacion' => date('Y-m-d H:i:s'),
            'tipo_reporte' => 'estadisticas_sistema'
        ];
        
        return ['datos' => $estadisticas, 'metadatos' => $metadatos];
        
    } catch (Exception $e) {
        error_log("Error exportando estadísticas: " . $e->getMessage());
        return ['datos' => [], 'metadatos' => ['error' => $e->getMessage()]];
    }
}

/**
 * Exportar datos completos del sistema (solo admin)
 */
function exportarCompleto($contenidoManager, $usuarioManager, $nivel_detalle, $limite) {
    $exportacion_completa = [
        'contenido' => exportarContenido($contenidoManager, null, 'todos', $nivel_detalle, $limite)['datos'],
        'usuarios' => exportarUsuarios($usuarioManager, $nivel_detalle, $limite)['datos'],
        'estadisticas' => exportarEstadisticas($usuarioManager, $contenidoManager, $nivel_detalle)['datos']
    ];
    
    $metadatos = [
        'tipo_exportacion' => 'completa',
        'nivel_detalle' => $nivel_detalle,
        'secciones' => ['contenido', 'usuarios', 'estadisticas'],
        'total_contenido' => count($exportacion_completa['contenido']),
        'total_usuarios' => count($exportacion_completa['usuarios'])
    ];
    
    return ['datos' => $exportacion_completa, 'metadatos' => $metadatos];
}

/**
 * Exportar datos como JSON
 */
function exportarComoJSON($datos, $nombre_archivo, $descargar) {
    $json_output = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($descargar) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.json"');
        header('Content-Length: ' . strlen($json_output));
        echo $json_output;
        exit;
    } else {
        ApiResponse::success([
            'datos_exportados' => $datos,
            'formato' => 'json',
            'nombre_archivo' => $nombre_archivo . '.json',
            'tamaño_bytes' => strlen($json_output)
        ], 'Exportación JSON completada');
    }
}

/**
 * Exportar datos como XML
 */
function exportarComoXML($datos, $nombre_archivo, $descargar) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><exportacion></exportacion>');
    
    arrayToXML($datos, $xml);
    
    $xml_output = $xml->asXML();
    
    if ($descargar) {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.xml"');
        header('Content-Length: ' . strlen($xml_output));
        echo $xml_output;
        exit;
    } else {
        ApiResponse::success([
            'datos_exportados' => $datos,
            'formato' => 'xml',
            'nombre_archivo' => $nombre_archivo . '.xml',
            'tamaño_bytes' => strlen($xml_output)
        ], 'Exportación XML completada');
    }
}

/**
 * Convertir array a XML recursivamente
 */
function arrayToXML($array, &$xml) {
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            if (is_numeric($key)) {
                $key = 'item_' . $key;
            }
            $subnode = $xml->addChild($key);
            arrayToXML($value, $subnode);
        } else {
            if (is_numeric($key)) {
                $key = 'item_' . $key;
            }
            $xml->addChild($key, htmlspecialchars($value));
        }
    }
}

/**
 * Convertir XML a array
 */
function convertirXMLaArray($xml_string) {
    try {
        $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xml), true);
    } catch (Exception $e) {
        error_log("Error convirtiendo XML: " . $e->getMessage());
        return false;
    }
}

/**
 * Validar estructura de datos para importación
 */
function validarEstructuraDatos($datos, $tipo_importacion) {
    $errores = [];
    
    if (!isset($datos['datos']) || !is_array($datos['datos'])) {
        $errores[] = 'Estructura inválida: se requiere campo "datos" con array de elementos';
        return $errores;
    }
    
    switch ($tipo_importacion) {
        case 'contenido':
            foreach ($datos['datos'] as $index => $item) {
                if (!isset($item['titulo']) || empty($item['titulo'])) {
                    $errores[] = "Registro {$index}: campo 'titulo' requerido";
                }
                if (!isset($item['tipo']) || !in_array($item['tipo'], ['pelicula', 'serie'])) {
                    $errores[] = "Registro {$index}: campo 'tipo' debe ser 'pelicula' o 'serie'";
                }
            }
            break;
            
        case 'usuarios':
            foreach ($datos['datos'] as $index => $item) {
                if (!isset($item['email']) || !filter_var($item['email'], FILTER_VALIDATE_EMAIL)) {
                    $errores[] = "Registro {$index}: campo 'email' inválido";
                }
                if (!isset($item['nombre']) || empty($item['nombre'])) {
                    $errores[] = "Registro {$index}: campo 'nombre' requerido";
                }
            }
            break;
    }
    
    return $errores;
}

/**
 * Ejecutar importación de datos
 */
function ejecutarImportacion($datos, $tipo_importacion, $modo, $sobrescribir, $contenidoManager, $usuarioManager) {
    $resultados = [
        'total_procesados' => 0,
        'insertados' => 0,
        'actualizados' => 0,
        'errores' => 0,
        'detalles' => []
    ];
    
    foreach ($datos['datos'] as $index => $item) {
        try {
            $resultado_item = null;
            
            switch ($tipo_importacion) {
                case 'contenido':
                    $resultado_item = importarContenido($item, $modo, $sobrescribir, $contenidoManager);
                    break;
                    
                case 'usuarios':
                    $resultado_item = importarUsuario($item, $modo, $sobrescribir, $usuarioManager);
                    break;
            }
            
            if ($resultado_item) {
                if ($resultado_item['accion'] === 'insertado') {
                    $resultados['insertados']++;
                } elseif ($resultado_item['accion'] === 'actualizado') {
                    $resultados['actualizados']++;
                }
                
                $resultados['detalles'][] = [
                    'indice' => $index,
                    'accion' => $resultado_item['accion'],
                    'id' => $resultado_item['id']
                ];
            }
            
            $resultados['total_procesados']++;
            
        } catch (Exception $e) {
            $resultados['errores']++;
            $resultados['detalles'][] = [
                'indice' => $index,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $resultados;
}

/**
 * Importar contenido individual
 */
function importarContenido($item, $modo, $sobrescribir, $contenidoManager) {
    // Implementación simplificada - en un proyecto real sería más robusta
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Verificar si existe contenido con el mismo título
        $sql_check = "SELECT id FROM contenido WHERE titulo = :titulo AND tipo = :tipo";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindParam(':titulo', $item['titulo']);
        $stmt_check->bindParam(':tipo', $item['tipo']);
        $stmt_check->execute();
        $existente = $stmt_check->fetch();
        
        if ($existente && !$sobrescribir) {
            throw new Exception("Contenido ya existe y sobrescribir está deshabilitado");
        }
        
        if ($existente && ($modo === 'actualizar' || $modo === 'upsert')) {
            // Actualizar existente
            $sql = "UPDATE contenido SET descripcion = :descripcion, año_lanzamiento = :año WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':descripcion', $item['descripcion']);
            $stmt->bindParam(':año', $item['año_lanzamiento']);
            $stmt->bindParam(':id', $existente['id']);
            $stmt->execute();
            
            return ['accion' => 'actualizado', 'id' => $existente['id']];
        } else {
            // Insertar nuevo
            $sql = "INSERT INTO contenido (titulo, tipo, descripcion, año_lanzamiento, genero_id) VALUES (:titulo, :tipo, :descripcion, :año, :genero_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':titulo', $item['titulo']);
            $stmt->bindParam(':tipo', $item['tipo']);
            $stmt->bindParam(':descripcion', $item['descripcion']);
            $stmt->bindParam(':año', $item['año_lanzamiento']);
            $stmt->bindParam(':genero_id', $item['genero_id'] ?? 1);
            $stmt->execute();
            
            return ['accion' => 'insertado', 'id' => $conn->lastInsertId()];
        }
        
    } catch (Exception $e) {
        throw new Exception("Error importando contenido: " . $e->getMessage());
    }
}

/**
 * Importar usuario individual (solo admin)
 */
function importarUsuario($item, $modo, $sobrescribir, $usuarioManager) {
    // Implementación simplificada
    throw new Exception("Importación de usuarios no implementada en esta versión");
}

/**
 * Validar formato de fecha YYYY-MM-DD
 */
function validarFecha($fecha) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}
?>