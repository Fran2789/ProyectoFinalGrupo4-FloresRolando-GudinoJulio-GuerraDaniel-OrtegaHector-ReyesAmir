/*
 * INTEGRACIÓN COMPLETA PARA DETALLE_CONTENIDO.PHP
 
 */

class DetalleContenidoIntegrado {
    constructor() {
        this.contenidoId = null;
        this.usuarioLogueado = false;
        this.contenidoData = null;
        this.calificacionActual = null;
        this.esFavorito = false;
        this.initialized = false;
        
        console.log('🎬 Sistema integrado de detalle contenido inicializado');
    }
    
    /**
     * Inicializar el sistema completo
     */
    async init() {
        if (this.initialized || !window.CineAPI) {
            return;
        }
        
        // Obtener datos básicos de la página
        this.contenidoId = this.extraerContenidoId();
        this.usuarioLogueado = window.usuarioLogueado || false;
        
        if (!this.contenidoId) {
            console.error('❌ No se pudo obtener ID del contenido');
            return;
        }
        
        console.log(`🚀 Inicializando detalle para contenido ${this.contenidoId}`);
        
        try {
            // 1. Guardar visita en historial
            if (this.usuarioLogueado) {
                await this.guardarEnHistorial();
            }
            
            // 2. Configurar sistema de favoritos
            await this.configurarFavoritos();
            
            // 3. Configurar sistema de calificaciones
            await this.configurarCalificaciones();
            
            // 4. Cargar estado inicial
            await this.cargarEstadoInicial();
            
            // 5. Configurar recomendaciones dinámicas
            await this.configurarRecomendaciones();
            
            // 6. Configurar auto-actualizaciones
            this.configurarAutoActualizaciones();
            
            this.initialized = true;
            console.log('✅ Sistema de detalle completamente integrado');
            
            // Notificación sutil de carga completa
            this.mostrarNotificacionSutil('Sistema de interacciones activado', 'success');
            
        } catch (error) {
            console.error('❌ Error en inicialización:', error);
            this.mostrarNotificacionSutil('Error cargando funcionalidades avanzadas', 'warning');
        }
    }
    
    /**
     * Extraer ID del contenido de la URL o elementos
     */
    extraerContenidoId() {
        // Intentar obtener de URL
        const urlParams = new URLSearchParams(window.location.search);
        let id = urlParams.get('id');
        
        if (id) return parseInt(id);
        
        // Buscar en elementos data-*
        const elemento = document.querySelector('[data-content-id], [data-contenido-id]');
        if (elemento) {
            return parseInt(elemento.getAttribute('data-content-id') || elemento.getAttribute('data-contenido-id'));
        }
        
        // Buscar en enlaces
        const enlace = document.querySelector('a[href*="detalle_contenido.php?id="]');
        if (enlace) {
            const match = enlace.href.match(/id=(\d+)/);
            if (match) return parseInt(match[1]);
        }
        
        return null;
    }
    
    /**
     * Guardar visita en historial
     */
    async guardarEnHistorial() {
        try {
            await window.CineAPI.guardarHistorial(this.contenidoId);
            console.log('📝 Visita guardada en historial');
        } catch (error) {
            // Error silencioso para historial
            console.warn('⚠️ No se pudo guardar en historial:', error.message);
        }
    }
    
    /**
     * Configurar sistema de favoritos
     */
    async configurarFavoritos() {
        // Buscar botón existente o crear uno nuevo
        let botonFavorito = document.getElementById('favoriteBtn');
        
        if (!botonFavorito) {
            botonFavorito = this.crearBotonFavorito();
            this.insertarBotonFavorito(botonFavorito);
        }
        
        // Configurar event listeners
        botonFavorito.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleFavoritoAvanzado();
        });
        
        console.log('❤️ Sistema de favoritos configurado');
    }
    
    /**
     * Crear botón de favorito moderno
     */
    crearBotonFavorito() {
        const boton = document.createElement('button');
        boton.id = 'favoriteBtn';
        boton.className = 'favorite-button modern-btn';
        boton.innerHTML = `
            <span id="favoriteIcon" class="favorite-icon">🤍</span>
            <span id="favoriteText" class="favorite-text">Agregar a favoritos</span>
            <span class="loading-spinner" style="display: none;">⏳</span>
        `;
        
        return boton;
    }
    
    /**
     * Insertar botón de favorito en el lugar apropiado
     */
    insertarBotonFavorito(boton) {
        // Buscar el mejor lugar para insertar
        const contenedorAcciones = document.querySelector('.action-buttons, .movie-actions, .content-actions');
        
        if (contenedorAcciones) {
            contenedorAcciones.appendChild(boton);
        } else {
            // Crear contenedor si no existe
            const nuevoContenedor = document.createElement('div');
            nuevoContenedor.className = 'action-buttons';
            nuevoContenedor.appendChild(boton);
            
            // Insertar después del título o poster
            const titulo = document.querySelector('h1, .content-title, .movie-title');
            if (titulo) {
                titulo.parentNode.insertBefore(nuevoContenedor, titulo.nextSibling);
            } else {
                document.querySelector('.content-info, .movie-info, main').appendChild(nuevoContenedor);
            }
        }
    }
    
    /**
     * Toggle favorito avanzado con animaciones
     */
    async toggleFavoritoAvanzado() {
        if (!this.usuarioLogueado) {
            window.CineAPI.showNotification('Debes iniciar sesión para agregar favoritos', 'warning');
            return;
        }
        
        const boton = document.getElementById('favoriteBtn');
        const icono = document.getElementById('favoriteIcon');
        const texto = document.getElementById('favoriteText');
        const spinner = boton.querySelector('.loading-spinner');
        
        // Estado de carga
        boton.disabled = true;
        icono.style.display = 'none';
        spinner.style.display = 'inline';
        texto.textContent = 'Procesando...';
        
        try {
            const resultado = await window.CineAPI.toggleFavorito(this.contenidoId);
            
            if (resultado.success) {
                // Actualizar estado local
                this.esFavorito = !this.esFavorito;
                
                // Animación de cambio
                this.animarCambioFavorito(boton, icono, texto);
                
                // Actualizar contadores si existen
                this.actualizarContadoresFavoritos();
                
                // Confeti si se agregó a favoritos
                if (this.esFavorito) {
                    this.mostrarAnimacionExito();
                }
            }
            
        } catch (error) {
            console.error('❌ Error en toggle favorito:', error);
            this.animarError(boton);
        } finally {
            // Restaurar estado del botón
            boton.disabled = false;
            spinner.style.display = 'none';
            icono.style.display = 'inline';
        }
    }
    
    /**
     * Animar cambio de favorito
     */
    animarCambioFavorito(boton, icono, texto) {
        // Efecto de escala
        boton.style.transform = 'scale(1.1)';
        
        setTimeout(() => {
            // Actualizar contenido
            if (this.esFavorito) {
                icono.textContent = '❤️';
                texto.textContent = 'En favoritos';
                boton.classList.add('favorito-activo');
            } else {
                icono.textContent = '🤍';
                texto.textContent = 'Agregar a favoritos';
                boton.classList.remove('favorito-activo');
            }
            
            // Restaurar escala
            boton.style.transform = 'scale(1)';
        }, 150);
    }
    
    /**
     * Configurar sistema de calificaciones
     */
    async configurarCalificaciones() {
        // Mejorar el sistema de calificaciones existente
        const formularioCalificacion = document.getElementById('ratingForm');
        const estrellas = document.querySelectorAll('.star');
        
        if (formularioCalificacion) {
            // Reemplazar submit por AJAX
            this.convertirFormularioCalificaciones();
        }
        
        if (estrellas.length > 0) {
            // Mejorar interactividad de estrellas
            this.mejorarEstrellas();
        }
        
        console.log('⭐ Sistema de calificaciones configurado');
    }
    
    /**
     * Convertir formulario de calificaciones a AJAX
     */
    convertirFormularioCalificaciones() {
        // Buscar el formulario y reemplazar comportamiento
        const formsCalificacion = document.querySelectorAll('form[action*="detalle_contenido.php"]');
        
        formsCalificacion.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.procesarCalificacionAjax(form);
            });
        });
        
        // Crear formulario AJAX moderno si no existe
        this.crearFormularioCalificacionModerno();
    }
    
    /**
     * Crear formulario de calificación moderno
     */
    crearFormularioCalificacionModerno() {
        const contenedor = document.querySelector('.rating-section, .calificaciones, .reviews-section');
        
        if (!contenedor) return;
        
        const formularioModerno = document.createElement('div');
        formularioModerno.id = 'modernRatingForm';
        formularioModerno.className = 'modern-rating-form';
        formularioModerno.innerHTML = `
            <div class="rating-title">
                <h3>✨ Califica este contenido</h3>
                <p>Tu opinión ayuda a otros usuarios</p>
            </div>
            
            <div class="star-rating-modern" id="starRatingModern">
                ${[1, 2, 3, 4, 5].map(i => `
                    <button type="button" class="star-btn" data-rating="${i}" onclick="window.DetalleIntegrado.seleccionarEstrella(${i})">
                        <span class="star-icon">⭐</span>
                        <span class="star-label">${['Malo', 'Regular', 'Bueno', 'Muy bueno', 'Excelente'][i-1]}</span>
                    </button>
                `).join('')}
            </div>
            
            <div class="rating-input-section" id="ratingInputSection" style="display: none;">
                <textarea id="comentarioModerno" placeholder="Comparte tu opinión... (opcional)" maxlength="500"></textarea>
                <div class="rating-actions">
                    <button type="button" onclick="window.DetalleIntegrado.cancelarCalificacion()" class="btn-secondary">Cancelar</button>
                    <button type="button" onclick="window.DetalleIntegrado.guardarCalificacionModerna()" class="btn-primary">
                        Publicar Calificación
                    </button>
                </div>
            </div>
        `;
        
        contenedor.appendChild(formularioModerno);
    }
    
    /**
     * Seleccionar estrella
     */
    seleccionarEstrella(rating) {
        this.calificacionSeleccionada = rating;
        
        // Actualizar visualización de estrellas
        const estrellas = document.querySelectorAll('#starRatingModern .star-btn');
        estrellas.forEach((estrella, index) => {
            if (index < rating) {
                estrella.classList.add('selected');
                estrella.querySelector('.star-icon').textContent = '⭐';
            } else {
                estrella.classList.remove('selected');
                estrella.querySelector('.star-icon').textContent = '☆';
            }
        });
        
        // Mostrar sección de comentario
        document.getElementById('ratingInputSection').style.display = 'block';
        
        // Auto-scroll suave
        document.getElementById('ratingInputSection').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
    }
    
    /**
     * Guardar calificación moderna
     */
    async guardarCalificacionModerna() {
        if (!this.usuarioLogueado) {
            window.CineAPI.showNotification('Debes iniciar sesión para calificar', 'warning');
            return;
        }
        
        if (!this.calificacionSeleccionada) {
            window.CineAPI.showNotification('Selecciona una calificación', 'warning');
            return;
        }
        
        const comentario = document.getElementById('comentarioModerno').value.trim();
        const botonGuardar = document.querySelector('.btn-primary');
        
        // Estado de carga
        botonGuardar.disabled = true;
        botonGuardar.innerHTML = '⏳ Guardando...';
        
        try {
            let resultado;
            
            // Verificar si ya existe calificación
            if (this.calificacionActual) {
                resultado = await window.CineAPI.actualizarCalificacion(
                    this.contenidoId, 
                    this.calificacionSeleccionada, 
                    comentario
                );
            } else {
                resultado = await window.CineAPI.crearCalificacion(
                    this.contenidoId, 
                    this.calificacionSeleccionada, 
                    comentario
                );
            }
            
            if (resultado.success) {
                // Actualizar estado local
                this.calificacionActual = {
                    calificacion: this.calificacionSeleccionada,
                    comentario: comentario
                };
                
                // Actualizar UI
                this.actualizarUICalificacion();
                
                // Ocultar formulario
                this.ocultarFormularioCalificacion();
                
                // Actualizar estadísticas
                this.actualizarEstadisticasContenido();
                
                // Animación de éxito
                this.mostrarAnimacionExito();
            }
            
        } catch (error) {
            console.error('❌ Error guardando calificación:', error);
            window.CineAPI.showNotification('Error al guardar calificación', 'error');
        } finally {
            botonGuardar.disabled = false;
            botonGuardar.innerHTML = 'Publicar Calificación';
        }
    }
    
    /**
     * Cancelar calificación
     */
    cancelarCalificacion() {
        this.calificacionSeleccionada = null;
        document.getElementById('ratingInputSection').style.display = 'none';
        document.getElementById('comentarioModerno').value = '';
        
        // Limpiar estrellas
        document.querySelectorAll('#starRatingModern .star-btn').forEach(estrella => {
            estrella.classList.remove('selected');
            estrella.querySelector('.star-icon').textContent = '☆';
        });
    }
    
    /**
     * Cargar estado inicial completo
     */
    async cargarEstadoInicial() {
        if (!this.usuarioLogueado) return;
        
        const promesas = [
            this.cargarEstadoFavorito(),
            this.cargarCalificacionActual(),
            this.cargarEstadisticasContenido()
        ];
        
        try {
            await Promise.all(promesas);
            console.log('📊 Estado inicial cargado completamente');
        } catch (error) {
            console.warn('⚠️ Error cargando estado inicial:', error.message);
        }
    }
    
    /**
     * Cargar estado de favorito
     */
    async cargarEstadoFavorito() {
        try {
            const resultado = await window.CineAPI.verificarFavorito(this.contenidoId);
            
            if (resultado.success) {
                this.esFavorito = resultado.data.es_favorito;
                this.actualizarUIFavorito();
            }
        } catch (error) {
            console.warn('⚠️ Error cargando favorito:', error.message);
        }
    }
    
    /**
     * Actualizar UI de favorito
     */
    actualizarUIFavorito() {
        const icono = document.getElementById('favoriteIcon');
        const texto = document.getElementById('favoriteText');
        const boton = document.getElementById('favoriteBtn');
        
        if (!icono || !texto || !boton) return;
        
        if (this.esFavorito) {
            icono.textContent = '❤️';
            texto.textContent = 'En favoritos';
            boton.classList.add('favorito-activo');
        } else {
            icono.textContent = '🤍';
            texto.textContent = 'Agregar a favoritos';
            boton.classList.remove('favorito-activo');
        }
    }
    
    /**
     * Configurar recomendaciones dinámicas
     */
    async configurarRecomendaciones() {
        const seccionRecomendaciones = document.querySelector('.recomendaciones-relacionadas, .related-content, .similar-movies');
        
        if (seccionRecomendaciones) {
            await this.cargarRecomendacionesRelacionadas();
        }
    }
    
    /**
     * Cargar recomendaciones relacionadas
     */
    async cargarRecomendacionesRelacionadas() {
        try {
            const resultado = await window.CineAPI.obtenerRecomendaciones('similares', {
                contenidoId: this.contenidoId,
                limite: 6,
                incluirMetadata: true
            });
            
            if (resultado.success && resultado.data.recomendaciones.length > 0) {
                this.mostrarRecomendacionesRelacionadas(resultado.data.recomendaciones);
            }
        } catch (error) {
            console.warn('⚠️ Error cargando recomendaciones:', error.message);
        }
    }
    
    /**
     * Mostrar recomendaciones relacionadas
     */
    mostrarRecomendacionesRelacionadas(recomendaciones) {
        const contenedor = document.querySelector('.recomendaciones-relacionadas, .related-content');
        
        if (!contenedor) return;
        
        const html = `
            <div class="section-header">
                <h3>🎯 Te podría interesar</h3>
                <button onclick="window.DetalleIntegrado.refrescarRecomendaciones()" class="btn-refresh">
                    🔄 Más sugerencias
                </button>
            </div>
            <div class="recommendations-grid">
                ${recomendaciones.map(item => `
                    <div class="recommendation-card" onclick="verDetalle(${item.id})">
                        <div class="card-poster">
                            <img src="uploads/${item.poster || 'no-image.svg'}" alt="${item.titulo}">
                            <div class="compatibility-badge">${item.compatibilidad || 85}% Match</div>
                        </div>
                        <div class="card-info">
                            <h4>${item.titulo}</h4>
                            <div class="card-meta">
                                <span class="rating">⭐ ${item.calificacion}</span>
                                <span class="year">${item.año}</span>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
        contenedor.innerHTML = html;
    }
    
    /**
     * Configurar auto-actualizaciones
     */
    configurarAutoActualizaciones() {
        // Actualizar estadísticas cada 2 minutos
        setInterval(() => {
            this.actualizarEstadisticasContenido();
        }, 120000);
        
        console.log('🔄 Auto-actualizaciones configuradas');
    }
    
    /**
     * Actualizar estadísticas de contenido
     */
    async actualizarEstadisticasContenido() {
        try {
            const estadisticas = await window.CineAPI.obtenerCalificaciones(this.contenidoId, {
                incluirEstadisticas: true,
                limite: 1
            });
            
            if (estadisticas.success) {
                this.actualizarUIEstadisticas(estadisticas.data.estadisticas);
            }
        } catch (error) {
            console.warn('⚠️ Error actualizando estadísticas:', error.message);
        }
    }
    
    /**
     * Actualizar UI de estadísticas
     */
    actualizarUIEstadisticas(stats) {
        // Actualizar calificación promedio
        const promedioElements = document.querySelectorAll('.rating-average, .calificacion-promedio');
        promedioElements.forEach(el => {
            el.textContent = stats.calificacion_promedio;
        });
        
        // Actualizar número de calificaciones
        const totalElements = document.querySelectorAll('.total-ratings, .total-calificaciones');
        totalElements.forEach(el => {
            el.textContent = `${stats.total_calificaciones} calificaciones`;
        });
        
        // Actualizar número de favoritos
        const favoritosElements = document.querySelectorAll('.total-favorites, .total-favoritos');
        favoritosElements.forEach(el => {
            el.textContent = `${stats.total_favoritos} en favoritos`;
        });
    }
    
    /**
     * Mostrar animación de éxito
     */
    mostrarAnimacionExito() {
        // Crear elementos de confeti temporal
        const confeti = document.createElement('div');
        confeti.className = 'success-confetti';
        confeti.innerHTML = '🎉✨🎊⭐🎯';
        confeti.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            z-index: 9999;
            animation: successAnimation 1.5s ease forwards;
            pointer-events: none;
        `;
        
        document.body.appendChild(confeti);
        
        // Limpiar después de la animación
        setTimeout(() => {
            confeti.remove();
        }, 1500);
    }
    
    /**
     * Mostrar notificación sutil
     */
    mostrarNotificacionSutil(mensaje, tipo = 'info') {
        // Versión más sutil que las notificaciones normales
        const notificacion = document.createElement('div');
        notificacion.className = `subtle-notification ${tipo}`;
        notificacion.textContent = mensaje;
        notificacion.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 123, 255, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 8888;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        `;
        
        if (tipo === 'success') notificacion.style.background = 'rgba(40, 167, 69, 0.9)';
        if (tipo === 'warning') notificacion.style.background = 'rgba(255, 193, 7, 0.9)';
        
        document.body.appendChild(notificacion);
        
        // Animar entrada
        setTimeout(() => notificacion.style.transform = 'translateY(0)', 10);
        
        // Auto-eliminar
        setTimeout(() => {
            notificacion.style.transform = 'translateY(100%)';
            setTimeout(() => notificacion.remove(), 300);
        }, 3000);
    }
    
    /**
     * Refrescar recomendaciones
     */
    async refrescarRecomendaciones() {
        const botonRefresh = document.querySelector('.btn-refresh');
        if (botonRefresh) {
            botonRefresh.innerHTML = '⏳ Cargando...';
            botonRefresh.disabled = true;
        }
        
        try {
            await this.cargarRecomendacionesRelacionadas();
            this.mostrarNotificacionSutil('Recomendaciones actualizadas', 'success');
        } catch (error) {
            this.mostrarNotificacionSutil('Error actualizando recomendaciones', 'warning');
        } finally {
            if (botonRefresh) {
                botonRefresh.innerHTML = '🔄 Más sugerencias';
                botonRefresh.disabled = false;
            }
        }
    }
    
    /**
     * Obtener estadísticas del sistema
     */
    getEstadisticas() {
        return {
            contenido_id: this.contenidoId,
            usuario_logueado: this.usuarioLogueado,
            es_favorito: this.esFavorito,
            tiene_calificacion: !!this.calificacionActual,
            sistema_inicializado: this.initialized
        };
    }
}

// ===== ESTILOS CSS PARA LA INTEGRACIÓN =====

const estilosIntegracion = `
    /* Botón de favorito moderno */
    .favorite-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }
    
    .favorite-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    .favorite-button.favorito-activo {
        background: linear-gradient(135deg, #dc3545, #c82333);
    }
    
    .favorite-button.favorito-activo:hover {
        background: linear-gradient(135deg, #c82333, #bd2130);
    }
    
    /* Sistema de calificación moderno */
    .modern-rating-form {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 12px;
        padding: 1.5rem;
        margin: 1rem 0;
        border: 1px solid #dee2e6;
    }
    
    .rating-title h3 {
        margin: 0 0 0.5rem 0;
        color: #495057;
    }
    
    .rating-title p {
        margin: 0 0 1rem 0;
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .star-rating-modern {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .star-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
        padding: 0.75rem 0.5rem;
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .star-btn:hover {
        border-color: #007bff;
        background: #f8f9ff;
        transform: translateY(-2px);
    }
    
    .star-btn.selected {
        border-color: #ffc107;
        background: #fff8dc;
    }
    
    .star-icon {
        font-size: 1.5rem;
        transition: all 0.3s ease;
    }
    
    .star-btn.selected .star-icon {
        transform: scale(1.2);
    }
    
    .star-label {
        font-size: 0.8rem;
        color: #6c757d;
        font-weight: 500;
    }
    
    .star-btn.selected .star-label {
        color: #495057;
        font-weight: 600;
    }
    
    .rating-input-section {
        background: white;
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
    }
    
    #comentarioModerno {
        width: 100%;
        min-height: 80px;
        padding: 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        resize: vertical;
        font-family: inherit;
        margin-bottom: 1rem;
    }
    
    #comentarioModerno:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }
    
    .rating-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }
    
    .btn-secondary, .btn-primary {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
    }
    
    .btn-primary:hover {
        background: #0056b3;
    }
    
    .btn-primary:disabled {
        background: #6c757d;
        cursor: not-allowed;
    }
    
    /* Recomendaciones relacionadas */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .btn-refresh {
        background: none;
        border: 1px solid #dee2e6;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        color: #6c757d;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .btn-refresh:hover {
        border-color: #007bff;
        color: #007bff;
    }
    
    .recommendations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .recommendation-card {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .recommendation-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .card-poster {
        position: relative;
        aspect-ratio: 2/3;
        overflow: hidden;
    }
    
    .card-poster img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .compatibility-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(40, 167, 69, 0.9);
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .card-info {
        padding: 0.75rem;
    }
    
    .card-info h4 {
        margin: 0 0 0.5rem 0;
        font-size: 0.9rem;
        line-height: 1.3;
    }
    
    .card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        color: #6c757d;
    }
    
    /* Animaciones */
    @keyframes successAnimation {
        0% { 
            opacity: 0; 
            transform: translate(-50%, -50%) scale(0.5); 
        }
        50% { 
            opacity: 1; 
            transform: translate(-50%, -50%) scale(1.2); 
        }
        100% { 
            opacity: 0; 
            transform: translate(-50%, -50%) scale(0.8) translateY(-20px); 
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .star-rating-modern {
            grid-template-columns: repeat(5, 1fr);
            gap: 0.25rem;
        }
        
        .star-btn {
            padding: 0.5rem 0.25rem;
        }
        
        .star-icon {
            font-size: 1.2rem;
        }
        
        .star-label {
            font-size: 0.7rem;
        }
        
        .recommendations-grid {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }
        
        .rating-actions {
            flex-direction: column;
        }
    }
`;

// ===== INICIALIZACIÓN =====

// Crear instancia global
window.DetalleIntegrado = new DetalleContenidoIntegrado();

// Inyectar estilos
const styleSheet = document.createElement('style');
styleSheet.textContent = estilosIntegracion;
document.head.appendChild(styleSheet);

// Inicializar cuando esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.DetalleIntegrado.init();
    });
} else {
    window.DetalleIntegrado.init();
}

console.log('✅ Sistema integrado de detalle contenido cargado');

// ===== FUNCIONES GLOBALES DE CONVENIENCIA =====

// Para uso desde HTML
window.toggleFavoritoIntegrado = () => window.DetalleIntegrado.toggleFavoritoAvanzado();
window.calificarIntegrado = (rating) => window.DetalleIntegrado.seleccionarEstrella(rating);

/*
=== INSTRUCCIONES DE USO ===

1. INCLUIR EN detalle_contenido.php:
   <script src="js/api-client.js"></script>
   <script src="js/detalle_contenido_integrado.js"></script>

2. AGREGAR VARIABLES PHP AL FINAL DE <head>:
   <script>
       window.usuarioLogueado = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
       <?php if (isLoggedIn()): ?>
           window.usuarioId = <?php echo $_SESSION['user_id']; ?>;
       <?php endif; ?>
   </script>

3. TESTING:
   - Verificar que aparezcan botones de favorito modernos
   - Verificar que las calificaciones funcionen sin recargar
   - Verificar que aparezcan las recomendaciones relacionadas
   - Verificar notificaciones sutiles

¡El sistema transformará la página en una experiencia completamente moderna! 🚀
*/