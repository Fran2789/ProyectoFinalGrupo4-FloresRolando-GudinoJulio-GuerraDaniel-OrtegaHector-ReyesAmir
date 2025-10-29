<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Verificar autenticación y permisos de administrador
if (!isLoggedIn()) {
    redirect('auth/iniciar_sesion.php?redirect=' . urlencode('admin/reportes.php'));
}

if (!isAdmin()) {
    redirect('inicio.php');
}

$contenidoManager = new ContenidoManager();
$usuarioManager = new UsuarioManager();

// Verificar sesión
$usuarioManager->verificarSesion();

// Variables para filtros
$filtros = [
    'fecha_desde' => isset($_GET['fecha_desde']) ? sanitize_input($_GET['fecha_desde']) : date('Y-m-01'),
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? sanitize_input($_GET['fecha_hasta']) : date('Y-m-d'),
    'tipo_reporte' => isset($_GET['tipo_reporte']) ? sanitize_input($_GET['tipo_reporte']) : 'general',
    'formato_export' => isset($_GET['formato']) ? sanitize_input($_GET['formato']) : 'json'
];

// Procesar exportaciones
if (isset($_GET['export'])) {
    $tipo_export = sanitize_input($_GET['export']);
    procesarExportacion($tipo_export, $filtros, $usuarioManager, $contenidoManager);
    exit();
}

// Obtener datos para reportes
$estadisticas_generales = obtenerEstadisticasGenerales($usuarioManager, $contenidoManager, $filtros);
$datos_graficos = obtenerDatosGraficos($usuarioManager, $contenidoManager, $filtros);
$reportes_detallados = obtenerReportesDetallados($usuarioManager, $contenidoManager, $filtros);

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

/**
 * Función para obtener estadísticas generales
 */
function obtenerEstadisticasGenerales($usuarioManager, $contenidoManager, $filtros) {
    $stats = [];
    
    // Estadísticas de usuarios
    $stats['usuarios'] = $usuarioManager->obtenerEstadisticasGenerales();
    
    // Estadísticas de contenido
    $stats['contenido'] = $contenidoManager->obtenerEstadisticasContenido();
    
    // Estadísticas del período seleccionado
    $stats['periodo'] = obtenerEstadisticasPeriodo($usuarioManager, $contenidoManager, $filtros);
    
    return $stats;
}

/**
 * Función para obtener estadísticas de un período específico
 */
function obtenerEstadisticasPeriodo($usuarioManager, $contenidoManager, $filtros) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $fecha_desde = $filtros['fecha_desde'];
        $fecha_hasta = $filtros['fecha_hasta'];
        
        $stats = [];
        
        // Nuevos registros en el período
        $sql = "SELECT COUNT(*) as nuevos_usuarios FROM usuarios 
                WHERE fecha_registro BETWEEN :fecha_desde AND :fecha_hasta";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':fecha_desde', $fecha_desde);
        $stmt->bindParam(':fecha_hasta', $fecha_hasta);
        $stmt->execute();
        $stats['nuevos_usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC)['nuevos_usuarios'];
        
        // Actividad de navegación en el período
        $sql = "SELECT COUNT(*) as visitas_totales, 
                       COUNT(DISTINCT usuario_id) as usuarios_activos,
                       COUNT(DISTINCT contenido_id) as contenido_visitado
                FROM historial_navegacion 
                WHERE fecha_visita BETWEEN :fecha_desde AND :fecha_hasta";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':fecha_desde', $fecha_desde);
        $stmt->bindParam(':fecha_hasta', $fecha_hasta);
        $stmt->execute();
        $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats = array_merge($stats, $actividad);
        
        // Calificaciones en el período
        $sql = "SELECT COUNT(*) as nuevas_calificaciones,
                       AVG(calificacion) as calificacion_promedio_periodo
                FROM calificaciones 
                WHERE fecha_calificacion BETWEEN :fecha_desde AND :fecha_hasta";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':fecha_desde', $fecha_desde);
        $stmt->bindParam(':fecha_hasta', $fecha_hasta);
        $stmt->execute();
        $calificaciones = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats = array_merge($stats, $calificaciones);
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Error en obtenerEstadisticasPeriodo: " . $e->getMessage());
        return [];
    }
}

/**
 * Función para obtener datos para gráficos
 */
function obtenerDatosGraficos($usuarioManager, $contenidoManager, $filtros) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $datos = [];
        
        // Registros por mes (últimos 12 meses)
        $sql = "SELECT 
                    DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
                    COUNT(*) as registros
                FROM usuarios 
                WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
                ORDER BY mes";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $datos['registros_mensuales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contenido más visto
        $sql = "SELECT c.titulo, c.tipo, COUNT(h.id) as visitas
                FROM contenido c
                LEFT JOIN historial_navegacion h ON c.id = h.contenido_id
                WHERE c.activo = 1
                GROUP BY c.id
                ORDER BY visitas DESC
                LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $datos['contenido_popular'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Distribución por géneros
        $sql = "SELECT g.nombre, COUNT(c.id) as cantidad
                FROM generos g
                LEFT JOIN contenido c ON g.id = c.genero_id AND c.activo = 1
                GROUP BY g.id, g.nombre
                ORDER BY cantidad DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $datos['distribucion_generos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Actividad diaria (últimos 30 días)
        $sql = "SELECT 
                    DATE(fecha_visita) as fecha,
                    COUNT(*) as visitas,
                    COUNT(DISTINCT usuario_id) as usuarios_unicos
                FROM historial_navegacion 
                WHERE fecha_visita >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(fecha_visita)
                ORDER BY fecha";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $datos['actividad_diaria'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $datos;
        
    } catch (PDOException $e) {
        error_log("Error en obtenerDatosGraficos: " . $e->getMessage());
        return [];
    }
}

/**
 * Función para obtener reportes detallados
 */
function obtenerReportesDetallados($usuarioManager, $contenidoManager, $filtros) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $reportes = [];
        
        // Top usuarios más activos
        $sql = "SELECT u.nombre, u.email, u.fecha_registro,
                       COUNT(DISTINCT h.contenido_id) as contenido_visto,
                       COUNT(c.id) as calificaciones_dadas,
                       AVG(c.calificacion) as calificacion_promedio
                FROM usuarios u
                LEFT JOIN historial_navegacion h ON u.id = h.usuario_id
                LEFT JOIN calificaciones c ON u.id = c.usuario_id
                WHERE u.activo = 1
                GROUP BY u.id
                ORDER BY contenido_visto DESC, calificaciones_dadas DESC
                LIMIT 15";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $reportes['usuarios_activos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contenido mejor calificado
        $sql = "SELECT c.titulo, c.tipo, g.nombre as genero, c.año_lanzamiento,
                       c.calificacion, COUNT(cal.id) as numero_calificaciones
                FROM contenido c
                LEFT JOIN generos g ON c.genero_id = g.id
                LEFT JOIN calificaciones cal ON c.id = cal.contenido_id
                WHERE c.activo = 1
                GROUP BY c.id
                HAVING numero_calificaciones >= 3
                ORDER BY c.calificacion DESC, numero_calificaciones DESC
                LIMIT 15";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $reportes['contenido_mejor_calificado'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Análisis de preferencias por género
        $sql = "SELECT g.nombre as genero,
                       COUNT(DISTINCT h.usuario_id) as usuarios_interesados,
                       COUNT(h.id) as total_visitas,
                       AVG(c.calificacion) as calificacion_promedio_genero
                FROM generos g
                LEFT JOIN contenido c ON g.id = c.genero_id
                LEFT JOIN historial_navegacion h ON c.id = h.contenido_id
                WHERE c.activo = 1
                GROUP BY g.id, g.nombre
                ORDER BY usuarios_interesados DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $reportes['analisis_generos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Tendencias de contenido por año
        $sql = "SELECT c.año_lanzamiento,
                       COUNT(*) as cantidad_contenido,
                       AVG(c.calificacion) as calificacion_promedio,
                       SUM(CASE WHEN c.tipo = 'pelicula' THEN 1 ELSE 0 END) as peliculas,
                       SUM(CASE WHEN c.tipo = 'serie' THEN 1 ELSE 0 END) as series
                FROM contenido c
                WHERE c.activo = 1
                GROUP BY c.año_lanzamiento
                ORDER BY c.año_lanzamiento DESC
                LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $reportes['tendencias_por_año'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $reportes;
        
    } catch (PDOException $e) {
        error_log("Error en obtenerReportesDetallados: " . $e->getMessage());
        return [];
    }
}

/**
 * Función para procesar exportaciones
 */
function procesarExportacion($tipo, $filtros, $usuarioManager, $contenidoManager) {
    $formato = $filtros['formato_export'];
    $fecha_actual = date('Y-m-d_H-i-s');
    
    switch ($tipo) {
        case 'usuarios':
            $datos = $usuarioManager->obtenerTodosUsuarios(1, 1000, []);
            $filename = "usuarios_export_{$fecha_actual}";
            exportarDatos($datos['usuarios'], $formato, $filename, 'usuarios');
            break;
            
        case 'contenido':
            $datos = $contenidoManager->obtenerContenidoPaginado(1, 1000, []);
            $filename = "contenido_export_{$fecha_actual}";
            exportarDatos($datos['contenido'], $formato, $filename, 'contenido');
            break;
            
        case 'estadisticas':
            $stats = obtenerEstadisticasGenerales($usuarioManager, $contenidoManager, $filtros);
            $filename = "estadisticas_export_{$fecha_actual}";
            exportarDatos($stats, $formato, $filename, 'estadisticas');
            break;
            
        case 'actividad':
            $datos = obtenerReportesDetallados($usuarioManager, $contenidoManager, $filtros);
            $filename = "actividad_export_{$fecha_actual}";
            exportarDatos($datos, $formato, $filename, 'actividad');
            break;
            
        default:
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Tipo de exportación no válido']);
            break;
    }
}

/**
 * Función para exportar datos en diferentes formatos
 */
function exportarDatos($datos, $formato, $filename, $tipo) {
    switch ($formato) {
        case 'json':
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
            echo json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            if (!empty($datos) && is_array($datos)) {
                // Si es un array multidimensional, usar el primer elemento para headers
                $primera_fila = is_array($datos[0]) ? $datos[0] : $datos;
                
                if (is_array($primera_fila)) {
                    // Escribir headers
                    fputcsv($output, array_keys($primera_fila));
                    
                    // Escribir datos
                    foreach ($datos as $fila) {
                        if (is_array($fila)) {
                            fputcsv($output, $fila);
                        }
                    }
                }
            }
            
            fclose($output);
            break;
            
        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$filename}.xml\"");
            
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root></root>');
            arrayToXml($datos, $xml, $tipo);
            
            echo $xml->asXML();
            break;
            
        default:
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Formato no soportado']);
            break;
    }
}

/**
 * Función helper para convertir array a XML
 */
function arrayToXml($data, $xml, $node_name = 'item') {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (is_numeric($key)) {
                $subnode = $xml->addChild($node_name);
                arrayToXml($value, $subnode, $node_name);
            } else {
                $subnode = $xml->addChild($key);
                arrayToXml($value, $subnode, 'item');
            }
        } else {
            if (is_numeric($key)) {
                $xml->addChild($node_name, htmlspecialchars($value));
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Análisis - Administración</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="<?php echo $theme === 'dark' ? 'dark-theme' : ''; ?>">
    
    <!-- Navegación -->
    <nav class="navbar">
        <div class="container">
            <a href="../inicio.php" class="logo">CineRecomendaciones</a>
            
            <ul class="nav-links">
                <li><a href="../inicio.php">Inicio</a></li>
                <li><a href="../catalogo.php">Catálogo</a></li>
                <li><a href="../mis_recomendaciones.php">Mis Recomendaciones</a></li>
                <li><a href="../perfil_usuario.php">Mi Perfil</a></li>
                <li><a href="panel_admin.php">👑 Administración</a></li>
            </ul>
            
            <div class="user-menu">
                <button class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                    🌙
                </button>
                <span class="user-welcome">Admin: <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="../auth/cerrar_sesion.php" class="btn btn-outline">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <main class="container">
        
        <!-- Header de la página -->
        <section class="admin-header mb-4">
            <div class="admin-title">
                <h1>📈 Reportes y Análisis</h1>
                <p>Estadísticas detalladas y exportación de datos del sistema</p>
            </div>
            
            <div class="admin-actions">
                <button type="button" class="btn btn-primary" onclick="mostrarFiltros()">
                    🔍 Filtros Avanzados
                </button>
                <a href="panel_admin.php" class="btn btn-secondary">
                    📊 Volver al Dashboard
                </a>
            </div>
        </section>

        <!-- Filtros de período -->
        <section class="report-filters mb-4" id="filtrosSection">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🗓️ Configurar Período de Análisis</h3>
                </div>
                
                <form method="GET" action="reportes.php" class="filters-form">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="fecha_desde">Desde:</label>
                            <input type="date" 
                                   id="fecha_desde" 
                                   name="fecha_desde" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="fecha_hasta">Hasta:</label>
                            <input type="date" 
                                   id="fecha_hasta" 
                                   name="fecha_hasta" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="tipo_reporte">Tipo de reporte:</label>
                            <select id="tipo_reporte" name="tipo_reporte" class="form-control">
                                <option value="general" <?php echo $filtros['tipo_reporte'] === 'general' ? 'selected' : ''; ?>>
                                    📊 General
                                </option>
                                <option value="usuarios" <?php echo $filtros['tipo_reporte'] === 'usuarios' ? 'selected' : ''; ?>>
                                    👥 Usuarios
                                </option>
                                <option value="contenido" <?php echo $filtros['tipo_reporte'] === 'contenido' ? 'selected' : ''; ?>>
                                    🎬 Contenido
                                </option>
                                <option value="actividad" <?php echo $filtros['tipo_reporte'] === 'actividad' ? 'selected' : ''; ?>>
                                    📈 Actividad
                                </option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                🔄 Actualizar Reporte
                            </button>
                        </div>
                    </div>
                    
                    <div class="quick-filters">
                        <button type="button" class="btn btn-outline btn-sm" onclick="setQuickFilter('today')">
                            Hoy
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setQuickFilter('week')">
                            Esta semana
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setQuickFilter('month')">
                            Este mes
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setQuickFilter('quarter')">
                            Trimestre
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setQuickFilter('year')">
                            Este año
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Estadísticas del período seleccionado -->
        <section class="period-stats mb-4">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👤</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $estadisticas_generales['periodo']['nuevos_usuarios'] ?? 0; ?></div>
                        <div class="stat-label">Nuevos Usuarios</div>
                        <div class="stat-period">En el período seleccionado</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👁️</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($estadisticas_generales['periodo']['visitas_totales'] ?? 0); ?></div>
                        <div class="stat-label">Visitas Totales</div>
                        <div class="stat-period">Actividad de navegación</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $estadisticas_generales['periodo']['usuarios_activos'] ?? 0; ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                        <div class="stat-period">Únicos en el período</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $estadisticas_generales['periodo']['nuevas_calificaciones'] ?? 0; ?></div>
                        <div class="stat-label">Nuevas Reseñas</div>
                        <div class="stat-period">Promedio: <?php echo number_format($estadisticas_generales['periodo']['calificacion_promedio_periodo'] ?? 0, 1); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Exportaciones rápidas -->
        <section class="export-section mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📄 Exportar Datos</h3>
                    <p>Descarga reportes en diferentes formatos</p>
                </div>
                
                <div class="export-grid">
                    <div class="export-item">
                        <h4>👥 Datos de Usuarios</h4>
                        <p>Lista completa de usuarios registrados con estadísticas</p>
                        <div class="export-actions">
                            <a href="reportes.php?export=usuarios&formato=json" class="btn btn-outline btn-sm">JSON</a>
                            <a href="reportes.php?export=usuarios&formato=csv" class="btn btn-outline btn-sm">CSV</a>
                            <a href="reportes.php?export=usuarios&formato=xml" class="btn btn-outline btn-sm">XML</a>
                        </div>
                    </div>
                    
                    <div class="export-item">
                        <h4>🎬 Catálogo de Contenido</h4>
                        <p>Todas las películas y series con sus detalles</p>
                        <div class="export-actions">
                            <a href="reportes.php?export=contenido&formato=json" class="btn btn-outline btn-sm">JSON</a>
                            <a href="reportes.php?export=contenido&formato=csv" class="btn btn-outline btn-sm">CSV</a>
                            <a href="reportes.php?export=contenido&formato=xml" class="btn btn-outline btn-sm">XML</a>
                        </div>
                    </div>
                    
                    <div class="export-item">
                        <h4>📊 Estadísticas Generales</h4>
                        <p>Resumen completo del sistema y métricas</p>
                        <div class="export-actions">
                            <a href="reportes.php?export=estadisticas&formato=json" class="btn btn-outline btn-sm">JSON</a>
                            <a href="reportes.php?export=estadisticas&formato=csv" class="btn btn-outline btn-sm">CSV</a>
                            <a href="reportes.php?export=estadisticas&formato=xml" class="btn btn-outline btn-sm">XML</a>
                        </div>
                    </div>
                    
                    <div class="export-item">
                        <h4>📈 Reporte de Actividad</h4>
                        <p>Análisis detallado de uso y tendencias</p>
                        <div class="export-actions">
                            <a href="reportes.php?export=actividad&formato=json" class="btn btn-outline btn-sm">JSON</a>
                            <a href="reportes.php?export=actividad&formato=csv" class="btn btn-outline btn-sm">CSV</a>
                            <a href="reportes.php?export=actividad&formato=xml" class="btn btn-outline btn-sm">XML</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Gráficos y análisis visual -->
        <section class="charts-section mb-4">
            <div class="charts-grid">
                <!-- Gráfico de registros mensuales -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📅 Registros por Mes</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="registrosChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Gráfico de distribución por géneros -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🎭 Distribución por Géneros</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="generosChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Gráfico de actividad diaria -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">⚡ Actividad Diaria (30 días)</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="actividadChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Gráfico de contenido popular -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🔥 Contenido Más Popular</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="popularChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- Reportes detallados -->
        <section class="detailed-reports">
            <div class="reports-tabs">
                <button class="tab-btn active" onclick="showTab('usuarios-activos')">👥 Usuarios Activos</button>
                <button class="tab-btn" onclick="showTab('contenido-popular')">🎬 Contenido Popular</button>
                <button class="tab-btn" onclick="showTab('analisis-generos')">🎭 Análisis de Géneros</button>
                <button class="tab-btn" onclick="showTab('tendencias')">📈 Tendencias</button>
            </div>

            <!-- Tab: Usuarios Activos -->
            <div id="usuarios-activos" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">👥 Top Usuarios Más Activos</h3>
                    </div>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Pos.</th>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Contenido Visto</th>
                                    <th>Reseñas</th>
                                    <th>Calificación Promedio</th>
                                    <th>Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes_detallados['usuarios_activos'] as $index => $usuario): ?>
                                    <tr>
                                        <td class="position"><?php echo $index + 1; ?></td>
                                        <td class="user-name"><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                        <td class="user-email"><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td class="stat-number"><?php echo $usuario['contenido_visto']; ?></td>
                                        <td class="stat-number"><?php echo $usuario['calificaciones_dadas']; ?></td>
                                        <td class="rating">
                                            <?php if ($usuario['calificacion_promedio']): ?>
                                                ⭐ <?php echo number_format($usuario['calificacion_promedio'], 1); ?>
                                            <?php else: ?>
                                                <span class="no-rating">Sin calificaciones</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="date"><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Contenido Popular -->
            <div id="contenido-popular" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🎬 Contenido Mejor Calificado</h3>
                    </div>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Pos.</th>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Género</th>
                                    <th>Año</th>
                                    <th>Calificación</th>
                                    <th>Reseñas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes_detallados['contenido_mejor_calificado'] as $index => $contenido): ?>
                                    <tr>
                                        <td class="position"><?php echo $index + 1; ?></td>
                                        <td class="content-title"><?php echo htmlspecialchars($contenido['titulo']); ?></td>
                                        <td class="content-type">
                                            <span class="type-badge type-<?php echo $contenido['tipo']; ?>">
                                                <?php echo $contenido['tipo'] === 'pelicula' ? '🎬' : '📺'; ?>
                                            </span>
                                        </td>
                                        <td class="genre"><?php echo htmlspecialchars($contenido['genero']); ?></td>
                                        <td class="year"><?php echo $contenido['año_lanzamiento']; ?></td>
                                        <td class="rating">⭐ <?php echo number_format($contenido['calificacion'], 1); ?></td>
                                        <td class="reviews-count"><?php echo $contenido['numero_calificaciones']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Análisis de Géneros -->
            <div id="analisis-generos" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🎭 Análisis de Preferencias por Género</h3>
                    </div>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Género</th>
                                    <th>Usuarios Interesados</th>
                                    <th>Total Visitas</th>
                                    <th>Calificación Promedio</th>
                                    <th>Popularidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes_detallados['analisis_generos'] as $genero): ?>
                                    <tr>
                                        <td class="genre-name"><?php echo htmlspecialchars($genero['genero']); ?></td>
                                        <td class="stat-number"><?php echo $genero['usuarios_interesados']; ?></td>
                                        <td class="stat-number"><?php echo number_format($genero['total_visitas']); ?></td>
                                        <td class="rating">
                                            <?php if ($genero['calificacion_promedio_genero']): ?>
                                                ⭐ <?php echo number_format($genero['calificacion_promedio_genero'], 1); ?>
                                            <?php else: ?>
                                                <span class="no-rating">Sin calificaciones</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="popularity">
                                            <div class="popularity-bar">
                                                <div class="popularity-fill" 
                                                     style="width: <?php echo min(100, ($genero['usuarios_interesados'] / max(1, $estadisticas_generales['usuarios']['usuarios_activos'])) * 100); ?>%">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Tendencias -->
            <div id="tendencias" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📈 Tendencias de Contenido por Año</h3>
                    </div>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Año</th>
                                    <th>Total Contenido</th>
                                    <th>Películas</th>
                                    <th>Series</th>
                                    <th>Calificación Promedio</th>
                                    <th>Tendencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes_detallados['tendencias_por_año'] as $index => $año): ?>
                                    <tr>
                                        <td class="year-highlight"><?php echo $año['año_lanzamiento']; ?></td>
                                        <td class="stat-number"><?php echo $año['cantidad_contenido']; ?></td>
                                        <td class="content-count">🎬 <?php echo $año['peliculas']; ?></td>
                                        <td class="content-count">📺 <?php echo $año['series']; ?></td>
                                        <td class="rating">⭐ <?php echo number_format($año['calificacion_promedio'], 1); ?></td>
                                        <td class="trend">
                                            <?php 
                                            $next_year = isset($reportes_detallados['tendencias_por_año'][$index + 1]) ? 
                                                        $reportes_detallados['tendencias_por_año'][$index + 1]['cantidad_contenido'] : 0;
                                            $current = $año['cantidad_contenido'];
                                            
                                            if ($next_year > 0) {
                                                if ($current > $next_year) {
                                                    echo '<span class="trend-up">📈 Creciente</span>';
                                                } elseif ($current < $next_year) {
                                                    echo '<span class="trend-down">📉 Decreciente</span>';
                                                } else {
                                                    echo '<span class="trend-stable">➡️ Estable</span>';
                                                }
                                            } else {
                                                echo '<span class="trend-neutral">➖ N/A</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones. Panel de Administración - Reportes.</p>
        </div>
    </footer>

    <script>
        // Datos para gráficos (desde PHP)
        const datosGraficos = <?php echo json_encode($datos_graficos); ?>;

        // Función para cambiar tema
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            body.classList.toggle('dark-theme');
            document.documentElement.setAttribute('data-theme', newTheme);
            
            document.cookie = `theme=${newTheme}; expires=${new Date(Date.now() + 30*24*60*60*1000).toUTCString()}; path=<?php echo COOKIE_PATH; ?>`;
            
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }

        // Funciones para mostrar/ocultar filtros
        function mostrarFiltros() {
            const section = document.getElementById('filtrosSection');
            section.style.display = section.style.display === 'none' ? 'block' : 'none';
        }

        // Función para filtros rápidos
        function setQuickFilter(period) {
            const fechaHasta = document.getElementById('fecha_hasta');
            const fechaDesde = document.getElementById('fecha_desde');
            const today = new Date();
            
            fechaHasta.value = today.toISOString().split('T')[0];
            
            switch(period) {
                case 'today':
                    fechaDesde.value = today.toISOString().split('T')[0];
                    break;
                case 'week':
                    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    fechaDesde.value = weekAgo.toISOString().split('T')[0];
                    break;
                case 'month':
                    const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
                    fechaDesde.value = monthAgo.toISOString().split('T')[0];
                    break;
                case 'quarter':
                    const quarterAgo = new Date(today.getFullYear(), today.getMonth() - 3, today.getDate());
                    fechaDesde.value = quarterAgo.toISOString().split('T')[0];
                    break;
                case 'year':
                    const yearAgo = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
                    fechaDesde.value = yearAgo.toISOString().split('T')[0];
                    break;
            }
        }

        // Función para mostrar tabs
        function showTab(tabName) {
            // Ocultar todos los tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Quitar clase active de todos los botones
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar tab seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar botón correspondiente
            event.target.classList.add('active');
        }

        // Inicializar gráficos
        function initCharts() {
            // Configuración de colores para tema
            const isDark = document.body.classList.contains('dark-theme');
            const textColor = isDark ? '#f5f5f5' : '#333';
            const gridColor = isDark ? '#444' : '#ddd';

            // Gráfico de registros mensuales
            if (datosGraficos.registros_mensuales) {
                const ctx1 = document.getElementById('registrosChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: datosGraficos.registros_mensuales.map(item => item.mes),
                        datasets: [{
                            label: 'Registros',
                            data: datosGraficos.registros_mensuales.map(item => item.registros),
                            borderColor: '#e94560',
                            backgroundColor: 'rgba(233, 69, 96, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: { color: textColor }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            },
                            x: {
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            }
                        }
                    }
                });
            }

            // Gráfico de distribución por géneros
            if (datosGraficos.distribucion_generos) {
                const ctx2 = document.getElementById('generosChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: datosGraficos.distribucion_generos.map(item => item.nombre),
                        datasets: [{
                            data: datosGraficos.distribucion_generos.map(item => item.cantidad),
                            backgroundColor: [
                                '#e94560', '#1a1a2e', '#16213e', '#0f0f23',
                                '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4',
                                '#ffeaa7', '#dda0dd'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: textColor }
                            }
                        }
                    }
                });
            }

            // Gráfico de actividad diaria
            if (datosGraficos.actividad_diaria) {
                const ctx3 = document.getElementById('actividadChart').getContext('2d');
                new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: datosGraficos.actividad_diaria.map(item => 
                            new Date(item.fecha).toLocaleDateString('es-ES', { month: 'short', day: 'numeric' })
                        ),
                        datasets: [{
                            label: 'Visitas',
                            data: datosGraficos.actividad_diaria.map(item => item.visitas),
                            backgroundColor: 'rgba(233, 69, 96, 0.7)',
                            borderColor: '#e94560',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: { color: textColor }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            },
                            x: {
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            }
                        }
                    }
                });
            }

            // Gráfico de contenido popular
            if (datosGraficos.contenido_popular) {
                const ctx4 = document.getElementById('popularChart').getContext('2d');
                new Chart(ctx4, {
                    type: 'horizontalBar',
                    data: {
                        labels: datosGraficos.contenido_popular.slice(0, 8).map(item => 
                            item.titulo.length > 20 ? item.titulo.substring(0, 20) + '...' : item.titulo
                        ),
                        datasets: [{
                            label: 'Visitas',
                            data: datosGraficos.contenido_popular.slice(0, 8).map(item => item.visitas),
                            backgroundColor: datosGraficos.contenido_popular.slice(0, 8).map(item => 
                                item.tipo === 'pelicula' ? 'rgba(233, 69, 96, 0.7)' : 'rgba(26, 26, 46, 0.7)'
                            ),
                            borderColor: datosGraficos.contenido_popular.slice(0, 8).map(item => 
                                item.tipo === 'pelicula' ? '#e94560' : '#1a1a2e'
                            ),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: { color: textColor }
                            }
                        },
                        scales: {
                            y: {
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            },
                            x: {
                                beginAtZero: true,
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            }
                        }
                    }
                });
            }
        }

        // Configuración inicial
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Inicializar gráficos
            initCharts();

            // Ocultar filtros por defecto
            document.getElementById('filtrosSection').style.display = 'none';

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.card, .stat-card');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>

    <style>
        /* Estilos específicos para reportes */
        .report-filters {
            margin-bottom: 2rem;
        }

        .quick-filters {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .period-stats .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-period {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 1rem 0;
        }

        .export-item {
            padding: 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            text-align: center;
            transition: var(--transition);
        }

        .export-item:hover {
            border-color: var(--accent-color);
            transform: translateY(-2px);
        }

        .export-item h4 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .export-item p {
            margin-bottom: 1rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .export-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            padding: 1rem;
            height: 300px;
            position: relative;
        }

        .reports-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-dark);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .tab-btn:hover {
            border-color: var(--accent-color);
        }

        .tab-btn.active {
            background: var(--accent-color);
            color: var(--text-light);
            border-color: var(--accent-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .report-table th {
            background: var(--bg-light);
            font-weight: 600;
            color: var(--text-dark);
        }

        .position {
            font-weight: bold;
            color: var(--accent-color);
            text-align: center;
            width: 60px;
        }

        .stat-number {
            font-weight: 600;
            color: var(--primary-color);
        }

        .rating {
            color: #ffc107;
        }

        .no-rating {
            color: var(--text-secondary);
            font-style: italic;
        }

        .type-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .popularity-bar {
            width: 100px;
            height: 20px;
            background: var(--bg-light);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .popularity-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-color), var(--primary-color));
            transition: width 0.3s ease;
        }

        .year-highlight {
            font-weight: bold;
            color: var(--primary-color);
        }

        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
        .trend-neutral { color: var(--text-secondary); }

        /* Responsive */
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }

            .export-grid {
                grid-template-columns: 1fr;
            }

            .reports-tabs {
                flex-direction: column;
            }

            .tab-btn {
                text-align: center;
            }

            .chart-container {
                height: 250px;
            }

            .period-stats .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .quick-filters {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .export-actions {
                flex-direction: column;
            }

            .report-table {
                font-size: 0.9rem;
            }

            .report-table th,
            .report-table td {
                padding: 0.5rem;
            }
        }

        /* Dark theme adjustments */
        body.dark-theme .export-item,
        body.dark-theme .tab-btn {
            background: var(--secondary-color);
            border-color: var(--accent-color);
        }

        body.dark-theme .report-table th {
            background: var(--secondary-color);
        }

        body.dark-theme .popularity-bar {
            background: var(--primary-color);
        }
    </style>

</body>
</html>