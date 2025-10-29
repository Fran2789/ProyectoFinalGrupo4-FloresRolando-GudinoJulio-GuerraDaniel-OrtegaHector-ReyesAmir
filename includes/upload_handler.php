<?php
/**
 * Upload Handler - Manejo centralizado de subida de archivos
 * Proyecto: CineRecomendaciones
 * Ubicación: includes/upload_handler.php
 */

class UploadHandler {
    
    // Configuraciones por defecto
    private $config = [
        'upload_dir' => 'uploads/',
        'max_file_size' => 5242880, // 5MB en bytes
        'allowed_types' => [
            'images' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'],
            'documents' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'videos' => ['video/mp4', 'video/avi', 'video/mov', 'video/wmv']
        ],
        'allowed_extensions' => [
            'images' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'documents' => ['pdf', 'doc', 'docx'],
            'videos' => ['mp4', 'avi', 'mov', 'wmv']
        ]
    ];
    
    private $errors = [];
    
    public function __construct($custom_config = []) {
        // Sobrescribir configuración por defecto si se proporciona
        $this->config = array_merge($this->config, $custom_config);
        
        // Asegurar que el directorio de upload existe
        $this->ensureUploadDirectory();
    }
    
    /**
     * Subir archivo de imagen (posters, avatares, etc.)
     */
    public function uploadImage($file, $prefix = 'img', $subfolder = '') {
        return $this->uploadFile($file, 'images', $prefix, $subfolder);
    }
    
    /**
     * Subir archivo de documento
     */
    public function uploadDocument($file, $prefix = 'doc', $subfolder = '') {
        return $this->uploadFile($file, 'documents', $prefix, $subfolder);
    }
    
    /**
     * Subir archivo de video
     */
    public function uploadVideo($file, $prefix = 'vid', $subfolder = '') {
        return $this->uploadFile($file, 'videos', $prefix, $subfolder);
    }
    
    /**
     * Función principal para subir archivos
     */
    public function uploadFile($file, $type = 'images', $prefix = 'file', $subfolder = '') {
        $this->errors = [];
        
        // Validar que se proporcionó un archivo
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->errors[] = 'No se proporcionó ningún archivo';
            return $this->returnError();
        }
        
        // Verificar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return $this->returnError();
        }
        
        // Validar tipo de archivo
        if (!$this->validateFileType($file, $type)) {
            return $this->returnError();
        }
        
        // Validar tamaño de archivo
        if (!$this->validateFileSize($file)) {
            return $this->returnError();
        }
        
        // Validar extensión
        if (!$this->validateFileExtension($file, $type)) {
            return $this->returnError();
        }
        
        // Generar nombre único para el archivo
        $filename = $this->generateUniqueFilename($file, $prefix);
        
        // Determinar ruta completa
        $upload_path = $this->getUploadPath($subfolder);
        $full_path = $upload_path . $filename;
        
        // Crear subdirectorio si no existe
        if (!empty($subfolder)) {
            $this->ensureDirectory($upload_path);
        }
        
        // Mover archivo temporal a la ubicación final
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // Establecer permisos correctos
            chmod($full_path, 0644);
            
            return $this->returnSuccess($filename, $subfolder);
        } else {
            $this->errors[] = 'Error al mover el archivo a su ubicación final';
            return $this->returnError();
        }
    }
    
    /**
     * Eliminar archivo subido
     */
    public function deleteFile($filename, $subfolder = '') {
        $file_path = $this->getUploadPath($subfolder) . $filename;
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                return ['success' => true, 'message' => 'Archivo eliminado correctamente'];
            } else {
                return ['success' => false, 'message' => 'Error al eliminar el archivo'];
            }
        } else {
            return ['success' => false, 'message' => 'El archivo no existe'];
        }
    }
    
    /**
     * Redimensionar imagen si es necesario
     */
    public function resizeImage($filepath, $max_width = 800, $max_height = 600, $quality = 85) {
        if (!extension_loaded('gd')) {
            return ['success' => false, 'message' => 'Extensión GD no disponible'];
        }
        
        $image_info = getimagesize($filepath);
        if (!$image_info) {
            return ['success' => false, 'message' => 'No es una imagen válida'];
        }
        
        list($width, $height, $type) = $image_info;
        
        // No redimensionar si ya es más pequeña
        if ($width <= $max_width && $height <= $max_height) {
            return ['success' => true, 'message' => 'Imagen no necesita redimensionamiento'];
        }
        
        // Calcular nuevas dimensiones manteniendo aspect ratio
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = intval($width * $ratio);
        $new_height = intval($height * $ratio);
        
        // Crear imagen desde archivo
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($filepath);
                break;
            default:
                return ['success' => false, 'message' => 'Tipo de imagen no soportado para redimensionamiento'];
        }
        
        if (!$source) {
            return ['success' => false, 'message' => 'Error al crear imagen desde archivo'];
        }
        
        // Crear nueva imagen redimensionada
        $destination = imagecreatetruecolor($new_width, $new_height);
        
        // Preservar transparencia para PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
            imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Redimensionar
        imagecopyresampled(
            $destination, $source,
            0, 0, 0, 0,
            $new_width, $new_height,
            $width, $height
        );
        
        // Guardar imagen redimensionada
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($destination, $filepath, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($destination, $filepath, 9);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($destination, $filepath, $quality);
                break;
        }
        
        // Liberar memoria
        imagedestroy($source);
        imagedestroy($destination);
        
        if ($result) {
            return [
                'success' => true, 
                'message' => 'Imagen redimensionada correctamente',
                'new_dimensions' => ['width' => $new_width, 'height' => $new_height]
            ];
        } else {
            return ['success' => false, 'message' => 'Error al guardar imagen redimensionada'];
        }
    }
    
    /**
     * Obtener información del archivo
     */
    public function getFileInfo($filepath) {
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Archivo no encontrado'];
        }
        
        $info = [
            'filename' => basename($filepath),
            'size' => filesize($filepath),
            'size_formatted' => $this->formatFileSize(filesize($filepath)),
            'mime_type' => mime_content_type($filepath),
            'extension' => strtolower(pathinfo($filepath, PATHINFO_EXTENSION)),
            'last_modified' => filemtime($filepath),
            'is_image' => $this->isImageFile($filepath)
        ];
        
        // Información adicional para imágenes
        if ($info['is_image']) {
            $image_info = getimagesize($filepath);
            if ($image_info) {
                $info['width'] = $image_info[0];
                $info['height'] = $image_info[1];
                $info['dimensions'] = $image_info[0] . 'x' . $image_info[1];
            }
        }
        
        return ['success' => true, 'data' => $info];
    }
    
    /**
     * Validar tipo de archivo
     */
    private function validateFileType($file, $type) {
        if (!isset($this->config['allowed_types'][$type])) {
            $this->errors[] = 'Tipo de archivo no configurado';
            return false;
        }
        
        $allowed_types = $this->config['allowed_types'][$type];
        
        if (!in_array($file['type'], $allowed_types)) {
            $allowed_str = implode(', ', $allowed_types);
            $this->errors[] = "Tipo de archivo no permitido. Tipos permitidos: {$allowed_str}";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validar tamaño de archivo
     */
    private function validateFileSize($file) {
        if ($file['size'] > $this->config['max_file_size']) {
            $max_size_mb = round($this->config['max_file_size'] / 1024 / 1024, 2);
            $this->errors[] = "El archivo es demasiado grande. Tamaño máximo: {$max_size_mb}MB";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validar extensión de archivo
     */
    private function validateFileExtension($file, $type) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!isset($this->config['allowed_extensions'][$type])) {
            $this->errors[] = 'Extensiones no configuradas para este tipo';
            return false;
        }
        
        $allowed_extensions = $this->config['allowed_extensions'][$type];
        
        if (!in_array($extension, $allowed_extensions)) {
            $allowed_str = implode(', ', $allowed_extensions);
            $this->errors[] = "Extensión no permitida. Extensiones permitidas: {$allowed_str}";
            return false;
        }
        
        return true;
    }
    
    /**
     * Generar nombre único para archivo
     */
    private function generateUniqueFilename($file, $prefix) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $timestamp = time();
        $random = uniqid();
        
        return "{$prefix}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Obtener ruta de upload
     */
    private function getUploadPath($subfolder = '') {
        $base_path = $this->config['upload_dir'];
        
        if (!empty($subfolder)) {
            $base_path .= rtrim($subfolder, '/') . '/';
        }
        
        return $base_path;
    }
    
    /**
     * Asegurar que directorio existe
     */
    private function ensureDirectory($path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
    
    /**
     * Asegurar que directorio principal de uploads existe
     */
    private function ensureUploadDirectory() {
        $this->ensureDirectory($this->config['upload_dir']);
        
        // Crear archivo .htaccess para seguridad
        $htaccess_path = $this->config['upload_dir'] . '.htaccess';
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "# Protección de archivos upload\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "Options -ExecCGI\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "    Order Deny,Allow\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($htaccess_path, $htaccess_content);
        }
    }
    
    /**
     * Verificar si archivo es imagen
     */
    private function isImageFile($filepath) {
        $image_info = getimagesize($filepath);
        return $image_info !== false;
    }
    
    /**
     * Formatear tamaño de archivo
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Obtener mensaje de error de upload
     */
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'El archivo excede el tamaño máximo permitido por el servidor';
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo excede el tamaño máximo permitido por el formulario';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo fue subido parcialmente';
            case UPLOAD_ERR_NO_FILE:
                return 'No se subió ningún archivo';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta el directorio temporal';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en disco';
            case UPLOAD_ERR_EXTENSION:
                return 'Subida de archivo detenida por extensión';
            default:
                return 'Error desconocido al subir archivo';
        }
    }
    
    /**
     * Retornar respuesta de éxito
     */
    private function returnSuccess($filename, $subfolder = '') {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $this->getUploadPath($subfolder) . $filename,
            'url' => $this->getUploadPath($subfolder) . $filename,
            'message' => 'Archivo subido correctamente'
        ];
    }
    
    /**
     * Retornar respuesta de error
     */
    private function returnError() {
        return [
            'success' => false,
            'errors' => $this->errors,
            'message' => implode('; ', $this->errors)
        ];
    }
    
    /**
     * Obtener errores
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Limpiar directorio de uploads (solo archivos temporales)
     */
    public function cleanupOldFiles($days = 30, $pattern = '*_temp_*') {
        $upload_dir = $this->config['upload_dir'];
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $deleted_count = 0;
        
        $files = glob($upload_dir . $pattern);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        return [
            'success' => true,
            'deleted_count' => $deleted_count,
            'message' => "Se eliminaron {$deleted_count} archivos temporales"
        ];
    }
}

/**
 * Función helper para uso rápido
 */
function uploadPoster($file) {
    $uploader = new UploadHandler([
        'upload_dir' => '../uploads/',
        'max_file_size' => 5242880 // 5MB
    ]);
    
    return $uploader->uploadImage($file, 'poster');
}

/**
 * Función helper para subir avatar de usuario
 */
function uploadAvatar($file) {
    $uploader = new UploadHandler([
        'upload_dir' => '../uploads/',
        'max_file_size' => 2097152 // 2MB
    ]);
    
    $result = $uploader->uploadImage($file, 'avatar', 'avatars');
    
    // Redimensionar avatar automáticamente
    if ($result['success']) {
        $resize_result = $uploader->resizeImage($result['path'], 200, 200, 90);
        if (!$resize_result['success']) {
            error_log("Error al redimensionar avatar: " . $resize_result['message']);
        }
    }
    
    return $result;
}

/**
 * Función helper para subir documento
 */
function uploadDocument($file) {
    $uploader = new UploadHandler([
        'upload_dir' => '../uploads/',
        'max_file_size' => 10485760 // 10MB
    ]);
    
    return $uploader->uploadDocument($file, 'doc', 'documents');
}
?>