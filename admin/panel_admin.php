<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Verificar autenticación y permisos de administrador
if (!isLoggedIn()) {
    redirect('auth/iniciar_sesion.php?redirect=' . urlencode('admin/panel_admin.php'));
}

if (!isAdmin()) {
    redirect('inicio.php');
}

$contenidoManager = new ContenidoManager();
$usuarioManager = new UsuarioManager();

// Verificar sesión
$usuarioManager->verificarSesion();

// Obtener datos para el dashboard
$estadisticas_contenido = $contenidoManager->obtenerEstadisticasContenido();
$estadisticas_usuarios = $usuarioManager->obtenerEstadisticasGenerales();

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - CineRecomendaciones</title>
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
                <li><a href="panel_admin.php" class="active">👑 Administración</a></li>
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
        
        <!-- Header del panel -->
        <section class="hero-section text-center mt-4 mb-4">
            <h1 class="hero-title">👑 Panel de Administración</h1>
            <p class="hero-subtitle">Gestiona contenido, usuarios y configura la plataforma</p>
        </section>

        <!-- Navegación de secciones -->
        <section class="search-section mb-4">
            <div class="card">
                <div class="filters">
                    <a href="panel_admin.php" class="btn btn-primary">📊 Dashboard</a>
                    <a href="gestionar_contenido.php" class="btn btn-outline">🎬 Gestionar Contenido</a>
                    <a href="gestionar_usuarios.php" class="btn btn-outline">👥 Gestionar Usuarios</a>
                    <a href="reportes.php" class="btn btn-outline">📈 Reportes</a>
                </div>
            </div>
        </section>

        <!-- Dashboard - Estadísticas principales -->
        <div class="content-grid mb-4">
            <div class="card text-center">
                <h3>🎬</h3>
                <h2><?php echo $estadisticas_contenido['contenido']['total']; ?></h2>
                <p>Total Contenido</p>
                <a href="gestionar_contenido.php" class="btn btn-outline btn-sm mt-2">Gestionar</a>
            </div>
            
            <div class="card text-center">
                <h3>🎭</h3>
                <h2><?php echo $estadisticas_contenido['contenido']['peliculas']; ?></h2>
                <p>Películas</p>
                <a href="gestionar_contenido.php?tipo=pelicula" class="btn btn-outline btn-sm mt-2">Ver Películas</a>
            </div>
            
            <div class="card text-center">
                <h3>📺</h3>
                <h2><?php echo $estadisticas_contenido['contenido']['series']; ?></h2>
                <p>Series</p>
                <a href="gestionar_contenido.php?tipo=serie" class="btn btn-outline btn-sm mt-2">Ver Series</a>
            </div>
            
            <div class="card text-center">
                <h3>👥</h3>
                <h2><?php echo $estadisticas_usuarios['usuarios']['total_usuarios']; ?></h2>
                <p>Total Usuarios</p>
                <a href="gestionar_usuarios.php" class="btn btn-outline btn-sm mt-2">Gestionar</a>
            </div>
            
            <div class="card text-center">
                <h3>✅</h3>
                <h2><?php echo $estadisticas_usuarios['usuarios']['usuarios_activos']; ?></h2>
                <p>Usuarios Activos</p>
                <a href="gestionar_usuarios.php?activo=1" class="btn btn-outline btn-sm mt-2">Ver Activos</a>
            </div>
            
            <div class="card text-center">
                <h3>🆕</h3>
                <h2><?php echo $estadisticas_usuarios['usuarios']['nuevos_ultimo_mes']; ?></h2>
                <p>Nuevos (30 días)</p>
                <a href="reportes.php" class="btn btn-outline btn-sm mt-2">Ver Reportes</a>
            </div>
        </div>

        <!-- Datos adicionales -->
        <div class="content-grid">
            
            <!-- Contenido por género -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📊 Contenido por Género</h3>
                </div>
                <div class="p-3">
                    <?php foreach (array_slice($estadisticas_contenido['por_genero'], 0, 8) as $genero): ?>
                        <div class="mb-2 d-flex justify-between">
                            <span><?php echo htmlspecialchars($genero['nombre']); ?></span>
                            <strong><?php echo $genero['cantidad']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="gestionar_contenido.php" class="btn btn-primary btn-sm">Ver Todo el Contenido</a>
                    </div>
                </div>
            </div>

            <!-- Contenido más visto -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔥 Contenido Más Visto</h3>
                </div>
                <div class="p-3">
                    <?php foreach (array_slice($estadisticas_contenido['mas_visto'], 0, 5) as $index => $item): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-between">
                                <div>
                                    <strong>#<?php echo $index + 1; ?> <?php echo htmlspecialchars($item['titulo']); ?></strong>
                                    <br><small><?php echo ucfirst($item['tipo']); ?></small>
                                </div>
                                <span><?php echo $item['visitas']; ?> vistas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="reportes.php" class="btn btn-primary btn-sm">Ver Más Estadísticas</a>
                    </div>
                </div>
            </div>

            <!-- Usuarios más activos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⭐ Usuarios Más Activos</h3>
                </div>
                <div class="p-3">
                    <?php foreach (array_slice($estadisticas_usuarios['usuarios_activos'], 0, 5) as $index => $usuario): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-between">
                                <div>
                                    <strong>#<?php echo $index + 1; ?> <?php echo htmlspecialchars($usuario['nombre']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($usuario['email']); ?></small>
                                </div>
                                <span><?php echo $usuario['contenido_visto']; ?> vistas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="gestionar_usuarios.php" class="btn btn-primary btn-sm">Gestionar Usuarios</a>
                    </div>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⚡ Acciones Rápidas</h3>
                </div>
                <div class="p-3">
                    <div class="mb-3">
                        <a href="gestionar_contenido.php" class="btn btn-primary w-100 mb-2">
                            ➕ Agregar Contenido
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <a href="reportes.php" class="btn btn-secondary w-100 mb-2">
                            📊 Exportar Datos
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <a href="gestionar_usuarios.php" class="btn btn-outline w-100 mb-2">
                            👥 Ver Usuarios
                        </a>
                    </div>
                    
                    <div>
                        <a href="../inicio.php" class="btn btn-outline w-100">
                            🏠 Ir al Sitio Web
                        </a>
                    </div>
                </div>
            </div>

            <!-- Resumen del sistema -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📈 Resumen del Sistema</h3>
                </div>
                <div class="p-3">
                    <div class="mb-2 d-flex justify-between">
                        <span>Calificación promedio:</span>
                        <strong>⭐ <?php echo number_format($estadisticas_contenido['contenido']['calificacion_promedio'], 1); ?></strong>
                    </div>
                    
                    <div class="mb-2 d-flex justify-between">
                        <span>Género más popular:</span>
                        <strong>
                            <?php 
                            $genero_popular = !empty($estadisticas_contenido['por_genero']) ? 
                                $estadisticas_contenido['por_genero'][0]['nombre'] : 'N/A';
                            echo htmlspecialchars($genero_popular);
                            ?>
                        </strong>
                    </div>
                    
                    <div class="mb-2 d-flex justify-between">
                        <span>Usuarios activos:</span>
                        <strong>
                            <?php 
                            $total = $estadisticas_usuarios['usuarios']['total_usuarios'];
                            $activos = $estadisticas_usuarios['usuarios']['usuarios_activos'];
                            $porcentaje = $total > 0 ? round(($activos / $total) * 100, 1) : 0;
                            echo "{$porcentaje}%";
                            ?>
                        </strong>
                    </div>
                    
                    <div class="d-flex justify-between">
                        <span>Ratio Películas/Series:</span>
                        <strong>
                            <?php 
                            $peliculas = $estadisticas_contenido['contenido']['peliculas'];
                            $series = $estadisticas_contenido['contenido']['series'];
                            echo "{$peliculas}:{$series}";
                            ?>
                        </strong>
                    </div>
                </div>
            </div>

            <!-- Notificaciones/Alertas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔔 Notificaciones</h3>
                </div>
                <div class="p-3">
                    <?php if ($estadisticas_contenido['contenido']['total'] < 50): ?>
                        <div class="alert alert-info mb-2">
                            <strong>📈 Catálogo pequeño</strong><br>
                            Considera agregar más contenido para mejorar las recomendaciones.
                            <a href="gestionar_contenido.php" class="btn btn-primary btn-sm mt-1">Agregar Contenido</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($estadisticas_usuarios['usuarios']['usuarios_activos'] < ($estadisticas_usuarios['usuarios']['total_usuarios'] * 0.7)): ?>
                        <div class="alert alert-info mb-2">
                            <strong>👥 Muchos usuarios inactivos</strong><br>
                            Considera estrategias para reactivar usuarios.
                            <a href="gestionar_usuarios.php?activo=0" class="btn btn-secondary btn-sm mt-1">Ver Inactivos</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($estadisticas_usuarios['usuarios']['nuevos_ultimo_mes'] > 5): ?>
                        <div class="alert alert-success mb-2">
                            <strong>🎉 ¡Nuevos usuarios!</strong><br>
                            Has tenido <?php echo $estadisticas_usuarios['usuarios']['nuevos_ultimo_mes']; ?> nuevos registros este mes.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($estadisticas_contenido['mas_visto'])): ?>
                        <div class="alert alert-info">
                            <strong>👁️ Sin visualizaciones</strong><br>
                            Aún no hay contenido visitado por los usuarios.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

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

        // Configuración inicial
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.card');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>

</body>
</html>