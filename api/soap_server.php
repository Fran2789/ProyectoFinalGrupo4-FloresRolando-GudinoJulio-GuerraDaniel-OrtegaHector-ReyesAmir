<?php
/**
 * SERVIDOR SOAP - CineRecomendaciones
 * 
 * === REQUISITO DEL PROYECTO ===
 * "Webservices SOAP y REST para cada categoría de películas"
 * "Consumo de Webservices con XML/JSON"
 * 
 * URL del servicio: localhost/PHP/proyecto_recomendaciones/api/soap_server.php
 * WSDL: localhost/PHP/proyecto_recomendaciones/api/soap_server.php?wsdl
 * 
 * === OPERACIONES DISPONIBLES ===
 * 
 * 1. obtenerContenido($tipo, $genero_id, $limite)
 *    - Obtiene lista de películas/series
 *    - Parámetros: tipo (string), genero_id (int), limite (int)
 *    - Retorna: array de contenido
 * 
 * 2. buscarContenido($termino, $tipo, $genero_id)
 *    - Busca contenido por término
 *    - Parámetros: termino (string), tipo (string), genero_id (int)
 *    - Retorna: array de resultados
 * 
 * 3. obtenerRecomendaciones($usuario_id, $limite)
 *    - Obtiene recomendaciones personalizadas
 *    - Parámetros: usuario_id (int), limite (int)
 *    - Retorna: array de recomendaciones
 * 
 * 4. obtenerDetalleContenido($contenido_id)
 *    - Obtiene detalles completos de una película/serie
 *    - Parámetros: contenido_id (int)
 *    - Retorna: objeto con detalles completos
 * 
 * 5. obtenerGeneros()
 *    - Obtiene lista de todos los géneros
 *    - Parámetros: ninguno
 *    - Retorna: array de géneros
 * 
 * 6. calificarContenido($usuario_id, $contenido_id, $calificacion, $comentario)
 *    - Permite calificar contenido
 *    - Parámetros: usuario_id (int), contenido_id (int), calificacion (int), comentario (string)
 *    - Retorna: resultado de la operación
 * 
 * 7. autenticarUsuario($email, $password)
 *    - Autentica usuario para el servicio
 *    - Parámetros: email (string), password (string)
 *    - Retorna: token de sesión o error
 * 
 * 8. obtenerEstadisticas()
 *    - Obtiene estadísticas generales del sistema
 *    - Parámetros: ninguno
 *    - Retorna: objeto con estadísticas
 */

// Incluir dependencias
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ContenidoManager.php';
require_once __DIR__ . '/../classes/UsuarioManager.php';

// Configuración del servidor SOAP
ini_set('soap.wsdl_cache_enabled', 0);
ini_set('soap.wsdl_cache_ttl', 0);

/**
 * Clase principal del servicio SOAP
 */
class CineRecomendacionesSOAP {
    
    private $contenidoManager;
    private $usuarioManager;
    private $database;
    
    public function __construct() {
        $this->contenidoManager = new ContenidoManager();
        $this->usuarioManager = new UsuarioManager();
        $this->database = new Database();
    }
    
    /**
     * Obtener contenido (películas/series) con filtros
     * 
     * @param string $tipo Tipo de contenido ('pelicula', 'serie', 'todos')
     * @param int $genero_id ID del género (opcional)
     * @param int $limite Número máximo de resultados
     * @return array Lista de contenido
     */
    public function obtenerContenido($tipo = 'todos', $genero_id = null, $limite = 20) {
        try {
            // Validar parámetros
            $tipos_validos = ['pelicula', 'serie', 'todos'];
            if (!in_array($tipo, $tipos_validos)) {
                throw new SoapFault('Client', 'Tipo de contenido inválido. Use: pelicula, serie o todos');
            }
            
            if ($limite <= 0 || $limite > 1000) {
                $limite = 20;
            }
            
            // Obtener contenido usando búsqueda del ContentManager
            $contenido = $this->contenidoManager->buscarContenido('', $genero_id, $tipo, $limite);
            
            // Formatear para SOAP
            $resultado = [];
            foreach ($contenido as $item) {
                $resultado[] = [
                    'id' => intval($item['id']),
                    'titulo' => $item['titulo'],
                    'tipo' => $item['tipo'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'año_lanzamiento' => intval($item['año_lanzamiento']),
                    'calificacion' => floatval($item['calificacion']),
                    'genero_id' => intval($item['genero_id']),
                    'genero_nombre' => $item['genero_nombre'] ?? '',
                    'poster' => $item['poster'] ?? '',
                    'duracion' => $item['duracion'] ?? '',
                    'fecha_agregado' => $item['fecha_agregado'] ?? ''
                ];
            }
            
            return [
                'success' => true,
                'total' => count($resultado),
                'contenido' => $resultado,
                'filtros' => [
                    'tipo' => $tipo,
                    'genero_id' => $genero_id,
                    'limite' => $limite
                ]
            ];
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error obteniendo contenido: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar contenido por término de búsqueda
     * 
     * @param string $termino Término de búsqueda
     * @param string $tipo Tipo de contenido (opcional)
     * @param int $genero_id ID del género (opcional)
     * @return array Resultados de búsqueda
     */
    public function buscarContenido($termino, $tipo = 'todos', $genero_id = null) {
        try {
            if (empty($termino) || strlen($termino) < 2) {
                throw new SoapFault('Client', 'El término de búsqueda debe tener al menos 2 caracteres');
            }
            
            // Sanitizar término
            $termino = htmlspecialchars(trim($termino));
            
            // Realizar búsqueda
            $resultados = $this->contenidoManager->buscarContenido($termino, $genero_id, $tipo, 50);
            
            // Formatear resultados
            $contenido_formateado = [];
            foreach ($resultados as $item) {
                $contenido_formateado[] = [
                    'id' => intval($item['id']),
                    'titulo' => $item['titulo'],
                    'tipo' => $item['tipo'],
                    'año_lanzamiento' => intval($item['año_lanzamiento']),
                    'calificacion' => floatval($item['calificacion']),
                    'genero_nombre' => $item['genero_nombre'] ?? '',
                    'relevancia' => $this->calcularRelevancia($item['titulo'], $termino)
                ];
            }
            
            return [
                'success' => true,
                'termino_busqueda' => $termino,
                'total_resultados' => count($contenido_formateado),
                'resultados' => $contenido_formateado
            ];
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error en búsqueda: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener recomendaciones personalizadas para un usuario
     * 
     * @param int $usuario_id ID del usuario
     * @param int $limite Número de recomendaciones
     * @return array Recomendaciones personalizadas
     */
    public function obtenerRecomendaciones($usuario_id, $limite = 10) {
        try {
            if (!is_numeric($usuario_id) || $usuario_id <= 0) {
                throw new SoapFault('Client', 'ID de usuario inválido');
            }
            
            // Verificar que el usuario existe
            $usuario = $this->usuarioManager->obtenerUsuario($usuario_id);
            if (!$usuario) {
                throw new SoapFault('Client', 'Usuario no encontrado');
            }
            
            // Obtener recomendaciones personalizadas
            $recomendaciones = $this->contenidoManager->obtenerRecomendacionesPersonalizadas($usuario_id, $limite);
            
            // Formatear para SOAP
            $resultado = [];
            foreach ($recomendaciones as $item) {
                $resultado[] = [
                    'id' => intval($item['id']),
                    'titulo' => $item['titulo'],
                    'tipo' => $item['tipo'],
                    'calificacion' => floatval($item['calificacion']),
                    'genero_nombre' => $item['genero_nombre'] ?? '',
                    'año_lanzamiento' => intval($item['año_lanzamiento']),
                    'razon_recomendacion' => $this->obtenerRazonRecomendacion($item, $usuario_id),
                    'score_compatibilidad' => $this->calcularCompatibilidad($item, $usuario_id)
                ];
            }
            
            return [
                'success' => true,
                'usuario_id' => $usuario_id,
                'total_recomendaciones' => count($resultado),
                'recomendaciones' => $resultado,
                'algoritmo' => 'personalizado_basado_en_preferencias'
            ];
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error obteniendo recomendaciones: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener detalles completos de un contenido específico
     * 
     * @param int $contenido_id ID del contenido
     * @return array Detalles completos del contenido
     */
    public function obtenerDetalleContenido($contenido_id) {
        try {
            if (!is_numeric($contenido_id) || $contenido_id <= 0) {
                throw new SoapFault('Client', 'ID de contenido inválido');
            }
            
            // Obtener detalles del contenido
            $contenido = $this->contenidoManager->obtenerDetalleContenido($contenido_id);
            if (!$contenido) {
                throw new SoapFault('Client', 'Contenido no encontrado');
            }
            
            // Obtener calificaciones
            $calificaciones = $this->contenidoManager->obtenerCalificaciones($contenido_id, 10);
            
            // Obtener contenido relacionado
            $relacionado = $this->contenidoManager->obtenerContenidoRelacionado($contenido_id, 5);
            
            return [
                'success' => true,
                'contenido' => [
                    'id' => intval($contenido['id']),
                    'titulo' => $contenido['titulo'],
                    'tipo' => $contenido['tipo'],
                    'descripcion' => $contenido['descripcion'] ?? '',
                    'año_lanzamiento' => intval($contenido['año_lanzamiento']),
                    'calificacion' => floatval($contenido['calificacion']),
                    'genero_id' => intval($contenido['genero_id']),
                    'genero_nombre' => $contenido['genero_nombre'] ?? '',
                    'poster' => $contenido['poster'] ?? '',
                    'duracion' => $contenido['duracion'] ?? '',
                    'director' => $contenido['director'] ?? '',
                    'actores' => $contenido['actores'] ?? '',
                    'fecha_agregado' => $contenido['fecha_agregado'] ?? ''
                ],
                'estadisticas' => [
                    'total_calificaciones' => count($calificaciones),
                    'calificacion_promedio' => floatval($contenido['calificacion']),
                    'popularidad' => $this->calcularPopularidad($contenido)
                ],
                'calificaciones_recientes' => array_slice($calificaciones, 0, 5),
                'contenido_relacionado' => array_map(function($item) {
                    return [
                        'id' => intval($item['id']),
                        'titulo' => $item['titulo'],
                        'calificacion' => floatval($item['calificacion'])
                    ];
                }, $relacionado)
            ];
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error obteniendo detalles: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener lista de todos los géneros disponibles
     * 
     * @return array Lista de géneros
     */
    public function obtenerGeneros() {
        try {
            $generos = $this->contenidoManager->obtenerGeneros();
            
            $resultado = [];
            foreach ($generos as $genero) {
                $resultado[] = [
                    'id' => intval($genero['id']),
                    'nombre' => $genero['nombre'],
                    'descripcion' => $genero['descripcion'] ?? '',
                    'total_contenido' => $this->contarContenidoPorGenero($genero['id'])
                ];
            }
            
            return [
                'success' => true,
                'total_generos' => count($resultado),
                'generos' => $resultado
            ];
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error obteniendo géneros: ' . $e->getMessage());
        }
    }
    
    /**
     * Calificar contenido (requiere autenticación previa)
     * 
     * @param int $usuario_id ID del usuario
     * @param int $contenido_id ID del contenido
     * @param int $calificacion Calificación (1-5)
     * @param string $comentario Comentario opcional
     * @return array Resultado de la operación
     */
    public function calificarContenido($usuario_id, $contenido_id, $calificacion, $comentario = '') {
        try {
            // Validar parámetros
            if (!is_numeric($usuario_id) || $usuario_id <= 0) {
                throw new SoapFault('Client', 'ID de usuario inválido');
            }
            
            if (!is_numeric($contenido_id) || $contenido_id <= 0) {
                throw new SoapFault('Client', 'ID de contenido inválido');
            }
            
            if (!is_numeric($calificacion) || $calificacion < 1 || $calificacion > 5) {
                throw new SoapFault('Client', 'Calificación debe estar entre 1 y 5');
            }
            
            // Verificar que el usuario existe
            $usuario = $this->usuarioManager->obtenerUsuario($usuario_id);
            if (!$usuario) {
                throw new SoapFault('Client', 'Usuario no encontrado');
            }
            
            // Verificar que el contenido existe
            $contenido = $this->contenidoManager->obtenerDetalleContenido($contenido_id);
            if (!$contenido) {
                throw new SoapFault('Client', 'Contenido no encontrado');
            }
            
            // Sanitizar comentario
            $comentario = htmlspecialchars(trim($comentario));
            if (strlen($comentario) > 1000) {
                throw new SoapFault('Client', 'Comentario muy largo (máximo 1000 caracteres)');
            }
            
            // Guardar calificación
            $resultado = $this->contenidoManager->guardarCalificacion(
                $usuario_id,
                $contenido_id,
                $calificacion,
                $comentario
            );
            
            if ($resultado) {
                // Obtener contenido actualizado
                $contenido_actualizado = $this->contenidoManager->obtenerDetalleContenido($contenido_id);
                
                return [
                    'success' => true,
                    'mensaje' => 'Calificación guardada correctamente',
                    'calificacion' => [
                        'usuario_id' => $usuario_id,
                        'contenido_id' => $contenido_id,
                        'calificacion' => $calificacion,
                        'comentario' => $comentario,
                        'fecha' => date('Y-m-d H:i:s')
                    ],
                    'contenido_actualizado' => [
                        'titulo' => $contenido_actualizado['titulo'],
                        'calificacion_anterior' => floatval($contenido['calificacion']),
                        'calificacion_nueva' => floatval($contenido_actualizado['calificacion'])
                    ]
                ];
            } else {
                throw new SoapFault('Server', 'Error al guardar la calificación');
            }
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error calificando contenido: ' . $e->getMessage());
        }
    }
    
    /**
     * Autenticar usuario para usar el servicio
     * 
     * @param string $email Email del usuario
     * @param string $password Contraseña
     * @return array Token de sesión o error
     */
    public function autenticarUsuario($email, $password) {
        try {
            if (empty($email) || empty($password)) {
                throw new SoapFault('Client', 'Email y contraseña son requeridos');
            }
            
            // Validar email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new SoapFault('Client', 'Formato de email inválido');
            }
            
            // Intentar autenticar
            $usuario = $this->usuarioManager->autenticarUsuario($email, $password);
            
            if ($usuario) {
                // Generar token simple para la sesión SOAP
                $token = $this->generarTokenSesion($usuario['id']);
                
                return [
                    'success' => true,
                    'usuario' => [
                        'id' => intval($usuario['id']),
                        'nombre' => $usuario['nombre'],
                        'email' => $usuario['email'],
                        'rol' => $usuario['rol']
                    ],
                    'token_sesion' => $token,
                    'expira_en' => '24 horas',
                    'mensaje' => 'Autenticación exitosa'
                ];
            } else {
                throw new SoapFault('Client', 'Credenciales inválidas');
            }
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error en autenticación: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener estadísticas generales del sistema
     * 
     * @return array Estadísticas del sistema
     */
    public function obtenerEstadisticas() {
        try {
            $estadisticas = $this->usuarioManager->obtenerEstadisticasGenerales();
            
            // Obtener estadísticas adicionales
            $conn = $this->database->getConnection();
            
            // Top géneros
            $sql = "SELECT g.nombre, COUNT(c.id) as total_contenido
                   FROM generos g
                   LEFT JOIN contenido c ON g.id = c.genero_id
                   GROUP BY g.id, g.nombre
                   ORDER BY total_contenido DESC
                   LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $top_generos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contenido mejor calificado
            $sql = "SELECT titulo, tipo, calificacion
                   FROM contenido
                   WHERE calificacion >= 4.5
                   ORDER BY calificacion DESC
                   LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $mejor_calificado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'fecha_reporte' => date('Y-m-d H:i:s'),
                'estadisticas_generales' => $estadisticas,
                'top_generos' => $top_generos,
                'contenido_mejor_calificado' => $mejor_calificado,
                'sistema' => [
                    'version' => '1.0',
                    'servidor_soap' => 'CineRecomendaciones SOAP Server',
                    'total_operaciones' => 8
                ]
            ];
            
        } catch (Exception $e) {
            throw new SoapFault('Server', 'Error obteniendo estadísticas: ' . $e->getMessage());
        }
    }
    
    // ===== MÉTODOS AUXILIARES PRIVADOS =====
    
    /**
     * Calcular relevancia de un resultado de búsqueda
     */
    private function calcularRelevancia($titulo, $termino) {
        $titulo_lower = strtolower($titulo);
        $termino_lower = strtolower($termino);
        
        if (strpos($titulo_lower, $termino_lower) === 0) {
            return 100; // Coincidencia exacta al inicio
        } elseif (strpos($titulo_lower, $termino_lower) !== false) {
            return 75; // Coincidencia en cualquier parte
        } else {
            return 25; // Coincidencia parcial
        }
    }
    
    /**
     * Obtener razón de recomendación
     */
    private function obtenerRazonRecomendacion($contenido, $usuario_id) {
        // Simplificado - en un proyecto real sería más sofisticado
        $preferencias = $this->usuarioManager->obtenerPreferencias($usuario_id);
        
        if (!empty($preferencias)) {
            $generos_favoritos = array_column($preferencias, 'genero_id');
            if (in_array($contenido['genero_id'], $generos_favoritos)) {
                return "Te gusta " . ($contenido['genero_nombre'] ?? 'este género');
            }
        }
        
        if ($contenido['calificacion'] >= 4.5) {
            return "Excelente calificación (" . $contenido['calificacion'] . "/5)";
        }
        
        return "Contenido popular y bien calificado";
    }
    
    /**
     * Calcular score de compatibilidad
     */
    private function calcularCompatibilidad($contenido, $usuario_id) {
        $score = 50; // Base
        
        // Bonus por género favorito
        $preferencias = $this->usuarioManager->obtenerPreferencias($usuario_id);
        if (!empty($preferencias)) {
            $generos_favoritos = array_column($preferencias, 'genero_id');
            if (in_array($contenido['genero_id'], $generos_favoritos)) {
                $score += 30;
            }
        }
        
        // Bonus por calificación alta
        if ($contenido['calificacion'] >= 4.5) {
            $score += 20;
        } elseif ($contenido['calificacion'] >= 4.0) {
            $score += 10;
        }
        
        return min(100, $score);
    }
    
    /**
     * Calcular popularidad del contenido
     */
    private function calcularPopularidad($contenido) {
        return min(100, $contenido['calificacion'] * 20);
    }
    
    /**
     * Contar contenido por género
     */
    private function contarContenidoPorGenero($genero_id) {
        try {
            $conn = $this->database->getConnection();
            $sql = "SELECT COUNT(*) as total FROM contenido WHERE genero_id = :genero_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':genero_id', $genero_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($result['total']);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Generar token de sesión simple
     */
    private function generarTokenSesion($usuario_id) {
        return 'SOAP_' . $usuario_id . '_' . time() . '_' . uniqid();
    }
}

// ===== CONFIGURACIÓN Y EJECUCIÓN DEL SERVIDOR SOAP =====

try {
    // Si se solicita WSDL, generarlo
    if (isset($_GET['wsdl'])) {
        generarWSDL();
        exit;
    }
    
    // Configurar servidor SOAP
    $server = new SoapServer(null, [
        'uri' => 'http://localhost/PHP/proyecto_recomendaciones/api/soap_server.php',
        'soap_version' => SOAP_1_2,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'trace' => 1,
        'exceptions' => true
    ]);
    
    // Registrar la clase del servicio
    $server->setClass('CineRecomendacionesSOAP');
    
    // Manejar la petición
    $server->handle();
    
} catch (Exception $e) {
    // Manejar errores del servidor
    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">';
    echo '<soap:Body>';
    echo '<soap:Fault>';
    echo '<soap:Code><soap:Value>soap:Server</soap:Value></soap:Code>';
    echo '<soap:Reason><soap:Text>Error del servidor SOAP: ' . htmlspecialchars($e->getMessage()) . '</soap:Text></soap:Reason>';
    echo '</soap:Fault>';
    echo '</soap:Body>';
    echo '</soap:Envelope>';
}

/**
 * Generar WSDL dinámico
 */
function generarWSDL() {
    $service_url = 'http://localhost/PHP/proyecto_recomendaciones/api/soap_server.php';
    $target_namespace = 'http://localhost/PHP/proyecto_recomendaciones/soap';
    
    header('Content-Type: text/xml; charset=utf-8');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<definitions xmlns="http://schemas.xmlsoap.org/wsdl/"' . "\n";
    echo '             xmlns:tns="' . $target_namespace . '"' . "\n";
    echo '             xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"' . "\n";
    echo '             xmlns:xsd="http://www.w3.org/2001/XMLSchema"' . "\n";
    echo '             targetNamespace="' . $target_namespace . '">' . "\n";
    
    // Types
    echo '<types>' . "\n";
    echo '  <xsd:schema targetNamespace="' . $target_namespace . '">' . "\n";
    echo '    <xsd:element name="ContenidoArray" type="xsd:anyType"/>' . "\n";
    echo '    <xsd:element name="ResultadoOperacion" type="xsd:anyType"/>' . "\n";
    echo '  </xsd:schema>' . "\n";
    echo '</types>' . "\n";
    
    // Messages
    $operaciones = [
        'obtenerContenido' => [
            'input' => ['tipo' => 'xsd:string', 'genero_id' => 'xsd:int', 'limite' => 'xsd:int'],
            'output' => ['return' => 'tns:ContenidoArray']
        ],
        'buscarContenido' => [
            'input' => ['termino' => 'xsd:string', 'tipo' => 'xsd:string', 'genero_id' => 'xsd:int'],
            'output' => ['return' => 'tns:ContenidoArray']
        ],
        'obtenerRecomendaciones' => [
            'input' => ['usuario_id' => 'xsd:int', 'limite' => 'xsd:int'],
            'output' => ['return' => 'tns:ContenidoArray']
        ],
        'obtenerDetalleContenido' => [
            'input' => ['contenido_id' => 'xsd:int'],
            'output' => ['return' => 'tns:ResultadoOperacion']
        ],
        'obtenerGeneros' => [
            'input' => [],
            'output' => ['return' => 'tns:ContenidoArray']
        ],
        'calificarContenido' => [
            'input' => ['usuario_id' => 'xsd:int', 'contenido_id' => 'xsd:int', 'calificacion' => 'xsd:int', 'comentario' => 'xsd:string'],
            'output' => ['return' => 'tns:ResultadoOperacion']
        ],
        'autenticarUsuario' => [
            'input' => ['email' => 'xsd:string', 'password' => 'xsd:string'],
            'output' => ['return' => 'tns:ResultadoOperacion']
        ],
        'obtenerEstadisticas' => [
            'input' => [],
            'output' => ['return' => 'tns:ResultadoOperacion']
        ]
    ];
    
    foreach ($operaciones as $op => $params) {
        echo '<message name="' . $op . 'Request">' . "\n";
        foreach ($params['input'] as $param => $type) {
            echo '  <part name="' . $param . '" type="' . $type . '"/>' . "\n";
        }
        echo '</message>' . "\n";
        
        echo '<message name="' . $op . 'Response">' . "\n";
        foreach ($params['output'] as $param => $type) {
            echo '  <part name="' . $param . '" type="' . $type . '"/>' . "\n";
        }
        echo '</message>' . "\n";
    }
    
    // PortType
    echo '<portType name="CineRecomendacionesPortType">' . "\n";
    foreach ($operaciones as $op => $params) {
        echo '  <operation name="' . $op . '">' . "\n";
        echo '    <input message="tns:' . $op . 'Request"/>' . "\n";
        echo '    <output message="tns:' . $op . 'Response"/>' . "\n";
        echo '  </operation>' . "\n";
    }
    echo '</portType>' . "\n";
    
    // Binding
    echo '<binding name="CineRecomendacionesBinding" type="tns:CineRecomendacionesPortType">' . "\n";
    echo '  <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>' . "\n";
    foreach ($operaciones as $op => $params) {
        echo '  <operation name="' . $op . '">' . "\n";
        echo '    <soap:operation soapAction="' . $target_namespace . '#' . $op . '"/>' . "\n";
        echo '    <input><soap:body use="encoded" namespace="' . $target_namespace . '" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/></input>' . "\n";
        echo '    <output><soap:body use="encoded" namespace="' . $target_namespace . '" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/></output>' . "\n";
        echo '  </operation>' . "\n";
    }
    echo '</binding>' . "\n";
    
    // Service
    echo '<service name="CineRecomendacionesService">' . "\n";
    echo '  <port name="CineRecomendacionesPort" binding="tns:CineRecomendacionesBinding">' . "\n";
    echo '    <soap:address location="' . $service_url . '"/>' . "\n";
    echo '  </port>' . "\n";
    echo '</service>' . "\n";
    
    echo '</definitions>' . "\n";
}
?>