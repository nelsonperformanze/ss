jQuery(document).ready(function($) {
    let generationInProgress = false;
    
    // Toggle del sistema de caché
    $('#fsc-toggle-cache').on('change', function() {
        const checkbox = $(this);
        const isEnabled = checkbox.is(':checked');
        
        $.ajax({
            url: fsc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fsc_toggle_cache',
                nonce: fsc_ajax.nonce
            },
            beforeSend: function() {
                checkbox.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar estado visual
                    const statusText = $('.fsc-status-text');
                    if (response.data.enabled) {
                        statusText.text('● Activo').removeClass('inactive').addClass('active');
                    } else {
                        statusText.text('● Inactivo').removeClass('active').addClass('inactive');
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
    $('#fsc-generate-all').on('click', function() {
        if (generationInProgress) {
            return;
        }
        
        const button = $(this);
        const originalText = button.html();
        const progressContainer = $('#fsc-generation-progress');
        const progressBar = progressContainer.find('.fsc-progress-fill');
        const statusText = $('#fsc-generation-status');
        
        if (!confirm('¿Generar TODAS las páginas estáticas? Esto puede tomar varios minutos y usar recursos del servidor.')) {
            return;
        }
        
        generationInProgress = true;
        button.html('<span class="fsc-btn-icon">⏳</span> Generando...').prop('disabled', true);
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
            url: fsc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fsc_generate_all_pages',
                nonce: fsc_ajax.nonce
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
    $('#fsc-preload-cache').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.html('<span class="fsc-btn-icon">⏳</span> Precargando...').prop('disabled', true);
        
        $.ajax({
            url: fsc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fsc_preload_cache',
                nonce: fsc_ajax.nonce
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
    $('#fsc-optimize-assets').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.html('<span class="fsc-btn-icon">⏳</span> Optimizando...').prop('disabled', true);
        
        $.ajax({
            url: fsc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fsc_optimize_assets',
                nonce: fsc_ajax.nonce
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
    $('#fsc-clear-cache').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        if (!confirm('¿Limpiar todos los archivos estáticos? Esta acción no se puede deshacer.')) {
            return;
        }
        
        button.html('<span class="fsc-btn-icon">⏳</span> Limpiando...').prop('disabled', true);
        
        $.ajax({
            url: fsc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fsc_clear_cache',
                nonce: fsc_ajax.nonce
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
    $('.fsc-collapsible').on('click', function() {
        const target = $(this).data('target');
        const content = $('#' + target);
        const arrow = $(this).find('.fsc-arrow');
        
        content.slideToggle();
        arrow.toggleClass('rotated');
    });
    
    // Función para mostrar notificaciones
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="fsc-notification fsc-notification-${type}">
                ${message}
                <button class="fsc-notification-close">&times;</button>
            </div>
        `);
        
        // Agregar estilos si no existen
        if (!$('#fsc-notification-styles').length) {
            $('head').append(`
                <style id="fsc-notification-styles">
                .fsc-notification {
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
                    animation: fscSlideIn 0.3s ease-out;
                }
                
                .fsc-notification-success { background: #00a32a; }
                .fsc-notification-error { background: #d63638; }
                .fsc-notification-warning { background: #f56e28; }
                .fsc-notification-info { background: #0073aa; }
                
                .fsc-notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 18px;
                    margin-left: 10px;
                    cursor: pointer;
                    padding: 0;
                }
                
                @keyframes fscSlideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                </style>
            `);
        }
        
        $('body').append(notification);
        
        notification.find('.fsc-notification-close').on('click', function() {
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
                url: fsc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fsc_get_stats',
                    nonce: fsc_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Actualizar estadísticas en tiempo real
                        const stats = response.data;
                        $('.fsc-stat-number').eq(0).text(stats.files);
                        $('.fsc-stat-number').eq(3).text(formatBytes(stats.size));
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
function fscClearCache() {
    if (confirm('¿Limpiar todos los archivos estáticos?')) {
        jQuery.ajax({
            url: fsc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'fsc_clear_cache',
                nonce: fsc_ajax.nonce
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