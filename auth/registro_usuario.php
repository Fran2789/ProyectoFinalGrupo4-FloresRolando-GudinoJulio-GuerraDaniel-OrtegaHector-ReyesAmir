<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';

// Redirigir si ya está logueado
if (isLoggedIn()) {
    redirect('inicio.php');
}

$usuarioManager = new UsuarioManager();
$contenidoManager = new ContenidoManager();

// Obtener géneros para el formulario
$generos = $contenidoManager->obtenerGeneros();

// Variables para el formulario
$mensaje = '';
$tipo_mensaje = '';
$mostrar_preferencias = false;
$usuario_registrado_id = null;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['paso']) && $_POST['paso'] === '1') {
        // Paso 1: Registro básico
        $nombre = sanitize_input($_POST['nombre']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirmar_password = $_POST['confirmar_password'];
        
        // Validaciones adicionales del frontend
        if ($password !== $confirmar_password) {
            $mensaje = 'Las contraseñas no coinciden';
            $tipo_mensaje = 'error';
        } else {
            $resultado = $usuarioManager->registrarUsuario($nombre, $email, $password);
            
            if ($resultado['success']) {
                $mostrar_preferencias = true;
                $usuario_registrado_id = $resultado['usuario_id'];
                $mensaje = $resultado['message'] . '. Ahora selecciona tus géneros favoritos:';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = $resultado['message'];
                $tipo_mensaje = 'error';
            }
        }
        
    } elseif (isset($_POST['paso']) && $_POST['paso'] === '2') {
        // Paso 2: Guardar preferencias
        $usuario_id = sanitize_input($_POST['usuario_id']);
        $generos_seleccionados = isset($_POST['generos']) ? $_POST['generos'] : [];
        
        if (!empty($generos_seleccionados)) {
            $resultado = $usuarioManager->guardarPreferencias($usuario_id, $generos_seleccionados);
            
            if ($resultado['success']) {
                // Iniciar sesión automáticamente
                $usuario = $usuarioManager->obtenerUsuario($usuario_id);
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_name'] = $usuario['nombre'];
                $_SESSION['user_email'] = $usuario['email'];
                $_SESSION['user_role'] = $usuario['rol'];
                $_SESSION['login_time'] = time();
                
                redirect('inicio.php');
            } else {
                $mensaje = $resultado['message'];
                $tipo_mensaje = 'error';
            }
        } else {
            // Si no selecciona géneros, igual continuar pero sin preferencias
            $usuario = $usuarioManager->obtenerUsuario($usuario_id);
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nombre'];
            $_SESSION['user_email'] = $usuario['email'];
            $_SESSION['user_role'] = $usuario['rol'];
            $_SESSION['login_time'] = time();
            
            redirect('inicio.php');
        }
    }
}

// Obtener tema de la cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - CineRecomendaciones</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
</head>
<body class="<?php echo $theme === 'dark' ? 'dark-theme' : ''; ?>">

    <!-- Navegación simplificada -->
    <nav class="navbar">
        <div class="container">
            <a href="../inicio.php" class="logo">CineRecomendaciones</a>
            
            <div class="user-menu">
                <button class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                    🌙
                </button>
                <a href="iniciar_sesion.php" class="btn btn-outline">Iniciar Sesión</a>
                <a href="../inicio.php" class="btn btn-secondary">Volver al Inicio</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="auth-container">
            
            <?php if (!$mostrar_preferencias): ?>
                <!-- PASO 1: Formulario de registro básico -->
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="card-title">¡Únete a CineRecomendaciones!</h1>
                        <p>Crea tu cuenta y descubre películas y series perfectas para ti</p>
                    </div>

                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                            <?php echo htmlspecialchars($mensaje); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="registro_usuario.php" id="registroForm">
                        <input type="hidden" name="paso" value="1">
                        
                        <div class="form-group">
                            <label for="nombre">Nombre completo:</label>
                            <input type="text" 
                                   id="nombre" 
                                   name="nombre" 
                                   class="form-control" 
                                   required 
                                   maxlength="100"
                                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                                   placeholder="Ej: Juan Pérez">
                            <small class="form-text">Tu nombre será visible en tus reseñas</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Correo electrónico:</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   required 
                                   maxlength="150"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="tu@email.com">
                            <small class="form-text">Usaremos tu email para las recomendaciones personalizadas</small>
                        </div>

                        <div class="form-group">
                            <label for="password">Contraseña:</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   required 
                                   minlength="6"
                                   placeholder="Mínimo 6 caracteres">
                            <small class="form-text">Debe tener al menos 6 caracteres</small>
                        </div>

                        <div class="form-group">
                            <label for="confirmar_password">Confirmar contraseña:</label>
                            <input type="password" 
                                   id="confirmar_password" 
                                   name="confirmar_password" 
                                   class="form-control" 
                                   required 
                                   minlength="6"
                                   placeholder="Repite tu contraseña">
                            <div id="password-match-message" class="form-text"></div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="terminos" required>
                                <label for="terminos">
                                    Acepto los términos de servicio y la política de privacidad
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="newsletter" name="newsletter" checked>
                                <label for="newsletter">
                                    Quiero recibir recomendaciones personalizadas por email
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Crear mi cuenta
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <p>¿Ya tienes cuenta? <a href="iniciar_sesion.php">Inicia sesión aquí</a></p>
                    </div>
                </div>

            <?php else: ?>
                <!-- PASO 2: Selección de géneros favoritos -->
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="card-title">🎭 ¡Personaliza tu experiencia!</h1>
                        <p>Selecciona tus géneros favoritos para recibir mejores recomendaciones</p>
                    </div>

                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                            <?php echo htmlspecialchars($mensaje); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="registro_usuario.php" id="preferenciasForm">
                        <input type="hidden" name="paso" value="2">
                        <input type="hidden" name="usuario_id" value="<?php echo $usuario_registrado_id; ?>">
                        
                        <div class="form-group">
                            <label>Selecciona al menos 3 géneros que te gusten:</label>
                            <div class="generos-grid">
                                <?php foreach ($generos as $genero): ?>
                                    <div class="form-check genero-item">
                                        <input type="checkbox" 
                                               id="genero_<?php echo $genero['id']; ?>" 
                                               name="generos[]" 
                                               value="<?php echo $genero['id']; ?>"
                                               class="genero-checkbox">
                                        <label for="genero_<?php echo $genero['id']; ?>" class="genero-label">
                                            <span class="genero-icon"><?php echo obtenerIconoGenero($genero['nombre']); ?></span>
                                            <span class="genero-nombre"><?php echo htmlspecialchars($genero['nombre']); ?></span>
                                            <span class="genero-descripcion"><?php echo htmlspecialchars($genero['descripcion']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="form-text">Puedes cambiar tus preferencias después en tu perfil</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="guardarPreferencias" disabled>
                                Finalizar registro
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="omitirPreferencias()">
                                Omitir por ahora
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

            <!-- Beneficios de registrarse -->
            <div class="card benefits-card mt-4">
                <div class="card-header text-center">
                    <h3>¿Por qué registrarte?</h3>
                </div>
                
                <div class="benefits-grid">
                    <div class="benefit-item">
                        <span class="benefit-icon">🎯</span>
                        <h4>Recomendaciones personalizadas</h4>
                        <p>Descubre contenido basado en tus gustos únicos</p>
                    </div>
                    
                    <div class="benefit-item">
                        <span class="benefit-icon">⭐</span>
                        <h4>Califica y comenta</h4>
                        <p>Comparte tu opinión y ayuda a otros usuarios</p>
                    </div>
                    
                    <div class="benefit-item">
                        <span class="benefit-icon">📚</span>
                        <h4>Historial personal</h4>
                        <p>Lleva el registro de lo que has visto</p>
                    </div>
                    
                    <div class="benefit-item">
                        <span class="benefit-icon">🔒</span>
                        <h4>100% Gratis y seguro</h4>
                        <p>Tu privacidad es nuestra prioridad</p>
                    </div>
                </div>
            </div>
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
            
            body.classList.toggle('dark-theme');
            document.documentElement.setAttribute('data-theme', newTheme);
            
            // Guardar en cookie
            document.cookie = `theme=${newTheme}; expires=${new Date(Date.now() + 30*24*60*60*1000).toUTCString()}; path=<?php echo COOKIE_PATH; ?>`;
            
            // Cambiar icono del botón
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }

        // Validación de contraseñas en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmarPassword = document.getElementById('confirmar_password');
            const matchMessage = document.getElementById('password-match-message');
            const registroForm = document.getElementById('registroForm');

            function validarPasswords() {
                if (confirmarPassword.value === '') {
                    matchMessage.textContent = '';
                    matchMessage.className = 'form-text';
                    return;
                }

                if (password.value === confirmarPassword.value) {
                    matchMessage.textContent = '✅ Las contraseñas coinciden';
                    matchMessage.className = 'form-text text-success';
                } else {
                    matchMessage.textContent = '❌ Las contraseñas no coinciden';
                    matchMessage.className = 'form-text text-error';
                }
            }

            if (password && confirmarPassword) {
                password.addEventListener('input', validarPasswords);
                confirmarPassword.addEventListener('input', validarPasswords);

                // Validación del formulario
                registroForm.addEventListener('submit', function(e) {
                    if (password.value !== confirmarPassword.value) {
                        e.preventDefault();
                        alert('Las contraseñas no coinciden');
                        return false;
                    }
                });
            }

            // Validación de selección de géneros
            const checkboxes = document.querySelectorAll('.genero-checkbox');
            const guardarBtn = document.getElementById('guardarPreferencias');

            if (checkboxes.length > 0 && guardarBtn) {
                function validarSeleccion() {
                    const seleccionados = document.querySelectorAll('.genero-checkbox:checked');
                    const minimo = 3;
                    
                    if (seleccionados.length >= minimo) {
                        guardarBtn.disabled = false;
                        guardarBtn.textContent = `Continuar (${seleccionados.length} géneros seleccionados)`;
                    } else {
                        guardarBtn.disabled = true;
                        guardarBtn.textContent = `Selecciona al menos ${minimo} géneros (${seleccionados.length}/${minimo})`;
                    }
                }

                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', validarSeleccion);
                });

                // Validación inicial
                validarSeleccion();
            }

            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            if (themeButton) {
                themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
            }
        });

        // Función para omitir preferencias
        function omitirPreferencias() {
            if (confirm('¿Estás seguro? Podrás configurar tus preferencias después en tu perfil.')) {
                // Enviar formulario sin géneros seleccionados
                const form = document.getElementById('preferenciasForm');
                form.submit();
            }
        }

        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elementos = document.querySelectorAll('.card, .benefit-item');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>

    <style>
        /* Estilos específicos para el registro */
        .auth-container {
            max-width: 600px;
            margin: 2rem auto;
        }

        .generos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .genero-item {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1rem;
            transition: var(--transition);
            cursor: pointer;
            background: var(--card-bg);
        }

        .genero-item:hover {
            border-color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .genero-item input:checked + .genero-label {
            color: var(--accent-color);
            font-weight: bold;
        }

        .genero-item input:checked {
            accent-color: var(--accent-color);
        }

        .genero-label {
            display: block;
            cursor: pointer;
            margin: 0;
        }

        .genero-icon {
            font-size: 2rem;
            display: block;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .genero-nombre {
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }

        .genero-descripcion {
            font-size: 0.9rem;
            color: var(--text-secondary);
            display: block;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .benefit-item {
            text-align: center;
            padding: 1rem;
        }

        .benefit-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
        }

        .benefit-item h4 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .benefit-item p {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .text-success {
            color: #28a745 !important;
        }

        .text-error {
            color: #dc3545 !important;
        }

        .form-text {
            font-size: 0.875rem;
            margin-top: 0.25rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .auth-container {
                margin: 1rem auto;
                padding: 0 1rem;
            }

            .generos-grid {
                grid-template-columns: 1fr;
            }

            .benefits-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
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