/*
 * CLIENTE JAVASCRIPT CENTRALIZADO PARA TODAS LAS APIs
*/

class CineRecomendacionesAPI {
    constructor() {
        this.baseURL = window.location.origin + '/PHP/proyecto_recomendaciones/api/';
        this.cache = new Map();
        this.loadingStates = new Set();
        this.retryAttempts = 3;
        this.retryDelay = 1000;
        
        // Configurar interceptores globales
        this.setupGlobalErrorHandling();
        
        console.log('🚀 CineRecomendaciones API Client initialized');
    }

    // ===== MÉTODOS HELPER GENERALES =====

    /**
     * Realizar petición HTTP con manejo de errores
     */
    async makeRequest(endpoint, options = {}) {
        const url = this.baseURL + endpoint;
        const requestId = `${options.method || 'GET'}_${endpoint}`;
        
        // Evitar peticiones duplicadas
        if (this.loadingStates.has(requestId)) {
            console.warn(`⚠️ Petición duplicada evitada: ${requestId}`);
            return null;
        }

        // Verificar cache para GET requests
        if ((!options.method || options.method === 'GET') && this.cache.has(url)) {
            const cached = this.cache.get(url);
            if (Date.now() - cached.timestamp < 300000) { // 5 minutos
                console.log(`📦 Usando cache para: ${endpoint}`);
                return cached.data;
            }
        }

        this.loadingStates.add(requestId);
        this.showLoading(requestId);

        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        const finalOptions = { ...defaultOptions, ...options };

        try {
            const response = await this.fetchWithRetry(url, finalOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            // Guardar en cache si es GET exitoso
            if (finalOptions.method === 'GET' && data.success) {
                this.cache.set(url, {
                    data: data,
                    timestamp: Date.now()
                });
            }

            this.hideLoading(requestId);
            this.loadingStates.delete(requestId);

            if (!data.success) {
                throw new Error(data.error?.message || 'Error en la respuesta de la API');
            }

            return data;

        } catch (error) {
            this.hideLoading(requestId);
            this.loadingStates.delete(requestId);
            
            console.error(`❌ Error en ${endpoint}:`, error);
            this.showNotification(`Error: ${error.message}`, 'error');
            
            throw error;
        }
    }

    /**
     * Fetch con reintentos automáticos
     */
    async fetchWithRetry(url, options, attempt = 1) {
        try {
            return await fetch(url, options);
        } catch (error) {
            if (attempt < this.retryAttempts && error.name === 'TypeError') {
                console.warn(`🔄 Reintento ${attempt}/${this.retryAttempts} para ${url}`);
                await new Promise(resolve => setTimeout(resolve, this.retryDelay * attempt));
                return this.fetchWithRetry(url, options, attempt + 1);
            }
            throw error;
        }
    }

    // ===== API DE HISTORIAL =====

    /**
     * Guardar página visitada en el historial
     */
    async guardarHistorial(contenidoId, usuarioId = null) {
        try {
            return await this.makeRequest('guardar_historial.php', {
                method: 'POST',
                body: JSON.stringify({
                    contenido_id: contenidoId,
                    usuario_id: usuarioId
                })
            });
        } catch (error) {
            // Error silencioso para historial
            console.warn('No se pudo guardar historial:', error.message);
            return null;
        }
    }

    /**
     * Obtener historial del usuario
     */
    async obtenerHistorial(usuarioId = null, limite = 20, pagina = 1) {
        const params = new URLSearchParams({
            limite: limite,
            pagina: pagina
        });
        
        if (usuarioId) params.append('usuario_id', usuarioId);
        
        return await this.makeRequest(`obtener_historial.php?${params}`);
    }

    // ===== API DE RECOMENDACIONES =====

    /**
     * Obtener recomendaciones personalizadas
     */
    async obtenerRecomendaciones(tipo = 'personalizadas', opciones = {}) {
        const params = new URLSearchParams({
            tipo: tipo,
            limite: opciones.limite || 12,
            pagina: opciones.pagina || 1,
            incluir_metadata: opciones.incluirMetadata !== false
        });

        if (opciones.usuarioId) params.append('usuario_id', opciones.usuarioId);
        if (opciones.generoId) params.append('genero_id', opciones.generoId);
        if (opciones.contenidoId) params.append('contenido_id', opciones.contenidoId);

        return await this.makeRequest(`obtener_recomendaciones.php?${params}`);
    }

    /**
     * Refrescar recomendaciones (limpia cache)
     */
    async refrescarRecomendaciones(tipo = 'personalizadas') {
        this.limpiarCache('obtener_recomendaciones.php');
        return await this.obtenerRecomendaciones(tipo);
    }

    // ===== API DE CALIFICACIONES =====

    /**
     * Obtener calificaciones de un contenido
     */
    async obtenerCalificaciones(contenidoId, opciones = {}) {
        const params = new URLSearchParams({
            contenido_id: contenidoId,
            limite: opciones.limite || 10,
            pagina: opciones.pagina || 1,
            incluir_estadisticas: opciones.incluirEstadisticas !== false
        });

        if (opciones.usuarioId) params.append('usuario_id', opciones.usuarioId);

        return await this.makeRequest(`calificar_contenido.php?${params}`);
    }

    /**
     * Verificar si usuario ya calificó un contenido
     */
    async verificarCalificacion(contenidoId) {
        const params = new URLSearchParams({
            contenido_id: contenidoId,
            solo_verificar: true
        });

        return await this.makeRequest(`calificar_contenido.php?${params}`);
    }

    /**
     * Crear nueva calificación
     */
    async crearCalificacion(contenidoId, calificacion, comentario = '') {
        const resultado = await this.makeRequest('calificar_contenido.php', {
            method: 'POST',
            body: JSON.stringify({
                contenido_id: contenidoId,
                calificacion: calificacion,
                comentario: comentario
            })
        });

        if (resultado.success) {
            this.showNotification('¡Calificación guardada correctamente!', 'success');
            this.limpiarCache('calificar_contenido.php');
        }

        return resultado;
    }

    /**
     * Actualizar calificación existente
     */
    async actualizarCalificacion(contenidoId, calificacion, comentario = '') {
        const resultado = await this.makeRequest('calificar_contenido.php', {
            method: 'PUT',
            body: JSON.stringify({
                contenido_id: contenidoId,
                calificacion: calificacion,
                comentario: comentario
            })
        });

        if (resultado.success) {
            this.showNotification('Calificación actualizada', 'success');
            this.limpiarCache('calificar_contenido.php');
        }

        return resultado;
    }

    /**
     * Eliminar calificación
     */
    async eliminarCalificacion(contenidoId) {
        const resultado = await this.makeRequest('calificar_contenido.php', {
            method: 'DELETE',
            body: JSON.stringify({
                contenido_id: contenidoId
            })
        });

        if (resultado.success) {
            this.showNotification('Calificación eliminada', 'info');
            this.limpiarCache('calificar_contenido.php');
        }

        return resultado;
    }

    // ===== API DE BÚSQUEDA =====

    /**
     * Buscar contenido con filtros avanzados
     */
    async buscarContenido(termino = '', filtros = {}) {
        const params = new URLSearchParams();
        
        if (termino) params.append('q', termino);
        if (filtros.titulo) params.append('titulo', filtros.titulo);
        if (filtros.tipo) params.append('tipo', filtros.tipo);
        if (filtros.generoId) params.append('genero_id', filtros.generoId);
        if (filtros.añoMin) params.append('año_min', filtros.añoMin);
        if (filtros.añoMax) params.append('año_max', filtros.añoMax);
        if (filtros.calificacionMin) params.append('calificacion_min', filtros.calificacionMin);
        if (filtros.orden) params.append('orden', filtros.orden);
        if (filtros.direccion) params.append('direccion', filtros.direccion);
        
        params.append('limite', filtros.limite || 20);
        params.append('pagina', filtros.pagina || 1);
        params.append('incluir_estadisticas', filtros.incluirEstadisticas !== false);
        params.append('incluir_sugerencias', filtros.incluirSugerencias !== false);

        return await this.makeRequest(`buscar_contenido.php?${params}`);
    }

    /**
     * Autocompletado para búsqueda
     */
    async autocompletar(termino, limite = 5) {
        if (!termino || termino.length < 2) return { success: true, data: { sugerencias: [] } };

        const params = new URLSearchParams({
            titulo: termino,
            autocompletar: true,
            limite: limite
        });

        return await this.makeRequest(`buscar_contenido.php?${params}`);
    }

    // ===== API DE FAVORITOS =====

    /**
     * Obtener lista de favoritos
     */
    async obtenerFavoritos(opciones = {}) {
        const params = new URLSearchParams({
            tipo: opciones.tipo || 'todos',
            orden: opciones.orden || 'fecha_agregado',
            direccion: opciones.direccion || 'desc',
            limite: opciones.limite || 20,
            pagina: opciones.pagina || 1,
            incluir_estadisticas: opciones.incluirEstadisticas !== false,
            incluir_recomendaciones: opciones.incluirRecomendaciones || false
        });

        if (opciones.usuarioId) params.append('usuario_id', opciones.usuarioId);
        if (opciones.generoId) params.append('genero_id', opciones.generoId);

        return await this.makeRequest(`gestionar_favoritos.php?${params}`);
    }

    /**
     * Verificar si contenido está en favoritos
     */
    async verificarFavorito(contenidoId) {
        const params = new URLSearchParams({
            solo_verificar: true,
            contenido_id: contenidoId
        });

        return await this.makeRequest(`gestionar_favoritos.php?${params}`);
    }

    /**
     * Agregar a favoritos
     */
    async agregarFavorito(contenidoId, notas = '') {
        const resultado = await this.makeRequest('gestionar_favoritos.php', {
            method: 'POST',
            body: JSON.stringify({
                contenido_id: contenidoId,
                notas: notas
            })
        });

        if (resultado.success) {
            this.showNotification(resultado.data.mensaje_usuario, 'success');
            this.limpiarCache('gestionar_favoritos.php');
        }

        return resultado;
    }

    /**
     * Eliminar de favoritos
     */
    async eliminarFavorito(contenidoId) {
        const resultado = await this.makeRequest('gestionar_favoritos.php', {
            method: 'DELETE',
            body: JSON.stringify({
                contenido_id: contenidoId
            })
        });

        if (resultado.success) {
            this.showNotification(resultado.data.mensaje_usuario, 'info');
            this.limpiarCache('gestionar_favoritos.php');
        }

        return resultado;
    }

    /**
     * Toggle favorito (agregar/quitar automáticamente)
     */
    async toggleFavorito(contenidoId, notas = '') {
        try {
            const verificacion = await this.verificarFavorito(contenidoId);
            
            if (verificacion.data.es_favorito) {
                return await this.eliminarFavorito(contenidoId);
            } else {
                return await this.agregarFavorito(contenidoId, notas);
            }
        } catch (error) {
            console.error('Error en toggle favorito:', error);
            throw error;
        }
    }

    // ===== API DE EXPORTACIÓN =====

    /**
     * Exportar datos del sistema
     */
    async exportarDatos(tipo, formato = 'json', filtros = {}) {
        const params = new URLSearchParams({
            tipo: tipo,
            formato: formato,
            descargar: true,
            ...filtros
        });

        try {
            const response = await fetch(this.baseURL + `exportar_datos.php?${params}`, {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Error en la exportación');
            }

            // Crear descarga automática
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `export_${tipo}_${new Date().toISOString().slice(0,10)}.${formato}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            this.showNotification('Exportación completada', 'success');
            return { success: true };

        } catch (error) {
            this.showNotification('Error en la exportación', 'error');
            throw error;
        }
    }

    /**
     * Importar datos al sistema
     */
    async importarDatos(archivo, tipoImportacion, modo = 'upsert') {
        const formData = new FormData();
        formData.append('archivo', archivo);
        formData.append('tipo_importacion', tipoImportacion);
        formData.append('modo', modo);

        const resultado = await this.makeRequest('exportar_datos.php', {
            method: 'POST',
            headers: {}, // Dejar que el navegador establezca Content-Type para FormData
            body: formData
        });

        if (resultado.success) {
            this.showNotification(
                `Importación completada: ${resultado.data.insertados} insertados, ${resultado.data.actualizados} actualizados`,
                'success'
            );
        }

        return resultado;
    }

    // ===== API DE ESTADÍSTICAS (ADMIN) =====

    /**
     * Obtener estadísticas para administradores
     */
    async obtenerEstadisticas(tipo = 'general', opciones = {}) {
        const params = new URLSearchParams({
            tipo: tipo,
            formato: opciones.formato || 'json',
            periodo: opciones.periodo || 'mes',
            nivel_detalle: opciones.nivelDetalle || 'completo',
            incluir_graficos: opciones.incluirGraficos !== false,
            limite: opciones.limite || 100
        });

        if (opciones.fechaDesde) params.append('fecha_desde', opciones.fechaDesde);
        if (opciones.fechaHasta) params.append('fecha_hasta', opciones.fechaHasta);
        if (opciones.generoId) params.append('genero_id', opciones.generoId);
        if (opciones.agrupacion) params.append('agrupacion', opciones.agrupacion);

        return await this.makeRequest(`estadisticas_admin.php?${params}`);
    }

    // ===== UTILIDADES DE UI =====

    /**
     * Mostrar indicador de carga
     */
    showLoading(requestId) {
        // Buscar indicadores de carga existentes
        const loadingElements = document.querySelectorAll(`[data-loading="${requestId}"]`);
        loadingElements.forEach(el => {
            el.style.opacity = '0.7';
            el.style.pointerEvents = 'none';
        });

        // Mostrar spinner global si existe
        const globalSpinner = document.getElementById('global-loading');
        if (globalSpinner) {
            globalSpinner.style.display = 'block';
        }

        // Agregar clase loading al body
        document.body.classList.add('api-loading');
    }

    /**
     * Ocultar indicador de carga
     */
    hideLoading(requestId) {
        const loadingElements = document.querySelectorAll(`[data-loading="${requestId}"]`);
        loadingElements.forEach(el => {
            el.style.opacity = '';
            el.style.pointerEvents = '';
        });

        // Ocultar spinner global si no hay más peticiones
        if (this.loadingStates.size === 0) {
            const globalSpinner = document.getElementById('global-loading');
            if (globalSpinner) {
                globalSpinner.style.display = 'none';
            }
            document.body.classList.remove('api-loading');
        }
    }

    /**
     * Mostrar notificación al usuario
     */
    showNotification(message, type = 'info', duration = 5000) {
        // Crear contenedor de notificaciones si no existe
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 300px;
            `;
            document.body.appendChild(container);
        }

        // Crear notificación
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
            color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            font-size: 14px;
            word-wrap: break-word;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; font-size: 18px; cursor: pointer; padding: 0; margin-left: 10px;">×</button>
            </div>
        `;

        container.appendChild(notification);

        // Animar entrada
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);

        // Auto-eliminar
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }
        }, duration);
    }

    /**
     * Configurar manejo global de errores
     */
    setupGlobalErrorHandling() {
        window.addEventListener('unhandledrejection', (event) => {
            if (event.reason.message && event.reason.message.includes('API')) {
                console.error('Error no manejado en API:', event.reason);
                event.preventDefault();
            }
        });
    }

    /**
     * Limpiar cache específico o completo
     */
    limpiarCache(endpoint = null) {
        if (endpoint) {
            const keys = Array.from(this.cache.keys()).filter(key => key.includes(endpoint));
            keys.forEach(key => this.cache.delete(key));
        } else {
            this.cache.clear();
        }
    }

    /**
     * Obtener estadísticas del cache
     */
    getEstadisticasCache() {
        return {
            total_entradas: this.cache.size,
            memoria_aproximada: JSON.stringify(Array.from(this.cache.entries())).length,
            peticiones_activas: this.loadingStates.size
        };
    }
}

// ===== INICIALIZACIÓN Y EXPORTACIÓN GLOBAL =====

// Crear instancia global
window.CineAPI = new CineRecomendacionesAPI();

// Agregar estilos CSS para loading y notificaciones
const styles = document.createElement('style');
styles.textContent = `
    .api-loading {
        cursor: wait;
    }
    
    .api-loading * {
        pointer-events: none;
    }
    
    #global-loading {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        z-index: 9999;
        display: none;
    }
    
    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 10px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .notification {
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }
    
    /* Mejorar botones con estados de carga */
    .btn[data-loading] {
        position: relative;
        color: transparent !important;
    }
    
    .btn[data-loading]:before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 16px;
        height: 16px;
        border: 2px solid currentColor;
        border-top: 2px solid transparent;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        color: white;
    }
`;
document.head.appendChild(styles);

// Crear spinner global
const globalSpinner = document.createElement('div');
globalSpinner.id = 'global-loading';
globalSpinner.innerHTML = `
    <div class="loading-spinner"></div>
    <div style="text-align: center; font-size: 14px; color: #666;">
        Cargando...
    </div>
`;
document.body.appendChild(globalSpinner);

console.log('✅ CineRecomendaciones API Client cargado correctamente');

// ===== FUNCIONES DE CONVENIENCIA GLOBALES =====

/**
 * Funciones globales para facilitar el uso en HTML
 */
window.verDetalle = function(contenidoId) {
    // Guardar en historial antes de navegar
    if (window.CineAPI && typeof window.usuarioLogueado !== 'undefined' && window.usuarioLogueado) {
        window.CineAPI.guardarHistorial(contenidoId);
    }
    window.location.href = `detalle_contenido.php?id=${contenidoId}`;
};

window.toggleFavorito = async function(contenidoId, buttonElement) {
    if (!window.CineAPI) return;
    
    try {
        if (buttonElement) {
            buttonElement.setAttribute('data-loading', 'true');
            buttonElement.disabled = true;
        }
        
        const resultado = await window.CineAPI.toggleFavorito(contenidoId);
        
        if (buttonElement && resultado.success) {
            // Actualizar UI del botón
            const esFavorito = buttonElement.classList.contains('favorito-activo');
            if (esFavorito) {
                buttonElement.classList.remove('favorito-activo');
                buttonElement.innerHTML = '🤍 Agregar a favoritos';
            } else {
                buttonElement.classList.add('favorito-activo');
                buttonElement.innerHTML = '❤️ En favoritos';
            }
        }
        
    } catch (error) {
        console.error('Error en toggle favorito:', error);
    } finally {
        if (buttonElement) {
            buttonElement.removeAttribute('data-loading');
            buttonElement.disabled = false;
        }
    }
};

window.calificarContenido = async function(contenidoId, calificacion, comentario = '') {
    if (!window.CineAPI) return;
    
    try {
        const resultado = await window.CineAPI.crearCalificacion(contenidoId, calificacion, comentario);
        
        if (resultado.success) {
            // Actualizar UI de calificaciones
            const ratingContainer = document.querySelector(`[data-content-id="${contenidoId}"] .rating-display`);
            if (ratingContainer) {
                location.reload(); // Recargar para mostrar nueva calificación
            }
        }
        
        return resultado;
    } catch (error) {
        console.error('Error calificando contenido:', error);
        throw error;
    }
};

console.log('🎯 Funciones globales de conveniencia registradas');