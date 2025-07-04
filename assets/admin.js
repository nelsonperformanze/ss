jQuery(document).ready(function($) {
    let generationInProgress = false;
    
    // Toggle del sistema de caché
    $('#sbp-toggle-cache').on('change', function() {
        const checkbox = $(this);
        const isEnabled = checkbox.is(':checked');
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_toggle_cache',
                nonce: sbp_ajax.nonce
            },
            beforeSend: function() {
                checkbox.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar estado visual
                    const statusText = $('.sbp-status-text');
                    if (response.data.enabled) {
                        statusText.text('🟢 Activo').removeClass('inactive').addClass('active');
                    } else {
                        statusText.text('🔴 Inactivo').removeClass('active').addClass('inactive');
                    }
                    
                    showNotification('✅ ' + response.data.message, 'success');
                } else {
                    // Revertir checkbox si hay error
                    checkbox.prop('checked', !isEnabled);
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                // Revertir checkbox si hay error
                checkbox.prop('checked', !isEnabled);
                showNotification('❌ Error de conexión: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                checkbox.prop('disabled', false);
            }
        });
    });
    
    // Generar todas las páginas
    $('#sbp-generate-all').on('click', function() {
        if (generationInProgress) {
            return;
        }
        
        const button = $(this);
        const originalText = button.html();
        const progressContainer = $('#sbp-generation-progress');
        const progressBar = progressContainer.find('.sbp-progress-fill');
        const statusText = $('#sbp-generation-status');
        
        if (!confirm('¿Convertir TODAS las páginas a estáticas? Esto puede tomar varios minutos y usar recursos del servidor.')) {
            return;
        }
        
        generationInProgress = true;
        button.html('<span class="sbp-btn-icon">⏳</span> Generando...').prop('disabled', true);
        progressContainer.show();
        
        // Simular progreso
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 2;
            if (progress > 95) progress = 95;
            
            progressBar.css('width', progress + '%');
            statusText.text(`Generando páginas... ${Math.round(progress)}%`);
        }, 2000);
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_generate_all_pages',
                nonce: sbp_ajax.nonce
            },
            timeout: 600000, // 10 minutos
            success: function(response) {
                clearInterval(progressInterval);
                progressBar.css('width', '100%');
                
                if (response.success) {
                    statusText.text(`✅ ¡Completado! ${response.data.success} páginas generadas de ${response.data.total} total`);
                    showNotification(`✅ Generación exitosa: ${response.data.success}/${response.data.total} páginas`, 'success');
                    
                    if (response.data.errors > 0) {
                        showNotification(`⚠️ ${response.data.errors} páginas tuvieron errores`, 'warning');
                    }
                    
                    // Actualizar estadísticas
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    statusText.text('❌ Error en la generación');
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                statusText.text('❌ Error de conexión o timeout');
                showNotification('❌ Error: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                generationInProgress = false;
                button.html(originalText).prop('disabled', false);
                
                setTimeout(() => {
                    progressContainer.hide();
                }, 5000);
            }
        });
    });
    
    // Precarga rápida
    $('#sbp-preload-cache').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.html('<span class="sbp-btn-icon">⏳</span> Precargando...').prop('disabled', true);
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_preload_cache',
                nonce: sbp_ajax.nonce
            },
            timeout: 120000, // 2 minutos
            success: function(response) {
                if (response.success) {
                    showNotification(`✅ Precarga completada: ${response.data.pages} páginas`, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('❌ Error de conexión: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Optimizar assets
    $('#sbp-optimize-assets').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.html('<span class="sbp-btn-icon">⏳</span> Optimizando...').prop('disabled', true);
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_optimize_assets',
                nonce: sbp_ajax.nonce
            },
            timeout: 180000, // 3 minutos
            success: function(response) {
                if (response.success) {
                    showNotification('✅ ' + response.data.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('❌ Error de conexión: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Limpiar caché
    $('#sbp-clear-cache').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        if (!confirm('¿Limpiar todos los archivos estáticos? Esta acción no se puede deshacer.')) {
            return;
        }
        
        button.html('<span class="sbp-btn-icon">⏳</span> Limpiando...').prop('disabled', true);
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_clear_cache',
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('✅ Caché limpiado exitosamente', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('❌ Error de conexión: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Configuración colapsable
    $('.sbp-collapsible').on('click', function() {
        const target = $(this).data('target');
        const content = $('#' + target);
        const arrow = $(this).find('.sbp-arrow');
        
        content.slideToggle();
        arrow.toggleClass('rotated');
    });
    
    // Función para mostrar notificaciones
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="sbp-notification sbp-notification-${type}">
                ${message}
                <button class="sbp-notification-close">&times;</button>
            </div>
        `);
        
        // Agregar estilos si no existen
        if (!$('#sbp-notification-styles').length) {
            $('head').append(`
                <style id="sbp-notification-styles">
                .sbp-notification {
                    position: fixed;
                    top: 32px;
                    right: 20px;
                    padding: 12px 20px;
                    border-radius: 6px;
                    color: white;
                    font-weight: 500;
                    z-index: 999999;
                    max-width: 400px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    animation: sbpSlideIn 0.3s ease-out;
                }
                
                .sbp-notification-success { background: #00a32a; }
                .sbp-notification-error { background: #d63638; }
                .sbp-notification-warning { background: #f56e28; }
                .sbp-notification-info { background: #0073aa; }
                
                .sbp-notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 18px;
                    margin-left: 10px;
                    cursor: pointer;
                    padding: 0;
                }
                
                @keyframes sbpSlideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                </style>
            `);
        }
        
        $('body').append(notification);
        
        notification.find('.sbp-notification-close').on('click', function() {
            notification.fadeOut(300, function() { $(this).remove(); });
        });
        
        setTimeout(() => {
            if (notification.is(':visible')) {
                notification.fadeOut(300, function() { $(this).remove(); });
            }
        }, 7000);
    }
    
    // Auto-refresh de estadísticas cada 30 segundos
    setInterval(function() {
        if (!generationInProgress) {
            $.ajax({
                url: sbp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbp_get_stats',
                    nonce: sbp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Actualizar estadísticas en tiempo real
                        const stats = response.data;
                        $('.sbp-stat-number').eq(0).text(stats.files);
                        $('.sbp-stat-number').eq(3).text(formatBytes(stats.size));
                    }
                },
                error: function() {
                    // Silencioso - no mostrar errores para auto-refresh
                }
            });
        }
    }, 30000);
    
    // Función auxiliar para formatear bytes
    function formatBytes(bytes, precision = 1) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        
        while (bytes > 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        
        return Math.round(bytes * Math.pow(10, precision)) / Math.pow(10, precision) + ' ' + units[i];
    }
});

// Función global para la barra de administración
function sbpClearCache() {
    if (confirm('¿Limpiar todos los archivos estáticos?')) {
        jQuery.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_clear_cache',
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Caché limpiado exitosamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error de conexión');
            }
        });
    }
}