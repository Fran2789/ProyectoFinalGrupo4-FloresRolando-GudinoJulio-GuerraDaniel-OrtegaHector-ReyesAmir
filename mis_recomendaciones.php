<?php
require_once 'config/database.php';
require_once 'classes/ContenidoManager.php';
require_once 'classes/UsuarioManager.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect('auth/iniciar_sesion.php?redirect=' . urlencode('mis_recomendaciones.php'));
}

// CAMBIO 1: Verificar si es una petición AJAX para respuestas JSON
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

$contenidoManager = new ContenidoManager();
$usuarioManager = new UsuarioManager();

// Verificar sesión
$usuarioManager->verificarSesion();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'actualizar_preferencias') {
        $generos_seleccionados = isset($_POST['generos']) ? $_POST['generos'] : [];
        
        $resultado = $usuarioManager->guardarPreferencias($_SESSION['user_id'], $generos_seleccionados);
        $mensaje = $resultado['message'];
        $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
        
        // Si es AJAX, responder con JSON
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $resultado['success'],
                'message' => $resultado['message']
            ]);
            exit();
        }
        
        // Refrescar la página para mostrar nuevas recomendaciones
        if ($resultado['success']) {
            header("Location: mis_recomendaciones.php?updated=1");
            exit();
        }
    }
}

// Mostrar mensaje de actualización exitosa
if (isset($_GET['updated'])) {
    $mensaje = 'Preferencias actualizadas. Las recomendaciones se han actualizado.';
    $tipo_mensaje = 'success';
}

// Obtener datos del usuario
$usuario = $usuarioManager->obtenerUsuario($_SESSION['user_id']);
$estadisticas = $usuarioManager->obtenerEstadisticasUsuario($_SESSION['user_id']);
$preferencias = $usuarioManager->obtenerPreferencias($_SESSION['user_id']);
$historial_reciente = $contenidoManager->obtenerHistorialUsuario($_SESSION['user_id'], 10);

// Obtener todos los géneros para el formulario
$generos = $contenidoManager->obtenerGeneros();

// Obtener recomendaciones personalizadas
$recomendaciones_principales = $contenidoManager->obtenerRecomendacionesPersonalizadas($_SESSION['user_id'], 12);

// Obtener recomendaciones por categorías específicas
$recomendaciones_por_categoria = [];

// Si tiene preferencias, obtener más recomendaciones por género
if (!empty($preferencias)) {
    foreach (array_slice($preferencias, 0, 3) as $pref) { // Solo los primeros 3 géneros
        $contenido_genero = $contenidoManager->buscarContenido('', $pref['genero_id'], '', 6);
        if (!empty($contenido_genero)) {
            $recomendaciones_por_categoria[$pref['nombre']] = $contenido_genero;
        }
    }
}

// Recomendaciones basadas en contenido similar (mismo género que el último visto)
$recomendaciones_similares = [];
if (!empty($historial_reciente)) {
    $ultimo_visto = $historial_reciente[0];
    $similares = $contenidoManager->obtenerContenidoRelacionado($ultimo_visto['id'], 6);
    if (!empty($similares)) {
        $recomendaciones_similares = $similares;
    }
}

// Obtener contenido trending (mejor calificado recientemente)
$trending = $contenidoManager->obtenerContenidoDestacado(null, 8);

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

/**
 * Función para calcular porcentaje de compatibilidad
 */
function calcularCompatibilidad($contenido, $preferencias_usuario) {
    if (empty($preferencias_usuario)) return rand(60, 85);
    
    $generos_usuario = array_column($preferencias_usuario, 'genero_id');
    
    if (in_array($contenido['genero_id'], $generos_usuario)) {
        return rand(85, 98);
    } else {
        return rand(50, 75);
    }
}

/**
 * Función helper para formatear duración de tiempo
 */
function tiempoTranscurrido($fecha) {
    $tiempo = time() - strtotime($fecha);
    
    if ($tiempo < 60) {
        return 'hace un momento';
    } elseif ($tiempo < 3600) {
        $minutos = floor($tiempo / 60);
        return "hace {$minutos} minuto" . ($minutos > 1 ? 's' : '');
    } elseif ($tiempo < 86400) {
        $horas = floor($tiempo / 3600);
        return "hace {$horas} hora" . ($horas > 1 ? 's' : '');
    } else {
        $dias = floor($tiempo / 86400);
        return "hace {$dias} día" . ($dias > 1 ? 's' : '');
    }
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Recomendaciones - CineRecomendaciones</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    <meta name="description" content="Descubre películas y series personalizadas según tus gustos">

    <!-- CAMBIO 2: Variables JavaScript para integración con APIs -->
    <script>
        // Variables esenciales para las APIs
        window.usuarioLogueado = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
        window.proyectoBase = '/PHP/proyecto_recomendaciones';
        
        window.usuarioId = <?php echo $_SESSION['user_id']; ?>;
        window.usuarioNombre = '<?php echo addslashes($_SESSION['user_name']); ?>';
        window.usuarioRol = '<?php echo $_SESSION['role'] ?? 'usuario'; ?>';
        
        // Configuración de recomendaciones
        window.recomendacionesConfig = {
            tipoActual: 'personalizadas',
            preferenciasUsuario: <?php echo json_encode($preferencias); ?>,
            estadisticasUsuario: <?php echo json_encode($estadisticas); ?>,
            historialReciente: <?php echo json_encode($historial_reciente); ?>,
            generos: <?php echo json_encode($generos); ?>
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
                <li><a href="mis_recomendaciones.php" class="active">Mis Recomendaciones</a></li>
                <li><a href="perfil_usuario.php">Mi Perfil</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin/panel_admin.php">Administración</a></li>
                <?php endif; ?>
            </ul>
            
            <div class="user-menu">
                <button class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                    🌙
                </button>
                <span class="user-welcome">¡Hola, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="auth/cerrar_sesion.php" class="btn btn-outline">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <main class="container">
        
        <!-- Header personalizado -->
        <section class="recommendations-header mb-4">
            <div class="header-content">
                <h1 class="page-title">🎯 Mis Recomendaciones</h1>
                <p class="page-subtitle">Contenido seleccionado especialmente para ti</p>
            </div>
            
            <div class="user-stats">
                <div class="stat-bubble">
                    <span class="stat-number" id="statVisto"><?php echo $estadisticas['contenido_visto']; ?></span>
                    <span class="stat-label">Visto</span>
                </div>
                <div class="stat-bubble">
                    <span class="stat-number" id="statReseñas"><?php echo $estadisticas['calificaciones_dadas']; ?></span>
                    <span class="stat-label">Reseñas</span>
                </div>
                <div class="stat-bubble">
                    <span class="stat-number" id="statGeneros"><?php echo count($preferencias); ?></span>
                    <span class="stat-label">Géneros</span>
                </div>
            </div>
        </section>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> mb-4" id="messageAlert">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- CAMBIO 3: Panel de control de preferencias mejorado -->
        <section class="preferences-panel mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🎭 Ajustar Mis Preferencias</h3>
                    <div class="preferences-controls">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="togglePreferencesForm()">
                            ⚙️ Editar
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="recomendacionesInteligentes()" title="Recomendaciones basadas en tu actividad">
                            🧠 IA Sugiere
                        </button>
                    </div>
                </div>
                
                <!-- Mostrar preferencias actuales -->
                <div class="current-preferences" id="currentPreferences">
                    <?php if (!empty($preferencias)): ?>
                        <p><strong>Tus géneros favoritos:</strong></p>
                        <div class="preferences-display" id="preferencesDisplay">
                            <?php foreach ($preferencias as $pref): ?>
                                <span class="preference-tag">
                                    <?php echo obtenerIconoGenero($pref['nombre']); ?>
                                    <?php echo htmlspecialchars($pref['nombre']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- NUEVO: Estadísticas de preferencias -->
                        <div class="preferences-stats">
                            <small class="text-muted">
                                📊 Basado en <?php echo $estadisticas['contenido_visto']; ?> contenidos vistos y <?php echo $estadisticas['calificaciones_dadas']; ?> reseñas
                            </small>
                        </div>
                    <?php else: ?>
                        <div class="info-banner">
                            <div class="info-icon">💡</div>
                            <div class="info-content">
                                <strong>¡Personaliza tus recomendaciones!</strong>
                                Selecciona tus géneros favoritos para recibir sugerencias más precisas.
                                <a href="#" onclick="togglePreferencesForm()" class="info-link">Configurar ahora</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulario de preferencias (oculto por defecto) -->
                <div id="preferencesForm" style="display: none;">
                    <form method="POST" action="mis_recomendaciones.php" id="formPreferencias">
                        <input type="hidden" name="action" value="actualizar_preferencias">
                        
                        <div class="form-group">
                            <label>Selecciona tus géneros favoritos:</label>
                            <div class="preferences-grid" id="preferencesGrid">
                                <?php 
                                $preferencias_ids = array_column($preferencias, 'genero_id');
                                foreach ($generos as $genero): 
                                ?>
                                    <div class="preference-item">
                                        <input type="checkbox" 
                                               id="genero_<?php echo $genero['id']; ?>" 
                                               name="generos[]" 
                                               value="<?php echo $genero['id']; ?>"
                                               <?php echo in_array($genero['id'], $preferencias_ids) ? 'checked' : ''; ?>>
                                        <label for="genero_<?php echo $genero['id']; ?>" class="preference-label">
                                            <span class="preference-icon"><?php echo obtenerIconoGenero($genero['nombre']); ?></span>
                                            <span class="preference-name"><?php echo htmlspecialchars($genero['nombre']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="btnActualizarPreferencias">
                                Actualizar Preferencias
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="togglePreferencesForm()">
                                Cancelar
                            </button>
                            <button type="button" class="btn btn-outline" onclick="aplicarPreferenciasIA()">
                                🧠 Aplicar sugerencias IA
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- CAMBIO 4: Controles de recomendaciones dinámicas -->
        <section class="recommendations-controls mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="controls-header">
                        <h4>🎯 Tipo de Recomendaciones</h4>
                        <div class="controls-actions">
                            <button type="button" class="btn btn-outline btn-sm" onclick="sorprendeme()" title="Contenido aleatorio de calidad">
                                🎲 Sorpréndeme
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="refrescarRecomendacionesInteligente()" id="btnRefresh">
                                🔄 Refrescar
                            </button>
                        </div>
                    </div>
                    
                    <div class="recommendation-types">
                        <button type="button" class="rec-type-btn active" data-type="personalizadas" onclick="cambiarTipoRecomendacion('personalizadas')">
                            🌟 Para Ti
                        </button>
                        <button type="button" class="rec-type-btn" data-type="similares" onclick="cambiarTipoRecomendacion('similares')">
                            🔄 Similares
                        </button>
                        <button type="button" class="rec-type-btn" data-type="trending" onclick="cambiarTipoRecomendacion('trending')">
                            🔥 Trending
                        </button>
                        <button type="button" class="rec-type-btn" data-type="genero" onclick="cambiarTipoRecomendacion('genero')">
                            🎭 Por Género
                        </button>
                        <button type="button" class="rec-type-btn" data-type="nuevos" onclick="cambiarTipoRecomendacion('nuevos')">
                            🆕 Recientes
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- CAMBIO 5: Recomendaciones principales dinámicas -->
        <section class="main-recommendations mb-4" id="mainRecommendationsSection">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="recommendationsTitle">
                        🌟 Recomendado Para Ti
                    </h3>
                    <p id="recommendationsSubtitle">Basado en tus preferencias y actividad reciente</p>
                </div>
                
                <!-- Indicador de carga -->
                <div id="recommendationsLoading" class="recommendations-loading" style="display: none;">
                    <div class="loading-content">
                        <div class="loading-spinner"></div>
                        <p>🎬 Preparando recomendaciones personalizadas...</p>
                    </div>
                </div>
                
                <!-- Grid de recomendaciones -->
                <div class="recommendations-grid" id="recommendationsGrid">
                    <?php if (!empty($recomendaciones_principales)): ?>
                        <?php foreach ($recomendaciones_principales as $item): ?>
                            <div class="recommendation-item fade-in" data-content-id="<?php echo $item['id']; ?>" onclick="verDetalle(<?php echo $item['id']; ?>)">
                                <div class="item-poster">
                                    <img src="uploads/<?php echo $item['poster'] ?: 'no-image.svg'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['titulo']); ?>"
                                         loading="lazy"
                                         onerror="this.src='uploads/no-image.svg'">
                                    
                                    <div class="recommendation-badge">
                                        <?php echo calcularCompatibilidad($item, $preferencias); ?>% Match
                                    </div>
                                    
                                    <!-- NUEVO: Botón de favoritos -->
                                    <button class="favorite-btn-recommendation" 
                                            data-content-id="<?php echo $item['id']; ?>"
                                            onclick="toggleFavoritoRecomendacion(event, <?php echo $item['id']; ?>)"
                                            title="Agregar a favoritos">
                                        🤍
                                    </button>
                                    
                                    <div class="item-overlay">
                                        <div class="overlay-content">
                                            <h4><?php echo htmlspecialchars($item['titulo']); ?></h4>
                                            <p class="item-description">
                                                <?php echo htmlspecialchars(substr($item['descripcion'], 0, 100)) . '...'; ?>
                                            </p>
                                            <div class="overlay-actions">
                                                <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); verDetalle(<?php echo $item['id']; ?>)">
                                                    Ver detalles
                                                </button>
                                                <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); vistaRapidaRecomendacion(<?php echo $item['id']; ?>)">
                                                    👁️ Vista rápida
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="item-info">
                                    <h3 class="item-title"><?php echo htmlspecialchars($item['titulo']); ?></h3>
                                    <div class="item-meta">
                                        <span class="item-genre"><?php echo htmlspecialchars($item['genero_nombre']); ?></span>
                                        <span class="item-year"><?php echo $item['año_lanzamiento']; ?></span>
                                    </div>
                                    <div class="item-rating">
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo $i <= round($item['calificacion']) ? 'filled' : ''; ?>">⭐</span>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-number"><?php echo number_format($item['calificacion'], 1); ?></span>
                                    </div>
                                    <div class="item-type">
                                        <span class="type-badge type-<?php echo $item['tipo']; ?>">
                                            <?php echo $item['tipo'] === 'pelicula' ? '🎬 Película' : '📺 Serie'; ?>
                                        </span>
                                    </div>
                                    <div class="match-score">
                                        <span class="match-percentage" title="Porcentaje de compatibilidad basado en tus gustos">
                                            <?php echo calcularCompatibilidad($item, $preferencias); ?>% Match
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-recommendations-message">
                            <p>🎯 Configura tus preferencias para recibir recomendaciones personalizadas</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="recommendations-actions" id="recommendationsActions">
                    <a href="catalogo.php" class="btn btn-primary">
                        Ver Más en el Catálogo
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="refrescarRecomendacionesInteligente()">
                        🔄 Refrescar Sugerencias
                    </button>
                    <button type="button" class="btn btn-outline" onclick="exportarRecomendaciones()" title="Guardar recomendaciones">
                        📄 Exportar Lista
                    </button>
                </div>
            </div>
        </section>

        <!-- Recomendaciones por categoría (mantenidas para fallback) -->
        <?php if (!empty($recomendaciones_por_categoria)): ?>
            <div id="categoryRecommendations">
                <?php foreach ($recomendaciones_por_categoria as $genero_nombre => $contenido_genero): ?>
                    <section class="category-recommendations mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php echo obtenerIconoGenero($genero_nombre); ?> 
                                    Más de <?php echo htmlspecialchars($genero_nombre); ?>
                                </h3>
                                <a href="catalogo.php?genero=<?php echo array_search($genero_nombre, array_column($generos, 'nombre')); ?>" 
                                   class="btn btn-outline btn-sm">
                                    Ver todos
                                </a>
                            </div>
                            
                            <div class="recommendations-grid">
                                <?php foreach (array_slice($contenido_genero, 0, 6) as $item): ?>
                                    <div class="recommendation-item fade-in" data-content-id="<?php echo $item['id']; ?>" onclick="verDetalle(<?php echo $item['id']; ?>)">
                                        <div class="item-poster">
                                            <img src="uploads/<?php echo $item['poster'] ?: 'no-image.svg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['titulo']); ?>"
                                                 loading="lazy"
                                                 onerror="this.src='uploads/no-image.svg'">
                                            
                                            <!-- Botón de favoritos -->
                                            <button class="favorite-btn-recommendation" 
                                                    data-content-id="<?php echo $item['id']; ?>"
                                                    onclick="toggleFavoritoRecomendacion(event, <?php echo $item['id']; ?>)"
                                                    title="Agregar a favoritos">
                                                🤍
                                            </button>
                                            
                                            <div class="item-overlay">
                                                <div class="overlay-content">
                                                    <h4><?php echo htmlspecialchars($item['titulo']); ?></h4>
                                                    <div class="overlay-actions">
                                                        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); verDetalle(<?php echo $item['id']; ?>)">
                                                            Ver
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="item-info">
                                            <h3 class="item-title"><?php echo htmlspecialchars($item['titulo']); ?></h3>
                                            <div class="item-rating">
                                                <span class="rating-number">⭐ <?php echo number_format($item['calificacion'], 1); ?></span>
                                                <span class="item-year"><?php echo $item['año_lanzamiento']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Sección de historial reciente (mejorada) -->
        <?php if (!empty($recomendaciones_similares) && !empty($historial_reciente)): ?>
            <section class="similar-recommendations mb-4" id="similarRecommendations">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            🔄 Porque viste "<?php echo htmlspecialchars($historial_reciente[0]['titulo']); ?>"
                        </h3>
                        <small class="text-muted">
                            Visto <?php echo tiempoTranscurrido($historial_reciente[0]['fecha_visita']); ?>
                        </small>
                    </div>
                    
                    <div class="recommendations-grid">
                        <?php foreach ($recomendaciones_similares as $item): ?>
                            <div class="recommendation-item fade-in" data-content-id="<?php echo $item['id']; ?>" onclick="verDetalle(<?php echo $item['id']; ?>)">
                                <div class="item-poster">
                                    <img src="uploads/<?php echo $item['poster'] ?: 'no-image.svg'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['titulo']); ?>"
                                         loading="lazy"
                                         onerror="this.src='uploads/no-image.svg'">
                                    
                                    <!-- Botón de favoritos -->
                                    <button class="favorite-btn-recommendation" 
                                            data-content-id="<?php echo $item['id']; ?>"
                                            onclick="toggleFavoritoRecomendacion(event, <?php echo $item['id']; ?>)"
                                            title="Agregar a favoritos">
                                        🤍
                                    </button>
                                </div>
                                
                                <div class="item-info">
                                    <h3 class="item-title"><?php echo htmlspecialchars($item['titulo']); ?></h3>
                                    <div class="item-rating">
                                        <span class="rating-number">⭐ <?php echo number_format($item['calificacion'], 1); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Sin recomendaciones -->
        <?php if (empty($recomendaciones_principales) && empty($recomendaciones_por_categoria)): ?>
            <section class="no-recommendations" id="noRecommendations">
                <div class="card text-center">
                    <div class="empty-state">
                        <div class="empty-icon">🎯</div>
                        <h3>¡Ayúdanos a conocerte mejor!</h3>
                        <p>Para darte las mejores recomendaciones, necesitamos saber qué te gusta.</p>
                        
                        <div class="suggestions">
                            <h4>Te sugerimos:</h4>
                            <div class="reasons-list">
                                <ul>
                                    <li>✅ Configura tus géneros favoritos arriba</li>
                                    <li>🎬 Explora el catálogo y califica contenido</li>
                                    <li>⭐ Deja reseñas en películas que has visto</li>
                                    <li>📚 Ve más contenido para crear tu historial</li>
                                </ul>
                            </div>
                            
                            <div class="suggestion-actions">
                                <button type="button" class="btn btn-primary" onclick="togglePreferencesForm()">
                                    🎭 Configurar Preferencias
                                </button>
                                <a href="catalogo.php" class="btn btn-secondary">
                                    🔍 Explorar Catálogo
                                </a>
                                <button type="button" class="btn btn-outline" onclick="recomendacionesInteligentes()">
                                    🧠 Sugerencias IA
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Estadísticas personales (mejorada) -->
        <section class="user-stats-detailed mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📊 Tu Actividad</h3>
                    <button type="button" class="btn btn-outline btn-sm" onclick="actualizarEstadisticas()">
                        🔄 Actualizar
                    </button>
                </div>
                
                <div class="stats-grid" id="statsGrid">
                    <div class="stat-item">
                        <div class="stat-icon">👁️</div>
                        <div class="stat-info">
                            <div class="stat-value" id="statDetalleVisto"><?php echo $estadisticas['contenido_visto']; ?></div>
                            <div class="stat-label">Contenido Visto</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-info">
                            <div class="stat-value" id="statDetalleReseñas"><?php echo $estadisticas['calificaciones_dadas']; ?></div>
                            <div class="stat-label">Reseñas Escritas</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <div class="stat-value" id="statDetallePromedio"><?php echo $estadisticas['calificacion_promedio']; ?></div>
                            <div class="stat-label">Tu Calificación Promedio</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">🎭</div>
                        <div class="stat-info">
                            <div class="stat-value" id="statDetalleFavorito"><?php echo htmlspecialchars($estadisticas['genero_favorito']); ?></div>
                            <div class="stat-label">Género Favorito</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Modal de vista rápida -->
    <div id="quickViewModal" class="modal" style="display: none;">
        <div class="modal-content quick-view-content">
            <div class="modal-header">
                <h3 id="quickViewTitle">Vista Rápida</h3>
                <button type="button" class="close" onclick="cerrarVistaRapida()">&times;</button>
            </div>
            <div class="modal-body" id="quickViewBody">
                <!-- Contenido dinámico -->
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
        // Variables globales para recomendaciones
        let tipoRecomendacionActual = 'personalizadas';
        let favoritosCache = new Set();
        let recomendacionesActuales = [];

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

        // NUEVA: Función para ver detalle con historial
        function verDetalle(id) {
            // Guardar en historial usando las APIs
            if (window.CineAPI) {
                window.CineAPI.guardarHistorial(id);
            } else {
                // Fallback tradicional
                fetch('api/guardar_historial.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        contenido_id: id,
                        usuario_id: window.usuarioId
                    })
                }).catch(console.warn);
            }
            
            window.location.href = `detalle_contenido.php?id=${id}`;
        }

        // NUEVA: Cambiar tipo de recomendación dinámicamente
        async function cambiarTipoRecomendacion(tipo) {
            // Actualizar botones activos
            document.querySelectorAll('.rec-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-type="${tipo}"]`).classList.add('active');
            
            tipoRecomendacionActual = tipo;
            
            // Mostrar loading
            mostrarLoadingRecomendaciones();
            
            // Actualizar título
            actualizarTituloRecomendaciones(tipo);
            
            // Solo usar API si está disponible
            if (!window.CineAPI) {
                console.warn('API no disponible, manteniendo contenido PHP');
                ocultarLoadingRecomendaciones();
                return;
            }
            
            try {
                const opciones = {
                    limite: 12,
                    incluirMetadata: true
                };
                
                // Configurar opciones según el tipo
                switch(tipo) {
                    case 'similares':
                        if (window.recomendacionesConfig.historialReciente.length > 0) {
                            opciones.contenidoId = window.recomendacionesConfig.historialReciente[0].id;
                        }
                        break;
                    case 'genero':
                        if (window.recomendacionesConfig.preferenciasUsuario.length > 0) {
                            opciones.generoId = window.recomendacionesConfig.preferenciasUsuario[0].genero_id;
                        }
                        break;
                }
                
                const resultado = await window.CineAPI.obtenerRecomendaciones(tipo, opciones);
                
                if (resultado.success && resultado.data.recomendaciones.length > 0) {
                    mostrarRecomendacionesDinamicas(resultado.data.recomendaciones);
                    recomendacionesActuales = resultado.data.recomendaciones;
                    
                    // Cargar estados de favoritos
                    setTimeout(() => cargarEstadosFavoritosRecomendaciones(), 500);
                    
                    window.CineAPI.showNotification(`Recomendaciones ${tipo} actualizadas`, 'success');
                } else {
                    mostrarNoRecomendaciones(tipo);
                }
                
            } catch (error) {
                console.error('Error cambiando recomendaciones:', error);
                mostrarErrorRecomendaciones();
            } finally {
                ocultarLoadingRecomendaciones();
            }
        }

        // Mostrar loading de recomendaciones
        function mostrarLoadingRecomendaciones() {
            const loading = document.getElementById('recommendationsLoading');
            const grid = document.getElementById('recommendationsGrid');
            
            loading.style.display = 'block';
            grid.style.opacity = '0.5';
        }

        // Ocultar loading de recomendaciones
        function ocultarLoadingRecomendaciones() {
            const loading = document.getElementById('recommendationsLoading');
            const grid = document.getElementById('recommendationsGrid');
            
            loading.style.display = 'none';
            grid.style.opacity = '1';
        }

        // Actualizar título según tipo de recomendación
        function actualizarTituloRecomendaciones(tipo) {
            const titulo = document.getElementById('recommendationsTitle');
            const subtitulo = document.getElementById('recommendationsSubtitle');
            
            const tipos = {
                'personalizadas': {
                    titulo: '🌟 Recomendado Para Ti',
                    subtitulo: 'Basado en tus preferencias y actividad reciente'
                },
                'similares': {
                    titulo: '🔄 Contenido Similar',
                    subtitulo: 'Basado en tu último contenido visto'
                },
                'trending': {
                    titulo: '🔥 Trending Ahora',
                    subtitulo: 'Lo más popular y mejor calificado'
                },
                'genero': {
                    titulo: '🎭 Por Género Favorito',
                    subtitulo: 'Contenido de tus géneros preferidos'
                },
                'nuevos': {
                    titulo: '🆕 Recién Agregados',
                    subtitulo: 'Las últimas incorporaciones al catálogo'
                }
            };
            
            titulo.textContent = tipos[tipo]?.titulo || '🎬 Recomendaciones';
            subtitulo.textContent = tipos[tipo]?.subtitulo || 'Contenido seleccionado para ti';
        }

        // Mostrar recomendaciones dinámicamente
        function mostrarRecomendacionesDinamicas(recomendaciones) {
            const grid = document.getElementById('recommendationsGrid');
            
            const html = recomendaciones.map(item => generarItemRecomendacion(item)).join('');
            grid.innerHTML = html;
            
            // Animaciones de entrada
            setTimeout(() => {
                grid.querySelectorAll('.recommendation-item').forEach((item, index) => {
                    item.style.animationDelay = `${index * 0.1}s`;
                    item.classList.add('fade-in-up');
                });
            }, 50);
        }

        // Generar HTML para item de recomendación
        function generarItemRecomendacion(item) {
            const compatibilidad = calcularCompatibilidadJS(item);
            
            return `
                <div class="recommendation-item" data-content-id="${item.id}" onclick="verDetalle(${item.id})">
                    <div class="item-poster">
                        <img src="uploads/${item.poster || 'no-image.svg'}" 
                             alt="${item.titulo}"
                             loading="lazy"
                             onerror="this.src='uploads/no-image.svg'">
                        
                        <div class="recommendation-badge">
                            ${compatibilidad}% Match
                        </div>
                        
                        <button class="favorite-btn-recommendation" 
                                data-content-id="${item.id}"
                                onclick="toggleFavoritoRecomendacion(event, ${item.id})"
                                title="Agregar a favoritos">
                            🤍
                        </button>
                        
                        <div class="item-overlay">
                            <div class="overlay-content">
                                <h4>${item.titulo}</h4>
                                <p class="item-description">
                                    ${item.descripcion ? item.descripcion.substring(0, 100) + '...' : 'Sin descripción'}
                                </p>
                                <div class="overlay-actions">
                                    <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); verDetalle(${item.id})">
                                        Ver detalles
                                    </button>
                                    <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); vistaRapidaRecomendacion(${item.id})">
                                        👁️ Vista rápida
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="item-info">
                        <h3 class="item-title">${item.titulo}</h3>
                        <div class="item-meta">
                            <span class="item-genre">${item.genero_nombre || 'Sin género'}</span>
                            <span class="item-year">${item.año_lanzamiento || 'N/A'}</span>
                        </div>
                        <div class="item-rating">
                            <div class="stars">
                                ${[1,2,3,4,5].map(i => `<span class="star ${i <= Math.round(item.calificacion || 0) ? 'filled' : ''}">⭐</span>`).join('')}
                            </div>
                            <span class="rating-number">${(item.calificacion || 0).toFixed(1)}</span>
                        </div>
                        <div class="item-type">
                            <span class="type-badge type-${item.tipo}">
                                ${item.tipo === 'pelicula' ? '🎬 Película' : '📺 Serie'}
                            </span>
                        </div>
                        <div class="match-score">
                            <span class="match-percentage" title="Porcentaje de compatibilidad basado en tus gustos">
                                ${compatibilidad}% Match
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Calcular compatibilidad en JavaScript
        function calcularCompatibilidadJS(item) {
            const preferencias = window.recomendacionesConfig.preferenciasUsuario;
            
            if (!preferencias || preferencias.length === 0) {
                return Math.floor(Math.random() * 25) + 60; // 60-85
            }
            
            const generosUsuario = preferencias.map(p => p.genero_id);
            
            if (generosUsuario.includes(item.genero_id)) {
                return Math.floor(Math.random() * 13) + 85; // 85-98
            } else {
                return Math.floor(Math.random() * 25) + 50; // 50-75
            }
        }

        // NUEVA: Toggle favorito en recomendaciones
        async function toggleFavoritoRecomendacion(event, contenidoId) {
            event.preventDefault();
            event.stopPropagation();
            
            const boton = document.querySelector(`[data-content-id="${contenidoId}"].favorite-btn-recommendation`);
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
                    alert('Funcionalidad de favoritos - ID: ' + contenidoId);
                    boton.textContent = originalContent;
                }
                
            } catch (error) {
                console.error('Error en toggle favorito:', error);
                boton.textContent = originalContent;
            } finally {
                boton.disabled = false;
            }
        }

        // Cargar estados de favoritos para recomendaciones
        async function cargarEstadosFavoritosRecomendaciones() {
            if (!window.CineAPI) return;
            
            const botones = document.querySelectorAll('.favorite-btn-recommendation[data-content-id]');
            
            // Procesar de a lotes
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

        // NUEVA: Refrescar recomendaciones inteligente
        async function refrescarRecomendacionesInteligente() {
            const boton = document.getElementById('btnRefresh');
            
            boton.disabled = true;
            boton.textContent = '⏳ Cargando...';
            
            try {
                if (window.CineAPI) {
                    // Limpiar cache de recomendaciones
                    await window.CineAPI.limpiarCache('obtener_recomendaciones.php');
                    
                    // Cambiar al mismo tipo para refrescar
                    await cambiarTipoRecomendacion(tipoRecomendacionActual);
                    
                    window.CineAPI.showNotification('Recomendaciones refrescadas', 'success');
                } else {
                    // Fallback: recargar página
                    location.reload();
                }
                
            } catch (error) {
                console.error('Error refrescando recomendaciones:', error);
                if (window.CineAPI) {
                    window.CineAPI.showNotification('Error refrescando recomendaciones', 'error');
                }
            } finally {
                boton.disabled = false;
                boton.textContent = '🔄 Refrescar';
            }
        }

        // NUEVA: Función sorpréndeme
        async function sorprendeme() {
            if (!window.CineAPI) {
                alert('Funcionalidad no disponible sin API');
                return;
            }
            
            mostrarLoadingRecomendaciones();
            
            try {
                // Obtener contenido aleatorio de alta calidad
                const resultado = await window.CineAPI.obtenerRecomendaciones('populares', {
                    limite: 12,
                    calificacionMin: 4,
                    orden: 'aleatorio'
                });
                
                if (resultado.success) {
                    document.getElementById('recommendationsTitle').textContent = '🎲 ¡Sorpresa!';
                    document.getElementById('recommendationsSubtitle').textContent = 'Contenido aleatorio de alta calidad seleccionado para ti';
                    
                    mostrarRecomendacionesDinamicas(resultado.data.recomendaciones);
                    
                    setTimeout(() => cargarEstadosFavoritosRecomendaciones(), 500);
                    
                    window.CineAPI.showNotification('¡Sorpresa! Aquí tienes contenido increíble', 'success');
                }
                
            } catch (error) {
                console.error('Error en sorpréndeme:', error);
                mostrarErrorRecomendaciones();
            } finally {
                ocultarLoadingRecomendaciones();
            }
        }

        // NUEVA: Recomendaciones inteligentes basadas en IA
        async function recomendacionesInteligentes() {
            if (!window.CineAPI) {
                // Fallback: sugerir géneros populares
                const generosPopulares = [1, 2, 3, 4]; // IDs de géneros más populares
                aplicarPreferenciasIA(generosPopulares);
                return;
            }
            
            try {
                // Obtener estadísticas del usuario para sugerir géneros
                const estadisticas = await window.CineAPI.obtenerEstadisticas('usuario', {
                    usuarioId: window.usuarioId,
                    incluirSugerencias: true
                });
                
                if (estadisticas.success && estadisticas.data.sugerencias_generos) {
                    const sugerencias = estadisticas.data.sugerencias_generos;
                    
                    if (sugerencias.length > 0) {
                        // Mostrar modal de confirmación
                        const generos = sugerencias.map(s => s.nombre).join(', ');
                        const confirmar = confirm(`La IA sugiere estos géneros basado en tu actividad:\n\n${generos}\n\n¿Aplicar estas preferencias?`);
                        
                        if (confirmar) {
                            aplicarPreferenciasIA(sugerencias.map(s => s.id));
                        }
                    } else {
                        window.CineAPI.showNotification('Necesitas más actividad para sugerencias de IA', 'info');
                    }
                } else {
                    window.CineAPI.showNotification('No se pudieron obtener sugerencias de IA', 'warning');
                }
                
            } catch (error) {
                console.error('Error en recomendaciones inteligentes:', error);
                window.CineAPI.showNotification('Error obteniendo sugerencias de IA', 'error');
            }
        }

        // Aplicar preferencias sugeridas por IA
        function aplicarPreferenciasIA(generosIds = null) {
            const grid = document.getElementById('preferencesGrid');
            const checkboxes = grid.querySelectorAll('input[type="checkbox"]');
            
            // Limpiar selecciones actuales
            checkboxes.forEach(cb => cb.checked = false);
            
            if (generosIds) {
                // Aplicar sugerencias específicas
                generosIds.forEach(id => {
                    const checkbox = document.getElementById(`genero_${id}`);
                    if (checkbox) checkbox.checked = true;
                });
            } else {
                // Fallback: seleccionar géneros populares
                const popularesIds = [1, 2, 3, 4, 5]; // Ajustar según tu base de datos
                popularesIds.forEach(id => {
                    const checkbox = document.getElementById(`genero_${id}`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Mostrar el formulario si está oculto
            const form = document.getElementById('preferencesForm');
            if (form.style.display === 'none') {
                togglePreferencesForm();
            }
            
            if (window.CineAPI) {
                window.CineAPI.showNotification('Preferencias sugeridas aplicadas. ¡Revisa y guarda!', 'info');
            }
        }

        // NUEVA: Vista rápida de recomendación
        async function vistaRapidaRecomendacion(contenidoId) {
            const modal = document.getElementById('quickViewModal');
            const title = document.getElementById('quickViewTitle');
            const body = document.getElementById('quickViewBody');
            
            title.textContent = 'Cargando...';
            body.innerHTML = '<div class="loading-quick-view">⏳ Obteniendo información...</div>';
            modal.style.display = 'block';
            
            try {
                // Buscar en recomendaciones actuales
                const item = recomendacionesActuales.find(r => r.id == contenidoId);
                
                if (item) {
                    title.textContent = `Vista Rápida - ${item.titulo}`;
                    body.innerHTML = `
                        <div class="quick-view">
                            <div class="quick-poster">
                                <img src="uploads/${item.poster || 'no-image.svg'}" alt="${item.titulo}">
                            </div>
                            <div class="quick-info">
                                <h4>${item.titulo}</h4>
                                <div class="quick-meta">
                                    <span>📅 ${item.año_lanzamiento || 'N/A'}</span>
                                    <span>🎭 ${item.genero_nombre || 'Sin género'}</span>
                                    <span>⭐ ${(item.calificacion || 0).toFixed(1)}</span>
                                </div>
                                <p class="quick-description">
                                    ${item.descripcion || 'Sin descripción disponible'}
                                </p>
                                <div class="quick-actions">
                                    <button class="btn btn-primary" onclick="cerrarVistaRapida(); verDetalle(${contenidoId})">
                                        Ver Detalles Completos
                                    </button>
                                    <button class="btn btn-outline" onclick="toggleFavoritoRecomendacion(event, ${contenidoId})">
                                        ❤️ Favorito
                                    </button>
                                    <button class="btn btn-secondary" onclick="cerrarVistaRapida()">
                                        Cerrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    body.innerHTML = '<p>Información no disponible. <a onclick="cerrarVistaRapida(); verDetalle(' + contenidoId + ')">Ver detalles completos</a></p>';
                }
                
            } catch (error) {
                console.error('Error en vista rápida:', error);
                body.innerHTML = '<p>Error cargando información. <a onclick="cerrarVistaRapida(); verDetalle(' + contenidoId + ')">Ver detalles completos</a></p>';
            }
        }

        // Cerrar vista rápida
        function cerrarVistaRapida() {
            document.getElementById('quickViewModal').style.display = 'none';
        }

        // NUEVA: Actualizar preferencias con AJAX
        async function actualizarPreferenciasAjax() {
            const form = document.getElementById('formPreferencias');
            const boton = document.getElementById('btnActualizarPreferencias');
            
            boton.disabled = true;
            boton.textContent = 'Actualizando...';
            
            try {
                const formData = new FormData(form);
                
                const response = await fetch('mis_recomendaciones.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    // Actualizar UI de preferencias
                    actualizarUIPreferencias();
                    
                    // Ocultar formulario
                    togglePreferencesForm();
                    
                    // Refrescar recomendaciones
                    setTimeout(() => refrescarRecomendacionesInteligente(), 500);
                    
                    if (window.CineAPI) {
                        window.CineAPI.showNotification('Preferencias actualizadas correctamente', 'success');
                    }
                } else {
                    throw new Error(resultado.message || 'Error actualizando preferencias');
                }
                
            } catch (error) {
                console.error('Error actualizando preferencias:', error);
                if (window.CineAPI) {
                    window.CineAPI.showNotification('Error actualizando preferencias', 'error');
                } else {
                    alert('Error actualizando preferencias: ' + error.message);
                }
            } finally {
                boton.disabled = false;
                boton.textContent = 'Actualizar Preferencias';
            }
        }

        // Actualizar UI de preferencias
        function actualizarUIPreferencias() {
            const checkboxes = document.querySelectorAll('#preferencesGrid input[type="checkbox"]:checked');
            const display = document.getElementById('preferencesDisplay');
            
            if (checkboxes.length > 0) {
                const html = Array.from(checkboxes).map(checkbox => {
                    const label = checkbox.nextElementSibling;
                    const icono = label.querySelector('.preference-icon').textContent;
                    const nombre = label.querySelector('.preference-name').textContent;
                    
                    return `<span class="preference-tag">${icono} ${nombre}</span>`;
                }).join('');
                
                display.innerHTML = html;
                
                // Actualizar estadísticas
                document.getElementById('statGeneros').textContent = checkboxes.length;
            }
        }

        // NUEVA: Exportar recomendaciones
        async function exportarRecomendaciones() {
            if (!window.CineAPI) {
                alert('Funcionalidad no disponible sin API');
                return;
            }
            
            try {
                await window.CineAPI.exportarDatos('recomendaciones', 'json', {
                    usuario_id: window.usuarioId,
                    tipo: tipoRecomendacionActual
                });
                
            } catch (error) {
                console.error('Error exportando recomendaciones:', error);
                window.CineAPI.showNotification('Error exportando recomendaciones', 'error');
            }
        }

        // NUEVA: Actualizar estadísticas
        async function actualizarEstadisticas() {
            if (!window.CineAPI) {
                location.reload();
                return;
            }
            
            try {
                const resultado = await window.CineAPI.obtenerEstadisticas('usuario', {
                    usuarioId: window.usuarioId
                });
                
                if (resultado.success) {
                    const stats = resultado.data.estadisticas_usuario;
                    
                    document.getElementById('statDetalleVisto').textContent = stats.contenido_visto;
                    document.getElementById('statDetalleReseñas').textContent = stats.calificaciones_dadas;
                    document.getElementById('statDetallePromedio').textContent = stats.calificacion_promedio;
                    document.getElementById('statDetalleFavorito').textContent = stats.genero_favorito;
                    
                    // Actualizar también estadísticas del header
                    document.getElementById('statVisto').textContent = stats.contenido_visto;
                    document.getElementById('statReseñas').textContent = stats.calificaciones_dadas;
                    
                    window.CineAPI.showNotification('Estadísticas actualizadas', 'success');
                }
                
            } catch (error) {
                console.error('Error actualizando estadísticas:', error);
                window.CineAPI.showNotification('Error actualizando estadísticas', 'error');
            }
        }

        // Toggle formulario de preferencias
        function togglePreferencesForm() {
            const form = document.getElementById('preferencesForm');
            const currentPrefs = document.getElementById('currentPreferences');
            
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                currentPrefs.style.display = 'none';
            } else {
                form.style.display = 'none';
                currentPrefs.style.display = 'block';
            }
        }

        // Mostrar estado de no recomendaciones
        function mostrarNoRecomendaciones(tipo) {
            const grid = document.getElementById('recommendationsGrid');
            grid.innerHTML = `
                <div class="no-recommendations-message">
                    <div class="empty-icon">🎯</div>
                    <h3>No hay recomendaciones disponibles</h3>
                    <p>No se encontraron recomendaciones del tipo "${tipo}"</p>
                    <button class="btn btn-primary" onclick="cambiarTipoRecomendacion('personalizadas')">
                        Ver Recomendaciones Personalizadas
                    </button>
                </div>
            `;
        }

        // Mostrar error en recomendaciones
        function mostrarErrorRecomendaciones() {
            const grid = document.getElementById('recommendationsGrid');
            grid.innerHTML = `
                <div class="error-recommendations">
                    <div class="error-icon">❌</div>
                    <h3>Error cargando recomendaciones</h3>
                    <p>Hubo un problema al obtener las recomendaciones</p>
                    <button class="btn btn-primary" onclick="refrescarRecomendacionesInteligente()">
                        Intentar de nuevo
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

        // Configuración inicial
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎯 Mis Recomendaciones integrado con APIs inicializado');
            
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Configurar formulario de preferencias para AJAX
            const form = document.getElementById('formPreferencias');
            if (form && window.CineAPI) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    actualizarPreferenciasAjax();
                });
            }

            // Animaciones de entrada escalonadas
            setTimeout(() => {
                const items = document.querySelectorAll('.recommendation-item');
                items.forEach((item, index) => {
                    setTimeout(() => {
                        item.classList.add('fade-in');
                    }, index * 100);
                });
            }, 100);

            // Cargar estados de favoritos iniciales
            if (window.CineAPI) {
                setTimeout(() => cargarEstadosFavoritosRecomendaciones(), 1000);
            }

            // Cerrar modal al hacer click fuera
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('quickViewModal');
                if (event.target === modal) {
                    cerrarVistaRapida();
                }
            });

            // Auto-scroll suave para secciones
            if (window.location.hash) {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    </script>

    <!-- Integración con APIs REST -->
    <script src="js/api-client.js"></script>

    <!-- Estilos adicionales para funcionalidades nuevas -->
    <style>
        /* ===== ESTILOS PARA NUEVAS FUNCIONALIDADES ===== */

        /* Controles de preferencias mejorados */
        .preferences-controls {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .preferences-stats {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }

        /* Controles de recomendaciones */
        .recommendations-controls {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
        }

        .controls-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .controls-actions {
            display: flex;
            gap: 0.5rem;
        }

        .recommendation-types {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .rec-type-btn {
            padding: 0.5rem 1rem;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .rec-type-btn:hover {
            border-color: var(--primary-color);
            background: var(--bg-light);
        }

        .rec-type-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Loading para recomendaciones */
        .recommendations-loading {
            padding: 3rem;
            text-align: center;
        }

        .loading-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Botón de favoritos en recomendaciones */
        .favorite-btn-recommendation {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            backdrop-filter: blur(4px);
        }

        .favorite-btn-recommendation:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.1);
        }

        .favorite-btn-recommendation.active {
            background: rgba(220, 53, 69, 0.9);
        }

        /* Vista rápida */
        .quick-view-content {
            max-width: 700px;
            width: 90%;
        }

        .quick-view {
            display: flex;
            gap: 1.5rem;
        }

        .quick-poster {
            flex-shrink: 0;
        }

        .quick-poster img {
            width: 180px;
            height: 270px;
            object-fit: cover;
            border-radius: 8px;
        }

        .quick-info {
            flex: 1;
        }

        .quick-meta {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .quick-description {
            margin: 1rem 0;
            line-height: 1.5;
        }

        .quick-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .loading-quick-view {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        /* Estados de no recomendaciones y errores */
        .no-recommendations-message,
        .error-recommendations {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 2rem;
        }

        .no-recommendations-message .empty-icon,
        .error-recommendations .error-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .error-recommendations {
            color: var(--error-color, #dc3545);
        }

        /* Animaciones mejoradas */
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

        /* Responsive */
        @media (max-width: 768px) {
            .controls-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .recommendation-types {
                justify-content: center;
            }
            
            .rec-type-btn {
                flex: 1;
                min-width: 120px;
            }
            
            .preferences-controls {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .quick-view {
                flex-direction: column;
                text-align: center;
            }
            
            .quick-poster img {
                width: 150px;
                height: 225px;
            }
            
            .quick-actions {
                justify-content: center;
            }
            
            .favorite-btn-recommendation {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }
        }

        /* Tema oscuro */
        body.dark-theme .recommendations-controls {
            background: linear-gradient(135deg, #2d3748, #4a5568);
        }

        body.dark-theme .rec-type-btn {
            background: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-light);
        }

        body.dark-theme .rec-type-btn:hover {
            background: var(--bg-light);
        }

        body.dark-theme .favorite-btn-recommendation {
            background: rgba(255, 255, 255, 0.1);
        }

        body.dark-theme .favorite-btn-recommendation:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Estilos existentes mejorados */
        .recommendations-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 0;
            border-bottom: 2px solid var(--border-color);
        }

        .header-content h1 {
            margin: 0;
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-stats {
            display: flex;
            gap: 1.5rem;
        }

        .stat-bubble {
            text-align: center;
            padding: 1rem;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: var(--shadow);
            min-width: 80px;
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Grid de recomendaciones */
        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .recommendation-item {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .recommendation-item:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }

        .item-poster {
            position: relative;
            aspect-ratio: 2/3;
            overflow: hidden;
        }

        .item-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .recommendation-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .item-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: flex-end;
            padding: 1rem;
        }

        .recommendation-item:hover .item-overlay {
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

        .item-description {
            font-size: 0.8rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .overlay-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .item-info {
            padding: 1rem;
        }

        .item-title {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
        }

        .item-genre {
            color: var(--accent-color);
            font-weight: 500;
        }

        .item-year {
            color: var(--text-secondary);
        }

        .item-rating {
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
            font-size: 0.8rem;
            opacity: 0.3;
        }

        .star.filled {
            opacity: 1;
        }

        .rating-number {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .type-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
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

        .match-score {
            text-align: center;
        }

        .match-percentage {
            background: var(--primary-color);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Responsive para móviles */
        @media (max-width: 480px) {
            .recommendations-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .user-stats {
                justify-content: center;
                gap: 1rem;
            }

            .recommendations-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 1rem;
                padding: 1rem;
            }

            .header-content h1 {
                font-size: 2rem;
            }
        }
    </style>

</body>
</html>

<?php
// Función helper para obtener iconos de géneros
function obtenerIconoGenero($nombre) {
    $iconos = [
        'Acción' => '💥',
        'Drama' => '🎭',
        'Comedia' => '😄',
        'Terror' => '😱',
        'Ciencia Ficción' => '🚀',
        'Romance' => '💖',
        'Thriller' => '🔪',
        'Aventura' => '🗺️',
        'Fantasía' => '🧙',
        'Documental' => '📹'
    ];
    
    return isset($iconos[$nombre]) ? $iconos[$nombre] : '🎬';
}
?>