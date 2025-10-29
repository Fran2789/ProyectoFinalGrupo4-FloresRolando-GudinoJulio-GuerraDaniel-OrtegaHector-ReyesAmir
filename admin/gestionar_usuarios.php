<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Verificar autenticación y permisos de administrador
if (!isLoggedIn()) {
    redirect('auth/iniciar_sesion.php?redirect=' . urlencode('admin/gestionar_usuarios.php'));
}

if (!isAdmin()) {
    redirect('inicio.php');
}

$usuarioManager = new UsuarioManager();
$contenidoManager = new ContenidoManager();

// Verificar sesión
$usuarioManager->verificarSesion();

// Variables para el formulario y mensajes
$mensaje = '';
$tipo_mensaje = '';
$mostrar_modal = false;
$usuario_editar = null;
$accion_modal = 'editar';

// Procesar acciones del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        switch ($action) {
            case 'actualizar_usuario':
                $resultado = procesarActualizarUsuario($usuarioManager);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
                
            case 'cambiar_rol':
                $usuario_id = intval($_POST['usuario_id']);
                $nuevo_rol = sanitize_input($_POST['nuevo_rol']);
                $resultado = $usuarioManager->cambiarRolUsuario($usuario_id, $nuevo_rol);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
                
            case 'toggle_activo':
                $usuario_id = intval($_POST['usuario_id']);
                $resultado = $usuarioManager->toggleUsuarioActivo($usuario_id);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
                
            case 'eliminar_usuario':
                $usuario_id = intval($_POST['usuario_id']);
                $resultado = $usuarioManager->eliminarUsuario($usuario_id);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
                
            case 'restablecer_password':
                $usuario_id = intval($_POST['usuario_id']);
                $resultado = $usuarioManager->restablecerPassword($usuario_id);
                if ($resultado['success']) {
                    $mensaje = 'Contraseña restablecida. Nueva contraseña: ' . $resultado['nueva_password'];
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = $resultado['message'];
                    $tipo_mensaje = 'error';
                }
                break;
        }
    }
}

// Procesar acciones GET (editar)
if (isset($_GET['action'])) {
    $action = sanitize_input($_GET['action']);
    
    if ($action === 'editar' && isset($_GET['id'])) {
        $usuario_id = intval($_GET['id']);
        $usuario_editar = $usuarioManager->obtenerUsuario($usuario_id);
        if ($usuario_editar) {
            $mostrar_modal = true;
            $accion_modal = 'editar';
        }
    }
}

// Obtener parámetros de filtrado y búsqueda
$filtros = [
    'busqueda' => isset($_GET['buscar']) ? sanitize_input($_GET['buscar']) : '',
    'rol' => isset($_GET['rol']) ? sanitize_input($_GET['rol']) : '',
    'activo' => isset($_GET['activo']) ? intval($_GET['activo']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? sanitize_input($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? sanitize_input($_GET['fecha_hasta']) : '',
    'ordenar' => isset($_GET['ordenar']) ? sanitize_input($_GET['ordenar']) : 'fecha_desc'
];

// Parámetros de paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = isset($_GET['por_pagina']) ? min(50, max(10, intval($_GET['por_pagina']))) : 20;

// Obtener usuarios filtrados y paginados
$resultado = $usuarioManager->obtenerTodosUsuarios($pagina_actual, $por_pagina, $filtros);
$usuarios = $resultado['usuarios'];
$total_items = $resultado['total'];
$total_paginas = $resultado['total_paginas'];

// Obtener estadísticas generales
$estadisticas = $usuarioManager->obtenerEstadisticasGenerales();

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

/**
 * Función para procesar actualizar usuario
 */
function procesarActualizarUsuario($manager) {
    $usuario_id = intval($_POST['usuario_id']);
    $nombre = sanitize_input($_POST['nombre']);
    $email = sanitize_input($_POST['email']);
    
    // Validaciones
    if (empty($nombre) || empty($email)) {
        return ['success' => false, 'message' => 'Nombre y email son obligatorios'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email inválido'];
    }
    
    $resultado = $manager->actualizarPerfil($usuario_id, $nombre, $email);
    return $resultado;
}

/**
 * Función helper para construir URLs de paginación
 */
function construirUrlPaginacion($pagina) {
    $params = $_GET;
    $params['pagina'] = $pagina;
    return 'gestionar_usuarios.php?' . http_build_query($params);
}

/**
 * Función helper para formatear fecha
 */
function formatearFecha($fecha) {
    return date('d/m/Y H:i', strtotime($fecha));
}

/**
 * Función helper para calcular días desde registro
 */
function diasDesdeRegistro($fecha) {
    $fecha_registro = new DateTime($fecha);
    $hoy = new DateTime();
    $diferencia = $hoy->diff($fecha_registro);
    return $diferencia->days;
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - Administración</title>
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
                <h1>👥 Gestionar Usuarios</h1>
                <p>Administra usuarios registrados en la plataforma</p>
            </div>
            
            <div class="admin-actions">
                <button type="button" class="btn btn-secondary" onclick="exportarUsuarios()">
                    📄 Exportar Datos
                </button>
                <a href="panel_admin.php" class="btn btn-outline">
                    📊 Volver al Dashboard
                </a>
            </div>
        </section>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> mb-4">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <section class="user-stats mb-4">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👤</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $estadisticas['usuarios']['total_usuarios']; ?></div>
                        <div class="stat-label">Total Usuarios</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $estadisticas['usuarios']['usuarios_activos']; ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👑</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $estadisticas['usuarios']['administradores']; ?></div>
                        <div class="stat-label">Administradores</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🆕</div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $estadisticas['usuarios']['nuevos_ultimo_mes']; ?></div>
                        <div class="stat-label">Nuevos (30 días)</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filtros y búsqueda -->
        <section class="admin-filters mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔍 Filtros de búsqueda</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="limpiarFiltros()">
                        Limpiar filtros
                    </button>
                </div>
                
                <form method="GET" action="gestionar_usuarios.php" class="admin-filters-form">
                    <div class="filters-row">
                        <!-- Búsqueda por texto -->
                        <div class="filter-group">
                            <label for="buscar">Buscar:</label>
                            <input type="text" 
                                   id="buscar" 
                                   name="buscar" 
                                   class="form-control" 
                                   placeholder="Nombre o email..."
                                   value="<?php echo htmlspecialchars($filtros['busqueda']); ?>">
                        </div>
                        
                        <!-- Filtro por rol -->
                        <div class="filter-group">
                            <label for="rol">Rol:</label>
                            <select id="rol" name="rol" class="form-control">
                                <option value="">Todos los roles</option>
                                <option value="user" <?php echo $filtros['rol'] === 'user' ? 'selected' : ''; ?>>
                                    👤 Usuario
                                </option>
                                <option value="admin" <?php echo $filtros['rol'] === 'admin' ? 'selected' : ''; ?>>
                                    👑 Administrador
                                </option>
                            </select>
                        </div>
                        
                        <!-- Filtro por estado -->
                        <div class="filter-group">
                            <label for="activo">Estado:</label>
                            <select id="activo" name="activo" class="form-control">
                                <option value="">Todos</option>
                                <option value="1" <?php echo $filtros['activo'] === 1 ? 'selected' : ''; ?>>
                                    ✅ Activos
                                </option>
                                <option value="0" <?php echo $filtros['activo'] === 0 ? 'selected' : ''; ?>>
                                    ❌ Inactivos
                                </option>
                            </select>
                        </div>
                        
                        <!-- Filtro por fecha desde -->
                        <div class="filter-group">
                            <label for="fecha_desde">Registrado desde:</label>
                            <input type="date" 
                                   id="fecha_desde" 
                                   name="fecha_desde" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                        </div>
                    </div>
                    
                    <div class="filters-row">
                        <!-- Filtro por fecha hasta -->
                        <div class="filter-group">
                            <label for="fecha_hasta">Registrado hasta:</label>
                            <input type="date" 
                                   id="fecha_hasta" 
                                   name="fecha_hasta" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                        </div>
                        
                        <!-- Ordenamiento -->
                        <div class="filter-group">
                            <label for="ordenar">Ordenar por:</label>
                            <select id="ordenar" name="ordenar" class="form-control">
                                <option value="fecha_desc" <?php echo $filtros['ordenar'] === 'fecha_desc' ? 'selected' : ''; ?>>
                                    📅 Más recientes
                                </option>
                                <option value="nombre_asc" <?php echo $filtros['ordenar'] === 'nombre_asc' ? 'selected' : ''; ?>>
                                    🔤 A-Z (nombre)
                                </option>
                                <option value="email_asc" <?php echo $filtros['ordenar'] === 'email_asc' ? 'selected' : ''; ?>>
                                    📧 A-Z (email)
                                </option>
                                <option value="actividad_desc" <?php echo $filtros['ordenar'] === 'actividad_desc' ? 'selected' : ''; ?>>
                                    ⭐ Más activos
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
                        
                        <div class="filter-group">
                            <!-- Espacio para alineación -->
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary">
                            🔍 Aplicar filtros
                        </button>
                        <a href="gestionar_usuarios.php" class="btn btn-secondary">
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
                    <strong><?php echo number_format($total_items); ?></strong> usuarios encontrados
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

        <!-- Tabla de usuarios -->
        <?php if (!empty($usuarios)): ?>
            <section class="users-table mb-4">
                <div class="card">
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Registro</th>
                                    <th>Actividad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr class="table-row">
                                        <td class="user-cell">
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    👤
                                                </div>
                                                <div class="user-details">
                                                    <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong>
                                                    <br><small class="user-id">ID: <?php echo $usuario['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="email-cell">
                                            <a href="mailto:<?php echo htmlspecialchars($usuario['email']); ?>" 
                                               class="email-link">
                                                <?php echo htmlspecialchars($usuario['email']); ?>
                                            </a>
                                        </td>
                                        <td class="role-cell">
                                            <span class="role-badge role-<?php echo $usuario['rol']; ?>">
                                                <?php echo $usuario['rol'] === 'admin' ? '👑 Admin' : '👤 Usuario'; ?>
                                            </span>
                                        </td>
                                        <td class="status-cell">
                                            <span class="status-badge status-<?php echo $usuario['activo'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $usuario['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td class="date-cell">
                                            <span class="date-main"><?php echo formatearFecha($usuario['fecha_registro']); ?></span>
                                            <br><small class="date-relative">hace <?php echo diasDesdeRegistro($usuario['fecha_registro']); ?> días</small>
                                        </td>
                                        <td class="activity-cell">
                                            <div class="activity-stats">
                                                <div class="activity-item">
                                                    <span class="activity-number"><?php echo $usuario['contenido_visto']; ?></span>
                                                    <span class="activity-label">visto</span>
                                                </div>
                                                <div class="activity-item">
                                                    <span class="activity-number"><?php echo $usuario['calificaciones_dadas']; ?></span>
                                                    <span class="activity-label">reseñas</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button type="button" 
                                                        class="btn btn-outline btn-sm" 
                                                        onclick="editarUsuario(<?php echo $usuario['id']; ?>)"
                                                        title="Editar usuario">
                                                    ✏️
                                                </button>
                                                
                                                <div class="dropdown">
                                                    <button type="button" 
                                                            class="btn btn-secondary btn-sm dropdown-toggle" 
                                                            onclick="toggleDropdown(<?php echo $usuario['id']; ?>)"
                                                            title="Más acciones">
                                                        ⚙️
                                                    </button>
                                                    <div class="dropdown-menu" id="dropdown-<?php echo $usuario['id']; ?>">
                                                        <button type="button" 
                                                                class="dropdown-item" 
                                                                onclick="cambiarRol(<?php echo $usuario['id']; ?>, '<?php echo $usuario['rol'] === 'admin' ? 'user' : 'admin'; ?>')">
                                                            <?php echo $usuario['rol'] === 'admin' ? '👤 Hacer Usuario' : '👑 Hacer Admin'; ?>
                                                        </button>
                                                        
                                                        <button type="button" 
                                                                class="dropdown-item" 
                                                                onclick="toggleActivo(<?php echo $usuario['id']; ?>)">
                                                            <?php echo $usuario['activo'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                                        </button>
                                                        
                                                        <button type="button" 
                                                                class="dropdown-item" 
                                                                onclick="restablecerPassword(<?php echo $usuario['id']; ?>)">
                                                            🔑 Restablecer Contraseña
                                                        </button>
                                                        
                                                        <div class="dropdown-divider"></div>
                                                        
                                                        <button type="button" 
                                                                class="dropdown-item text-danger" 
                                                                onclick="confirmarEliminar(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')">
                                                            🗑️ Eliminar Usuario
                                                        </button>
                                                    </div>
                                                </div>
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
                    <div class="no-results-icon">👥</div>
                    <h3>No se encontraron usuarios</h3>
                    <p>Intenta modificar los filtros de búsqueda para ver más resultados.</p>
                    <div class="no-results-actions">
                        <button type="button" class="btn btn-secondary" onclick="limpiarFiltros()">
                            🔄 Limpiar filtros
                        </button>
                        <a href="panel_admin.php" class="btn btn-primary">
                            📊 Volver al Dashboard
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Modal para editar usuario -->
    <div id="modalUsuario" class="modal" style="display: <?php echo $mostrar_modal ? 'block' : 'none'; ?>;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo">Editar Usuario</h3>
                <button type="button" class="close" onclick="cerrarModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formUsuario" method="POST" action="gestionar_usuarios.php">
                    <input type="hidden" name="action" value="actualizar_usuario">
                    <input type="hidden" name="usuario_id" id="usuarioId" value="">
                    
                    <div class="form-group">
                        <label for="nombre">Nombre completo *</label>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               class="form-control" 
                               required 
                               maxlength="100"
                               value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['nombre']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               required 
                               maxlength="150"
                               value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['email']) : ''; ?>">
                    </div>
                    
                    <div class="user-info-display">
                        <div class="info-row">
                            <span class="info-label">ID del usuario:</span>
                            <span class="info-value" id="displayUsuarioId"><?php echo $usuario_editar ? $usuario_editar['id'] : ''; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Fecha de registro:</span>
                            <span class="info-value" id="displayFechaRegistro">
                                <?php echo $usuario_editar ? formatearFecha($usuario_editar['fecha_registro']) : ''; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Rol actual:</span>
                            <span class="info-value" id="displayRol">
                                <?php echo $usuario_editar ? ($usuario_editar['rol'] === 'admin' ? '👑 Administrador' : '👤 Usuario') : ''; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Estado:</span>
                            <span class="info-value" id="displayEstado">
                                <?php echo $usuario_editar ? ($usuario_editar['activo'] ? '✅ Activo' : '❌ Inactivo') : ''; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Actualizar Usuario
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
                <p>¿Estás seguro de que quieres eliminar el siguiente usuario?</p>
                <div class="user-to-delete">
                    <strong id="nombreAEliminar"></strong>
                </div>
                <p class="warning-text">
                    ⚠️ Esta acción eliminará permanentemente:
                </p>
                <ul class="warning-list">
                    <li>Toda la información del usuario</li>
                    <li>Su historial de navegación</li>
                    <li>Sus calificaciones y comentarios</li>
                    <li>Sus preferencias personalizadas</li>
                </ul>
                <p class="warning-text"><strong>Esta acción NO se puede deshacer.</strong></p>
                
                <form id="formEliminar" method="POST" action="gestionar_usuarios.php">
                    <input type="hidden" name="action" value="eliminar_usuario">
                    <input type="hidden" name="usuario_id" id="usuarioIdEliminar" value="">
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">
                            🗑️ Sí, eliminar permanentemente
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarModalEliminar()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formularios ocultos para acciones rápidas -->
    <form id="formCambiarRol" method="POST" action="gestionar_usuarios.php" style="display: none;">
        <input type="hidden" name="action" value="cambiar_rol">
        <input type="hidden" name="usuario_id" id="cambiarRolUsuarioId">
        <input type="hidden" name="nuevo_rol" id="cambiarRolNuevoRol">
    </form>

    <form id="formToggleActivo" method="POST" action="gestionar_usuarios.php" style="display: none;">
        <input type="hidden" name="action" value="toggle_activo">
        <input type="hidden" name="usuario_id" id="toggleActivoUsuarioId">
    </form>

    <form id="formRestablecerPassword" method="POST" action="gestionar_usuarios.php" style="display: none;">
        <input type="hidden" name="action" value="restablecer_password">
        <input type="hidden" name="usuario_id" id="restablecerPasswordUsuarioId">
    </form>

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

        // Función para editar usuario
        function editarUsuario(id) {
            window.location.href = `gestionar_usuarios.php?action=editar&id=${id}`;
        }

        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalUsuario').style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Limpiar URL si venimos de edición
            if (window.location.search.includes('action=editar')) {
                window.history.replaceState({}, document.title, 'gestionar_usuarios.php');
            }
        }

        // Función para confirmar eliminación
        function confirmarEliminar(id, nombre) {
            document.getElementById('nombreAEliminar').textContent = nombre;
            document.getElementById('usuarioIdEliminar').value = id;
            document.getElementById('modalEliminar').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Función para cerrar modal de eliminar
        function cerrarModalEliminar() {
            document.getElementById('modalEliminar').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Función para cambiar rol
        function cambiarRol(id, nuevoRol) {
            const accion = nuevoRol === 'admin' ? 'hacer administrador' : 'hacer usuario regular';
            
            if (confirm(`¿Estás seguro de que quieres ${accion} a este usuario?`)) {
                document.getElementById('cambiarRolUsuarioId').value = id;
                document.getElementById('cambiarRolNuevoRol').value = nuevoRol;
                document.getElementById('formCambiarRol').submit();
            }
        }

        // Función para toggle activo
        function toggleActivo(id) {
            if (confirm('¿Estás seguro de que quieres cambiar el estado de este usuario?')) {
                document.getElementById('toggleActivoUsuarioId').value = id;
                document.getElementById('formToggleActivo').submit();
            }
        }

        // Función para restablecer contraseña
        function restablecerPassword(id) {
            if (confirm('¿Estás seguro de que quieres restablecer la contraseña de este usuario? Se generará una nueva contraseña temporal.')) {
                document.getElementById('restablecerPasswordUsuarioId').value = id;
                document.getElementById('formRestablecerPassword').submit();
            }
        }

        // Función para toggle dropdown
        function toggleDropdown(id) {
            const dropdown = document.getElementById(`dropdown-${id}`);
            const isOpen = dropdown.classList.contains('show');
            
            // Cerrar todos los dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
            
            // Abrir este dropdown si no estaba abierto
            if (!isOpen) {
                dropdown.classList.add('show');
            }
        }

        // Función para limpiar filtros
        function limpiarFiltros() {
            window.location.href = 'gestionar_usuarios.php';
        }

        // Función para exportar usuarios
        function exportarUsuarios() {
            window.open('reportes.php?export=usuarios', '_blank');
        }

        // Configuración inicial
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Mostrar modal si venimos de edición
            <?php if ($mostrar_modal): ?>
                document.getElementById('modalUsuario').style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Configurar datos en el modal
                document.getElementById('usuarioId').value = '<?php echo $usuario_editar['id']; ?>';
                document.getElementById('displayUsuarioId').textContent = '<?php echo $usuario_editar['id']; ?>';
                document.getElementById('displayFechaRegistro').textContent = '<?php echo formatearFecha($usuario_editar['fecha_registro']); ?>';
                document.getElementById('displayRol').textContent = '<?php echo $usuario_editar['rol'] === 'admin' ? '👑 Administrador' : '👤 Usuario'; ?>';
                document.getElementById('displayEstado').textContent = '<?php echo $usuario_editar['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>';
            <?php endif; ?>

            // Cerrar dropdowns al hacer click fuera
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
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
                const modalUsuario = document.getElementById('modalUsuario');
                const modalEliminar = document.getElementById('modalEliminar');
                
                if (e.target === modalUsuario) {
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

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.card, .table-row, .stat-card');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 50);
            });
        });
    </script>

</body>
</html>