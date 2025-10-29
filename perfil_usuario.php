<?php
require_once 'config/database.php';
require_once 'classes/UsuarioManager.php';
require_once 'classes/ContenidoManager.php';

// Verificar autenticación
if (!isLoggedIn()) {
    redirect('auth/iniciar_sesion.php?redirect=' . urlencode('perfil_usuario.php'));
}

$usuarioManager = new UsuarioManager();
$contenidoManager = new ContenidoManager();

// Verificar sesión
$usuarioManager->verificarSesion();

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = '';
$seccion_activa = isset($_GET['seccion']) ? sanitize_input($_GET['seccion']) : 'perfil';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action'])) {
        $action = sanitize_input($_POST['action']);
        
        switch ($action) {
            case 'actualizar_perfil':
                $nombre = sanitize_input($_POST['nombre']);
                $email = sanitize_input($_POST['email']);
                
                $resultado = $usuarioManager->actualizarPerfil($_SESSION['user_id'], $nombre, $email);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
                
            case 'cambiar_password':
                $password_actual = $_POST['password_actual'];
                $password_nueva = $_POST['password_nueva'];
                $confirmar_password = $_POST['confirmar_password'];
                
                if ($password_nueva !== $confirmar_password) {
                    $mensaje = 'Las contraseñas nuevas no coinciden';
                    $tipo_mensaje = 'error';
                } else {
                    $resultado = $usuarioManager->cambiarPassword($_SESSION['user_id'], $password_actual, $password_nueva);
                    $mensaje = $resultado['message'];
                    $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                }
                break;
                
            case 'actualizar_preferencias':
                $generos_seleccionados = isset($_POST['generos']) ? $_POST['generos'] : [];
                
                $resultado = $usuarioManager->guardarPreferencias($_SESSION['user_id'], $generos_seleccionados);
                $mensaje = $resultado['message'];
                $tipo_mensaje = $resultado['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Obtener datos del usuario
$usuario = $usuarioManager->obtenerUsuario($_SESSION['user_id']);
$estadisticas = $usuarioManager->obtenerEstadisticasUsuario($_SESSION['user_id']);
$preferencias = $usuarioManager->obtenerPreferencias($_SESSION['user_id']);
$actividad_reciente = $usuarioManager->obtenerActividadReciente($_SESSION['user_id'], 10);
$historial = $contenidoManager->obtenerHistorialUsuario($_SESSION['user_id'], 10);

// Obtener todos los géneros para el formulario de preferencias
$generos = $contenidoManager->obtenerGeneros();

// Obtener configuración del usuario
$configuracion = $usuarioManager->obtenerConfiguracionUsuario($_SESSION['user_id']);

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - CineRecomendaciones</title>
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
                <li><a href="mis_recomendaciones.php">Mis Recomendaciones</a></li>
                <li><a href="perfil_usuario.php" class="active">Mi Perfil</a></li>
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
        
        <!-- Header del perfil -->
        <section class="profile-header mb-4">
            <div class="profile-banner">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <span class="avatar-icon">👤</span>
                    </div>
                    <div class="profile-details">
                        <h1 class="profile-name"><?php echo htmlspecialchars($usuario['nombre']); ?></h1>
                        <p class="profile-role">
                            <?php echo $usuario['rol'] === 'admin' ? '👑 Administrador' : '🎬 Cinéfilo'; ?>
                        </p>
                        <p class="profile-member-since">
                            Miembro desde <?php echo date('F Y', strtotime($usuario['fecha_registro'])); ?>
                        </p>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estadisticas['contenido_visto']; ?></span>
                        <span class="stat-label">Contenido visto</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estadisticas['calificaciones_dadas']; ?></span>
                        <span class="stat-label">Reseñas escritas</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $estadisticas['calificacion_promedio']; ?></span>
                        <span class="stat-label">Calificación promedio</span>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> mb-4">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Navegación de secciones -->
        <nav class="profile-nav mb-4">
            <div class="profile-nav-links">
                <a href="perfil_usuario.php?seccion=perfil" 
                   class="profile-nav-link <?php echo $seccion_activa === 'perfil' ? 'active' : ''; ?>">
                    👤 Información Personal
                </a>
                <a href="perfil_usuario.php?seccion=preferencias" 
                   class="profile-nav-link <?php echo $seccion_activa === 'preferencias' ? 'active' : ''; ?>">
                    🎭 Preferencias
                </a>
                <a href="perfil_usuario.php?seccion=historial" 
                   class="profile-nav-link <?php echo $seccion_activa === 'historial' ? 'active' : ''; ?>">
                    📚 Mi Historial
                </a>
                <a href="perfil_usuario.php?seccion=configuracion" 
                   class="profile-nav-link <?php echo $seccion_activa === 'configuracion' ? 'active' : ''; ?>">
                    ⚙️ Configuración
                </a>
            </div>
        </nav>

        <!-- Contenido de las secciones -->
        <div class="profile-content">
            
            <?php if ($seccion_activa === 'perfil'): ?>
                <!-- Sección: Información Personal -->
                <section class="profile-section">
                    <div class="section-grid">
                        <!-- Formulario de perfil -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Información Personal</h3>
                            </div>
                            
                            <form method="POST" action="perfil_usuario.php?seccion=perfil">
                                <input type="hidden" name="action" value="actualizar_perfil">
                                
                                <div class="form-group">
                                    <label for="nombre">Nombre completo:</label>
                                    <input type="text" 
                                           id="nombre" 
                                           name="nombre" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Correo electrónico:</label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>" 
                                           required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    Actualizar Información
                                </button>
                            </form>
                        </div>

                        <!-- Cambiar contraseña -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Cambiar Contraseña</h3>
                            </div>
                            
                            <form method="POST" action="perfil_usuario.php?seccion=perfil" id="formPassword">
                                <input type="hidden" name="action" value="cambiar_password">
                                
                                <div class="form-group">
                                    <label for="password_actual">Contraseña actual:</label>
                                    <input type="password" 
                                           id="password_actual" 
                                           name="password_actual" 
                                           class="form-control" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password_nueva">Nueva contraseña:</label>
                                    <input type="password" 
                                           id="password_nueva" 
                                           name="password_nueva" 
                                           class="form-control" 
                                           minlength="6" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirmar_password">Confirmar nueva contraseña:</label>
                                    <input type="password" 
                                           id="confirmar_password" 
                                           name="confirmar_password" 
                                           class="form-control" 
                                           minlength="6" 
                                           required>
                                    <div id="password-match" class="form-text"></div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" id="btnCambiarPassword">
                                    Cambiar Contraseña
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

            <?php elseif ($seccion_activa === 'preferencias'): ?>
                <!-- Sección: Preferencias -->
                <section class="profile-section">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">🎭 Mis Preferencias de Géneros</h3>
                            <p>Selecciona tus géneros favoritos para recibir mejores recomendaciones</p>
                        </div>
                        
                        <form method="POST" action="perfil_usuario.php?seccion=preferencias">
                            <input type="hidden" name="action" value="actualizar_preferencias">
                            
                            <div class="preferences-grid">
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
                            
                            <div class="form-actions mt-3">
                                <button type="submit" class="btn btn-primary">
                                    Guardar Preferencias
                                </button>
                                <p class="form-text">
                                    Tip: Selecciona al menos 3 géneros para obtener mejores recomendaciones
                                </p>
                            </div>
                        </form>
                    </div>

                    <!-- Género favorito actual -->
                    <?php if (!empty($estadisticas['genero_favorito']) && $estadisticas['genero_favorito'] !== 'N/A'): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h3 class="card-title">📊 Tu Género Favorito</h3>
                            </div>
                            <div class="favorite-genre">
                                <div class="genre-highlight">
                                    <span class="genre-icon"><?php echo obtenerIconoGenero($estadisticas['genero_favorito']); ?></span>
                                    <h4><?php echo htmlspecialchars($estadisticas['genero_favorito']); ?></h4>
                                    <p>Basado en tu historial de visualización</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

            <?php elseif ($seccion_activa === 'historial'): ?>
                <!-- Sección: Historial -->
                <section class="profile-section">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">📚 Mi Historial de Visualización</h3>
                            <div class="card-actions">
                                <button class="btn btn-outline btn-sm" onclick="exportarHistorial()">
                                    📄 Exportar
                                </button>
                            </div>
                        </div>
                        
                        <?php if (!empty($historial)): ?>
                            <div class="history-list">
                                <?php foreach ($historial as $item): ?>
                                    <div class="history-item">
                                        <div class="history-poster">
                                            <img src="uploads/<?php echo $item['poster'] ?: 'no-image.svg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['titulo']); ?>"
                                                 onerror="this.src='uploads/no-image.svg'">
                                        </div>
                                        <div class="history-info">
                                            <h4 class="history-title">
                                                <a href="detalle_contenido.php?id=<?php echo $item['id']; ?>">
                                                    <?php echo htmlspecialchars($item['titulo']); ?>
                                                </a>
                                            </h4>
                                            <div class="history-meta">
                                                <span class="history-type"><?php echo ucfirst($item['tipo']); ?></span>
                                                <span class="history-genre"><?php echo htmlspecialchars($item['genero_nombre']); ?></span>
                                                <span class="history-rating">⭐ <?php echo number_format($item['calificacion'], 1); ?></span>
                                            </div>
                                            <div class="history-date">
                                                Visto el <?php echo date('d/m/Y H:i', strtotime($item['fecha_visita'])); ?>
                                            </div>
                                        </div>
                                        <div class="history-actions">
                                            <a href="detalle_contenido.php?id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline btn-sm">
                                                Ver detalles
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="catalogo.php" class="btn btn-primary">
                                    Explorar más contenido
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">📺</div>
                                <h4>Aún no has visto ningún contenido</h4>
                                <p>Explora nuestro catálogo y descubre nuevas películas y series</p>
                                <a href="catalogo.php" class="btn btn-primary">
                                    Explorar catálogo
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            <?php elseif ($seccion_activa === 'configuracion'): ?>
                <!-- Sección: Configuración -->
                <section class="profile-section">
                    <div class="section-grid">
                        <!-- Configuración de tema -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">🎨 Apariencia</h3>
                            </div>
                            
                            <div class="config-section">
                                <div class="config-item">
                                    <label>Tema de la aplicación:</label>
                                    <div class="theme-selector">
                                        <button class="theme-option <?php echo $theme === 'light' ? 'active' : ''; ?>" 
                                                onclick="setTheme('light')">
                                            ☀️ Claro
                                        </button>
                                        <button class="theme-option <?php echo $theme === 'dark' ? 'active' : ''; ?>" 
                                                onclick="setTheme('dark')">
                                            🌙 Oscuro
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Opciones de privacidad -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">🔒 Privacidad y Datos</h3>
                            </div>
                            
                            <div class="config-section">
                                <div class="config-item">
                                    <div class="config-actions">
                                        <button class="btn btn-outline" onclick="exportarDatos()">
                                            📄 Exportar mis datos
                                        </button>
                                        <button class="btn btn-danger" onclick="confirmarEliminacion()">
                                            🗑️ Eliminar cuenta
                                        </button>
                                    </div>
                                    <p class="form-text">
                                        Puedes exportar todos tus datos o eliminar permanentemente tu cuenta
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de la cuenta -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">ℹ️ Información de la Cuenta</h3>
                        </div>
                        
                        <div class="account-info">
                            <div class="info-row">
                                <span class="info-label">ID de usuario:</span>
                                <span class="info-value">#<?php echo $usuario['id']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Fecha de registro:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Estado de la cuenta:</span>
                                <span class="info-value">
                                    <?php echo $usuario['activo'] ? '✅ Activa' : '❌ Inactiva'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tipo de cuenta:</span>
                                <span class="info-value">
                                    <?php echo $usuario['rol'] === 'admin' ? '👑 Administrador' : '👤 Usuario'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

            <?php endif; ?>

        </div>

    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones. Proyecto universitario.</p>
        </div>
    </footer>

    <script>
        // Función para cambiar tema
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);
        }

        function setTheme(theme) {
            const body = document.body;
            
            if (theme === 'dark') {
                body.classList.add('dark-theme');
            } else {
                body.classList.remove('dark-theme');
            }
            
            document.documentElement.setAttribute('data-theme', theme);
            
            // Guardar en cookie
            document.cookie = `theme=${theme}; expires=${new Date(Date.now() + 30*24*60*60*1000).toUTCString()}; path=<?php echo COOKIE_PATH; ?>`;
            
            // Actualizar botón de tema
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = theme === 'dark' ? '☀️' : '🌙';
            
            // Actualizar selector de tema
            document.querySelectorAll('.theme-option').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[onclick="setTheme('${theme}')"]`).classList.add('active');
        }

        // Validación de contraseñas
        function validarPasswords() {
            const nueva = document.getElementById('password_nueva');
            const confirmar = document.getElementById('confirmar_password');
            const mensaje = document.getElementById('password-match');
            const btn = document.getElementById('btnCambiarPassword');

            if (confirmar.value === '') {
                mensaje.textContent = '';
                mensaje.className = 'form-text';
                return;
            }

            if (nueva.value === confirmar.value) {
                mensaje.textContent = '✅ Las contraseñas coinciden';
                mensaje.className = 'form-text text-success';
                btn.disabled = false;
            } else {
                mensaje.textContent = '❌ Las contraseñas no coinciden';
                mensaje.className = 'form-text text-error';
                btn.disabled = true;
            }
        }

        // Exportar historial
        function exportarHistorial() {
            window.open('api/exportar_historial.php', '_blank');
        }

        // Exportar datos completos
        function exportarDatos() {
            if (confirm('¿Deseas exportar todos tus datos? Se generará un archivo JSON con toda tu información.')) {
                window.open('api/exportar_datos_usuario.php', '_blank');
            }
        }

        // Confirmar eliminación de cuenta
        function confirmarEliminacion() {
            if (confirm('⚠️ ¿Estás seguro de que quieres eliminar tu cuenta?\n\nEsta acción NO se puede deshacer y perderás:\n- Todo tu historial\n- Tus calificaciones y comentarios\n- Tus preferencias\n\n¿Continuar?')) {
                if (confirm('Última confirmación: ¿Realmente quieres eliminar permanentemente tu cuenta?')) {
                    window.location.href = 'auth/eliminar_cuenta.php';
                }
            }
        }

        // Configuración inicial
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Validación de contraseñas en tiempo real
            const passwordInputs = document.querySelectorAll('#password_nueva, #confirmar_password');
            passwordInputs.forEach(input => {
                input.addEventListener('input', validarPasswords);
            });

            // Validación del formulario de contraseña
            const formPassword = document.getElementById('formPassword');
            if (formPassword) {
                formPassword.addEventListener('submit', function(e) {
                    const nueva = document.getElementById('password_nueva').value;
                    const confirmar = document.getElementById('confirmar_password').value;
                    
                    if (nueva !== confirmar) {
                        e.preventDefault();
                        alert('Las contraseñas no coinciden');
                        return false;
                    }
                });
            }

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.card, .stat-card, .history-item');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>

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