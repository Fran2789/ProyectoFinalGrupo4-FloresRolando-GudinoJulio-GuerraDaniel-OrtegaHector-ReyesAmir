<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect('inicio.php');
}

$usuarioManager = new UsuarioManager();

// Procesar el cierre de sesión
$resultado = $usuarioManager->cerrarSesion();

// Opcional: Limpiar cookies de recordar sesión
if (isset($_COOKIE['remember_login'])) {
    setcookie('remember_login', '', time() - 3600, COOKIE_PATH);
}

// También podemos mantener el tema y última email por conveniencia
// pero limpiar otros datos sensibles

// Redirigir a la página principal con mensaje de confirmación
$mensaje = urlencode('Sesión cerrada correctamente. ¡Hasta pronto!');
redirect('inicio.php?mensaje=' . $mensaje);
?>