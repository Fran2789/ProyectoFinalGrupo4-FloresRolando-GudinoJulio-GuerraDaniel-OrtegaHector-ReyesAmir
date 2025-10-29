<?php
require_once 'config/database.php';
require_once 'classes/ContenidoManager.php';
require_once 'classes/UsuarioManager.php';

// CAMBIO 1: Verificar si es una petición AJAX para respuestas JSON
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$contenidoManager = new ContenidoManager();
$usuarioManager = new UsuarioManager();

// Verificar sesión si está logueado
if (isLoggedIn()) {
    $usuarioManager->verificarSesion();
}

// Obtener géneros para filtros
$generos = $contenidoManager->obtenerGeneros();

// Parámetros de filtrado y búsqueda
$filtros = [
    'busqueda' => isset($_GET['buscar']) ? sanitize_input($_GET['buscar']) : '',
    'genero_id' => isset($_GET['genero']) ? sanitize_input($_GET['genero']) : '',
    'tipo' => isset($_GET['tipo']) ? sanitize_input($_GET['tipo']) : '',
    'año' => isset($_GET['año']) ? sanitize_input($_GET['año']) : '',
    'calificacion_min' => isset($_GET['calificacion_min']) ? floatval($_GET['calificacion_min']) : 0,
    'ordenar' => isset($_GET['ordenar']) ? sanitize_input($_GET['ordenar']) : 'calificacion_desc'
];

// Parámetros de paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = isset($_GET['por_pagina']) ? min(50, max(10, intval($_GET['por_pagina']))) : 20;

// Obtener contenido filtrado y paginado
$resultado = $contenidoManager->obtenerContenidoPaginado($pagina_actual, $por_pagina, $filtros);
$contenido = $resultado['contenido'];
$total_items = $resultado['total'];
$total_paginas = $resultado['total_paginas'];

// Obtener años disponibles para el filtro
$años_disponibles = $contenidoManager->obtenerAñosDisponibles();

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

// Estadísticas rápidas para mostrar
$stats_catalogo = [
    'total_peliculas' => $contenidoManager->contarContenidoPorTipo('pelicula'),
    'total_series' => $contenidoManager->contarContenidoPorTipo('serie'),
    'total_contenido' => $contenidoManager->contarContenidoTotal()
];
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo Completo - CineRecomendaciones</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    <meta name="description" content="Explora nuestro catálogo completo de películas y series. Filtra por género, año, calificación y más.">

    <!-- CAMBIO 2: Variables JavaScript para integración con APIs -->
    <script>
        // Variables esenciales para las APIs
        window.usuarioLogueado = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
        window.proyectoBase = '/PHP/proyecto_recomendaciones';
        
        <?php if (isLoggedIn()): ?>
            window.usuarioId = <?php echo $_SESSION['user_id']; ?>;
            window.usuarioNombre = '<?php echo addslashes($_SESSION['user_name']); ?>';
            window.usuarioRol = '<?php echo $_SESSION['role'] ?? 'usuario'; ?>';
        <?php endif; ?>
        
        // Configuración del catálogo
        window.catalogoConfig = {
            paginaActual: <?php echo $pagina_actual; ?>,
            porPagina: <?php echo $por_pagina; ?>,
            totalItems: <?php echo $total_items; ?>,
            totalPaginas: <?php echo $total_paginas; ?>,
            filtrosActuales: <?php echo json_encode($filtros); ?>
        };
    </script>
</head>
<body class="<?php echo $theme === 'dark' ? 'dark-theme' : ''; ?>">
    
    <!-- Navegación -->
    <nav class="navbar">
        <div class="container">
            <a href="inicio.php" class="logo">CineRecomendaciones</a>
            
            <ul class="nav-links">
                <li><a href="inicio.php">Inicio</a></li>
                <li><a href="catalogo.php" class="active">Catálogo</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="mis_recomendaciones.php">Mis Recomendaciones</a></li>
                    <li><a href="perfil_usuario.php">Mi Perfil</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/panel_admin.php">Administración</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <div class="user-menu">
                <button class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                    🌙
                </button>
                
                <?php if (isLoggedIn()): ?>
                    <span class="user-welcome">¡Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    <a href="auth/cerrar_sesion.php" class="btn btn-outline">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="auth/iniciar_sesion.php" class="btn btn-outline">Iniciar Sesión</a>
                    <a href="auth/registro_usuario.php" class="btn btn-primary">Registrarse</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container">
        
        <!-- Header del catálogo -->
        <section class="catalog-header mb-4">
            <div class="catalog-title">
                <h1>📽️ Catálogo Completo</h1>
                <p>Explora nuestra colección de <span id="totalItems"><?php echo $stats_catalogo['total_contenido']; ?></span> títulos</p>
            </div>
            
            <div class="catalog-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats_catalogo['total_peliculas']; ?></span>
                    <span class="stat-label">Películas</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $stats_catalogo['total_series']; ?></span>
                    <span class="stat-label">Series</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($generos); ?></span>
                    <span class="stat-label">Géneros</span>
                </div>
            </div>
        </section>

        <!-- CAMBIO 3: Filtros avanzados con búsqueda en tiempo real -->
        <section class="advanced-filters mb-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🔍 Filtros de búsqueda</h2>
                    <div class="filter-controls">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="limpiarFiltros()">
                            Limpiar filtros
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" id="toggleAdvanced" onclick="toggleFiltrosAvanzados()">
                            ⚙️ Más filtros
                        </button>
                    </div>
                </div>
                
                <form method="GET" action="catalogo.php" class="filters-form" id="filtrosForm">
                    <div class="filters-row">
                        <!-- NUEVO: Búsqueda con autocompletado -->
                        <div class="filter-group search-container">
                            <label for="buscar">Buscar título:</label>
                            <div class="search-input-container">
                                <input type="text" 
                                       id="buscar" 
                                       name="buscar" 
                                       class="form-control search-input" 
                                       placeholder="Buscar películas o series..."
                                       value="<?php echo htmlspecialchars($filtros['busqueda']); ?>"
                                       autocomplete="off"
                                       oninput="buscarEnTiempoReal(this.value)">
                                
                                <!-- Contenedor de sugerencias -->
                                <div id="searchSuggestions" class="search-suggestions"></div>
                                
                                <!-- Indicador de carga -->
                                <div id="searchLoading" class="search-loading" style="display: none;">
                                    <span>🔍</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filtro por tipo -->
                        <div class="filter-group">
                            <label for="tipo">Tipo de contenido:</label>
                            <select id="tipo" name="tipo" class="form-control filter-select">
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
                            <select id="genero" name="genero" class="form-control filter-select">
                                <option value="">Todos los géneros</option>
                                <?php foreach ($generos as $genero): ?>
                                    <option value="<?php echo $genero['id']; ?>" 
                                            <?php echo $filtros['genero_id'] == $genero['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($genero['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Filtros avanzados (inicialmente ocultos) -->
                    <div class="filters-row advanced-filters" id="advancedFilters" style="display: none;">
                        <!-- Filtro por año -->
                        <div class="filter-group">
                            <label for="año">Año de lanzamiento:</label>
                            <select id="año" name="año" class="form-control filter-select">
                                <option value="">Cualquier año</option>
                                <?php foreach ($años_disponibles as $año): ?>
                                    <option value="<?php echo $año; ?>" 
                                            <?php echo $filtros['año'] == $año ? 'selected' : ''; ?>>
                                        <?php echo $año; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Filtro por calificación -->
                        <div class="filter-group">
                            <label for="calificacion_min">Calificación mínima:</label>
                            <select id="calificacion_min" name="calificacion_min" class="form-control filter-select">
                                <option value="0">Cualquier calificación</option>
                                <option value="1" <?php echo $filtros['calificacion_min'] == 1 ? 'selected' : ''; ?>>
                                    ⭐ 1+ estrellas
                                </option>
                                <option value="2" <?php echo $filtros['calificacion_min'] == 2 ? 'selected' : ''; ?>>
                                    ⭐⭐ 2+ estrellas
                                </option>
                                <option value="3" <?php echo $filtros['calificacion_min'] == 3 ? 'selected' : ''; ?>>
                                    ⭐⭐⭐ 3+ estrellas
                                </option>
                                <option value="4" <?php echo $filtros['calificacion_min'] == 4 ? 'selected' : ''; ?>>
                                    ⭐⭐⭐⭐ 4+ estrellas
                                </option>
                                <option value="4.5" <?php echo $filtros['calificacion_min'] == 4.5 ? 'selected' : ''; ?>>
                                    ⭐⭐⭐⭐⭐ 4.5+ estrellas
                                </option>
                            </select>
                        </div>
                        
                        <!-- Ordenamiento -->
                        <div class="filter-group">
                            <label for="ordenar">Ordenar por:</label>
                            <select id="ordenar" name="ordenar" class="form-control filter-select">
                                <option value="calificacion_desc" <?php echo $filtros['ordenar'] === 'calificacion_desc' ? 'selected' : ''; ?>>
                                    📊 Mejor calificados
                                </option>
                                <option value="fecha_desc" <?php echo $filtros['ordenar'] === 'fecha_desc' ? 'selected' : ''; ?>>
                                    🆕 Más recientes
                                </option>
                                <option value="titulo_asc" <?php echo $filtros['ordenar'] === 'titulo_asc' ? 'selected' : ''; ?>>
                                    🔤 A-Z (título)
                                </option>
                                <option value="año_desc" <?php echo $filtros['ordenar'] === 'año_desc' ? 'selected' : ''; ?>>
                                    📅 Año (reciente a antiguo)
                                </option>
                                <option value="año_asc" <?php echo $filtros['ordenar'] === 'año_asc' ? 'selected' : ''; ?>>
                                    📅 Año (antiguo a reciente)
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary">
                            🔍 Aplicar filtros
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="filtroRapido('favoritos')">
                            ⭐ Solo favoritos (4+)
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="filtroRapido('recientes')">
                            🆕 Últimos agregados
                        </button>
                        
                        <!-- NUEVO: Botón de filtros inteligentes -->
                        <?php if (isLoggedIn()): ?>
                            <button type="button" class="btn btn-accent" onclick="filtrosPersonalizados()">
                                🎯 Para mí
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <!-- Resultados y paginación superior -->
        <section class="results-header mb-3" id="resultsHeader">
            <div class="results-info">
                <p>
                    <strong id="resultCount"><?php echo number_format($total_items); ?></strong> resultados encontrados
                    <span id="searchTermDisplay">
                        <?php if (!empty($filtros['busqueda'])): ?>
                            para "<em><?php echo htmlspecialchars($filtros['busqueda']); ?></em>"
                        <?php endif; ?>
                    </span>
                </p>
                <p class="results-range" id="resultsRange">
                    Mostrando <?php echo ($pagina_actual - 1) * $por_pagina + 1; ?> - 
                    <?php echo min($pagina_actual * $por_pagina, $total_items); ?> de <?php echo $total_items; ?>
                </p>
            </div>
            
            <div class="results-controls">
                <label for="por_pagina_top">Mostrar:</label>
                <select id="por_pagina_top" onchange="cambiarPorPagina(this.value)">
                    <option value="10" <?php echo $por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo $por_pagina == 20 ? 'selected' : ''; ?>>20</option>
                    <option value="30" <?php echo $por_pagina == 30 ? 'selected' : ''; ?>>30</option>
                    <option value="50" <?php echo $por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                </select>
                <span>por página</span>
                
                <!-- NUEVO: Indicador de carga -->
                <div id="catalogLoading" class="catalog-loading" style="display: none;">
                    <span>⏳ Cargando...</span>
                </div>
            </div>
        </section>

        <!-- CAMBIO 4: Contenido del catálogo con favoritos integrados -->
        <?php if (!empty($contenido)): ?>
            <section class="catalog-content mb-4">
                <div class="content-grid" id="contentGrid">
                    <?php foreach ($contenido as $item): ?>
                        <div class="content-item fade-in" data-content-id="<?php echo $item['id']; ?>" onclick="verDetalle(<?php echo $item['id']; ?>)">
                            <div class="content-poster">
                                <img src="uploads/<?php echo $item['poster'] ?: 'no-image.svg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['titulo']); ?>"
                                     loading="lazy"
                                     onerror="this.src='uploads/no-image.svg'">
                                
                                <!-- NUEVO: Botón de favoritos -->
                                <?php if (isLoggedIn()): ?>
                                    <button class="favorite-btn-catalog" 
                                            data-content-id="<?php echo $item['id']; ?>"
                                            onclick="toggleFavoritoCatalogo(event, <?php echo $item['id']; ?>)"
                                            title="Agregar/quitar de favoritos">
                                        🤍
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Overlay con información adicional -->
                                <div class="content-overlay">
                                    <div class="overlay-content">
                                        <h4><?php echo htmlspecialchars($item['titulo']); ?></h4>
                                        <p class="content-description">
                                            <?php echo htmlspecialchars(substr($item['descripcion'], 0, 120)) . '...'; ?>
                                        </p>
                                        <div class="overlay-actions">
                                            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); verDetalle(<?php echo $item['id']; ?>)">
                                                Ver detalles
                                            </button>
                                            
                                            <!-- NUEVO: Acciones rápidas -->
                                            <?php if (isLoggedIn()): ?>
                                                <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); previsualizacionRapida(<?php echo $item['id']; ?>)">
                                                    👁️ Vista rápida
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="content-info">
                                <h3 class="content-title"><?php echo htmlspecialchars($item['titulo']); ?></h3>
                                <div class="content-meta">
                                    <span class="content-genre"><?php echo htmlspecialchars($item['genero_nombre']); ?></span>
                                    <span class="content-year"><?php echo $item['año_lanzamiento']; ?></span>
                                </div>
                                <div class="content-rating">
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= round($item['calificacion']) ? 'filled' : ''; ?>">⭐</span>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-number"><?php echo number_format($item['calificacion'], 1); ?></span>
                                </div>
                                <div class="content-type">
                                    <span class="type-badge type-<?php echo $item['tipo']; ?>">
                                        <?php echo $item['tipo'] === 'pelicula' ? '🎬 Película' : '📺 Serie'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- CAMBIO 5: Paginación dinámica -->
            <?php if ($total_paginas > 1): ?>
                <section class="pagination-section" id="paginationSection">
                    <nav class="pagination-nav">
                        <div class="pagination-info">
                            <span>Página <span id="currentPage"><?php echo $pagina_actual; ?></span> de <span id="totalPages"><?php echo $total_paginas; ?></span></span>
                        </div>
                        
                        <div class="pagination-controls" id="paginationControls">
                            <?php if ($pagina_actual > 1): ?>
                                <button class="btn btn-outline btn-sm" onclick="cambiarPagina(1)">
                                    ⏮️ Primera
                                </button>
                                <button class="btn btn-outline btn-sm" onclick="cambiarPagina(<?php echo $pagina_actual - 1; ?>)">
                                    ⬅️ Anterior
                                </button>
                            <?php endif; ?>
                            
                            <?php
                            // Mostrar enlaces de páginas cercanas
                            $inicio = max(1, $pagina_actual - 2);
                            $fin = min($total_paginas, $pagina_actual + 2);
                            
                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <button class="btn <?php echo $i === $pagina_actual ? 'btn-primary' : 'btn-outline'; ?> btn-sm"
                                        onclick="cambiarPagina(<?php echo $i; ?>)">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            
                            <?php if ($pagina_actual < $total_paginas): ?>
                                <button class="btn btn-outline btn-sm" onclick="cambiarPagina(<?php echo $pagina_actual + 1; ?>)">
                                    Siguiente ➡️
                                </button>
                                <button class="btn btn-outline btn-sm" onclick="cambiarPagina(<?php echo $total_paginas; ?>)">
                                    Última ⏭️
                                </button>
                            <?php endif; ?>
                        </div>
                    </nav>
                </section>
            <?php endif; ?>

        <?php else: ?>
            <!-- No hay resultados -->
            <section class="no-results" id="noResults">
                <div class="card text-center">
                    <div class="no-results-icon">🔍</div>
                    <h3>No se encontraron resultados</h3>
                    <p>Intenta modificar los filtros de búsqueda o explora nuestro catálogo completo.</p>
                    <div class="no-results-actions">
                        <button type="button" class="btn btn-primary" onclick="limpiarFiltros()">
                            Ver todo el catálogo
                        </button>
                        <a href="inicio.php" class="btn btn-secondary">
                            Volver al inicio
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Sugerencias para usuarios no registrados -->
        <?php if (!isLoggedIn()): ?>
            <section class="cta-section mt-4">
                <div class="card">
                    <h3>🎯 ¿Quieres recomendaciones personalizadas?</h3>
                    <p>Regístrate gratis y recibe sugerencias basadas en tus gustos específicos</p>
                    <a href="auth/registro_usuario.php" class="btn btn-primary">
                        Crear cuenta gratis
                    </a>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Modal de vista rápida -->
    <div id="quickPreviewModal" class="modal" style="display: none;">
        <div class="modal-content quick-preview-content">
            <div class="modal-header">
                <h3 id="quickPreviewTitle">Vista rápida</h3>
                <button type="button" class="close" onclick="cerrarVistaRapida()">&times;</button>
            </div>
            <div class="modal-body" id="quickPreviewBody">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones. Proyecto universitario.</p>
        </div>
    </footer>

    <!-- CAMBIO 6: Scripts integrados con APIs -->
    <script>
        // Variables globales para el catálogo
        let searchTimeout;
        let favoritosCache = new Set();
        let filtrosActivos = false;
        let paginaActual = <?php echo $pagina_actual; ?>;
        let totalPaginas = <?php echo $total_paginas; ?>;

        // Función para cambiar tema
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            body.classList.toggle('dark-theme');
            document.documentElement.setAttribute('data-theme', newTheme);
            
            document.cookie = `theme=${newTheme}; expires=${new Date(Date.now() + 30*24*60*60*1000).toUTCString()}; path=/PHP/proyecto_recomendaciones/`;
            
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }

        // NUEVA: Búsqueda en tiempo real con autocompletado
        async function buscarEnTiempoReal(termino) {
            clearTimeout(searchTimeout);
            
            const loading = document.getElementById('searchLoading');
            const suggestions = document.getElementById('searchSuggestions');
            
            if (termino.length < 2) {
                suggestions.innerHTML = '';
                loading.style.display = 'none';
                return;
            }
            
            loading.style.display = 'block';
            
            searchTimeout = setTimeout(async () => {
                // Solo usar API si está disponible
                if (!window.CineAPI) {
                    loading.style.display = 'none';
                    return;
                }
                
                try {
                    const resultado = await window.CineAPI.autocompletar(termino, 5);
                    
                    if (resultado.success && resultado.data.sugerencias.length > 0) {
                        mostrarSugerencias(resultado.data.sugerencias);
                    } else {
                        suggestions.innerHTML = '';
                    }
                    
                } catch (error) {
                    console.warn('Error en autocompletado:', error);
                    suggestions.innerHTML = '';
                } finally {
                    loading.style.display = 'none';
                }
            }, 300);
        }

        // Mostrar sugerencias de búsqueda
        function mostrarSugerencias(sugerencias) {
            const container = document.getElementById('searchSuggestions');
            
            const html = sugerencias.map(sugerencia => `
                <div class="suggestion-item" onclick="seleccionarSugerencia('${sugerencia.titulo}')">
                    <img src="uploads/${sugerencia.poster || 'no-image.svg'}" 
                         alt="${sugerencia.titulo}"
                         onerror="this.src='uploads/no-image.svg'">
                    <div class="suggestion-info">
                        <strong>${sugerencia.titulo}</strong>
                        <small>${sugerencia.tipo} - ${sugerencia.año || 'N/A'}</small>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = html;
        }

        // Seleccionar sugerencia
        function seleccionarSugerencia(titulo) {
            document.getElementById('buscar').value = titulo;
            document.getElementById('searchSuggestions').innerHTML = '';
            
            // Aplicar búsqueda
            aplicarFiltrosDinamicos();
        }

        // NUEVA: Aplicar filtros sin recargar página
        async function aplicarFiltrosDinamicos() {
            if (!window.CineAPI) {
                // Fallback: usar form submit tradicional
                document.getElementById('filtrosForm').submit();
                return;
            }
            
            const loading = document.getElementById('catalogLoading');
            const grid = document.getElementById('contentGrid');
            
            // Mostrar loading
            loading.style.display = 'block';
            grid.style.opacity = '0.6';
            
            try {
                // Recopilar filtros actuales
                const filtros = {
                    termino: document.getElementById('buscar').value,
                    tipo: document.getElementById('tipo').value,
                    generoId: document.getElementById('genero').value,
                    año: document.getElementById('año').value,
                    calificacionMin: document.getElementById('calificacion_min').value,
                    orden: document.getElementById('ordenar').value,
                    limite: parseInt(document.getElementById('por_pagina_top').value),
                    pagina: 1 // Resetear a primera página
                };
                
                const resultado = await window.CineAPI.buscarContenido(filtros.termino, filtros);
                
                if (resultado.success) {
                    actualizarCatalogoDinamico(resultado.data);
                    
                    // Notificación sutil
                    if (window.CineAPI) {
                        window.CineAPI.showNotification('Catálogo actualizado', 'success');
                    }
                }
                
            } catch (error) {
                console.error('Error aplicando filtros:', error);
                
                // Fallback: usar form submit
                document.getElementById('filtrosForm').submit();
                
            } finally {
                loading.style.display = 'none';
                grid.style.opacity = '1';
            }
        }

        // Actualizar catálogo dinámicamente
        function actualizarCatalogoDinamico(data) {
            const grid = document.getElementById('contentGrid');
            const resultCount = document.getElementById('resultCount');
            const resultsRange = document.getElementById('resultsRange');
            const currentPage = document.getElementById('currentPage');
            const totalPages = document.getElementById('totalPages');
            
            // Actualizar estadísticas
            resultCount.textContent = data.total_resultados.toLocaleString();
            
            // Actualizar contenido
            if (data.resultados && data.resultados.length > 0) {
                const html = data.resultados.map(item => generarItemCatalogo(item)).join('');
                grid.innerHTML = html;
                
                // Animar entrada
                setTimeout(() => {
                    grid.querySelectorAll('.content-item').forEach((item, index) => {
                        item.style.animationDelay = `${index * 0.05}s`;
                        item.classList.add('fade-in-up');
                    });
                }, 50);
                
                // Actualizar favoritos
                if (window.usuarioLogueado) {
                    cargarEstadosFavoritos();
                }
                
            } else {
                mostrarNoResults();
            }
            
            // Actualizar paginación
            actualizarPaginacion(data.paginacion);
        }

        // Generar HTML para item del catálogo
        function generarItemCatalogo(item) {
            const favoritoBtn = window.usuarioLogueado ? 
                `<button class="favorite-btn-catalog" 
                         data-content-id="${item.id}"
                         onclick="toggleFavoritoCatalogo(event, ${item.id})"
                         title="Agregar/quitar de favoritos">
                    🤍
                 </button>` : '';
            
            const accionesRapidas = window.usuarioLogueado ? 
                `<button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); previsualizacionRapida(${item.id})">
                    👁️ Vista rápida
                 </button>` : '';
            
            return `
                <div class="content-item" data-content-id="${item.id}" onclick="verDetalle(${item.id})">
                    <div class="content-poster">
                        <img src="uploads/${item.poster || 'no-image.svg'}" 
                             alt="${item.titulo}"
                             loading="lazy"
                             onerror="this.src='uploads/no-image.svg'">
                        
                        ${favoritoBtn}
                        
                        <div class="content-overlay">
                            <div class="overlay-content">
                                <h4>${item.titulo}</h4>
                                <p class="content-description">
                                    ${item.descripcion ? item.descripcion.substring(0, 120) + '...' : 'Sin descripción disponible'}
                                </p>
                                <div class="overlay-actions">
                                    <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); verDetalle(${item.id})">
                                        Ver detalles
                                    </button>
                                    ${accionesRapidas}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="content-info">
                        <h3 class="content-title">${item.titulo}</h3>
                        <div class="content-meta">
                            <span class="content-genre">${item.genero_nombre || 'Sin género'}</span>
                            <span class="content-year">${item.año_lanzamiento || 'N/A'}</span>
                        </div>
                        <div class="content-rating">
                            <div class="stars">
                                ${[1,2,3,4,5].map(i => `<span class="star ${i <= Math.round(item.calificacion) ? 'filled' : ''}">⭐</span>`).join('')}
                            </div>
                            <span class="rating-number">${(item.calificacion || 0).toFixed(1)}</span>
                        </div>
                        <div class="content-type">
                            <span class="type-badge type-${item.tipo}">
                                ${item.tipo === 'pelicula' ? '🎬 Película' : '📺 Serie'}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }

        // NUEVA: Toggle favorito en catálogo
        async function toggleFavoritoCatalogo(event, contenidoId) {
            event.preventDefault();
            event.stopPropagation();
            
            if (!window.usuarioLogueado) {
                if (window.CineAPI) {
                    window.CineAPI.showNotification('Debes iniciar sesión para agregar favoritos', 'warning');
                } else {
                    alert('Debes iniciar sesión para agregar favoritos');
                }
                return;
            }
            
            const boton = document.querySelector(`[data-content-id="${contenidoId}"].favorite-btn-catalog`);
            if (!boton) return;
            
            const originalContent = boton.textContent;
            
            // Estado de carga
            boton.textContent = '⏳';
            boton.disabled = true;
            
            try {
                if (window.CineAPI) {
                    const resultado = await window.CineAPI.toggleFavorito(contenidoId);
                    
                    if (resultado.success) {
                        const esFavorito = resultado.data.accion === 'agregado';
                        
                        // Actualizar UI
                        boton.textContent = esFavorito ? '❤️' : '🤍';
                        boton.classList.toggle('active', esFavorito);
                        
                        // Actualizar cache
                        if (esFavorito) {
                            favoritosCache.add(contenidoId.toString());
                        } else {
                            favoritosCache.delete(contenidoId.toString());
                        }
                        
                        // Animación de éxito
                        boton.style.transform = 'scale(1.3)';
                        setTimeout(() => {
                            boton.style.transform = 'scale(1)';
                        }, 200);
                        
                    } else {
                        boton.textContent = originalContent;
                    }
                } else {
                    // Fallback sin API
                    boton.textContent = originalContent;
                    alert('Funcionalidad de favoritos - ID: ' + contenidoId);
                }
                
            } catch (error) {
                console.error('Error en toggle favorito:', error);
                boton.textContent = originalContent;
            } finally {
                boton.disabled = false;
            }
        }

        // Cargar estados de favoritos para todos los items visibles
        async function cargarEstadosFavoritos() {
            if (!window.CineAPI || !window.usuarioLogueado) return;
            
            const botones = document.querySelectorAll('.favorite-btn-catalog[data-content-id]');
            
            // Procesar de a lotes para no sobrecargar
            const lotes = dividirEnLotes(Array.from(botones), 5);
            
            for (const lote of lotes) {
                await Promise.all(lote.map(async (boton) => {
                    const contenidoId = boton.getAttribute('data-content-id');
                    
                    try {
                        const resultado = await window.CineAPI.verificarFavorito(contenidoId);
                        
                        if (resultado.success) {
                            const esFavorito = resultado.data.es_favorito;
                            
                            boton.textContent = esFavorito ? '❤️' : '🤍';
                            boton.classList.toggle('active', esFavorito);
                            
                            // Actualizar cache
                            if (esFavorito) {
                                favoritosCache.add(contenidoId);
                            }
                        }
                    } catch (error) {
                        console.warn(`Error verificando favorito ${contenidoId}:`, error);
                    }
                }));
                
                // Pausa entre lotes
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }

        // Función para ver detalle de contenido (mejorada)
        function verDetalle(id) {
            // Guardar en historial si las APIs están disponibles
            if (window.CineAPI && window.usuarioLogueado) {
                window.CineAPI.guardarHistorial(id);
            }
            
            window.location.href = `detalle_contenido.php?id=${id}`;
        }

        // NUEVA: Vista rápida de contenido
        async function previsualizacionRapida(contenidoId) {
            const modal = document.getElementById('quickPreviewModal');
            const title = document.getElementById('quickPreviewTitle');
            const body = document.getElementById('quickPreviewBody');
            
            title.textContent = 'Cargando...';
            body.innerHTML = '<div class="loading-preview">⏳ Obteniendo información...</div>';
            modal.style.display = 'block';
            
            try {
                // Usar API si está disponible
                if (window.CineAPI) {
                    // Por ahora, mostrar información básica
                    const elemento = document.querySelector(`[data-content-id="${contenidoId}"]`);
                    const titulo = elemento.querySelector('.content-title').textContent;
                    const poster = elemento.querySelector('img').src;
                    
                    title.textContent = `Vista rápida - ${titulo}`;
                    body.innerHTML = `
                        <div class="quick-preview">
                            <div class="preview-poster">
                                <img src="${poster}" alt="${titulo}">
                            </div>
                            <div class="preview-info">
                                <h4>${titulo}</h4>
                                <p>Vista rápida del contenido. Para más detalles, visita la página completa.</p>
                                <div class="preview-actions">
                                    <button class="btn btn-primary" onclick="cerrarVistaRapida(); verDetalle(${contenidoId})">
                                        Ver detalles completos
                                    </button>
                                    <button class="btn btn-secondary" onclick="cerrarVistaRapida()">
                                        Cerrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // Fallback sin API
                    body.innerHTML = '<p>Funcionalidad no disponible sin API. <a onclick="verDetalle(' + contenidoId + ')">Ver detalles completos</a></p>';
                }
                
            } catch (error) {
                console.error('Error en vista rápida:', error);
                body.innerHTML = '<p>Error cargando vista rápida. <a onclick="verDetalle(' + contenidoId + ')">Ver detalles completos</a></p>';
            }
        }

        // Cerrar vista rápida
        function cerrarVistaRapida() {
            document.getElementById('quickPreviewModal').style.display = 'none';
        }

        // Función para limpiar filtros
        function limpiarFiltros() {
            if (window.CineAPI) {
                // Limpiar formulario
                document.getElementById('filtrosForm').reset();
                
                // Aplicar filtros vacíos
                aplicarFiltrosDinamicos();
            } else {
                // Fallback tradicional
                window.location.href = 'catalogo.php';
            }
        }

        // Toggle filtros avanzados
        function toggleFiltrosAvanzados() {
            const advanced = document.getElementById('advancedFilters');
            const toggle = document.getElementById('toggleAdvanced');
            
            if (advanced.style.display === 'none') {
                advanced.style.display = 'block';
                toggle.textContent = '⚙️ Menos filtros';
            } else {
                advanced.style.display = 'none';
                toggle.textContent = '⚙️ Más filtros';
            }
        }

        // Función para filtros rápidos
        function filtroRapido(tipo) {
            if (tipo === 'favoritos') {
                document.getElementById('calificacion_min').value = '4';
                document.getElementById('ordenar').value = 'calificacion_desc';
            } else if (tipo === 'recientes') {
                document.getElementById('ordenar').value = 'fecha_desc';
            }
            
            if (window.CineAPI) {
                aplicarFiltrosDinamicos();
            } else {
                document.getElementById('filtrosForm').submit();
            }
        }

        // NUEVA: Filtros personalizados basados en historial
        async function filtrosPersonalizados() {
            if (!window.CineAPI || !window.usuarioLogueado) {
                alert('Funcionalidad no disponible');
                return;
            }
            
            try {
                const resultado = await window.CineAPI.obtenerRecomendaciones('personalizadas', {
                    limite: 20,
                    incluirMetadata: true
                });
                
                if (resultado.success && resultado.data.recomendaciones.length > 0) {
                    // Simular aplicación de filtros personalizados
                    actualizarCatalogoDinamico({
                        resultados: resultado.data.recomendaciones,
                        total_resultados: resultado.data.recomendaciones.length,
                        paginacion: {
                            pagina_actual: 1,
                            total_paginas: 1,
                            por_pagina: 20
                        }
                    });
                    
                    window.CineAPI.showNotification('Mostrando contenido personalizado para ti', 'success');
                } else {
                    window.CineAPI.showNotification('No se encontraron recomendaciones personalizadas', 'info');
                }
                
            } catch (error) {
                console.error('Error obteniendo filtros personalizados:', error);
                window.CineAPI.showNotification('Error obteniendo recomendaciones personalizadas', 'error');
            }
        }

        // Función para cambiar elementos por página
        function cambiarPorPagina(valor) {
            if (window.CineAPI) {
                // Actualizar y aplicar filtros
                aplicarFiltrosDinamicos();
            } else {
                const url = new URL(window.location);
                url.searchParams.set('por_pagina', valor);
                url.searchParams.set('pagina', '1');
                window.location.href = url.toString();
            }
        }

        // NUEVA: Cambiar página dinámicamente
        async function cambiarPagina(pagina) {
            if (!window.CineAPI) {
                // Fallback tradicional
                const url = new URL(window.location);
                url.searchParams.set('pagina', pagina);
                window.location.href = url.toString();
                return;
            }
            
            const loading = document.getElementById('catalogLoading');
            const grid = document.getElementById('contentGrid');
            
            loading.style.display = 'block';
            grid.style.opacity = '0.6';
            
            try {
                // Usar filtros actuales
                const filtros = {
                    termino: document.getElementById('buscar').value,
                    tipo: document.getElementById('tipo').value,
                    generoId: document.getElementById('genero').value,
                    año: document.getElementById('año').value,
                    calificacionMin: document.getElementById('calificacion_min').value,
                    orden: document.getElementById('ordenar').value,
                    limite: parseInt(document.getElementById('por_pagina_top').value),
                    pagina: pagina
                };
                
                const resultado = await window.CineAPI.buscarContenido(filtros.termino, filtros);
                
                if (resultado.success) {
                    actualizarCatalogoDinamico(resultado.data);
                    
                    // Scroll suave al top
                    document.getElementById('resultsHeader').scrollIntoView({ 
                        behavior: 'smooth' 
                    });
                }
                
            } catch (error) {
                console.error('Error cambiando página:', error);
                // Fallback
                const url = new URL(window.location);
                url.searchParams.set('pagina', pagina);
                window.location.href = url.toString();
            } finally {
                loading.style.display = 'none';
                grid.style.opacity = '1';
            }
        }

        // Actualizar controles de paginación
        function actualizarPaginacion(paginacion) {
            const currentPage = document.getElementById('currentPage');
            const totalPages = document.getElementById('totalPages');
            const controls = document.getElementById('paginationControls');
            
            if (!paginacion) return;
            
            paginaActual = paginacion.pagina_actual;
            totalPaginas = paginacion.total_paginas;
            
            currentPage.textContent = paginaActual;
            totalPages.textContent = totalPaginas;
            
            // Regenerar controles de paginación
            generarControlesPaginacion();
        }

        // Generar controles de paginación dinámicamente
        function generarControlesPaginacion() {
            const controls = document.getElementById('paginationControls');
            if (!controls) return;
            
            let html = '';
            
            // Botón primera/anterior
            if (paginaActual > 1) {
                html += `
                    <button class="btn btn-outline btn-sm" onclick="cambiarPagina(1)">⏮️ Primera</button>
                    <button class="btn btn-outline btn-sm" onclick="cambiarPagina(${paginaActual - 1})">⬅️ Anterior</button>
                `;
            }
            
            // Páginas cercanas
            const inicio = Math.max(1, paginaActual - 2);
            const fin = Math.min(totalPaginas, paginaActual + 2);
            
            for (let i = inicio; i <= fin; i++) {
                const clase = i === paginaActual ? 'btn-primary' : 'btn-outline';
                html += `<button class="btn ${clase} btn-sm" onclick="cambiarPagina(${i})">${i}</button>`;
            }
            
            // Botón siguiente/última
            if (paginaActual < totalPaginas) {
                html += `
                    <button class="btn btn-outline btn-sm" onclick="cambiarPagina(${paginaActual + 1})">Siguiente ➡️</button>
                    <button class="btn btn-outline btn-sm" onclick="cambiarPagina(${totalPaginas})">Última ⏭️</button>
                `;
            }
            
            controls.innerHTML = html;
        }

        // Mostrar estado de no resultados
        function mostrarNoResults() {
            const grid = document.getElementById('contentGrid');
            grid.innerHTML = `
                <div class="no-results-grid">
                    <div class="no-results-icon">🔍</div>
                    <h3>No se encontraron resultados</h3>
                    <p>Intenta modificar los filtros de búsqueda</p>
                    <button class="btn btn-primary" onclick="limpiarFiltros()">
                        Ver todo el catálogo
                    </button>
                </div>
            `;
        }

        // Función helper para dividir arrays en lotes
        function dividirEnLotes(array, tamañoLote) {
            const lotes = [];
            for (let i = 0; i < array.length; i += tamañoLote) {
                lotes.push(array.slice(i, i + tamañoLote));
            }
            return lotes;
        }

        // Configuración inicial y event listeners
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎬 Catálogo integrado con APIs inicializado');
            
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Event listeners para filtros dinámicos
            if (window.CineAPI) {
                const filtros = document.querySelectorAll('.filter-select');
                filtros.forEach(filtro => {
                    filtro.addEventListener('change', () => {
                        setTimeout(() => aplicarFiltrosDinamicos(), 300);
                    });
                });
            } else {
                // Fallback: comportamiento tradicional
                const filtros = document.querySelectorAll('#filtrosForm select');
                filtros.forEach(filtro => {
                    filtro.addEventListener('change', function() {
                        setTimeout(() => {
                            document.getElementById('filtrosForm').submit();
                        }, 300);
                    });
                });
            }

            // Búsqueda con delay en input
            const buscarInput = document.getElementById('buscar');
            let searchTimeout;
            
            buscarInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length === 0 || this.value.length >= 3) {
                        if (window.CineAPI) {
                            aplicarFiltrosDinamicos();
                        } else {
                            document.getElementById('filtrosForm').submit();
                        }
                    }
                }, 1000);
            });

            // Cerrar sugerencias al hacer click fuera
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.search-container')) {
                    document.getElementById('searchSuggestions').innerHTML = '';
                }
            });

            // Cerrar modal al hacer click fuera
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('quickPreviewModal');
                if (event.target === modal) {
                    cerrarVistaRapida();
                }
            });

            // Cargar estados de favoritos si está logueado
            if (window.usuarioLogueado && window.CineAPI) {
                setTimeout(() => cargarEstadosFavoritos(), 1000);
            }

            // Animaciones de entrada
            setTimeout(() => {
                const items = document.querySelectorAll('.content-item');
                items.forEach((item, index) => {
                    setTimeout(() => {
                        item.classList.add('fade-in');
                    }, index * 50);
                });
            }, 100);
        });
    </script>

    <!-- Integración con APIs REST -->
    <script src="js/api-client.js"></script>

    <!-- Estilos adicionales para funcionalidades nuevas -->
    <style>
        /* ===== ESTILOS PARA NUEVAS FUNCIONALIDADES ===== */

        /* Búsqueda con autocompletado */
        .search-container {
            position: relative;
        }

        .search-input-container {
            position: relative;
        }

        .search-loading {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            animation: pulse 1s infinite;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0 0 8px 8px;
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: var(--shadow);
        }

        .suggestion-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
        }

        .suggestion-item:hover {
            background-color: var(--bg-light);
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item img {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .suggestion-info strong {
            display: block;
            color: var(--text-dark);
        }

        .suggestion-info small {
            color: var(--text-secondary);
        }

        /* Controles de filtros mejorados */
        .filter-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .catalog-loading {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Botón de favoritos en catálogo */
        .favorite-btn-catalog {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            backdrop-filter: blur(4px);
        }

        .favorite-btn-catalog:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }

        .favorite-btn-catalog.active {
            background: rgba(220, 53, 69, 0.9);
        }

        /* Modal de vista rápida */
        .quick-preview-content {
            max-width: 600px;
            width: 90%;
        }

        .quick-preview {
            display: flex;
            gap: 1.5rem;
        }

        .preview-poster {
            flex-shrink: 0;
        }

        .preview-poster img {
            width: 150px;
            height: 225px;
            object-fit: cover;
            border-radius: 8px;
        }

        .preview-info {
            flex: 1;
        }

        .preview-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.75rem;
        }

        .loading-preview {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        /* Estados de no resultados en grid */
        .no-results-grid {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
        }

        .no-results-grid .no-results-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* Animaciones */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Filtros avanzados */
        .advanced-filters {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 0.25rem;
            }
            
            .search-suggestions {
                position: fixed;
                left: 10px;
                right: 10px;
                top: auto;
                max-height: 50vh;
            }
            
            .quick-preview {
                flex-direction: column;
                text-align: center;
            }
            
            .preview-poster img {
                width: 120px;
                height: 180px;
            }
            
            .favorite-btn-catalog {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
        }

        /* Tema oscuro */
        body.dark-theme .search-suggestions {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        body.dark-theme .suggestion-item:hover {
            background-color: var(--bg-light);
        }

        body.dark-theme .favorite-btn-catalog {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
        }

        body.dark-theme .favorite-btn-catalog:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Estilos específicos del catálogo existentes */
        .catalog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 0;
            border-bottom: 2px solid var(--border-color);
        }

        .catalog-title h1 {
            margin: 0;
            font-size: 2.5rem;
            color: var(--primary-color);
        }

        .catalog-title p {
            margin: 0.5rem 0 0 0;
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .catalog-stats {
            display: flex;
            gap: 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .filters-form {
            space-y: 1rem;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filters-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: var(--border-radius);
        }

        .results-info p {
            margin: 0;
        }

        .results-range {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .results-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-item {
            position: relative;
            cursor: pointer;
            transition: var(--transition);
        }

        .content-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .content-poster {
            position: relative;
            overflow: hidden;
            border-radius: var(--border-radius);
            aspect-ratio: 2/3;
        }

        .content-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .content-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            opacity: 0;
            transition: var(--transition);
            display: flex;
            align-items: flex-end;
            padding: 1rem;
        }

        .content-item:hover .content-overlay {
            opacity: 1;
        }

        .overlay-content {
            color: white;
            width: 100%;
        }

        .overlay-content h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
        }

        .content-description {
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .overlay-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .content-info {
            padding: 1rem;
        }

        .content-title {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .content-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .content-genre {
            color: var(--accent-color);
            font-weight: 500;
        }

        .content-year {
            color: var(--text-secondary);
        }

        .content-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .stars {
            display: flex;
            gap: 0.1rem;
        }

        .star {
            font-size: 0.9rem;
            opacity: 0.3;
        }

        .star.filled {
            opacity: 1;
        }

        .rating-number {
            font-weight: 600;
            color: var(--text-dark);
        }

        .type-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .type-pelicula {
            background: #e3f2fd;
            color: #1565c0;
        }

        .type-serie {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        body.dark-theme .type-pelicula {
            background: #1565c0;
            color: #e3f2fd;
        }

        body.dark-theme .type-serie {
            background: #7b1fa2;
            color: #f3e5f5;
        }

        .pagination-section {
            margin: 3rem 0;
        }

        .pagination-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .pagination-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-weight: 500;
            color: var(--text-dark);
        }

        .no-results {
            text-align: center;
            padding: 4rem 0;
        }

        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .no-results h3 {
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .no-results p {
            margin-bottom: 2rem;
            color: var(--text-secondary);
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .no-results-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .nav-links .active {
            background-color: rgba(255,255,255,0.2);
            border-radius: 5px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .catalog-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .catalog-stats {
                gap: 1rem;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .filters-actions {
                flex-direction: column;
                align-items: center;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .content-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1rem;
            }

            .pagination-nav {
                flex-direction: column;
                gap: 1rem;
            }

            .pagination-controls {
                flex-wrap: wrap;
                justify-content: center;
            }

            .overlay-content {
                padding: 0.5rem;
            }

            .overlay-actions {
                flex-direction: column;
                gap: 0.25rem;
            }

            .no-results-actions {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .content-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .catalog-title h1 {
                font-size: 2rem;
            }
        }
    </style>

</body>
</html>

<?php
/**
 * Función helper para construir URLs de paginación manteniendo filtros
 */
function construirUrlPaginacion($pagina) {
    $params = $_GET;
    $params['pagina'] = $pagina;
    return 'catalogo.php?' . http_build_query($params);
}
?>