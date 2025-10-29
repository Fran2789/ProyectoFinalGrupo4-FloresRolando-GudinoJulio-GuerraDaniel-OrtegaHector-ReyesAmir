<?php
// Configuración de la base de datos
class Database {
    private $host = "localhost";
    private $db_name = "proyecto_recomendaciones";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                  $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Configuración de constantes globales
define('BASE_URL', 'http://localhost/PHP/proyecto_recomendaciones/');
define('UPLOAD_PATH', 'uploads/');
define('SESSION_TIMEOUT', 3600); // 1 hora

// Configuración de cookies
define('COOKIE_EXPIRE', time() + (86400 * 30)); // 30 días
define('COOKIE_PATH', '/PHP/proyecto_recomendaciones/');

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Función para limpiar datos de entrada (seguridad)
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar si es administrador
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Función para redirigir
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}
?>