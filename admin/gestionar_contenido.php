<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Verificar autenticación y permisos de administrador
if (!isLoggedIn()) {
    redirect('auth/iniciar_sesion.php?redirect=' . urlencode('admin/gestionar_contenido.php'));
}

if (!isAdmin()) {
    redirect('inicio.php');
}

$contenidoManager = new ContenidoManager();
$usuarioManager = new UsuarioManager();

// Verificar sesión
$usuarioManager->verificarSesion();

// Variables para el formulario y mensajes
$mensaje = '';
$tipo_mensaje = '';
$mostrar_modal = false;
$contenido_editar = null;
$accion_modal = 'agregar';

// Procesar acciones del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        switch ($action) {
            case 'agregar':
                $resultado = procesarAgregarContenido($contenidoManager);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
                
            case 'actualizar':
                $resultado = procesarActualizarContenido($contenidoManager);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
                
            case 'eliminar':
                $contenido_id = intval($_POST['contenido_id']);
                if ($contenidoManager->eliminarContenido($contenido_id)) {
                    $mensaje = 'Contenido eliminado correctamente';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al eliminar el contenido';
                    $tipo_mensaje = 'error';
                }
                break;
        }
    }
}

// Procesar acciones GET (editar, eliminar)
if (isset($_GET['action'])) {
    $action = sanitize_input($_GET['action']);
    
    if ($action === 'editar' && isset($_GET['id'])) {
        $contenido_id = intval($_GET['id']);
        $contenido_editar = $contenidoManager->obtenerDetalleContenido($contenido_id);
        if ($contenido_editar) {
            $mostrar_modal = true;
            $accion_modal = 'editar';
        }
    }
}

// Obtener parámetros de filtrado y búsqueda
$filtros = [
    'busqueda' => isset($_GET['buscar']) ? sanitize_input($_GET['buscar']) : '',
    'genero_id' => isset($_GET['genero']) ? sanitize_input($_GET['genero']) : '',
    'tipo' => isset($_GET['tipo']) ? sanitize_input($_GET['tipo']) : '',
    'año' => isset($_GET['año']) ? sanitize_input($_GET['año']) : '',
    'calificacion_min' => isset($_GET['calificacion_min']) ? floatval($_GET['calificacion_min']) : 0,
    'ordenar' => isset($_GET['ordenar']) ? sanitize_input($_GET['ordenar']) : 'fecha_desc',
    'activo' => isset($_GET['activo']) ? intval($_GET['activo']) : 1
];

// Parámetros de paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = isset($_GET['por_pagina']) ? min(50, max(10, intval($_GET['por_pagina']))) : 20;

// Obtener contenido filtrado y paginado
$resultado = $contenidoManager->obtenerContenidoPaginado($pagina_actual, $por_pagina, $filtros);
$contenido = $resultado['contenido'];
$total_items = $resultado['total'];
$total_paginas = $resultado['total_paginas'];

// Obtener datos para los formularios
$generos = $contenidoManager->obtenerGeneros();
$años_disponibles = $contenidoManager->obtenerAñosDisponibles();

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

/**
 * Función para procesar agregar contenido
 */
function procesarAgregarContenido($manager) {
    $datos = recopilarDatosFormulario();
    
    // Validar datos
    $validacion = validarDatosContenido($datos);
    if (!$validacion['success']) {
        return $validacion;
    }
    
    // Procesar upload de imagen
    $poster_filename = '';
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $upload_result = procesarUploadPoster($_FILES['poster']);
        if ($upload_result['success']) {
            $poster_filename = $upload_result['filename'];
        } else {
            return $upload_result;
        }
    }
    
    $datos['poster'] = $poster_filename;
    
    $contenido_id = $manager->agregarContenido($datos);
    
    if ($contenido_id) {
        return ['success' => true, 'message' => 'Contenido agregado correctamente'];
    } else {
        return ['success' => false, 'message' => 'Error al agregar el contenido'];
    }
}

/**
 * Función para procesar actualizar contenido
 */
function procesarActualizarContenido($manager) {
    $contenido_id = intval($_POST['contenido_id']);
    $datos = recopilarDatosFormulario();
    
    // Validar datos
    $validacion = validarDatosContenido($datos);
    if (!$validacion['success']) {
        return $validacion;
    }
    
    // Obtener datos actuales para mantener poster si no se sube uno nuevo
    $contenido_actual = $manager->obtenerDetalleContenido($contenido_id);
    $datos['poster'] = $contenido_actual['poster'];
    
    // Procesar upload de imagen si se proporciona
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $upload_result = procesarUploadPoster($_FILES['poster']);
        if ($upload_result['success']) {
            // Eliminar poster anterior si existe
            if (!empty($contenido_actual['poster']) && file_exists('../uploads/' . $contenido_actual['poster'])) {
                unlink('../uploads/' . $contenido_actual['poster']);
            }
            $datos['poster'] = $upload_result['filename'];
        } else {
            return $upload_result;
        }
    }
    
    if ($manager->actualizarContenido($contenido_id, $datos)) {
        return ['success' => true, 'message' => 'Contenido actualizado correctamente'];
    } else {
        return ['success' => false, 'message' => 'Error al actualizar el contenido'];
    }
}

/**
 * Función para recopilar datos del formulario
 */
function recopilarDatosFormulario() {
    return [
        'titulo' => sanitize_input($_POST['titulo']),
        'descripcion' => sanitize_input($_POST['descripcion']),
        'tipo' => sanitize_input($_POST['tipo']),
        'genero_id' => intval($_POST['genero_id']),
        'año_lanzamiento' => intval($_POST['año_lanzamiento']),
        'duracion' => sanitize_input($_POST['duracion']),
        'trailer_url' => sanitize_input($_POST['trailer_url'])
    ];
}

/**
 * Función para validar datos del contenido
 */
function validarDatosContenido($datos) {
    if (empty($datos['titulo'])) {
        return ['success' => false, 'message' => 'El título es obligatorio'];
    }
    
    if (empty($datos['descripcion'])) {
        return ['success' => false, 'message' => 'La descripción es obligatoria'];
    }
    
    if (!in_array($datos['tipo'], ['pelicula', 'serie'])) {
        return ['success' => false, 'message' => 'Tipo de contenido inválido'];
    }
    
    if ($datos['genero_id'] <= 0) {
        return ['success' => false, 'message' => 'Debe seleccionar un género válido'];
    }
    
    $año_actual = date('Y');
    if ($datos['año_lanzamiento'] < 1900 || $datos['año_lanzamiento'] > ($año_actual + 2)) {
        return ['success' => false, 'message' => 'Año de lanzamiento inválido'];
    }
    
    return ['success' => true];
}

/**
 * Función para procesar upload de poster
 */
function procesarUploadPoster($file) {
    $upload_dir = '../uploads/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Verificar tipo de archivo
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido. Use JPG, PNG o WebP'];
    }
    
    // Verificar tamaño
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 5MB'];
    }
    
    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'poster_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Crear directorio si no existe
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
}

/**
 * Función helper para construir URLs de paginación
 */
function construirUrlPaginacion($pagina) {
    $params = $_GET;
    $params['pagina'] = $pagina;
    return 'gestionar_contenido.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Contenido - Administración</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
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
                <h1>🎬 Gestionar Contenido</h1>
                <p>Administra películas y series de la plataforma</p>
            </div>
            
            <div class="admin-actions">
                <button type="button" class="btn btn-primary" onclick="mostrarModalAgregar()">
                    ➕ Agregar Contenido
                </button>
                <a href="panel_admin.php" class="btn btn-secondary">
                    📊 Volver al Dashboard
                </a>
            </div>
        </section>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> mb-4">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Filtros y búsqueda -->
        <section class="admin-filters mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔍 Filtros de búsqueda</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="limpiarFiltros()">
                        Limpiar filtros
                    </button>
                </div>
                
                <form method="GET" action="gestionar_contenido.php" class="admin-filters-form">
                    <div class="filters-row">
                        <!-- Búsqueda por texto -->
                        <div class="filter-group">
                            <label for="buscar">Buscar:</label>
                            <input type="text" 
                                   id="buscar" 
                                   name="buscar" 
                                   class="form-control" 
                                   placeholder="Título o descripción..."
                                   value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                        </div>
                        
                        <!-- Filtro por tipo -->
                        <div class="filter-group">
                            <label for="tipo">Tipo:</label>
                            <select id="tipo" name="tipo" class="form-control">
                                <option value="">Todos</option>
                                <option value="pelicula" <?php echo $filtros['tipo'] === 'pelicula' ? 'selected' : ''; ?>>
                                    🎬 Películas
                                </option>
                                <option value="serie" <?php echo $filtros['tipo'] === 'serie' ? 'selected' : ''; ?>>
                                    📺 Series
                                </option>
                            </select>
                        </div>
                        
                        <!-- Filtro por género -->
                        <div class="filter-group">
                            <label for="genero">Género:</label>
                            <select id="genero" name="genero" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($generos as $genero): ?>
                                    <option value="<?php echo $genero['id']; ?>" 
                                            <?php echo $filtros['genero_id'] == $genero['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($genero['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Filtro por año -->
                        <div class="filter-group">
                            <label for="año">Año:</label>
                            <select id="año" name="año" class="form-control">
                                <option value="">Cualquier año</option>
                                <?php foreach ($años_disponibles as $año): ?>
                                    <option value="<?php echo $año; ?>" 
                                            <?php echo $filtros['año'] == $año ? 'selected' : ''; ?>>
                                        <?php echo $año; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-row">
                        <!-- Filtro por calificación -->
                        <div class="filter-group">
                            <label for="calificacion_min">Calificación mínima:</label>
                            <select id="calificacion_min" name="calificacion_min" class="form-control">
                                <option value="0">Cualquier calificación</option>
                                <option value="1" <?php echo $filtros['calificacion_min'] == 1 ? 'selected' : ''; ?>>1+ estrellas</option>
                                <option value="2" <?php echo $filtros['calificacion_min'] == 2 ? 'selected' : ''; ?>>2+ estrellas</option>
                                <option value="3" <?php echo $filtros['calificacion_min'] == 3 ? 'selected' : ''; ?>>3+ estrellas</option>
                                <option value="4" <?php echo $filtros['calificacion_min'] == 4 ? 'selected' : ''; ?>>4+ estrellas</option>
                                <option value="4.5" <?php echo $filtros['calificacion_min'] == 4.5 ? 'selected' : ''; ?>>4.5+ estrellas</option>
                            </select>
                        </div>
                        
                        <!-- Filtro por estado -->
                        <div class="filter-group">
                            <label for="activo">Estado:</label>
                            <select id="activo" name="activo" class="form-control">
                                <option value="1" <?php echo $filtros['activo'] == 1 ? 'selected' : ''; ?>>✅ Activos</option>
                                <option value="0" <?php echo $filtros['activo'] == 0 ? 'selected' : ''; ?>>❌ Inactivos</option>
                            </select>
                        </div>
                        
                        <!-- Ordenamiento -->
                        <div class="filter-group">
                            <label for="ordenar">Ordenar por:</label>
                            <select id="ordenar" name="ordenar" class="form-control">
                                <option value="fecha_desc" <?php echo $filtros['ordenar'] === 'fecha_desc' ? 'selected' : ''; ?>>
                                    📅 Más recientes
                                </option>
                                <option value="calificacion_desc" <?php echo $filtros['ordenar'] === 'calificacion_desc' ? 'selected' : ''; ?>>
                                    ⭐ Mejor calificados
                                </option>
                                <option value="titulo_asc" <?php echo $filtros['ordenar'] === 'titulo_asc' ? 'selected' : ''; ?>>
                                    🔤 A-Z (título)
                                </option>
                                <option value="año_desc" <?php echo $filtros['ordenar'] === 'año_desc' ? 'selected' : ''; ?>>
                                    📅 Año (reciente)
                                </option>
                            </select>
                        </div>
                        
                        <!-- Items por página -->
                        <div class="filter-group">
                            <label for="por_pagina">Mostrar:</label>
                            <select id="por_pagina" name="por_pagina" class="form-control">
                                <option value="10" <?php echo $por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $por_pagina == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?php echo $por_pagina == 30 ? 'selected' : ''; ?>>30</option>
                                <option value="50" <?php echo $por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary">
                            🔍 Aplicar filtros
                        </button>
                        <a href="gestionar_contenido.php" class="btn btn-secondary">
                            🔄 Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <!-- Información de resultados -->
        <section class="results-info mb-3">
            <div class="results-summary">
                <p>
                    <strong><?php echo number_format($total_items); ?></strong> elementos encontrados
                    <?php if (!empty($filtros['busqueda'])): ?>
                        para "<em><?php echo htmlspecialchars($filtros['busqueda']); ?></em>"
                    <?php endif; ?>
                </p>
                <p class="results-range">
                    Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?> 
                    (<?php echo ($pagina_actual - 1) * $por_pagina + 1; ?> - 
                    <?php echo min($pagina_actual * $por_pagina, $total_items); ?> de <?php echo $total_items; ?>)
                </p>
            </div>
        </section>

        <!-- Tabla de contenido -->
        <?php if (!empty($contenido)): ?>
            <section class="content-table mb-4">
                <div class="card">
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Poster</th>
                                    <th>Título</th>
                                    <th>Tipo</th>
                                    <th>Género</th>
                                    <th>Año</th>
                                    <th>Calificación</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contenido as $item): ?>
                                    <tr class="table-row">
                                        <td class="poster-cell">
                                            <?php if (!empty($item['poster'])): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($item['poster']); ?>" 
                                                     alt="Poster" 
                                                     class="poster-thumbnail"
                                                     onerror="this.src='../uploads/no-image.svg'">
                                            <?php else: ?>
                                                <div class="no-poster">📽️</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="title-cell">
                                            <strong><?php echo htmlspecialchars($item['titulo']); ?></strong>
                                            <br><small class="duration"><?php echo htmlspecialchars($item['duracion']); ?></small>
                                        </td>
                                        <td class="type-cell">
                                            <span class="type-badge type-<?php echo $item['tipo']; ?>">
                                                <?php echo $item['tipo'] === 'pelicula' ? '🎬 Película' : '📺 Serie'; ?>
                                            </span>
                                        </td>
                                        <td class="genre-cell">
                                            <?php echo htmlspecialchars($item['genero_nombre']); ?>
                                        </td>
                                        <td class="year-cell">
                                            <?php echo $item['año_lanzamiento']; ?>
                                        </td>
                                        <td class="rating-cell">
                                            <div class="rating-display">
                                                <span class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <span class="star <?php echo $i <= round($item['calificacion']) ? 'filled' : ''; ?>">⭐</span>
                                                    <?php endfor; ?>
                                                </span>
                                                <span class="rating-number"><?php echo number_format($item['calificacion'], 1); ?></span>
                                            </div>
                                        </td>
                                        <td class="status-cell">
                                            <span class="status-badge status-<?php echo $item['activo'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $item['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <a href="../detalle_contenido.php?id=<?php echo $item['id']; ?>" 
                                                   class="btn btn-outline btn-sm" 
                                                   title="Ver detalle" 
                                                   target="_blank">
                                                    👁️
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-secondary btn-sm" 
                                                        onclick="editarContenido(<?php echo $item['id']; ?>)"
                                                        title="Editar">
                                                    ✏️
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm" 
                                                        onclick="confirmarEliminar(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['titulo']); ?>')"
                                                        title="Eliminar">
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <section class="pagination-section">
                    <nav class="pagination-nav">
                        <div class="pagination-info">
                            <span>Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></span>
                        </div>
                        
                        <div class="pagination-controls">
                            <?php if ($pagina_actual > 1): ?>
                                <a href="<?php echo construirUrlPaginacion(1); ?>" class="btn btn-outline btn-sm">
                                    ⏮️ Primera
                                </a>
                                <a href="<?php echo construirUrlPaginacion($pagina_actual - 1); ?>" class="btn btn-outline btn-sm">
                                    ⬅️ Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $inicio = max(1, $pagina_actual - 2);
                            $fin = min($total_paginas, $pagina_actual + 2);
                            
                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <a href="<?php echo construirUrlPaginacion($i); ?>" 
                                   class="btn <?php echo $i === $pagina_actual ? 'btn-primary' : 'btn-outline'; ?> btn-sm">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($pagina_actual < $total_paginas): ?>
                                <a href="<?php echo construirUrlPaginacion($pagina_actual + 1); ?>" class="btn btn-outline btn-sm">
                                    Siguiente ➡️
                                </a>
                                <a href="<?php echo construirUrlPaginacion($total_paginas); ?>" class="btn btn-outline btn-sm">
                                    Última ⏭️
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </section>
            <?php endif; ?>

        <?php else: ?>
            <!-- No hay resultados -->
            <section class="no-results">
                <div class="card text-center">
                    <div class="no-results-icon">🔍</div>
                    <h3>No se encontraron resultados</h3>
                    <p>Intenta modificar los filtros de búsqueda o agrega nuevo contenido.</p>
                    <div class="no-results-actions">
                        <button type="button" class="btn btn-primary" onclick="mostrarModalAgregar()">
                            ➕ Agregar Contenido
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="limpiarFiltros()">
                            🔄 Limpiar filtros
                        </button>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Modal para agregar/editar contenido -->
    <div id="modalContenido" class="modal" style="display: <?php echo $mostrar_modal ? 'block' : 'none'; ?>;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo">
                    <?php echo $accion_modal === 'editar' ? 'Editar Contenido' : 'Agregar Nuevo Contenido'; ?>
                </h3>
                <button type="button" class="close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formContenido" method="POST" action="gestionar_contenido.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="agregar">
                    <input type="hidden" name="contenido_id" id="contenidoId" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="titulo">Título *</label>
                            <input type="text" 
                                   id="titulo" 
                                   name="titulo" 
                                   class="form-control" 
                                   required 
                                   maxlength="200"
                                   value="<?php echo $contenido_editar ? htmlspecialchars($contenido_editar['titulo']) : ''; ?>"
                                   placeholder="Ej: Avengers: Endgame">
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_modal">Tipo *</label>
                            <select id="tipo_modal" name="tipo" class="form-control" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="pelicula" <?php echo ($contenido_editar && $contenido_editar['tipo'] === 'pelicula') ? 'selected' : ''; ?>>
                                    🎬 Película
                                </option>
                                <option value="serie" <?php echo ($contenido_editar && $contenido_editar['tipo'] === 'serie') ? 'selected' : ''; ?>>
                                    📺 Serie
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción *</label>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  class="form-control" 
                                  required 
                                  rows="4" 
                                  maxlength="1000"
                                  placeholder="Describe la trama, historia o sinopsis..."><?php echo $contenido_editar ? htmlspecialchars($contenido_editar['descripcion']) : ''; ?></textarea>
                        <small class="form-text">Máximo 1000 caracteres</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="genero_id_modal">Género *</label>
                            <select id="genero_id_modal" name="genero_id" class="form-control" required>
                                <option value="">Seleccionar género</option>
                                <?php foreach ($generos as $genero): ?>
                                    <option value="<?php echo $genero['id']; ?>" 
                                            <?php echo ($contenido_editar && $contenido_editar['genero_id'] == $genero['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($genero['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="año_lanzamiento">Año de lanzamiento *</label>
                            <input type="number" 
                                   id="año_lanzamiento" 
                                   name="año_lanzamiento" 
                                   class="form-control" 
                                   required 
                                   min="1900" 
                                   max="<?php echo date('Y') + 2; ?>"
                                   value="<?php echo $contenido_editar ? $contenido_editar['año_lanzamiento'] : date('Y'); ?>"
                                   placeholder="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duracion">Duración</label>
                            <input type="text" 
                                   id="duracion" 
                                   name="duracion" 
                                   class="form-control" 
                                   maxlength="50"
                                   value="<?php echo $contenido_editar ? htmlspecialchars($contenido_editar['duracion']) : ''; ?>"
                                   placeholder="Ej: 2h 30min o 3 temporadas">
                            <small class="form-text">Para películas: duración en horas/minutos. Para series: número de temporadas</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="trailer_url">URL del trailer</label>
                            <input type="url" 
                                   id="trailer_url" 
                                   name="trailer_url" 
                                   class="form-control" 
                                   maxlength="500"
                                   value="<?php echo $contenido_editar ? htmlspecialchars($contenido_editar['trailer_url']) : ''; ?>"
                                   placeholder="https://www.youtube.com/watch?v=...">
                            <small class="form-text">YouTube, Vimeo u otra plataforma de video</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="poster">Poster/Imagen</label>
                        <input type="file" 
                               id="poster" 
                               name="poster" 
                               class="form-control" 
                               accept="image/jpeg,image/jpg,image/png,image/webp">
                        <small class="form-text">
                            Formatos permitidos: JPG, PNG, WebP. Tamaño máximo: 5MB. 
                            <?php if ($contenido_editar && !empty($contenido_editar['poster'])): ?>
                                <br><strong>Archivo actual:</strong> <?php echo htmlspecialchars($contenido_editar['poster']); ?>
                            <?php endif; ?>
                        </small>
                        
                        <?php if ($contenido_editar && !empty($contenido_editar['poster'])): ?>
                            <div class="current-poster mt-2">
                                <img src="../uploads/<?php echo htmlspecialchars($contenido_editar['poster']); ?>" 
                                     alt="Poster actual" 
                                     class="current-poster-img"
                                     onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="btnGuardar">
                            <?php echo $accion_modal === 'editar' ? 'Actualizar Contenido' : 'Agregar Contenido'; ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div id="modalEliminar" class="modal" style="display: none;">
        <div class="modal-content modal-confirm">
            <div class="modal-header">
                <h3>🗑️ Confirmar Eliminación</h3>
                <button type="button" class="close" onclick="cerrarModalEliminar()">&times;</button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres eliminar el siguiente contenido?</p>
                <div class="content-to-delete">
                    <strong id="tituloAEliminar"></strong>
                </div>
                <p class="warning-text">⚠️ Esta acción no se puede deshacer. El contenido será marcado como inactivo.</p>
                
                <form id="formEliminar" method="POST" action="gestionar_contenido.php">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="contenido_id" id="contenidoIdEliminar" value="">
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">
                            🗑️ Sí, eliminar
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalEliminar()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones. Panel de Administración.</p>
        </div>
    </footer>

    <script>
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

        // Función para mostrar modal de agregar
        function mostrarModalAgregar() {
            document.getElementById('modalTitulo').textContent = 'Agregar Nuevo Contenido';
            document.getElementById('formAction').value = 'agregar';
            document.getElementById('contenidoId').value = '';
            document.getElementById('btnGuardar').textContent = 'Agregar Contenido';
            
            // Limpiar formulario
            document.getElementById('formContenido').reset();
            
            // Mostrar modal
            document.getElementById('modalContenido').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Función para editar contenido
        function editarContenido(id) {
            window.location.href = `gestionar_contenido.php?action=editar&id=${id}`;
        }

        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalContenido').style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Limpiar URL si venimos de edición
            if (window.location.search.includes('action=editar')) {
                window.history.replaceState({}, document.title, 'gestionar_contenido.php');
            }
        }

        // Función para confirmar eliminación
        function confirmarEliminar(id, titulo) {
            document.getElementById('tituloAEliminar').textContent = titulo;
            document.getElementById('contenidoIdEliminar').value = id;
            document.getElementById('modalEliminar').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Función para cerrar modal de eliminar
        function cerrarModalEliminar() {
            document.getElementById('modalEliminar').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Función para limpiar filtros
        function limpiarFiltros() {
            window.location.href = 'gestionar_contenido.php';
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Mostrar modal si venimos de edición
            <?php if ($mostrar_modal): ?>
                document.getElementById('modalContenido').style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Configurar formulario para edición
                document.getElementById('modalTitulo').textContent = 'Editar Contenido';
                document.getElementById('formAction').value = 'actualizar';
                document.getElementById('contenidoId').value = '<?php echo $contenido_editar['id']; ?>';
                document.getElementById('btnGuardar').textContent = 'Actualizar Contenido';
            <?php endif; ?>

            // Validación en tiempo real del formulario
            const form = document.getElementById('formContenido');
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            
            function validarFormulario() {
                let valido = true;
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        valido = false;
                    }
                });
                
                document.getElementById('btnGuardar').disabled = !valido;
                return valido;
            }

            inputs.forEach(input => {
                input.addEventListener('input', validarFormulario);
                input.addEventListener('change', validarFormulario);
            });

            // Validación inicial
            validarFormulario();

            // Prevenir cierre accidental del modal
            form.addEventListener('submit', function(e) {
                const btnGuardar = document.getElementById('btnGuardar');
                btnGuardar.disabled = true;
                btnGuardar.textContent = 'Guardando...';
            });

            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModal();
                    cerrarModalEliminar();
                }
            });

            // Cerrar modal haciendo click fuera
            window.addEventListener('click', function(e) {
                const modalContenido = document.getElementById('modalContenido');
                const modalEliminar = document.getElementById('modalEliminar');
                
                if (e.target === modalContenido) {
                    cerrarModal();
                }
                if (e.target === modalEliminar) {
                    cerrarModalEliminar();
                }
            });

            // Auto-submit de filtros
            const filtroSelects = document.querySelectorAll('.admin-filters-form select');
            filtroSelects.forEach(select => {
                select.addEventListener('change', function() {
                    setTimeout(() => {
                        document.querySelector('.admin-filters-form').submit();
                    }, 300);
                });
            });

            // Búsqueda con delay
            const buscarInput = document.getElementById('buscar');
            let searchTimeout;
            
            buscarInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length === 0 || this.value.length >= 3) {
                        document.querySelector('.admin-filters-form').submit();
                    }
                }, 1000);
            });

            // Validación del archivo de imagen
            const posterInput = document.getElementById('poster');
            posterInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Validar tamaño (5MB)
                    const maxSize = 5 * 1024 * 1024;
                    if (file.size > maxSize) {
                        alert('El archivo es demasiado grande. Máximo 5MB.');
                        this.value = '';
                        return;
                    }
                    
                    // Validar tipo
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Tipo de archivo no permitido. Use JPG, PNG o WebP.');
                        this.value = '';
                        return;
                    }
                }
            });

            // Contador de caracteres para descripción
            const descripcionTextarea = document.getElementById('descripcion');
            const maxLength = 1000;
            
            function updateCharCount() {
                const remaining = maxLength - descripcionTextarea.value.length;
                let helpText = descripcionTextarea.parentNode.querySelector('.form-text');
                helpText.textContent = `${remaining} caracteres restantes`;
                
                if (remaining < 100) {
                    helpText.style.color = remaining < 0 ? 'red' : 'orange';
                } else {
                    helpText.style.color = '';
                }
            }
            
            descripcionTextarea.addEventListener('input', updateCharCount);
            updateCharCount();

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.card, .table-row');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 50);
            });
        });
    </script>

</body>
</html>