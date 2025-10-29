<?php
require_once __DIR__ . '/../config/database.php';

class UsuarioManager {
    private $conn;
    private $table_usuarios = "usuarios";
    private $table_preferencias = "preferencias_usuario";
    private $table_historial = "historial_navegacion";
    private $table_calificaciones = "calificaciones";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Registrar nuevo usuario
     */
    public function registrarUsuario($nombre, $email, $password) {
        try {
            // Verificar si el email ya existe
            if ($this->existeEmail($email)) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }

            // Validar datos
            if (empty($nombre) || empty($email) || empty($password)) {
                return ['success' => false, 'message' => 'Todos los campos son obligatorios'];
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Email inválido'];
            }

            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres'];
            }

            // Hashear contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insertar usuario
            $sql = "INSERT INTO " . $this->table_usuarios . " (nombre, email, password) 
                    VALUES (:nombre, :email, :password)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":nombre", $nombre);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $password_hash);
            
            if ($stmt->execute()) {
                $usuario_id = $this->conn->lastInsertId();
                return [
                    'success' => true, 
                    'message' => 'Usuario registrado exitosamente',
                    'usuario_id' => $usuario_id
                ];
            } else {
                return ['success' => false, 'message' => 'Error al registrar usuario'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en registrarUsuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Verificar si un email ya existe
     */
    public function existeEmail($email) {
        try {
            $sql = "SELECT id FROM " . $this->table_usuarios . " WHERE email = :email";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            return $stmt->fetch() !== false;
            
        } catch(PDOException $e) {
            error_log("Error en existeEmail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Iniciar sesión
     */
    public function iniciarSesion($email, $password) {
        try {
            $sql = "SELECT id, nombre, email, password, rol, activo 
                    FROM " . $this->table_usuarios . " 
                    WHERE email = :email AND activo = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($password, $usuario['password'])) {
                // Iniciar sesión
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_name'] = $usuario['nombre'];
                $_SESSION['user_email'] = $usuario['email'];
                $_SESSION['user_role'] = $usuario['rol'];
                $_SESSION['login_time'] = time();
                
                // Guardar cookie de recordar usuario (opcional)
                setcookie('last_email', $email, COOKIE_EXPIRE, COOKIE_PATH);
                
                return [
                    'success' => true, 
                    'message' => 'Sesión iniciada correctamente',
                    'usuario' => $usuario
                ];
            } else {
                return ['success' => false, 'message' => 'Email o contraseña incorrectos'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en iniciarSesion: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Cerrar sesión
     */
    public function cerrarSesion() {
        // Destruir todas las variables de sesión
        $_SESSION = array();
        
        // Borrar cookie de sesión si existe
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir la sesión
        session_destroy();
        
        return ['success' => true, 'message' => 'Sesión cerrada correctamente'];
    }

    /**
     * Obtener información del usuario
     */
    public function obtenerUsuario($id) {
        try {
            $sql = "SELECT id, nombre, email, rol, fecha_registro, activo 
                    FROM " . $this->table_usuarios . " 
                    WHERE id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerUsuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar perfil de usuario
     */
    public function actualizarPerfil($usuario_id, $nombre, $email) {
        try {
            // Verificar si el nuevo email ya existe (si cambió)
            $usuario_actual = $this->obtenerUsuario($usuario_id);
            if ($usuario_actual['email'] !== $email && $this->existeEmail($email)) {
                return ['success' => false, 'message' => 'El email ya está en uso'];
            }

            $sql = "UPDATE " . $this->table_usuarios . " 
                    SET nombre = :nombre, email = :email 
                    WHERE id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":nombre", $nombre);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":id", $usuario_id);
            
            if ($stmt->execute()) {
                // Actualizar sesión
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_email'] = $email;
                
                return ['success' => true, 'message' => 'Perfil actualizado correctamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar perfil'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en actualizarPerfil: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword($usuario_id, $password_actual, $password_nueva) {
        try {
            // Verificar contraseña actual
            $sql = "SELECT password FROM " . $this->table_usuarios . " WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $usuario_id);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
                return ['success' => false, 'message' => 'Contraseña actual incorrecta'];
            }

            if (strlen($password_nueva) < 6) {
                return ['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres'];
            }

            // Actualizar contraseña
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            
            $sql = "UPDATE " . $this->table_usuarios . " SET password = :password WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":password", $password_hash);
            $stmt->bindParam(":id", $usuario_id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar contraseña'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en cambiarPassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Guardar preferencias de usuario
     */
    public function guardarPreferencias($usuario_id, $generos_seleccionados) {
        try {
            // Comenzar transacción
            $this->conn->beginTransaction();
            
            // Eliminar preferencias actuales
            $sql_delete = "DELETE FROM " . $this->table_preferencias . " WHERE usuario_id = :usuario_id";
            $stmt_delete = $this->conn->prepare($sql_delete);
            $stmt_delete->bindParam(":usuario_id", $usuario_id);
            $stmt_delete->execute();
            
            // Insertar nuevas preferencias
            if (!empty($generos_seleccionados)) {
                $sql_insert = "INSERT INTO " . $this->table_preferencias . " (usuario_id, genero_id) 
                              VALUES (:usuario_id, :genero_id)";
                $stmt_insert = $this->conn->prepare($sql_insert);
                
                foreach ($generos_seleccionados as $genero_id) {
                    $stmt_insert->bindParam(":usuario_id", $usuario_id);
                    $stmt_insert->bindParam(":genero_id", $genero_id);
                    $stmt_insert->execute();
                }
            }
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Preferencias guardadas correctamente'];
            
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Error en guardarPreferencias: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar preferencias'];
        }
    }

    /**
     * Obtener preferencias de usuario
     */
    public function obtenerPreferencias($usuario_id) {
        try {
            $sql = "SELECT p.genero_id, g.nombre 
                    FROM " . $this->table_preferencias . " p
                    LEFT JOIN generos g ON p.genero_id = g.id
                    WHERE p.usuario_id = :usuario_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerPreferencias: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function obtenerEstadisticasUsuario($usuario_id) {
        try {
            $estadisticas = [];
            
            // Contenido visto
            $sql = "SELECT COUNT(DISTINCT contenido_id) as contenido_visto 
                    FROM " . $this->table_historial . " 
                    WHERE usuario_id = :usuario_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->execute();
            $estadisticas['contenido_visto'] = $stmt->fetch(PDO::FETCH_ASSOC)['contenido_visto'];
            
            // Calificaciones dadas
            $sql = "SELECT COUNT(*) as calificaciones_dadas, AVG(calificacion) as calificacion_promedio
                    FROM " . $this->table_calificaciones . " 
                    WHERE usuario_id = :usuario_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $estadisticas['calificaciones_dadas'] = $result['calificaciones_dadas'];
            $estadisticas['calificacion_promedio'] = round($result['calificacion_promedio'], 1);
            
            // Género favorito
            $sql = "SELECT g.nombre, COUNT(*) as veces_visto
                    FROM " . $this->table_historial . " h
                    LEFT JOIN contenido c ON h.contenido_id = c.id
                    LEFT JOIN generos g ON c.genero_id = g.id
                    WHERE h.usuario_id = :usuario_id AND g.nombre IS NOT NULL
                    GROUP BY g.id, g.nombre
                    ORDER BY veces_visto DESC
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->execute();
            $genero_favorito = $stmt->fetch(PDO::FETCH_ASSOC);
            $estadisticas['genero_favorito'] = $genero_favorito ? $genero_favorito['nombre'] : 'N/A';
            
            return $estadisticas;
            
        } catch(PDOException $e) {
            error_log("Error en obtenerEstadisticasUsuario: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerActividadReciente($usuario_id, $limite = 10) {
    try {
        $actividad = [];
        
        // Obtener calificaciones recientes
        $sql_calificaciones = "SELECT 
                                c.calificacion,
                                c.comentario,
                                c.fecha_calificacion as fecha,
                                cont.titulo,
                                cont.tipo,
                                cont.poster,
                                'calificacion' as tipo_actividad
                              FROM " . $this->table_calificaciones . " c
                              LEFT JOIN contenido cont ON c.contenido_id = cont.id
                              WHERE c.usuario_id = :usuario_id
                              ORDER BY c.fecha_calificacion DESC
                              LIMIT :limite";
        
        $stmt = $this->conn->prepare($sql_calificaciones);
        $stmt->bindParam(":usuario_id", $usuario_id);
        $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $calificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar calificaciones a la actividad
        foreach ($calificaciones as $cal) {
            $actividad[] = [
                'tipo' => 'calificacion',
                'titulo' => $cal['titulo'],
                'tipo_contenido' => $cal['tipo'],
                'poster' => $cal['poster'],
                'calificacion' => $cal['calificacion'],
                'comentario' => $cal['comentario'],
                'fecha' => $cal['fecha'],
                'descripcion' => "Calificó \"" . $cal['titulo'] . "\" con " . $cal['calificacion'] . " estrellas"
            ];
        }
        
        // Obtener historial reciente (contenido visto)
        $sql_historial = "SELECT 
                            h.fecha_visita as fecha,
                            cont.titulo,
                            cont.tipo,
                            cont.poster,
                            cont.id as contenido_id,
                            'visita' as tipo_actividad
                          FROM " . $this->table_historial . " h
                          LEFT JOIN contenido cont ON h.contenido_id = cont.id
                          WHERE h.usuario_id = :usuario_id
                          ORDER BY h.fecha_visita DESC
                          LIMIT :limite";
        
        $stmt_historial = $this->conn->prepare($sql_historial);
        $stmt_historial->bindParam(":usuario_id", $usuario_id);
        $stmt_historial->bindParam(":limite", $limite, PDO::PARAM_INT);
        $stmt_historial->execute();
        
        $historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
        
        // Agregar historial a la actividad (solo los más recientes para evitar spam)
        $historial_limitado = array_slice($historial, 0, 5);
        foreach ($historial_limitado as $hist) {
            $actividad[] = [
                'tipo' => 'visita',
                'titulo' => $hist['titulo'],
                'tipo_contenido' => $hist['tipo'],
                'poster' => $hist['poster'],
                'contenido_id' => $hist['contenido_id'],
                'fecha' => $hist['fecha'],
                'descripcion' => "Vio \"" . $hist['titulo'] . "\""
            ];
        }
        
        // Ordenar toda la actividad por fecha (más reciente primero)
        usort($actividad, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
        
        // Limitar al número solicitado
        return array_slice($actividad, 0, $limite);
        
    } catch(PDOException $e) {
        error_log("Error en obtenerActividadReciente: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener configuración del usuario (método adicional que también podría faltar)
 */
public function obtenerConfiguracionUsuario($usuario_id) {
    try {
        // Por ahora retornar configuración básica
        // En el futuro se puede expandir para incluir más configuraciones
        return [
            'notificaciones_email' => true,
            'perfil_publico' => false,
            'mostrar_actividad' => true,
            'tema_preferido' => 'auto'
        ];
        
    } catch(PDOException $e) {
        error_log("Error en obtenerConfiguracionUsuario: " . $e->getMessage());
        return [];
    }
}

    /**
     * Verificar tiempo de sesión (para logout automático)
     */
    public function verificarSesion() {
        if (!isLoggedIn()) {
            return false;
        }
        
        $tiempo_limite = SESSION_TIMEOUT;
        $tiempo_transcurrido = time() - $_SESSION['login_time'];
        
        if ($tiempo_transcurrido > $tiempo_limite) {
            $this->cerrarSesion();
            return false;
        }
        
        // Renovar tiempo de sesión
        $_SESSION['login_time'] = time();
        return true;
    }

    /**
     * Obtener todos los usuarios (para administradores)
     */
    public function obtenerTodosUsuarios($pagina = 1, $por_pagina = 20, $filtros = []) {
        try {
            $offset = ($pagina - 1) * $por_pagina;
            
            $sql = "SELECT id, nombre, email, rol, fecha_registro, activo,
                    (SELECT COUNT(*) FROM " . $this->table_historial . " WHERE usuario_id = u.id) as contenido_visto,
                    (SELECT COUNT(*) FROM " . $this->table_calificaciones . " WHERE usuario_id = u.id) as calificaciones_dadas
                    FROM " . $this->table_usuarios . " u
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($filtros['busqueda'])) {
                $sql .= " AND (u.nombre LIKE :busqueda OR u.email LIKE :busqueda)";
                $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
            }
            
            if (!empty($filtros['rol'])) {
                $sql .= " AND u.rol = :rol";
                $params[':rol'] = $filtros['rol'];
            }
            
            if (isset($filtros['activo'])) {
                $sql .= " AND u.activo = :activo";
                $params[':activo'] = $filtros['activo'];
            }
            
            $sql .= " ORDER BY u.fecha_registro DESC LIMIT :offset, :por_pagina";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
            
            $stmt->execute();
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total para paginación
            $sql_count = "SELECT COUNT(*) as total FROM " . $this->table_usuarios . " u WHERE 1=1";
            
            foreach ($filtros as $key => $value) {
                if (!empty($value)) {
                    switch ($key) {
                        case 'busqueda':
                            $sql_count .= " AND (u.nombre LIKE '%{$value}%' OR u.email LIKE '%{$value}%')";
                            break;
                        case 'rol':
                            $sql_count .= " AND u.rol = '{$value}'";
                            break;
                        case 'activo':
                            $sql_count .= " AND u.activo = {$value}";
                            break;
                    }
                }
            }
            
            $stmt_count = $this->conn->prepare($sql_count);
            $stmt_count->execute();
            $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'usuarios' => $usuarios,
                'total' => $total,
                'pagina_actual' => $pagina,
                'por_pagina' => $por_pagina,
                'total_paginas' => ceil($total / $por_pagina)
            ];
            
        } catch(PDOException $e) {
            error_log("Error en obtenerTodosUsuarios: " . $e->getMessage());
            return [
                'usuarios' => [],
                'total' => 0,
                'pagina_actual' => 1,
                'por_pagina' => $por_pagina,
                'total_paginas' => 0
            ];
        }
    }

    /**
     * Activar/desactivar usuario (para administradores)
     */
    public function toggleUsuarioActivo($usuario_id) {
        try {
            $sql = "UPDATE " . $this->table_usuarios . " 
                    SET activo = NOT activo 
                    WHERE id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $usuario_id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Estado del usuario actualizado'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar usuario'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en toggleUsuarioActivo: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Cambiar rol de usuario (para administradores)
     */
    public function cambiarRolUsuario($usuario_id, $nuevo_rol) {
        try {
            if (!in_array($nuevo_rol, ['user', 'admin'])) {
                return ['success' => false, 'message' => 'Rol inválido'];
            }
            
            $sql = "UPDATE " . $this->table_usuarios . " 
                    SET rol = :rol 
                    WHERE id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":rol", $nuevo_rol);
            $stmt->bindParam(":id", $usuario_id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Rol actualizado correctamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar rol'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en cambiarRolUsuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Obtener estadísticas generales de usuarios (para administradores)
     */
    public function obtenerEstadisticasGenerales() {
        try {
            $estadisticas = [];
            
            // Total de usuarios
            $sql = "SELECT 
                        COUNT(*) as total_usuarios,
                        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as usuarios_activos,
                        SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) as administradores,
                        SUM(CASE WHEN fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as nuevos_ultimo_mes
                    FROM " . $this->table_usuarios;
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $estadisticas['usuarios'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Usuarios más activos
            $sql = "SELECT u.nombre, u.email, COUNT(h.id) as contenido_visto
                    FROM " . $this->table_usuarios . " u
                    LEFT JOIN " . $this->table_historial . " h ON u.id = h.usuario_id
                    WHERE u.activo = 1
                    GROUP BY u.id
                    ORDER BY contenido_visto DESC
                    LIMIT 5";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $estadisticas['usuarios_activos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Registros por mes (últimos 6 meses)
            $sql = "SELECT 
                        DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
                        COUNT(*) as registros
                    FROM " . $this->table_usuarios . "
                    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
                    ORDER BY mes DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $estadisticas['registros_mensuales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $estadisticas;
            
        } catch(PDOException $e) {
            error_log("Error en obtenerEstadisticasGenerales: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Eliminar usuario permanentemente (para administradores)
     */
    public function eliminarUsuario($usuario_id) {
        try {
            // Verificar que no sea el último administrador
            if ($this->esUltimoAdmin($usuario_id)) {
                return ['success' => false, 'message' => 'No se puede eliminar el último administrador'];
            }
            
            // Comenzar transacción
            $this->conn->beginTransaction();
            
            // Eliminar datos relacionados
            $tablas_relacionadas = [
                $this->table_preferencias,
                $this->table_historial,
                $this->table_calificaciones
            ];
            
            foreach ($tablas_relacionadas as $tabla) {
                $sql = "DELETE FROM {$tabla} WHERE usuario_id = :usuario_id";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(":usuario_id", $usuario_id);
                $stmt->execute();
            }
            
            // Eliminar usuario
            $sql = "DELETE FROM " . $this->table_usuarios . " WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $usuario_id);
            $stmt->execute();
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Usuario eliminado correctamente'];
            
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Error en eliminarUsuario: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar usuario'];
        }
    }

    /**
     * Verificar si es el último administrador
     */
    private function esUltimoAdmin($usuario_id) {
        try {
            $sql = "SELECT COUNT(*) as total_admins,
                           SUM(CASE WHEN id = :usuario_id THEN 1 ELSE 0 END) as es_admin
                    FROM " . $this->table_usuarios . " 
                    WHERE rol = 'admin' AND activo = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ($result['total_admins'] == 1 && $result['es_admin'] == 1);
            
        } catch(PDOException $e) {
            error_log("Error en esUltimoAdmin: " . $e->getMessage());
            return true; // Por seguridad, asumir que sí es el último
        }
    }

    /**
     * Restablecer contraseña (para administradores)
     */
    public function restablecerPassword($usuario_id, $nueva_password = null) {
        try {
            // Si no se proporciona contraseña, generar una temporal
            if (!$nueva_password) {
                $nueva_password = $this->generarPasswordTemporal();
            }
            
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE " . $this->table_usuarios . " 
                    SET password = :password 
                    WHERE id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":password", $password_hash);
            $stmt->bindParam(":id", $usuario_id);
            
            if ($stmt->execute()) {
                return [
                    'success' => true, 
                    'message' => 'Contraseña restablecida',
                    'nueva_password' => $nueva_password
                ];
            } else {
                return ['success' => false, 'message' => 'Error al restablecer contraseña'];
            }
            
        } catch(PDOException $e) {
            error_log("Error en restablecerPassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Generar contraseña temporal
     */
    private function generarPasswordTemporal($longitud = 8) {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        
        for ($i = 0; $i < $longitud; $i++) {
            $password .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
        
        return $password;
    }

    /**
     * Validar token de sesión (para APIs)
     */
    public function validarToken($token) {
        try {
            $token_data = base64_decode($token);
            $parts = explode(':', $token_data);
            
            if (count($parts) !== 2) {
                return false;
            }
            
            $usuario_id = $parts[0];
            $timestamp = $parts[1];
            
            // Verificar que el token no haya expirado (24 horas)
            if (time() - $timestamp > 86400) {
                return false;
            }
            
            // Verificar que el usuario existe y está activo
            $usuario = $this->obtenerUsuario($usuario_id);
            if (!$usuario || !$usuario['activo']) {
                return false;
            }
            
            return $usuario;
            
        } catch(Exception $e) {
            error_log("Error en validarToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generar token de sesión (para APIs)
     */
    public function generarToken($usuario_id) {
        $token_data = $usuario_id . ':' . time();
        return base64_encode($token_data);
    }

    /**
     * Destructor de la clase
     */
    public function __destruct() {
        $this->conn = null;
    }
}
?>