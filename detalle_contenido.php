<?php
require_once 'config/database.php';
require_once 'classes/ContenidoManager.php';
require_once 'classes/UsuarioManager.php';

// CAMBIO 1: Verificar si es una petición AJAX para respuestas JSON
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Verificar que se proporcione un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('catalogo.php');
}

$contenido_id = intval($_GET['id']);
$contenidoManager = new ContenidoManager();
$usuarioManager = new UsuarioManager();

// Verificar sesión si está logueado
if (isLoggedIn()) {
    $usuarioManager->verificarSesion();
}

// Obtener detalles del contenido
$contenido = $contenidoManager->obtenerDetalleContenido($contenido_id);
if (!$contenido) {
    redirect('catalogo.php');
}

// Variables para mensajes y formularios
$mensaje = '';
$tipo_mensaje = '';
$mostrar_formulario_calificacion = false;

// Procesar formulario de calificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    if (isset($_POST['action']) && $_POST['action'] === 'calificar') {
        $calificacion = intval($_POST['calificacion']);
        $comentario = sanitize_input($_POST['comentario']);
        
        if ($calificacion >= 1 && $calificacion <= 5) {
            $resultado = $contenidoManager->guardarCalificacion(
                $_SESSION['user_id'], 
                $contenido_id, 
                $calificacion, 
                $comentario
            );
            
            if ($resultado) {
                $mensaje = 'Tu calificación ha sido guardada correctamente';
                $tipo_mensaje = 'success';
                // Recargar datos del contenido para mostrar nueva calificación
                $contenido = $contenidoManager->obtenerDetalleContenido($contenido_id);
            } else {
                $mensaje = 'Error al guardar la calificación';
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = 'La calificación debe estar entre 1 y 5 estrellas';
            $tipo_mensaje = 'error';
        }
    }
}

// Guardar en historial si está logueado
if (isLoggedIn()) {
    $contenidoManager->guardarHistorial($_SESSION['user_id'], $contenido_id);
}

// Obtener calificaciones y comentarios
$calificaciones = $contenidoManager->obtenerCalificaciones($contenido_id, 10);

// Obtener calificación del usuario actual si está logueado
$calificacion_usuario = null;
if (isLoggedIn()) {
    $calificacion_usuario = $contenidoManager->obtenerCalificacionUsuario($_SESSION['user_id'], $contenido_id);
}

// Obtener contenido relacionado
$contenido_relacionado = $contenidoManager->obtenerContenidoRelacionado($contenido_id, 6);

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

// Preparar datos para el schema JSON-LD (SEO)
$schema_data = [
    "@context" => "https://schema.org",
    "@type" => $contenido['tipo'] === 'pelicula' ? "Movie" : "TVSeries",
    "name" => $contenido['titulo'],
    "description" => $contenido['descripcion'],
    "genre" => $contenido['genero_nombre'],
    "datePublished" => $contenido['año_lanzamiento'],
    "aggregateRating" => [
        "@type" => "AggregateRating",
        "ratingValue" => $contenido['calificacion'],
        "ratingCount" => count($calificaciones),
        "bestRating" => "5",
        "worstRating" => "1"
    ]
];
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($contenido['titulo']); ?> - CineRecomendaciones</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    
    <!-- Meta tags para SEO -->
    <meta name="description" content="<?php echo htmlspecialchars(substr($contenido['descripcion'], 0, 160)); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($contenido['titulo'] . ', ' . $contenido['genero_nombre'] . ', ' . $contenido['tipo']); ?>">
    
    <!-- Open Graph para redes sociales -->
    <meta property="og:title" content="<?php echo htmlspecialchars($contenido['titulo']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr($contenido['descripcion'], 0, 160)); ?>">
    <meta property="og:image" content="<?php echo BASE_URL; ?>uploads/<?php echo $contenido['poster'] ?: 'no-image.svg'; ?>">
    <meta property="og:type" content="video.<?php echo $contenido['tipo'] === 'pelicula' ? 'movie' : 'tv_show'; ?>">
    
    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
        <?php echo json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>

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
        
        // Información del contenido actual
        window.contenidoActual = {
            id: <?php echo $contenido_id; ?>,
            titulo: '<?php echo addslashes($contenido['titulo']); ?>',
            tipo: '<?php echo $contenido['tipo']; ?>',
            genero: '<?php echo addslashes($contenido['genero_nombre']); ?>',
            genero_id: <?php echo $contenido['genero_id'] ?? 'null'; ?>
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
                <li><a href="catalogo.php">Catálogo</a></li>
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
        
        <!-- Breadcrumbs -->
        <nav class="breadcrumbs mb-3">
            <a href="inicio.php">Inicio</a>
            <span class="separator">></span>
            <a href="catalogo.php">Catálogo</a>
            <span class="separator">></span>
            <span class="current"><?php echo htmlspecialchars($contenido['titulo']); ?></span>
        </nav>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> mb-4">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Header del contenido -->
        <section class="content-header mb-4">
            <div class="content-hero">
                <div class="content-poster-large">
                    <img src="uploads/<?php echo $contenido['poster'] ?: 'no-image.svg'; ?>" 
                         alt="<?php echo htmlspecialchars($contenido['titulo']); ?>"
                         onerror="this.src='uploads/no-image.svg'">
                    
                    <!-- Overlay con acciones rápidas -->
                    <div class="poster-overlay">
                        <?php if (!empty($contenido['trailer_url'])): ?>
                            <button class="btn btn-primary btn-lg" onclick="mostrarTrailer('<?php echo htmlspecialchars($contenido['trailer_url']); ?>')">
                                ▶️ Ver Trailer
                            </button>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn()): ?>
                            <button class="btn btn-outline btn-lg" onclick="agregarALista(<?php echo $contenido_id; ?>)">
                                ❤️ Mi Lista
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="content-details">
                    <div class="content-title-section">
                        <h1 class="content-title"><?php echo htmlspecialchars($contenido['titulo']); ?></h1>
                        <div class="content-meta-info">
                            <span class="content-type-badge type-<?php echo $contenido['tipo']; ?>">
                                <?php echo $contenido['tipo'] === 'pelicula' ? '🎬 Película' : '📺 Serie'; ?>
                            </span>
                            <span class="content-year"><?php echo $contenido['año_lanzamiento']; ?></span>
                            <span class="content-duration"><?php echo htmlspecialchars($contenido['duracion']); ?></span>
                            <span class="content-genre">
                                <strong><?php echo htmlspecialchars($contenido['genero_nombre']); ?></strong>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Calificación promedio -->
                    <div class="rating-section">
                        <div class="rating-display">
                            <div class="stars-large">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star-large <?php echo $i <= round($contenido['calificacion']) ? 'filled' : ''; ?>">⭐</span>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-info">
                                <span class="rating-number"><?php echo number_format($contenido['calificacion'], 1); ?></span>
                                <span class="rating-count">(<?php echo count($calificaciones); ?> reseñas)</span>
                            </div>
                        </div>
                        
                        <!-- CAMBIO 3: Sistema de acciones moderno -->
                        <?php if (isLoggedIn()): ?>
                            <div class="user-actions">
                                <!-- Botón de calificación (mantener funcionalidad existente) -->
                                <button class="btn btn-primary" onclick="mostrarFormularioCalificacion()">
                                    <?php echo $calificacion_usuario ? 'Editar mi reseña' : 'Escribir reseña'; ?>
                                </button>
                                
                                <!-- NUEVO: Botón de favoritos dinámico -->
                                <div id="favoritesContainer">
                                    <button id="favoriteBtn" class="btn btn-secondary favorite-button" style="display: none;">
                                        <span id="favoriteIcon">🤍</span>
                                        <span id="favoriteText">Cargando...</span>
                                    </button>
                                </div>
                                
                                <!-- Mantener botón compartir existente -->
                                <button class="btn btn-secondary" onclick="compartir()">
                                    🔗 Compartir
                                </button>
                                
                                <!-- NUEVO: Botón de reporte -->
                                <button class="btn btn-outline btn-sm" onclick="reportarContenido()" title="Reportar contenido">
                                    ⚠️ Reportar
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="guest-actions">
                                <p><a href="auth/registro_usuario.php">Regístrate</a> para calificar, agregar a favoritos y comentar</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Descripción -->
                    <div class="content-description">
                        <h3>Sinopsis</h3>
                        <p><?php echo nl2br(htmlspecialchars($contenido['descripcion'])); ?></p>
                        
                        <?php if (!empty($contenido['genero_descripcion'])): ?>
                            <div class="genre-info">
                                <strong>Sobre el género <?php echo htmlspecialchars($contenido['genero_nombre']); ?>:</strong>
                                <p><?php echo htmlspecialchars($contenido['genero_descripcion']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Formulario de calificación (modal o desplegable) -->
        <?php if (isLoggedIn()): ?>
            <section class="rating-form-section" id="formularioCalificacion" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $calificacion_usuario ? 'Editar mi reseña' : 'Escribir una reseña'; ?>
                        </h3>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="ocultarFormularioCalificacion()">
                            ✕ Cancelar
                        </button>
                    </div>
                    
                    <form method="POST" action="detalle_contenido.php?id=<?php echo $contenido_id; ?>" id="formCalificacion">
                        <input type="hidden" name="action" value="calificar">
                        
                        <div class="form-group">
                            <label>Tu calificación:</label>
                            <div class="star-rating-input" id="starRating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" id="star<?php echo $i; ?>" name="calificacion" value="<?php echo $i; ?>" 
                                           <?php echo ($calificacion_usuario && $calificacion_usuario['calificacion'] == $i) ? 'checked' : ''; ?>>
                                    <label for="star<?php echo $i; ?>" class="star-input">⭐</label>
                                <?php endfor; ?>
                            </div>
                            <small class="form-text">Haz clic en las estrellas para calificar</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="comentario">Tu comentario (opcional):</label>
                            <textarea id="comentario" 
                                      name="comentario" 
                                      class="form-control" 
                                      rows="4" 
                                      placeholder="Comparte tu opinión sobre este <?php echo $contenido['tipo'] === 'pelicula' ? 'película' : 'serie'; ?>..."><?php echo $calificacion_usuario ? htmlspecialchars($calificacion_usuario['comentario']) : ''; ?></textarea>
                            <small class="form-text">Máximo 500 caracteres</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $calificacion_usuario ? 'Actualizar reseña' : 'Publicar reseña'; ?>
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="ocultarFormularioCalificacion()">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- Reseñas y comentarios -->
        <section class="reviews-section mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💬 Reseñas de usuarios</h3>
                    <div class="reviews-stats">
                        <span><?php echo count($calificaciones); ?> reseñas</span>
                        <?php if (count($calificaciones) > 0): ?>
                            <span>Promedio: <?php echo number_format($contenido['calificacion'], 1); ?>/5</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($calificaciones)): ?>
                    <div class="reviews-list">
                        <?php foreach ($calificaciones as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <strong class="reviewer-name"><?php echo htmlspecialchars($review['usuario_nombre']); ?></strong>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star-small <?php echo $i <= $review['calificacion'] ? 'filled' : ''; ?>">⭐</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="review-date">
                                        <?php echo date('d/m/Y', strtotime($review['fecha_calificacion'])); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($review['comentario'])): ?>
                                    <div class="review-content">
                                        <p><?php echo nl2br(htmlspecialchars($review['comentario'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isLoggedIn()): ?>
                                    <div class="review-actions">
                                        <button class="btn-link" onclick="marcarUtil(<?php echo $review['id']; ?>)">
                                            👍 Útil
                                        </button>
                                        <button class="btn-link" onclick="reportarReview(<?php echo $review['id']; ?>)">
                                            🚩 Reportar
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="reviews-footer text-center">
                        <button class="btn btn-outline" onclick="cargarMasReviews()">
                            Ver más reseñas
                        </button>
                    </div>
                    
                <?php else: ?>
                    <div class="no-reviews text-center">
                        <div class="no-reviews-icon">💭</div>
                        <h4>Aún no hay reseñas</h4>
                        <p>¡Sé el primero en compartir tu opinión!</p>
                        <?php if (isLoggedIn()): ?>
                            <button class="btn btn-primary" onclick="mostrarFormularioCalificacion()">
                                Escribir primera reseña
                            </button>
                        <?php else: ?>
                            <a href="auth/registro_usuario.php" class="btn btn-primary">
                                Regístrate para reseñar
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- CAMBIO 4: Recomendaciones dinámicas (reemplaza related-content actual) -->
        <section class="related-content mb-4" id="relatedContentSection">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🎯 Contenido relacionado</h3>
                    <div class="recommendation-controls">
                        <select id="recommendationType" onchange="cambiarTipoRecomendacion()">
                            <option value="similares">Similares a este <?php echo $contenido['tipo']; ?></option>
                            <option value="genero">Más de <?php echo htmlspecialchars($contenido['genero_nombre']); ?></option>
                            <option value="populares">Populares ahora</option>
                            <option value="trending">En tendencia</option>
                        </select>
                        <button id="refreshRecommendations" class="btn btn-outline btn-sm">
                            🔄 Actualizar
                        </button>
                    </div>
                </div>
                
                <!-- Contenedor dinámico para recomendaciones -->
                <div id="recommendationsContainer" class="related-grid">
                    <!-- Se mantiene el contenido PHP como fallback -->
                    <?php if (!empty($contenido_relacionado)): ?>
                        <?php foreach ($contenido_relacionado as $relacionado): ?>
                            <div class="related-item" onclick="verDetalle(<?php echo $relacionado['id']; ?>)">
                                <img src="uploads/<?php echo $relacionado['poster'] ?: 'no-image.svg'; ?>" 
                                     alt="<?php echo htmlspecialchars($relacionado['titulo']); ?>"
                                     loading="lazy"
                                     onerror="this.src='uploads/no-image.svg'">
                                <div class="related-info">
                                    <h4><?php echo htmlspecialchars($relacionado['titulo']); ?></h4>
                                    <div class="related-meta">
                                        <span class="related-rating">⭐ <?php echo number_format($relacionado['calificacion'], 1); ?></span>
                                        <span class="related-year"><?php echo $relacionado['año_lanzamiento']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="loading-recommendations">
                            <p>🔄 Cargando recomendaciones personalizadas...</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Call to action para usuarios no registrados -->
        <?php if (!isLoggedIn()): ?>
            <section class="cta-section">
                <div class="card text-center">
                    <h3>🌟 ¿Te gustó este contenido?</h3>
                    <p>Regístrate gratis para calificar, comentar y recibir recomendaciones personalizadas</p>
                    <div class="cta-actions">
                        <a href="auth/registro_usuario.php" class="btn btn-primary btn-lg">
                            Crear cuenta gratis
                        </a>
                        <a href="auth/iniciar_sesion.php" class="btn btn-outline btn-lg">
                            Ya tengo cuenta
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- Modal para trailer (si aplicable) -->
    <div id="trailerModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Trailer - <?php echo htmlspecialchars($contenido['titulo']); ?></h3>
                <button type="button" class="close" onclick="cerrarTrailer()">&times;</button>
            </div>
            <div class="modal-body" id="trailerContainer">
                <!-- El trailer se cargará aquí -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones. Proyecto universitario.</p>
        </div>
    </footer>

    <!-- CAMBIO 5: Scripts mejorados e integración con APIs -->
    <script>
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

        // Mostrar/ocultar formulario de calificación
        function mostrarFormularioCalificacion() {
            document.getElementById('formularioCalificacion').style.display = 'block';
            document.getElementById('formularioCalificacion').scrollIntoView({ behavior: 'smooth' });
        }

        function ocultarFormularioCalificacion() {
            document.getElementById('formularioCalificacion').style.display = 'none';
        }

        // Mostrar trailer
        function mostrarTrailer(url) {
            const modal = document.getElementById('trailerModal');
            const container = document.getElementById('trailerContainer');
            
            // Detectar tipo de URL y crear iframe apropiado
            let embedUrl = '';
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                const videoId = url.includes('youtu.be') ? 
                    url.split('/').pop() : 
                    url.split('v=')[1].split('&')[0];
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            } else {
                embedUrl = url;
            }
            
            container.innerHTML = `<iframe src="${embedUrl}" width="100%" height="400" frameborder="0" allowfullscreen></iframe>`;
            modal.style.display = 'block';
        }

        function cerrarTrailer() {
            const modal = document.getElementById('trailerModal');
            const container = document.getElementById('trailerContainer');
            container.innerHTML = '';
            modal.style.display = 'none';
        }

        // Función mejorada de compartir
        function compartir() {
            if (navigator.share) {
                navigator.share({
                    title: window.contenidoActual.titulo,
                    text: `Te recomiendo ver "${window.contenidoActual.titulo}" en CineRecomendaciones`,
                    url: window.location.href
                }).catch(console.error);
            } else {
                // Fallback mejorado: copiar URL al portapapeles
                navigator.clipboard.writeText(window.location.href).then(() => {
                    if (window.CineAPI) {
                        window.CineAPI.showNotification('URL copiada al portapapeles', 'success');
                    } else {
                        alert('URL copiada al portapapeles');
                    }
                }).catch(() => {
                    // Fallback del fallback: prompt
                    prompt('Copiar URL:', window.location.href);
                });
            }
        }

        // Nueva función: reportar contenido
        function reportarContenido() {
            const motivo = prompt('¿Por qué deseas reportar este contenido?\n\n1. Contenido inapropiado\n2. Información incorrecta\n3. Problema técnico\n4. Otro', '');
            if (motivo && motivo.trim()) {
                if (window.CineAPI) {
                    window.CineAPI.showNotification('Reporte enviado. Gracias por tu colaboración.', 'success');
                } else {
                    alert('Reporte registrado: ' + motivo);
                }
                console.log('Reporte:', { contenido_id: window.contenidoActual.id, motivo: motivo.trim() });
            }
        }

        // Función mejorada para cambiar tipo de recomendación
        async function cambiarTipoRecomendacion() {
            const tipo = document.getElementById('recommendationType').value;
            const container = document.getElementById('recommendationsContainer');
            
            // Mostrar loading
            container.innerHTML = `
                <div class="loading-recommendations">
                    <div class="loading-item"></div>
                    <div class="loading-item"></div>
                    <div class="loading-item"></div>
                    <div class="loading-item"></div>
                </div>
            `;
            
            // Solo usar API si está disponible, sino mantener contenido PHP
            if (!window.CineAPI) {
                console.warn('API Client no disponible, manteniendo contenido PHP');
                return;
            }
            
            try {
                const opciones = { 
                    limite: 6,
                    incluirMetadata: true
                };
                
                // Configurar opciones según el tipo
                switch(tipo) {
                    case 'similares':
                        opciones.contenidoId = window.contenidoActual.id;
                        break;
                    case 'genero':
                        opciones.generoId = window.contenidoActual.genero_id;
                        break;
                }
                
                const resultado = await window.CineAPI.obtenerRecomendaciones(tipo, opciones);
                
                if (resultado.success && resultado.data.recomendaciones.length > 0) {
                    mostrarRecomendacionesModernas(resultado.data.recomendaciones);
                } else {
                    container.innerHTML = '<div class="no-recommendations">No se encontraron recomendaciones para este criterio</div>';
                }
                
            } catch (error) {
                console.error('Error cambiando recomendaciones:', error);
                // Fallback: mostrar mensaje de error pero mantener funcionalidad
                container.innerHTML = '<div class="error-recommendations">Error cargando recomendaciones dinámicas. Recarga la página.</div>';
            }
        }

        // Mostrar recomendaciones con el nuevo formato
        function mostrarRecomendacionesModernas(recomendaciones) {
            const container = document.getElementById('recommendationsContainer');
            
            const html = recomendaciones.map(item => `
                <div class="related-item modern-item" onclick="verDetalle(${item.id})" data-content-id="${item.id}">
                    <div class="item-poster-container">
                        <img src="uploads/${item.poster || 'no-image.svg'}" 
                             alt="${item.titulo}"
                             loading="lazy"
                             onerror="this.src='uploads/no-image.svg'">
                        
                        <!-- Overlay con acciones rápidas -->
                        <div class="item-overlay">
                            <button class="quick-favorite" onclick="toggleFavoritoRapido(event, ${item.id})" title="Favorito">
                                🤍
                            </button>
                            ${item.compatibilidad ? `<div class="compatibility-badge">${item.compatibilidad}% Match</div>` : ''}
                        </div>
                    </div>
                    
                    <div class="related-info">
                        <h4 title="${item.titulo}">${item.titulo}</h4>
                        <div class="related-meta">
                            <span class="related-rating">⭐ ${item.calificacion || 'N/A'}</span>
                            <span class="related-year">${item.año || ''}</span>
                            <span class="related-type">${item.tipo || ''}</span>
                        </div>
                        
                        ${item.generos ? `
                            <div class="item-tags">
                                ${item.generos.slice(0, 2).map(genero => `<span class="tag">${genero}</span>`).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = html;
            
            // Agregar animación de entrada
            container.querySelectorAll('.related-item').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in-up');
            });
        }

        // Nueva función: verDetalle con historial
        function verDetalle(contenidoId) {
            // Guardar en historial antes de navegar
            if (window.CineAPI && window.usuarioLogueado) {
                window.CineAPI.guardarHistorial(contenidoId);
            }
            window.location.href = `detalle_contenido.php?id=${contenidoId}`;
        }

        // Toggle favorito rápido desde recomendaciones
        async function toggleFavoritoRapido(event, contenidoId) {
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
            
            const boton = event.target;
            const originalContent = boton.textContent;
            
            boton.textContent = '⏳';
            boton.disabled = true;
            
            try {
                if (window.CineAPI) {
                    const resultado = await window.CineAPI.toggleFavorito(contenidoId);
                    
                    if (resultado.success) {
                        const esFavorito = resultado.data.accion === 'agregado';
                        boton.textContent = esFavorito ? '❤️' : '🤍';
                        
                        // Animación de éxito
                        boton.style.transform = 'scale(1.3)';
                        setTimeout(() => {
                            boton.style.transform = 'scale(1)';
                        }, 200);
                    } else {
                        boton.textContent = originalContent;
                    }
                } else {
                    alert('Funcionalidad de favoritos - ID: ' + contenidoId);
                    boton.textContent = originalContent;
                }
                
            } catch (error) {
                console.error('Error en favorito rápido:', error);
                boton.textContent = originalContent;
            } finally {
                boton.disabled = false;
            }
        }

        // Agregar a lista personal
        function agregarALista(id) {
            // Implementar funcionalidad de lista personal
            alert('Funcionalidad de lista personal - ID: ' + id);
        }

        // Marcar reseña como útil
        function marcarUtil(reviewId) {
            // Implementar funcionalidad de voto útil
            alert('Marcar como útil - Review ID: ' + reviewId);
        }

        // Reportar reseña
        function reportarReview(reviewId) {
            if (confirm('¿Estás seguro de que quieres reportar esta reseña?')) {
                alert('Reseña reportada - Review ID: ' + reviewId);
            }
        }

        // Cargar más reseñas
        function cargarMasReviews() {
            // Implementar paginación de reseñas
            alert('Cargar más reseñas');
        }

        // Inicializar sistema de favoritos
        async function inicializarSistemaFavoritos() {
            const container = document.getElementById('favoritesContainer');
            const boton = document.getElementById('favoriteBtn');
            
            if (!container || !boton) return;
            
            try {
                // Verificar si ya es favorito
                const resultado = await window.CineAPI.verificarFavorito(window.contenidoActual.id);
                
                if (resultado.success) {
                    const esFavorito = resultado.data.es_favorito;
                    const icono = document.getElementById('favoriteIcon');
                    const texto = document.getElementById('favoriteText');
                    
                    icono.textContent = esFavorito ? '❤️' : '🤍';
                    texto.textContent = esFavorito ? 'En favoritos' : 'Agregar a favoritos';
                    
                    boton.style.display = 'inline-flex';
                    
                    // Configurar evento click
                    boton.addEventListener('click', async () => {
                        await toggleFavoritoPrincipal();
                    });
                }
            } catch (error) {
                console.warn('Error inicializando favoritos:', error);
                // Ocultar botón si hay error
                container.style.display = 'none';
            }
        }

        // Toggle favorito principal (botón grande)
        async function toggleFavoritoPrincipal() {
            const boton = document.getElementById('favoriteBtn');
            const icono = document.getElementById('favoriteIcon');
            const texto = document.getElementById('favoriteText');
            
            const originalIcon = icono.textContent;
            const originalText = texto.textContent;
            
            // Estado de carga
            boton.disabled = true;
            icono.textContent = '⏳';
            texto.textContent = 'Procesando...';
            
            try {
                const resultado = await window.CineAPI.toggleFavorito(window.contenidoActual.id);
                
                if (resultado.success) {
                    const esFavorito = resultado.data.accion === 'agregado';
                    
                    // Animación de cambio
                    icono.textContent = esFavorito ? '❤️' : '🤍';
                    texto.textContent = esFavorito ? 'En favoritos' : 'Agregar a favoritos';
                    
                    // Efecto visual
                    boton.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        boton.style.transform = 'scale(1)';
                    }, 200);
                } else {
                    // Restaurar estado original
                    icono.textContent = originalIcon;
                    texto.textContent = originalText;
                }
                
            } catch (error) {
                console.error('Error en toggle favorito:', error);
                icono.textContent = originalIcon;
                texto.textContent = originalText;
            } finally {
                boton.disabled = false;
            }
        }

        // Configuración inicial mejorada
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎬 detalle_contenido.php con APIs integradas');
            
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Configurar rating de estrellas interactivo
            const starInputs = document.querySelectorAll('.star-rating-input input');
            const starLabels = document.querySelectorAll('.star-rating-input label');
            
            starLabels.forEach((label, index) => {
                label.addEventListener('mouseenter', () => {
                    // Highlight estrellas al pasar el mouse
                    for (let i = 0; i <= index; i++) {
                        starLabels[i].classList.add('hover');
                    }
                });
                
                label.addEventListener('mouseleave', () => {
                    // Quitar highlight
                    starLabels.forEach(l => l.classList.remove('hover'));
                });
                
                label.addEventListener('click', () => {
                    // Actualizar selección visual
                    starLabels.forEach(l => l.classList.remove('selected'));
                    for (let i = 0; i <= index; i++) {
                        starLabels[i].classList.add('selected');
                    }
                });
            });

            // Cerrar modal al hacer click fuera
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('trailerModal');
                if (event.target === modal) {
                    cerrarTrailer();
                }
            });

            // Configurar el refresh de recomendaciones
            const refreshBtn = document.getElementById('refreshRecommendations');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', async () => {
                    refreshBtn.disabled = true;
                    refreshBtn.textContent = '⏳ Cargando...';
                    
                    try {
                        await cambiarTipoRecomendacion();
                        if (window.CineAPI) {
                            window.CineAPI.showNotification('Recomendaciones actualizadas', 'success');
                        }
                    } catch (error) {
                        if (window.CineAPI) {
                            window.CineAPI.showNotification('Error actualizando recomendaciones', 'error');
                        }
                    } finally {
                        refreshBtn.disabled = false;
                        refreshBtn.textContent = '🔄 Actualizar';
                    }
                });
            }

            // Inicializar sistema de favoritos si está disponible
            setTimeout(() => {
                if (window.CineAPI && window.usuarioLogueado) {
                    inicializarSistemaFavoritos();
                }
            }, 500);
            
            // Precargar recomendaciones dinámicas después de 1 segundo
            setTimeout(() => {
                if (window.CineAPI) {
                    cambiarTipoRecomendacion();
                }
            }, 1000);
            
            // Guardar visita en historial
            if (window.CineAPI && window.usuarioLogueado) {
                window.CineAPI.guardarHistorial(window.contenidoActual.id);
            }

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.card, .content-hero, .related-item');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>

    <!-- Integración con APIs REST -->
    <script src="js/api-client.js"></script>

    <!-- Estilos adicionales para las nuevas funcionalidades -->
    <style>
        /* ===== ESTILOS PARA FUNCIONALIDADES NUEVAS ===== */

        /* Botón de favoritos */
        .favorite-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .favorite-button:hover {
            transform: translateY(-2px);
        }

        /* Controles de recomendaciones */
        .recommendation-controls {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .recommendation-controls select {
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: white;
            min-width: 200px;
        }

        /* Items modernos de recomendaciones */
        .modern-item {
            position: relative;
            transition: all 0.3s ease;
        }

        .modern-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .item-poster-container {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }

        .item-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modern-item:hover .item-overlay {
            opacity: 1;
        }

        .quick-favorite {
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .quick-favorite:hover {
            background: white;
            transform: scale(1.1);
        }

        .compatibility-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .item-tags {
            display: flex;
            gap: 0.25rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .tag {
            background: #e9ecef;
            color: #495057;
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Loading items */
        .loading-recommendations {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .loading-item {
            height: 200px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Animación fade in up */
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

        /* Estados de mensaje */
        .no-recommendations, .error-recommendations {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }

        .error-recommendations {
            color: #dc3545;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .recommendation-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .recommendation-controls select {
                min-width: unset;
                width: 100%;
            }
            
            .item-overlay {
                opacity: 1; /* Siempre visible en móvil */
                background: rgba(0,0,0,0.3);
            }
            
            .quick-favorite {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }
        }

        /* Tema oscuro */
        body.dark-theme .recommendation-controls select {
            background: #2d3748;
            color: white;
            border-color: #4a5568;
        }

        body.dark-theme .tag {
            background: #4a5568;
            color: #e2e8f0;
        }

        body.dark-theme .loading-item {
            background: linear-gradient(90deg, #2d3748 25%, #4a5568 50%, #2d3748 75%);
            background-size: 200% 100%;
        }
    </style>

</body>
</html>