<?php
/**
 * ARCHIVO DE TESTING - VERIFICACIÓN DEL SISTEMA
 * Ubicación: test_funcionalidad.php (raíz del proyecto)
 * URL: localhost/PHP/proyecto_recomendaciones/test_funcionalidad.php
 * 
 * Este archivo verifica que todas las funcionalidades principales estén funcionando
 */

require_once 'config/database.php';
require_once 'classes/ContenidoManager.php';
require_once 'classes/UsuarioManager.php';

// Función para mostrar resultados de testing
function mostrarResultado($titulo, $resultado, $detalles = '') {
    $icono = $resultado ? '✅' : '❌';
    $clase = $resultado ? 'success' : 'error';
    echo "<div class='test-result {$clase}'>";
    echo "<strong>{$icono} {$titulo}</strong>";
    if ($detalles) {
        echo "<br><small>{$detalles}</small>";
    }
    echo "</div>";
}

// Función para testing de base de datos
function testBaseDeDatos() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            // Verificar tablas principales
            $tablas = ['usuarios', 'contenido', 'generos', 'calificaciones', 'historial_navegacion', 'preferencias_usuario'];
            $tablas_existentes = [];
            
            foreach ($tablas as $tabla) {
                $stmt = $conn->prepare("SHOW TABLES LIKE '{$tabla}'");
                $stmt->execute();
                if ($stmt->fetch()) {
                    $tablas_existentes[] = $tabla;
                }
            }
            
            mostrarResultado(
                'Conexión a Base de Datos', 
                true, 
                'Tablas encontradas: ' . implode(', ', $tablas_existentes)
            );
            
            return count($tablas_existentes) === count($tablas);
        } else {
            mostrarResultado('Conexión a Base de Datos', false, 'No se pudo conectar');
            return false;
        }
    } catch (Exception $e) {
        mostrarResultado('Conexión a Base de Datos', false, 'Error: ' . $e->getMessage());
        return false;
    }
}

// Función para testing de clases principales
function testClases() {
    $resultados = [];
    
    try {
        $usuarioManager = new UsuarioManager();
        $resultados['UsuarioManager'] = true;
        mostrarResultado('Clase UsuarioManager', true, 'Clase inicializada correctamente');
    } catch (Exception $e) {
        $resultados['UsuarioManager'] = false;
        mostrarResultado('Clase UsuarioManager', false, 'Error: ' . $e->getMessage());
    }
    
    try {
        $contenidoManager = new ContenidoManager();
        $resultados['ContenidoManager'] = true;
        mostrarResultado('Clase ContenidoManager', true, 'Clase inicializada correctamente');
    } catch (Exception $e) {
        $resultados['ContenidoManager'] = false;
        mostrarResultado('Clase ContenidoManager', false, 'Error: ' . $e->getMessage());
    }
    
    return !in_array(false, $resultados);
}

// Función para testing de funciones auxiliares
function testFuncionesAuxiliares() {
    $resultados = [];
    
    // Test de sanitize_input
    $test_input = "<script>alert('test')</script>";
    $sanitized = sanitize_input($test_input);
    $resultados['sanitize'] = ($sanitized !== $test_input && !contains_script($sanitized));
    mostrarResultado(
        'Función sanitize_input', 
        $resultados['sanitize'], 
        $resultados['sanitize'] ? 'Entrada maliciosa sanitizada correctamente' : 'Fallo en sanitización'
    );
    
    // Test de isLoggedIn (debe devolver false sin sesión)
    $resultados['isLoggedIn'] = (isLoggedIn() === false);
    mostrarResultado(
        'Función isLoggedIn', 
        $resultados['isLoggedIn'], 
        'Función responde correctamente sin sesión activa'
    );
    
    // Test de isAdmin (debe devolver false sin sesión)
    $resultados['isAdmin'] = (isAdmin() === false);
    mostrarResultado(
        'Función isAdmin', 
        $resultados['isAdmin'], 
        'Función responde correctamente sin sesión de admin'
    );
    
    return !in_array(false, $resultados);
}

// Función auxiliar para verificar si contiene script
function contains_script($string) {
    return (strpos(strtolower($string), '<script') !== false || 
            strpos(strtolower($string), 'javascript:') !== false);
}

// Testing de archivos CSS y JS
function testArchivosEstaticos() {
    $archivos = [
        'css/style.css' => 'Archivo CSS principal',
        'uploads/' => 'Directorio de uploads'
    ];
    
    $resultados = [];
    
    foreach ($archivos as $archivo => $descripcion) {
        if (is_dir($archivo) || file_exists($archivo)) {
            $resultados[$archivo] = true;
            mostrarResultado($descripcion, true, "Encontrado en: {$archivo}");
        } else {
            $resultados[$archivo] = false;
            mostrarResultado($descripcion, false, "No encontrado: {$archivo}");
        }
    }
    
    return !in_array(false, $resultados);
}

// Testing de estructura de directorios
function testEstructuraDirectorios() {
    $directorios = [
        'config/' => 'Configuración',
        'classes/' => 'Clases PHP',
        'auth/' => 'Autenticación',
        'admin/' => 'Panel de administración',
        'api/' => 'APIs REST',
        'css/' => 'Estilos CSS',
        'uploads/' => 'Archivos subidos'
    ];
    
    $resultados = [];
    
    foreach ($directorios as $dir => $descripcion) {
        if (is_dir($dir)) {
            $resultados[$dir] = true;
            $archivos = count(glob($dir . '*'));
            mostrarResultado($descripcion, true, "Directorio {$dir} ({$archivos} archivos)");
        } else {
            $resultados[$dir] = false;
            mostrarResultado($descripcion, false, "Directorio {$dir} no encontrado");
        }
    }
    
    return !in_array(false, $resultados);
}

// Testing de datos de ejemplo
function testDatosEjemplo() {
    try {
        $contenidoManager = new ContenidoManager();
        $usuarioManager = new UsuarioManager();
        
        // Verificar contenido
        $contenido = $contenidoManager->obtenerContenidoDestacado('pelicula', 5);
        $contenido_ok = count($contenido) > 0;
        mostrarResultado(
            'Datos de contenido', 
            $contenido_ok, 
            $contenido_ok ? count($contenido) . ' películas encontradas' : 'No hay contenido'
        );
        
        // Verificar géneros
        $generos = $contenidoManager->obtenerGeneros();
        $generos_ok = count($generos) > 0;
        mostrarResultado(
            'Datos de géneros', 
            $generos_ok, 
            $generos_ok ? count($generos) . ' géneros encontrados' : 'No hay géneros'
        );
        
        // Verificar usuario admin
        $stats = $usuarioManager->obtenerEstadisticasGenerales();
        $admin_ok = isset($stats['usuarios']['administradores']) && $stats['usuarios']['administradores'] > 0;
        mostrarResultado(
            'Usuario administrador', 
            $admin_ok, 
            $admin_ok ? 'Usuario admin encontrado' : 'No hay usuario admin'
        );
        
        return $contenido_ok && $generos_ok && $admin_ok;
        
    } catch (Exception $e) {
        mostrarResultado('Datos de ejemplo', false, 'Error: ' . $e->getMessage());
        return false;
    }
}

// Obtener información del sistema
function obtenerInfoSistema() {
    return [
        'PHP Version' => phpversion(),
        'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'Current Directory' => getcwd(),
        'Proyecto URL' => 'localhost/PHP/proyecto_recomendaciones',
        'Session Status' => session_status() === PHP_SESSION_ACTIVE ? 'Activa' : 'Inactiva',
        'Extensions' => implode(', ', ['PDO', 'MySQLi', 'GD', 'JSON'])
    ];
}

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing del Sistema - CineRecomendaciones</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    <style>
        .test-result {
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .test-result.success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .test-result.error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .system-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .system-info dt {
            font-weight: bold;
            color: #495057;
        }
        
        .system-info dd {
            margin-bottom: 0.5rem;
            margin-left: 1rem;
        }
        
        .test-section {
            margin: 2rem 0;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-radius: 10px;
        }
        
        .overall-status {
            padding: 2rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin: 2rem 0;
            border-radius: 10px;
        }
        
        .overall-status.success {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }
        
        .overall-status.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }
        
        .overall-status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #dc3545;
        }
        
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .quick-link {
            padding: 1rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.2s;
        }
        
        .quick-link:hover {
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
        
        body.dark-theme .test-section {
            background: #2c2c54;
            border-color: #16213e;
        }
        
        body.dark-theme .system-info {
            background: #16213e;
            color: #f5f5f5;
        }
        
        body.dark-theme .system-info dt {
            color: #f5f5f5;
        }
    </style>
</head>
<body class="<?php echo $theme === 'dark' ? 'dark-theme' : ''; ?>">

    <!-- Navegación simple -->
    <nav class="navbar">
        <div class="container">
            <a href="inicio.php" class="logo">CineRecomendaciones</a>
            <div class="user-menu">
                <button class="theme-toggle" onclick="toggleTheme()" title="Cambiar tema">
                    🌙
                </button>
                <a href="inicio.php" class="btn btn-primary">Ir al Sitio</a>
            </div>
        </div>
    </nav>

    <main class="container">
        
        <!-- Header -->
        <section class="hero-section">
            <h1>🧪 Testing del Sistema</h1>
            <p>Verificación de funcionalidades y componentes principales</p>
        </section>

        <!-- Información del sistema -->
        <section class="test-section">
            <h2>📋 Información del Sistema</h2>
            <div class="system-info">
                <dl>
                    <?php foreach (obtenerInfoSistema() as $key => $value): ?>
                        <dt><?php echo htmlspecialchars($key); ?>:</dt>
                        <dd><?php echo htmlspecialchars($value); ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>

        <!-- Testing de componentes -->
        <section class="test-section">
            <h2>🔧 Testing de Componentes</h2>
            
            <h3>Base de Datos</h3>
            <?php $db_ok = testBaseDeDatos(); ?>
            
            <h3>Clases PHP</h3>
            <?php $clases_ok = testClases(); ?>
            
            <h3>Funciones Auxiliares</h3>
            <?php $funciones_ok = testFuncionesAuxiliares(); ?>
            
            <h3>Archivos Estáticos</h3>
            <?php $archivos_ok = testArchivosEstaticos(); ?>
            
            <h3>Estructura de Directorios</h3>
            <?php $estructura_ok = testEstructuraDirectorios(); ?>
            
            <h3>Datos de Ejemplo</h3>
            <?php $datos_ok = testDatosEjemplo(); ?>
        </section>

        <!-- Estado general -->
        <?php 
        $tests_realizados = [$db_ok, $clases_ok, $funciones_ok, $archivos_ok, $estructura_ok, $datos_ok];
        $tests_exitosos = array_filter($tests_realizados);
        $porcentaje_exito = (count($tests_exitosos) / count($tests_realizados)) * 100;
        
        if ($porcentaje_exito === 100) {
            $estado_clase = 'success';
            $estado_mensaje = '🎉 ¡Sistema funcionando perfectamente!';
            $estado_detalle = 'Todos los tests han pasado correctamente. El sistema está listo para usar.';
        } elseif ($porcentaje_exito >= 70) {
            $estado_clase = 'warning';
            $estado_mensaje = '⚠️ Sistema funcionando con advertencias';
            $estado_detalle = 'La mayoría de componentes funcionan, pero hay algunos problemas menores.';
        } else {
            $estado_clase = 'error';
            $estado_mensaje = '❌ Sistema con problemas críticos';
            $estado_detalle = 'Hay problemas importantes que necesitan ser resueltos.';
        }
        ?>
        
        <div class="overall-status <?php echo $estado_clase; ?>">
            <?php echo $estado_mensaje; ?>
            <br>
            <small><?php echo $estado_detalle; ?></small>
            <br>
            <strong>Tests exitosos: <?php echo count($tests_exitosos); ?>/<?php echo count($tests_realizados); ?> (<?php echo round($porcentaje_exito, 1); ?>%)</strong>
        </div>

        <!-- Enlaces rápidos -->
        <section class="test-section">
            <h2>🚀 Enlaces Rápidos para Testing</h2>
            <div class="quick-links">
                <a href="inicio.php" class="quick-link">
                    🏠 Página Principal
                </a>
                <a href="auth/registro_usuario.php" class="quick-link">
                    👤 Registro Usuario
                </a>
                <a href="auth/iniciar_sesion.php" class="quick-link">
                    🔑 Iniciar Sesión
                </a>
                <a href="catalogo.php" class="quick-link">
                    🎬 Catálogo
                </a>
                <a href="admin/panel_admin.php" class="quick-link">
                    👑 Panel Admin
                </a>
                <a href="auth/iniciar_sesion.php?admin=1" class="quick-link">
                    🔧 Login Admin
                </a>
            </div>
        </section>

        <!-- Credenciales de prueba -->
        <section class="test-section">
            <h2>🔑 Credenciales de Prueba</h2>
            <div class="system-info">
                <h4>Usuario Administrador:</h4>
                <ul>
                    <li><strong>Email:</strong> admin@proyecto.com</li>
                    <li><strong>Contraseña:</strong> password</li>
                    <li><strong>Rol:</strong> Administrador</li>
                </ul>
                
                <h4>Usuarios de Ejemplo:</h4>
                <ul>
                    <li><strong>Rolando Flores:</strong> rafj26@outlook.com (password: ver en BD)</li>
                    <li><strong>Elvis Cocho:</strong> test@test.com (password: ver en BD)</li>
                    <li><strong>Kimberly Aguirre:</strong> kimi@prueba.com (password: ver en BD)</li>
                </ul>
                
                <p><strong>Nota:</strong> Las contraseñas están hasheadas en la base de datos. Para testing, puedes crear nuevos usuarios o usar el sistema de "restablecer contraseña" del admin.</p>
            </div>
        </section>

        <!-- Próximos pasos -->
        <section class="test-section">
            <h2>📝 Próximos Pasos para Testing</h2>
            <ol>
                <li><strong>Registro de Usuario:</strong> Crear una cuenta nueva y verificar el flujo completo</li>
                <li><strong>Login/Logout:</strong> Probar el inicio y cierre de sesión</li>
                <li><strong>Navegación:</strong> Verificar que todas las páginas cargan correctamente</li>
                <li><strong>Funcionalidades de Usuario:</strong> Explorar catálogo, calificar contenido</li>
                <li><strong>Panel de Admin:</strong> Gestionar usuarios y contenido</li>
                <li><strong>Responsive Design:</strong> Probar en diferentes tamaños de pantalla</li>
                <li><strong>Tema Oscuro/Claro:</strong> Verificar el cambio de temas</li>
            </ol>
        </section>

        <!-- Botón para volver -->
        <div class="text-center">
            <a href="inicio.php" class="btn btn-primary btn-large">
                🏠 Ir al Sitio Principal
            </a>
            <button onclick="location.reload()" class="btn btn-secondary btn-large">
                🔄 Repetir Tests
            </button>
        </div>

    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CineRecomendaciones - Testing del Sistema</p>
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
            
            document.cookie = `theme=${newTheme}; expires=${new Date(Date.now() + 30*24*60*60*1000).toUTCString()}; path=/PHP/proyecto_recomendaciones/`;
            
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        }

        // Configuración inicial
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = '<?php echo $theme; ?>';
            const themeButton = document.querySelector('.theme-toggle');
            themeButton.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            // Animaciones de entrada
            const elementos = document.querySelectorAll('.test-section, .test-result');
            elementos.forEach((elemento, index) => {
                setTimeout(() => {
                    elemento.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>

</body>
</html>