<?php
require_once 'config/database.php';
require_once 'classes/ContenidoManager.php';
require_once 'classes/UsuarioManager.php';

// Verificar si hay cookies de tema
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

// Inicializar managers
$contenidoManager = new ContenidoManager();
$usuarioManager = new UsuarioManager();

// Obtener contenido destacado
$peliculasDestacadas = $contenidoManager->obtenerContenidoDestacado('pelicula', 6);
$seriesDestacadas = $contenidoManager->obtenerContenidoDestacado('serie', 6);
$generos = $contenidoManager->obtenerGeneros();

// Variables para filtros
$filtroGenero = isset($_GET['genero']) ? sanitize_input($_GET['genero']) : '';
$filtroTipo = isset($_GET['tipo']) ? sanitize_input($_GET['tipo']) : '';
$busqueda = isset($_GET['buscar']) ? sanitize_input($_GET['buscar']) : '';

// Aplicar filtros si existen
if ($filtroGenero || $filtroTipo || $busqueda) {
    $contenidoFiltrado = $contenidoManager->buscarContenido($busqueda, $filtroGenero, $filtroTipo);
}

// Recomendaciones personalizadas si el usuario está logueado
$recomendaciones = [];
if (isLoggedIn()) {
    $recomendaciones = $contenidoManager->obtenerRecomendacionesPersonalizadas($_SESSION['user_id']);
}
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineRecomendaciones - Tu plataforma de entretenimiento</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
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

    <!-- Contenido Principal -->
    <main class="container">
        
        <!-- Hero Section -->
        <section class="hero-section text-center mt-4 mb-4">
            <h1 class="hero-title fade-in">Descubre tu próxima película o serie favorita</h1>
            <p class="hero-subtitle">Recomendaciones personalizadas basadas en tus gustos</p>
            
            <?php if (!isLoggedIn()): ?>
                <div class="hero-actions mt-3">
                    <a href="auth/registro_usuario.php" class="btn btn-primary btn-large pulse">
                        ¡Únete Gratis!
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Barra de búsqueda y filtros -->
        <section class="search-section mb-4">
            <div class="card">
                <form method="GET" action="inicio.php" class="filters">
                    <div class="filter-group">
                        <label for="buscar">Buscar:</label>
                        <input type="text" 
                               id="buscar" 
                               name="buscar" 
                               class="form-control" 
                               placeholder="Título de película o serie..."
                               value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="genero">Género:</label>
                        <select id="genero" name="genero" class="form-control">
                            <option value="">Todos los géneros</option>
                            <?php foreach ($generos as $genero): ?>
                                <option value="<?php echo $genero['id']; ?>" 
                                        <?php echo $filtroGenero == $genero['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($genero['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="tipo">Tipo:</label>
                        <select id="tipo" name="tipo" class="form-control">
                            <option value="">Películas y Series</option>
                            <option value="pelicula" <?php echo $filtroTipo === 'pelicula' ? 'selected' : ''; ?>>
                                Solo Películas
                            </option>
                            <option value="serie" <?php echo $filtroTipo === 'serie' ? 'selected' : ''; ?>>
                                Solo Series
                            </option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <a href="inicio.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </section>

        <!-- Resultados de búsqueda -->
        <?php if ($filtroGenero || $filtroTipo || $busqueda): ?>
            <section class="search-results mb-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Resultados de búsqueda</h2>
                        <p>
                            <?php 
                            $totalResultados = count($contenidoFiltrado);
                            echo "Se encontraron {$totalResultados} resultado(s)";
                            ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($contenidoFiltrado)): ?>
                        <div class="content-grid">
                            <?php foreach ($contenidoFiltrado as $item): ?>
                                <div class="content-item fade-in" onclick="verDetalle(<?php echo $item['id']; ?>)">
                                    <img src="uploads/<?php echo $item['poster'] ?: 'no-image.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['titulo']); ?>"
                                         onerror="this.src='uploads/no-image.jpg'">
                                    <div class="content-info">
                                        <h3 class="title"><?php echo htmlspecialchars($item['titulo']); ?></h3>
                                        <p class="genre"><?php echo htmlspecialchars($item['genero_nombre']); ?></p>
                                        <div class="rating">
                                            <span>⭐</span>
                                            <span><?php echo number_format($item['calificacion'], 1); ?></span>
                                            <span class="type-badge"><?php echo ucfirst($item['tipo']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-4">
                            <p>No se encontraron resultados para tu búsqueda.</p>
                            <a href="inicio.php" class="btn btn-primary">Ver todo el catálogo</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Recomendaciones personalizadas -->
        <?php if (isLoggedIn() && !empty($recomendaciones)): ?>
            <section class="recommendations mb-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🎯 Recomendado para ti</h2>
                        <p>Basado en tus gustos y preferencias</p>
                    </div>
                    
                    <div class="content-grid">
                        <?php foreach ($recomendaciones as $item): ?>
                            <div class="content-item fade-in" onclick="verDetalle(<?php echo $item['id']; ?>)">
                                <img src="uploads/<?php echo $item['poster'] ?: 'no-image.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['titulo']); ?>"
                                     onerror="this.src='uploads/no-image.jpg'">
                                <div class="content-info">
                                    <h3 class="title"><?php echo htmlspecialchars($item['titulo']); ?></h3>
                                    <p class="genre"><?php echo htmlspecialchars($item['genero_nombre']); ?></p>
                                    <div class="rating">
                                        <span>⭐</span>
                                        <span><?php echo number_format($item['calificacion'], 1); ?></span>
                                        <span class="type-badge"><?php echo ucfirst($item['tipo']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Películas destacadas -->
        <?php if (!($filtroGenero || $filtroTipo || $busqueda)): ?>
            <section class="featured-movies mb-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🎬 Películas Destacadas</h2>
                        <a href="catalogo.php?tipo=pelicula" class="btn btn-outline">Ver todas</a>
                    </div>
                    
                    <div class="content-grid">
                        <?php foreach ($peliculasDestacadas as $pelicula): ?>
                            <div class="content-item fade-in" onclick="verDetalle(<?php echo $pelicula['id']; ?>)">
                                <img src="uploads/<?php echo $pelicula['poster'] ?: 'no-image.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($pelicula['titulo']); ?>"
                                     onerror="this.src='uploads/no-image.jpg'">
                                <div class="content-info">
                                    <h3 class="title"><?php echo htmlspecialchars($pelicula['titulo']); ?></h3>
                                    <p class="genre"><?php echo htmlspecialchars($pelicula['genero_nombre']); ?></p>
                                    <div class="rating">
                                        <span>⭐</span>
                                        <span><?php echo number_format($pelicula['calificacion'], 1); ?></span>
                                        <span class="year"><?php echo $pelicula['año_lanzamiento']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Series destacadas -->
            <section class="featured-series mb-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📺 Series Destacadas</h2>
                        <a href="catalogo.php?tipo=serie" class="btn btn-outline">Ver todas</a>
                    </div>
                    
                    <div class="content-grid">
                        <?php foreach ($seriesDestacadas as $serie): ?>
                            <div class="content-item fade-in" onclick="verDetalle(<?php echo $serie['id']; ?>)">
                                <img src="uploads/<?php echo $serie['poster'] ?: 'no-image.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($serie['titulo']); ?>"
                                     onerror="this.src='uploads/no-image.jpg'">
                                <div class="content-info">
                                    <h3 class="title"><?php echo htmlspecialchars($serie['titulo']); ?></h3>
                                    <p class="genre"><?php echo htmlspecialchars($serie['genero_nombre']); ?></p>
                                    <div class="rating">
                                        <span>⭐</span>
                                        <span><?php echo number_format($serie['calificacion'], 1); ?></span>
                                        <span class="seasons"><?php echo $serie['duracion']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Call to Action para usuarios no registrados -->
        <?php if (!isLoggedIn()): ?>
            <section class="cta-section text-center mt-4 mb-4">
                <div class="card">
                    <h2>¿Listo para descubrir más?</h2>
                    <p>Regístrate gratis y recibe recomendaciones personalizadas basadas en tus gustos</p>
                    <div class="cta-benefits mt-3 mb-3">
                        <div class="benefit">✅ Recomendaciones personalizadas</div>
                        <div class="benefit">✅ Historial de películas vistas</div>
                        <div class="benefit">✅ Califica y comenta contenido</div>
                        <div class="benefit">✅ Crea tu lista de favoritos</div>
                    </div>
                    <a href="auth/registro_usuario.php" class="btn btn-primary btn-large">
                        Únete Ahora - Es Gratis
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones. Proyecto universitario - Plataforma de recomendaciones.</p>
            <p>Desarrollado con ❤️ usando PHP, MySQL y tecnologías web modernas.</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Función para cambiar tema
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            body.classList.toggle('dark-theme');
            document.documentElement.setAttribute('data-theme', newTheme);
            
            // Guardar en cookie
            document.cookie = `theme=${newTheme}; expires=${new Date(Date.now() + 30*24*60*60*1000).toUTCString()}; path=<?php echo COOKIE_PATH; ?>`;
            
            // Cambiar icono del botón
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }

        // Función para ver detalle de contenido
        function verDetalle(id) {
            // Guardar en historial de navegación si está logueado
            <?php if (isLoggedIn()): ?>
                fetch('api/guardar_historial.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        contenido_id: id,
                        usuario_id: <?php echo $_SESSION['user_id']; ?>
                    })
                });
            <?php endif; ?>
            
            // Redirigir a página de detalle
            window.location.href = `detalle_contenido.php?id=${id}`;
        }

        // Aplicar animaciones cuando el contenido carga
        document.addEventListener('DOMContentLoaded', function() {
            const items = document.querySelectorAll('.content-item');
            items.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('fade-in');
                }, index * 100);
            });

            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
        });

        // Búsqueda en tiempo real (opcional)
        const searchInput = document.getElementById('buscar');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length > 2) {
                        // Aquí se podría implementar búsqueda AJAX
                        console.log('Búsqueda:', this.value);
                    }
                }, 500);
            });
        }
    </script>

</body>
</html>