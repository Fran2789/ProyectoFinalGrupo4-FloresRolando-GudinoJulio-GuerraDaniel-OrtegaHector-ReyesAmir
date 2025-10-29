<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Redirigir si ya está logueado
if (isLoggedIn()) {
    redirect('inicio.php');
}

$usuarioManager = new UsuarioManager();

// Variables para el formulario
$mensaje = '';
$tipo_mensaje = '';
$email_recordado = '';

// Obtener email recordado de la cookie
if (isset($_COOKIE['last_email'])) {
    $email_recordado = $_COOKIE['last_email'];
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $recordar = isset($_POST['recordar']);
    
    // Validaciones básicas
    if (empty($email) || empty($password)) {
        $mensaje = 'Por favor completa todos los campos';
        $tipo_mensaje = 'error';
    } else {
        $resultado = $usuarioManager->iniciarSesion($email, $password);
        
        if ($resultado['success']) {
            // Si seleccionó "recordar", guardar cookie por más tiempo
            if ($recordar) {
                setcookie('remember_login', '1', time() + (86400 * 30), COOKIE_PATH);
                setcookie('last_email', $email, time() + (86400 * 30), COOKIE_PATH);
            }
            
            // Redirigir según el rol del usuario
            if ($_SESSION['user_role'] === 'admin') {
                redirect('admin/panel_admin.php');
            } else {
                // Verificar si hay una URL de retorno
                $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : 'inicio.php';
                redirect($redirect_url);
            }
        } else {
            $mensaje = $resultado['message'];
            $tipo_mensaje = 'error';
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
    <title>Iniciar Sesión - CineRecomendaciones</title>
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
                <a href="registro_usuario.php" class="btn btn-primary">Registrarse</a>
                <a href="../inicio.php" class="btn btn-secondary">Volver al Inicio</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="auth-container">
            
            <!-- Formulario de inicio de sesión -->
            <div class="card login-card">
                <div class="card-header text-center">
                    <h1 class="card-title">¡Bienvenido de vuelta!</h1>
                    <p>Inicia sesión para acceder a tus recomendaciones personalizadas</p>
                </div>

                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="iniciar_sesion.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" id="loginForm">
                    
                    <div class="form-group">
                        <label for="email">Correo electrónico:</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               required 
                               value="<?php echo htmlspecialchars($email_recordado); ?>"
                               placeholder="tu@email.com"
                               autocomplete="email">
                        <small class="form-text">Usa el mismo email con el que te registraste</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <div class="password-input-container">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   required 
                                   placeholder="Tu contraseña"
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                👁️
                            </button>
                        </div>
                        <small class="form-text">
                            <a href="recuperar_password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                        </small>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="recordar" name="recordar" <?php echo isset($_COOKIE['remember_login']) ? 'checked' : ''; ?>>
                            <label for="recordar">
                                Mantener sesión iniciada
                            </label>
                        </div>
                        <small class="form-text">Te recordaremos por 30 días</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                        Iniciar Sesión
                    </button>
                </form>

                <div class="divider">
                    <span>o</span>
                </div>

                <div class="text-center">
                    <p>¿No tienes cuenta? <a href="registro_usuario.php" class="register-link">Regístrate gratis</a></p>
                </div>

                <!-- Login rápido para pruebas (solo en desarrollo) -->
                <?php if ($_SERVER['SERVER_NAME'] === 'localhost'): ?>
                    <div class="quick-login">
                        <h4>🚀 Acceso rápido (desarrollo)</h4>
                        <div class="quick-login-buttons">
                            <button type="button" class="btn btn-outline" onclick="loginRapido('admin@proyecto.com', 'password')">
                                Admin Demo
                            </button>
                            <button type="button" class="btn btn-outline" onclick="loginRapido('usuario@demo.com', 'password')">
                                Usuario Demo
                            </button>
                        </div>
                        <small class="form-text">Credenciales de prueba para desarrollo</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Información adicional -->
            <div class="card info-card mt-4">
                <div class="card-header text-center">
                    <h3>¿Qué puedes hacer con tu cuenta?</h3>
                </div>
                
                <div class="features-grid">
                    <div class="feature-item">
                        <span class="feature-icon">🎯</span>
                        <h4>Recomendaciones únicas</h4>
                        <p>Algoritmo inteligente que aprende de tus gustos</p>
                    </div>
                    
                    <div class="feature-item">
                        <span class="feature-icon">📊</span>
                        <h4>Estadísticas personales</h4>
                        <p>Ve tu progreso y descubre patrones en tus gustos</p>
                    </div>
                    
                    <div class="feature-item">
                        <span class="feature-icon">💬</span>
                        <h4>Reseñas y comentarios</h4>
                        <p>Comparte tu opinión y lee la de otros usuarios</p>
                    </div>
                    
                    <div class="feature-item">
                        <span class="feature-icon">🔄</span>
                        <h4>Sincronización</h4>
                        <p>Accede a tu cuenta desde cualquier dispositivo</p>
                    </div>
                </div>
            </div>

            <!-- Testimonios ficticios -->
            <div class="card testimonials-card mt-4">
                <div class="card-header text-center">
                    <h3>Lo que dicen nuestros usuarios</h3>
                </div>
                
                <div class="testimonials-grid">
                    <div class="testimonial-item">
                        <div class="testimonial-content">
                            <p>"Las recomendaciones son increíbles. He descubierto series que jamás habría encontrado por mi cuenta."</p>
                        </div>
                        <div class="testimonial-author">
                            <strong>María González</strong>
                            <span>Usuario desde 2024</span>
                        </div>
                    </div>
                    
                    <div class="testimonial-item">
                        <div class="testimonial-content">
                            <p>"La interfaz es muy intuitiva y me encanta poder llevar el registro de todo lo que veo."</p>
                        </div>
                        <div class="testimonial-author">
                            <strong>Carlos Rodríguez</strong>
                            <span>Cinéfilo activo</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones. Proyecto universitario.</p>
            <p>
                <a href="../terminos.php">Términos</a> | 
                <a href="../privacidad.php">Privacidad</a> | 
                <a href="../contacto.php">Contacto</a>
            </p>
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

        // Función para mostrar/ocultar contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Login rápido para desarrollo
        function loginRapido(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            document.getElementById('loginForm').submit();
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');

            // Validación en tiempo real
            function validarFormulario() {
                const emailValido = emailInput.value.trim() !== '' && emailInput.checkValidity();
                const passwordValido = passwordInput.value.trim() !== '';
                
                if (emailValido && passwordValido) {
                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Iniciar Sesión';
                } else {
                    loginBtn.disabled = true;
                    loginBtn.textContent = 'Completa todos los campos';
                }
            }

            emailInput.addEventListener('input', validarFormulario);
            passwordInput.addEventListener('input', validarFormulario);

            // Validación inicial
            validarFormulario();

            // Animación del botón al enviar
            loginForm.addEventListener('submit', function() {
                loginBtn.disabled = true;
                loginBtn.textContent = 'Iniciando sesión...';
                loginBtn.classList.add('loading');
            });

            // Focus automático en el primer campo vacío
            if (emailInput.value === '') {
                emailInput.focus();
            } else {
                passwordInput.focus();
            }

            // Configurar tema inicial
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            if (themeButton) {
                themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
            }

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.card, .feature-item, .testimonial-item');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 100);
            });
        });

        // Detectar Enter para enviar formulario
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.target.matches('textarea')) {
                const form = document.getElementById('loginForm');
                if (form) {
                    form.submit();
                }
            }
        });
    </script>

    <style>
        /* Estilos específicos para el login */
        .auth-container {
            max-width: 500px;
            margin: 2rem auto;
        }

        .login-card {
            position: relative;
        }

        .password-input-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--text-dark);
            padding: 5px;
        }

        .password-toggle:hover {
            opacity: 0.7;
        }

        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            background: var(--card-bg);
            padding: 0 1rem;
            color: var(--text-secondary);
        }

        .register-link {
            color: var(--accent-color);
            font-weight: 600;
            text-decoration: none;
        }

        .register-link:hover {
            text-decoration: underline;
        }

        .forgot-password {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .quick-login {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            border: 1px dashed var(--border-color);
        }

        .quick-login h4 {
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-size: 1rem;
        }

        .quick-login-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .quick-login-buttons .btn {
            flex: 1;
            padding: 0.5rem;
            font-size: 0.9rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .feature-item {
            text-align: center;
            padding: 1rem;
        }

        .feature-icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 1rem;
        }

        .feature-item h4 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .feature-item p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .testimonial-item {
            padding: 1.5rem;
            background: var(--bg-light);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--accent-color);
        }

        .testimonial-content {
            margin-bottom: 1rem;
        }

        .testimonial-content p {
            font-style: italic;
            margin: 0;
            line-height: 1.5;
        }

        .testimonial-author strong {
            display: block;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .testimonial-author span {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Dark theme adjustments */
        body.dark-theme .quick-login {
            background: var(--secondary-color);
            border-color: var(--accent-color);
        }

        body.dark-theme .testimonial-item {
            background: var(--secondary-color);
        }

        @media (max-width: 768px) {
            .auth-container {
                margin: 1rem auto;
                padding: 0 1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .quick-login-buttons {
                flex-direction: column;
            }
        }
    </style>

</body>
</html>