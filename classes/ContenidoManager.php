<?php
require_once __DIR__ . '/../config/database.php';

class ContenidoManager {
    private $conn;
    private $table_contenido = "contenido";
    private $table_generos = "generos";
    private $table_calificaciones = "calificaciones";
    private $table_preferencias = "preferencias_usuario";
    private $table_historial = "historial_navegacion";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Obtener contenido destacado por tipo
     */
    public function obtenerContenidoDestacado($tipo = null, $limite = 10) {
        try {
            $sql = "SELECT c.*, g.nombre as genero_nombre 
                    FROM " . $this->table_contenido . " c
                    LEFT JOIN " . $this->table_generos . " g ON c.genero_id = g.id
                    WHERE c.activo = 1";
            
            if ($tipo) {
                $sql .= " AND c.tipo = :tipo";
            }
            
            $sql .= " ORDER BY c.calificacion DESC, c.fecha_agregado DESC LIMIT :limite";
            
            $stmt = $this->conn->prepare($sql);
            
            if ($tipo) {
                $stmt->bindParam(":tipo", $tipo);
            }
            $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerContenidoDestacado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todos los géneros
     */
    public function obtenerGeneros() {
        try {
            $sql = "SELECT * FROM " . $this->table_generos . " ORDER BY nombre ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerGeneros: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar contenido por diferentes criterios
     */
    public function buscarContenido($busqueda = '', $genero_id = '', $tipo = '', $limite = 50) {
        try {
            $sql = "SELECT c.*, g.nombre as genero_nombre 
                    FROM " . $this->table_contenido . " c
                    LEFT JOIN " . $this->table_generos . " g ON c.genero_id = g.id
                    WHERE c.activo = 1";
            
            $params = [];
            
            if (!empty($busqueda)) {
                $sql .= " AND (c.titulo LIKE :busqueda OR c.descripcion LIKE :busqueda)";
                $params[':busqueda'] = '%' . $busqueda . '%';
            }
            
            if (!empty($genero_id)) {
                $sql .= " AND c.genero_id = :genero_id";
                $params[':genero_id'] = $genero_id;
            }
            
            if (!empty($tipo)) {
                $sql .= " AND c.tipo = :tipo";
                $params[':tipo'] = $tipo;
            }
            
            $sql .= " ORDER BY c.calificacion DESC, c.titulo ASC LIMIT :limite";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en buscarContenido: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener detalles de un contenido específico
     */
    public function obtenerDetalleContenido($id) {
        try {
            $sql = "SELECT c.*, g.nombre as genero_nombre, g.descripcion as genero_descripcion
                    FROM " . $this->table_contenido . " c
                    LEFT JOIN " . $this->table_generos . " g ON c.genero_id = g.id
                    WHERE c.id = :id AND c.activo = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerDetalleContenido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener recomendaciones personalizadas basadas en preferencias del usuario
     */
    public function obtenerRecomendacionesPersonalizadas($usuario_id, $limite = 10) {
        try {
            // Obtener géneros preferidos del usuario
            $sql_preferencias = "SELECT genero_id FROM " . $this->table_preferencias . " 
                                WHERE usuario_id = :usuario_id";
            $stmt_pref = $this->conn->prepare($sql_preferencias);
            $stmt_pref->bindParam(":usuario_id", $usuario_id);
            $stmt_pref->execute();
            $preferencias = $stmt_pref->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($preferencias)) {
                // Si no tiene preferencias, mostrar contenido mejor calificado
                return $this->obtenerContenidoDestacado(null, $limite);
            }
            
            // Obtener contenido basado en preferencias, excluyendo lo que ya vió
            $placeholders = str_repeat('?,', count($preferencias) - 1) . '?';
            
            $sql = "SELECT c.*, g.nombre as genero_nombre 
                    FROM " . $this->table_contenido . " c
                    LEFT JOIN " . $this->table_generos . " g ON c.genero_id = g.id
                    WHERE c.activo = 1 
                    AND c.genero_id IN ($placeholders)
                    AND c.id NOT IN (
                        SELECT DISTINCT contenido_id 
                        FROM " . $this->table_historial . " 
                        WHERE usuario_id = ?
                    )
                    ORDER BY c.calificacion DESC, RAND()
                    LIMIT ?";
            
            $stmt = $this->conn->prepare($sql);
            
            // Bind parameters
            $params = array_merge($preferencias, [$usuario_id, $limite]);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerRecomendacionesPersonalizadas: " . $e->getMessage());
            return $this->obtenerContenidoDestacado(null, $limite);
        }
    }

    /**
     * Obtener contenido relacionado por género
     */
    public function obtenerContenidoRelacionado($contenido_id, $limite = 6) {
        try {
            // Primero obtener el género del contenido actual
            $sql_genero = "SELECT genero_id, tipo FROM " . $this->table_contenido . " WHERE id = :id";
            $stmt_genero = $this->conn->prepare($sql_genero);
            $stmt_genero->bindParam(":id", $contenido_id);
            $stmt_genero->execute();
            $contenido_actual = $stmt_genero->fetch(PDO::FETCH_ASSOC);
            
            if (!$contenido_actual) {
                return [];
            }
            
            // Obtener contenido del mismo género, excluyendo el actual
            $sql = "SELECT c.*, g.nombre as genero_nombre 
                    FROM " . $this->table_contenido . " c
                    LEFT JOIN " . $this->table_generos . " g ON c.genero_id = g.id
                    WHERE c.activo = 1 
                    AND c.genero_id = :genero_id 
                    AND c.id != :contenido_id
                    ORDER BY c.calificacion DESC, RAND()
                    LIMIT :limite";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":genero_id", $contenido_actual['genero_id']);
            $stmt->bindParam(":contenido_id", $contenido_id);
            $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerContenidoRelacionado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guardar calificación de usuario
     */
    public function guardarCalificacion($usuario_id, $contenido_id, $calificacion, $comentario = '') {
        try {
            // Verificar si ya existe una calificación
            $sql_check = "SELECT id FROM " . $this->table_calificaciones . " 
                         WHERE usuario_id = :usuario_id AND contenido_id = :contenido_id";
            $stmt_check = $this->conn->prepare($sql_check);
            $stmt_check->bindParam(":usuario_id", $usuario_id);
            $stmt_check->bindParam(":contenido_id", $contenido_id);
            $stmt_check->execute();
            
            if ($stmt_check->fetch()) {
                // Actualizar calificación existente
                $sql = "UPDATE " . $this->table_calificaciones . " 
                        SET calificacion = :calificacion, comentario = :comentario, 
                            fecha_calificacion = CURRENT_TIMESTAMP
                        WHERE usuario_id = :usuario_id AND contenido_id = :contenido_id";
            } else {
                // Insertar nueva calificación
                $sql = "INSERT INTO " . $this->table_calificaciones . " 
                        (usuario_id, contenido_id, calificacion, comentario) 
                        VALUES (:usuario_id, :contenido_id, :calificacion, :comentario)";
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->bindParam(":contenido_id", $contenido_id);
            $stmt->bindParam(":calificacion", $calificacion);
            $stmt->bindParam(":comentario", $comentario);
            
            if ($stmt->execute()) {
                // Actualizar calificación promedio del contenido
                $this->actualizarCalificacionPromedio($contenido_id);
                return true;
            }
            return false;
            
        } catch(PDOException $e) {
            error_log("Error en guardarCalificacion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar calificación promedio de un contenido
     */
    private function actualizarCalificacionPromedio($contenido_id) {
        try {
            $sql = "UPDATE " . $this->table_contenido . " 
                    SET calificacion = (
                        SELECT AVG(calificacion) 
                        FROM " . $this->table_calificaciones . " 
                        WHERE contenido_id = :contenido_id
                    )
                    WHERE id = :contenido_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":contenido_id", $contenido_id);
            $stmt->execute();
            
        } catch(PDOException $e) {
            error_log("Error en actualizarCalificacionPromedio: " . $e->getMessage());
        }
    }

    /**
     * Obtener calificaciones de un contenido
     */
    public function obtenerCalificaciones($contenido_id, $limite = 10) {
        try {
            $sql = "SELECT c.*, u.nombre as usuario_nombre 
                    FROM " . $this->table_calificaciones . " c
                    LEFT JOIN usuarios u ON c.usuario_id = u.id
                    WHERE c.contenido_id = :contenido_id
                    ORDER BY c.fecha_calificacion DESC
                    LIMIT :limite";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":contenido_id", $contenido_id);
            $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerCalificaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener calificación de un usuario para un contenido específico
     */
    public function obtenerCalificacionUsuario($usuario_id, $contenido_id) {
        try {
            $sql = "SELECT * FROM " . $this->table_calificaciones . " 
                    WHERE usuario_id = :usuario_id AND contenido_id = :contenido_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->bindParam(":contenido_id", $contenido_id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerCalificacionUsuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Guardar en historial de navegación
     */
    public function guardarHistorial($usuario_id, $contenido_id) {
        try {
            // Verificar si ya existe una entrada reciente (últimas 24 horas)
            $sql_check = "SELECT id FROM " . $this->table_historial . " 
                         WHERE usuario_id = :usuario_id AND contenido_id = :contenido_id 
                         AND fecha_visita > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $stmt_check = $this->conn->prepare($sql_check);
            $stmt_check->bindParam(":usuario_id", $usuario_id);
            $stmt_check->bindParam(":contenido_id", $contenido_id);
            $stmt_check->execute();
            
            if (!$stmt_check->fetch()) {
                // Solo insertar si no hay una visita reciente
                $sql = "INSERT INTO " . $this->table_historial . " (usuario_id, contenido_id) 
                        VALUES (:usuario_id, :contenido_id)";
                
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(":usuario_id", $usuario_id);
                $stmt->bindParam(":contenido_id", $contenido_id);
                $stmt->execute();
            }
            
            return true;
            
        } catch(PDOException $e) {
            error_log("Error en guardarHistorial: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener historial de un usuario
     */
    public function obtenerHistorialUsuario($usuario_id, $limite = 20) {
        try {
            $sql = "SELECT c.*, g.nombre as genero_nombre, h.fecha_visita
                    FROM " . $this->table_historial . " h
                    LEFT JOIN " . $this->table_contenido . " c ON h.contenido_id = c.id
                    LEFT JOIN " . $this->table_generos . " g ON c.genero_id = g.id
                    WHERE h.usuario_id = :usuario_id AND c.activo = 1
                    ORDER BY h.fecha_visita DESC
                    LIMIT :limite";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":usuario_id", $usuario_id);
            $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerHistorialUsuario: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de contenido para administradores
     */
    public function obtenerEstadisticasContenido() {
        try {
            $estadisticas = [];
            
            // Total de contenido
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN tipo = 'pelicula' THEN 1 ELSE 0 END) as peliculas,
                        SUM(CASE WHEN tipo = 'serie' THEN 1 ELSE 0 END) as series,
                        AVG(calificacion) as calificacion_promedio
                    FROM " . $this->table_contenido . " 
                    WHERE activo = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $estadisticas['contenido'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Contenido por género
            $sql = "SELECT g.nombre, COUNT(c.id) as cantidad
                    FROM " . $this->table_generos . " g
                    LEFT JOIN " . $this->table_contenido . " c ON g.id = c.genero_id AND c.activo = 1
                    GROUP BY g.id, g.nombre
                    ORDER BY cantidad DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $estadisticas['por_genero'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contenido más visto
            $sql = "SELECT c.titulo, c.tipo, COUNT(h.id) as visitas
                    FROM " . $this->table_contenido . " c
                    LEFT JOIN " . $this->table_historial . " h ON c.id = h.contenido_id
                    WHERE c.activo = 1
                    GROUP BY c.id
                    ORDER BY visitas DESC
                    LIMIT 10";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $estadisticas['mas_visto'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $estadisticas;
            
        } catch(PDOException $e) {
            error_log("Error en obtenerEstadisticasContenido: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Agregar nuevo contenido (para administradores)
     */
    public function agregarContenido($datos) {
        try {
            $sql = "INSERT INTO " . $this->table_contenido . " 
                    (titulo, descripcion, tipo, genero_id, año_lanzamiento, duracion, poster, trailer_url) 
                    VALUES (:titulo, :descripcion, :tipo, :genero_id, :año_lanzamiento, :duracion, :poster, :trailer_url)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":titulo", $datos['titulo']);
            $stmt->bindParam(":descripcion", $datos['descripcion']);
            $stmt->bindParam(":tipo", $datos['tipo']);
            $stmt->bindParam(":genero_id", $datos['genero_id']);
            $stmt->bindParam(":año_lanzamiento", $datos['año_lanzamiento']);
            $stmt->bindParam(":duracion", $datos['duracion']);
            $stmt->bindParam(":poster", $datos['poster']);
            $stmt->bindParam(":trailer_url", $datos['trailer_url']);
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
            
        } catch(PDOException $e) {
            error_log("Error en agregarContenido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar contenido existente
     */
    public function actualizarContenido($id, $datos) {
        try {
            $sql = "UPDATE " . $this->table_contenido . " 
                    SET titulo = :titulo, descripcion = :descripcion, tipo = :tipo, 
                        genero_id = :genero_id, año_lanzamiento = :año_lanzamiento, 
                        duracion = :duracion, poster = :poster, trailer_url = :trailer_url
                    WHERE id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id);
            $stmt->bindParam(":titulo", $datos['titulo']);
            $stmt->bindParam(":descripcion", $datos['descripcion']);
            $stmt->bindParam(":tipo", $datos['tipo']);
            $stmt->bindParam(":genero_id", $datos['genero_id']);
            $stmt->bindParam(":año_lanzamiento", $datos['año_lanzamiento']);
            $stmt->bindParam(":duracion", $datos['duracion']);
            $stmt->bindParam(":poster", $datos['poster']);
            $stmt->bindParam(":trailer_url", $datos['trailer_url']);
            
            return $stmt->execute();
            
        } catch(PDOException $e) {
            error_log("Error en actualizarContenido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar contenido (desactivar)
     */
    public function eliminarContenido($id) {
        try {
            $sql = "UPDATE " . $this->table_contenido . " SET activo = 0 WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id", $id);
            return $stmt->execute();
            
        } catch(PDOException $e) {
            error_log("Error en eliminarContenido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener contenido paginado para administración
     */
    public function obtenerContenidoPaginado($pagina = 1, $por_pagina = 20, $filtros = []) {
        try {
            $offset = ($pagina - 1) * $por_pagina;
            
            $sql = "SELECT c.*, g.nombre as genero_nombre 
                    FROM " . $this->table_contenido . " c
                    LEFT JOIN " . $this->table_generos . " g ON c.genero_id = g.id
                    WHERE c.activo = 1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filtros['busqueda'])) {
                $sql .= " AND (c.titulo LIKE :busqueda OR c.descripcion LIKE :busqueda)";
                $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
            }
            
            if (!empty($filtros['genero_id'])) {
                $sql .= " AND c.genero_id = :genero_id";
                $params[':genero_id'] = $filtros['genero_id'];
            }
            
            if (!empty($filtros['tipo'])) {
                $sql .= " AND c.tipo = :tipo";
                $params[':tipo'] = $filtros['tipo'];
            }
            
            if (!empty($filtros['año'])) {
                $sql .= " AND c.año_lanzamiento = :año";
                $params[':año'] = $filtros['año'];
            }
            
            if (!empty($filtros['calificacion_min'])) {
                $sql .= " AND c.calificacion >= :calificacion_min";
                $params[':calificacion_min'] = $filtros['calificacion_min'];
            }
            
            if (isset($filtros['activo'])) {
                $sql .= " AND c.activo = :activo";
                $params[':activo'] = $filtros['activo'];
            }
            
            // Aplicar ordenamiento
            switch ($filtros['ordenar']) {
                case 'titulo_asc':
                    $sql .= " ORDER BY c.titulo ASC";
                    break;
                case 'fecha_desc':
                    $sql .= " ORDER BY c.fecha_agregado DESC";
                    break;
                case 'año_desc':
                    $sql .= " ORDER BY c.año_lanzamiento DESC";
                    break;
                case 'año_asc':
                    $sql .= " ORDER BY c.año_lanzamiento ASC";
                    break;
                case 'calificacion_desc':
                default:
                    $sql .= " ORDER BY c.calificacion DESC, c.fecha_agregado DESC";
                    break;
            }
            
            $sql .= " LIMIT :offset, :por_pagina";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':por_pagina', $por_pagina, PDO::PARAM_INT);
            
            $stmt->execute();
            $contenido = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total para paginación
            $sql_count = "SELECT COUNT(*) as total 
                         FROM " . $this->table_contenido . " c
                         WHERE c.activo = 1";
            
            $params_count = [];
            
            if (!empty($filtros['busqueda'])) {
                $sql_count .= " AND (c.titulo LIKE :busqueda OR c.descripcion LIKE :busqueda)";
                $params_count[':busqueda'] = '%' . $filtros['busqueda'] . '%';
            }
            
            if (!empty($filtros['genero_id'])) {
                $sql_count .= " AND c.genero_id = :genero_id";
                $params_count[':genero_id'] = $filtros['genero_id'];
            }
            
            if (!empty($filtros['tipo'])) {
                $sql_count .= " AND c.tipo = :tipo";
                $params_count[':tipo'] = $filtros['tipo'];
            }
            
            if (!empty($filtros['año'])) {
                $sql_count .= " AND c.año_lanzamiento = :año";
                $params_count[':año'] = $filtros['año'];
            }
            
            if (!empty($filtros['calificacion_min'])) {
                $sql_count .= " AND c.calificacion >= :calificacion_min";
                $params_count[':calificacion_min'] = $filtros['calificacion_min'];
            }
            
            if (isset($filtros['activo'])) {
                $sql_count .= " AND c.activo = :activo";
                $params_count[':activo'] = $filtros['activo'];
            }
            
            $stmt_count = $this->conn->prepare($sql_count);
            
            foreach ($params_count as $key => $value) {
                $stmt_count->bindValue($key, $value);
            }
            
            $stmt_count->execute();
            $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'contenido' => $contenido,
                'total' => $total,
                'pagina_actual' => $pagina,
                'por_pagina' => $por_pagina,
                'total_paginas' => ceil($total / $por_pagina)
            ];
            
        } catch(PDOException $e) {
            error_log("Error en obtenerContenidoPaginado: " . $e->getMessage());
            return [
                'contenido' => [],
                'total' => 0,
                'pagina_actual' => 1,
                'por_pagina' => $por_pagina,
                'total_paginas' => 0
            ];
        }
    }

    /**
     * Obtener años disponibles para filtro
     */
    public function obtenerAñosDisponibles() {
        try {
            $sql = "SELECT DISTINCT año_lanzamiento 
                    FROM " . $this->table_contenido . " 
                    WHERE activo = 1 AND año_lanzamiento IS NOT NULL 
                    ORDER BY año_lanzamiento DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch(PDOException $e) {
            error_log("Error en obtenerAñosDisponibles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Contar contenido por tipo
     */
    public function contarContenidoPorTipo($tipo) {
        try {
            $sql = "SELECT COUNT(*) FROM " . $this->table_contenido . " WHERE activo = 1 AND tipo = :tipo";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->execute();
            
            return $stmt->fetchColumn();
            
        } catch(PDOException $e) {
            error_log("Error en contarContenidoPorTipo: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Contar total de contenido activo
     */
    public function contarContenidoTotal() {
        try {
            $sql = "SELECT COUNT(*) FROM " . $this->table_contenido . " WHERE activo = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchColumn();
            
        } catch(PDOException $e) {
            error_log("Error en contarContenidoTotal: " . $e->getMessage());
            return 0;
        }
    }
}
?>