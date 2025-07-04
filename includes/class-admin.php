<?php
/**
 * Clase para el panel de administración - Fast Static Cache Pro
 */
class FSC_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        
        // AJAX handlers
        add_action('wp_ajax_fsc_toggle_cache', array($this, 'ajax_toggle_cache'));
        add_action('wp_ajax_fsc_generate_all_pages', array($this, 'ajax_generate_all_pages'));
        add_action('wp_ajax_fsc_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_fsc_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_fsc_preload_cache', array($this, 'ajax_preload_cache'));
        add_action('wp_ajax_fsc_optimize_assets', array($this, 'ajax_optimize_assets'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Fast Static Cache Pro',
            'Static Cache Pro',
            'manage_options',
            'fast-static-cache',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        register_setting('fsc_settings', 'fsc_enabled');
        register_setting('fsc_settings', 'fsc_cache_lifetime');
        register_setting('fsc_settings', 'fsc_excluded_pages');
        register_setting('fsc_settings', 'fsc_excluded_user_agents');
        register_setting('fsc_settings', 'fsc_show_cache_info');
        register_setting('fsc_settings', 'fsc_ml_enabled');
        register_setting('fsc_settings', 'fsc_asset_optimization');
        register_setting('fsc_settings', 'fsc_aggressive_optimization');
        register_setting('fsc_settings', 'fsc_image_optimization');
        register_setting('fsc_settings', 'fsc_critical_css');
    }
    
    public function admin_page() {
        $stats = fsc_get_cache_stats();
        $total_pages = fsc_get_total_pages_count();
        $enabled = get_option('fsc_enabled', true);
        $ml_enabled = get_option('fsc_ml_enabled', true);
        $asset_optimization = get_option('fsc_asset_optimization', true);
        $aggressive_optimization = get_option('fsc_aggressive_optimization', false);
        $progress_percent = $total_pages['total'] > 0 ? round(($stats['files'] / $total_pages['total']) * 100, 1) : 0;
        ?>
        <div class="wrap">
            <h1>🚀 Fast Static Cache Pro</h1>
            
            <!-- Control Principal -->
            <div class="fsc-main-card">
                <div class="fsc-header">
                    <div class="fsc-status">
                        <h2>Sistema de Caché Estático Inteligente</h2>
                        <p class="fsc-status-text <?php echo $enabled ? 'active' : 'inactive'; ?>">
                            <?php echo $enabled ? '● Activo' : '● Inactivo'; ?>
                        </p>
                        <?php if ($ml_enabled): ?>
                        <p class="fsc-ml-status">🧠 Optimización ML Activa</p>
                        <?php endif; ?>
                    </div>
                    <div class="fsc-toggle">
                        <label class="fsc-switch">
                            <input type="checkbox" id="fsc-toggle-cache" <?php checked($enabled); ?>>
                            <span class="fsc-slider"></span>
                        </label>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="fsc-stats">
                    <div class="fsc-stat">
                        <span class="fsc-stat-number"><?php echo $stats['files']; ?></span>
                        <span class="fsc-stat-label">Páginas Estáticas</span>
                    </div>
                    <div class="fsc-stat">
                        <span class="fsc-stat-number"><?php echo $total_pages['total']; ?></span>
                        <span class="fsc-stat-label">Total Páginas</span>
                    </div>
                    <div class="fsc-stat">
                        <span class="fsc-stat-number"><?php echo $progress_percent; ?>%</span>
                        <span class="fsc-stat-label">Progreso</span>
                    </div>
                    <div class="fsc-stat">
                        <span class="fsc-stat-number"><?php echo $this->format_bytes($stats['size']); ?></span>
                        <span class="fsc-stat-label">Tamaño</span>
                    </div>
                </div>
                
                <!-- Barra de Progreso -->
                <div class="fsc-progress-container">
                    <div class="fsc-progress-bar">
                        <div class="fsc-progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                    <span class="fsc-progress-text"><?php echo $stats['files']; ?> de <?php echo $total_pages['total']; ?> páginas convertidas</span>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="fsc-actions-card">
                <h3>Acciones Principales</h3>
                <div class="fsc-actions">
                    <button type="button" id="fsc-generate-all" class="fsc-btn fsc-btn-primary">
                        <span class="fsc-btn-icon">🚀</span>
                        Generar TODAS las Páginas
                    </button>
                    <button type="button" id="fsc-preload-cache" class="fsc-btn fsc-btn-secondary">
                        <span class="fsc-btn-icon">⚡</span>
                        Precarga Rápida
                    </button>
                    <button type="button" id="fsc-optimize-assets" class="fsc-btn fsc-btn-info">
                        <span class="fsc-btn-icon">🎯</span>
                        Optimizar Assets
                    </button>
                    <button type="button" id="fsc-clear-cache" class="fsc-btn fsc-btn-danger">
                        <span class="fsc-btn-icon">🗑️</span>
                        Limpiar Caché
                    </button>
                </div>
                
                <!-- Progreso de Generación -->
                <div id="fsc-generation-progress" class="fsc-generation-progress" style="display: none;">
                    <div class="fsc-progress-bar">
                        <div class="fsc-progress-fill" style="width: 0%"></div>
                    </div>
                    <p id="fsc-generation-status">Iniciando generación...</p>
                </div>
            </div>
            
            <!-- Configuración Avanzada -->
            <div class="fsc-config-card">
                <h3 class="fsc-collapsible" data-target="fsc-config-content">
                    Configuración Avanzada <span class="fsc-arrow">▼</span>
                </h3>
                <div id="fsc-config-content" class="fsc-config-content" style="display: none;">
                    <form method="post" action="options.php">
                        <?php settings_fields('fsc_settings'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Optimización ML</th>
                                <td>
                                    <input type="checkbox" name="fsc_ml_enabled" value="1" <?php checked(get_option('fsc_ml_enabled', true)); ?> />
                                    <label>Activar optimización inteligente con TensorFlow.js</label>
                                    <p class="description">Usa Machine Learning para optimizar la carga de contenido basado en el comportamiento del usuario</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Optimización de Assets</th>
                                <td>
                                    <input type="checkbox" name="fsc_asset_optimization" value="1" <?php checked(get_option('fsc_asset_optimization', true)); ?> />
                                    <label>Activar optimización básica de assets (RECOMENDADO)</label>
                                    <p class="description">Optimizaciones seguras: lazy loading inteligente, preload headers, compresión de imágenes</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">⚠️ Optimización Agresiva</th>
                                <td>
                                    <input type="checkbox" name="fsc_aggressive_optimization" value="1" <?php checked(get_option('fsc_aggressive_optimization', false)); ?> />
                                    <label><strong>Activar optimización agresiva (EXPERIMENTAL)</strong></label>
                                    <p class="description" style="color: #d63638;">
                                        <strong>ADVERTENCIA:</strong> Puede romper el diseño del sitio. Solo activar si sabes lo que haces.<br>
                                        Incluye: minificación y combinación de CSS/JS, modificación de assets del tema.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Optimización de Imágenes</th>
                                <td>
                                    <input type="checkbox" name="fsc_image_optimization" value="1" <?php checked(get_option('fsc_image_optimization', true)); ?> />
                                    <label>Generar formatos WebP automáticamente</label>
                                    <p class="description">Convierte imágenes a formato WebP para mejor compresión (solo imágenes de uploads)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">CSS Crítico</th>
                                <td>
                                    <input type="checkbox" name="fsc_critical_css" value="1" <?php checked(get_option('fsc_critical_css', true)); ?> />
                                    <label>Generar CSS crítico básico</label>
                                    <p class="description">CSS crítico seguro para mejorar la carga inicial</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Tiempo de Vida del Caché</th>
                                <td>
                                    <input type="number" name="fsc_cache_lifetime" value="<?php echo get_option('fsc_cache_lifetime', 3600); ?>" min="60" step="60" />
                                    <p class="description">Segundos antes de que expire el caché (3600 = 1 hora)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Páginas Excluidas</th>
                                <td>
                                    <textarea name="fsc_excluded_pages" rows="3" cols="50"><?php echo esc_textarea(implode("\n", (array)get_option('fsc_excluded_pages', array('/cart', '/checkout', '/my-account')))); ?></textarea>
                                    <p class="description">Una URL por línea (ej: /carrito, /checkout). WooCommerce se excluye automáticamente.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">User Agents Excluidos</th>
                                <td>
                                    <textarea name="fsc_excluded_user_agents" rows="2" cols="50"><?php echo esc_textarea(implode("\n", (array)get_option('fsc_excluded_user_agents', array('bot', 'crawler', 'spider')))); ?></textarea>
                                    <p class="description">User agents que no deben recibir caché</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Mostrar Info de Caché</th>
                                <td>
                                    <input type="checkbox" name="fsc_show_cache_info" value="1" <?php checked(get_option('fsc_show_cache_info', true)); ?> />
                                    <label>Mostrar información en comentarios HTML</label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
            
            <!-- Información del Sistema -->
            <div class="fsc-info-card">
                <h3>Estado del Sistema</h3>
                <div class="fsc-system-info">
                    <div class="fsc-info-item">
                        <strong>WooCommerce:</strong> 
                        <?php echo class_exists('WooCommerce') ? '✅ Detectado (compatibilidad activa)' : '❌ No detectado'; ?>
                    </div>
                    <div class="fsc-info-item">
                        <strong>TensorFlow.js:</strong> 
                        <?php echo $ml_enabled ? '✅ Habilitado' : '❌ Deshabilitado'; ?>
                    </div>
                    <div class="fsc-info-item">
                        <strong>Optimización de Assets:</strong> 
                        <?php 
                        if ($asset_optimization) {
                            echo $aggressive_optimization ? '⚠️ Modo Agresivo' : '✅ Modo Seguro';
                        } else {
                            echo '❌ Deshabilitada';
                        }
                        ?>
                    </div>
                    <div class="fsc-info-item">
                        <strong>Última generación:</strong> 
                        <?php echo $stats['last_generated'] ? $stats['last_generated'] : 'Nunca'; ?>
                    </div>
                </div>
                
                <?php if ($aggressive_optimization): ?>
                <div class="fsc-warning">
                    <p><strong>⚠️ ADVERTENCIA:</strong> El modo agresivo está activado. Si experimentas problemas visuales, desactívalo.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .fsc-main-card, .fsc-actions-card, .fsc-config-card, .fsc-info-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .fsc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .fsc-status h2 {
            margin: 0 0 5px 0;
            font-size: 20px;
        }
        
        .fsc-status-text {
            margin: 0 0 5px 0;
            font-weight: 600;
            font-size: 14px;
        }
        
        .fsc-status-text.active {
            color: #00a32a;
        }
        
        .fsc-status-text.inactive {
            color: #d63638;
        }
        
        .fsc-ml-status {
            margin: 0;
            font-size: 12px;
            color: #0073aa;
            font-weight: 500;
        }
        
        /* Switch Toggle */
        .fsc-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }
        
        .fsc-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .fsc-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
        }
        
        .fsc-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .fsc-slider {
            background-color: #00a32a;
        }
        
        input:checked + .fsc-slider:before {
            transform: translateX(22px);
        }
        
        /* Estadísticas */
        .fsc-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .fsc-stat {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .fsc-stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        
        .fsc-stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Barra de Progreso */
        .fsc-progress-container {
            margin-bottom: 10px;
        }
        
        .fsc-progress-bar {
            width: 100%;
            height: 8px;
            background: #f0f0f1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        
        .fsc-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #005a87);
            transition: width 0.3s ease;
        }
        
        .fsc-progress-text {
            font-size: 12px;
            color: #666;
        }
        
        /* Botones */
        .fsc-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .fsc-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .fsc-btn-primary {
            background: #0073aa;
            color: white;
        }
        
        .fsc-btn-primary:hover {
            background: #005a87;
        }
        
        .fsc-btn-secondary {
            background: #f0f0f1;
            color: #333;
        }
        
        .fsc-btn-secondary:hover {
            background: #ddd;
        }
        
        .fsc-btn-info {
            background: #00a32a;
            color: white;
        }
        
        .fsc-btn-info:hover {
            background: #008a20;
        }
        
        .fsc-btn-danger {
            background: #d63638;
            color: white;
        }
        
        .fsc-btn-danger:hover {
            background: #b32d2e;
        }
        
        .fsc-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .fsc-btn-icon {
            font-size: 16px;
        }
        
        /* Progreso de Generación */
        .fsc-generation-progress {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        /* Configuración Colapsable */
        .fsc-collapsible {
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0 0 15px 0;
        }
        
        .fsc-collapsible:hover {
            color: #0073aa;
        }
        
        .fsc-arrow {
            transition: transform 0.3s;
        }
        
        .fsc-arrow.rotated {
            transform: rotate(180deg);
        }
        
        .fsc-config-content {
            transition: all 0.3s;
        }
        
        /* Sistema Info */
        .fsc-system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 10px;
        }
        
        .fsc-info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 14px;
        }
        
        /* Warning */
        .fsc-warning {
            margin-top: 15px;
            padding: 10px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            color: #856404;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fsc-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .fsc-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .fsc-actions {
                grid-template-columns: 1fr;
            }
            
            .fsc-system-info {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    private function format_bytes($bytes, $precision = 1) {
        $units = array('B', 'KB', 'MB', 'GB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_fast-static-cache') {
            return;
        }
        
        wp_enqueue_script(
            'fsc-admin',
            FSC_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            FSC_VERSION,
            true
        );
        
        wp_localize_script('fsc-admin', 'fsc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fsc_nonce')
        ));
    }
    
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $stats = fsc_get_cache_stats();
        
        $wp_admin_bar->add_menu(array(
            'id' => 'fsc-menu',
            'title' => 'Estático (' . $stats['files'] . ')',
            'href' => admin_url('options-general.php?page=fast-static-cache')
        ));
    }
    
    // AJAX Handlers
    public function ajax_toggle_cache() {
        check_ajax_referer('fsc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $current_status = get_option('fsc_enabled', true);
        $new_status = !$current_status;
        
        update_option('fsc_enabled', $new_status);
        
        wp_send_json_success(array(
            'enabled' => $new_status,
            'message' => $new_status ? 'Sistema activado' : 'Sistema desactivado'
        ));
    }
    
    public function ajax_generate_all_pages() {
        check_ajax_referer('fsc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        // Aumentar límites para procesamiento masivo
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        
        try {
            $result = fsc_generate_all_static_pages();
            
            wp_send_json_success(array(
                'message' => 'Generación completada exitosamente',
                'total' => $result['total'],
                'success' => $result['success'],
                'errors' => $result['errors']
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error durante la generación: ' . $e->getMessage());
        }
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('fsc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $result = fsc_clear_all_cache();
        
        if ($result) {
            wp_send_json_success('Caché limpiado exitosamente');
        } else {
            wp_send_json_error('Error al limpiar caché');
        }
    }
    
    public function ajax_get_stats() {
        check_ajax_referer('fsc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        $stats = fsc_get_cache_stats();
        wp_send_json_success($stats);
    }
    
    public function ajax_preload_cache() {
        check_ajax_referer('fsc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            $preloaded = fsc_preload_cache();
            
            wp_send_json_success(array(
                'message' => 'Precarga completada exitosamente',
                'pages' => $preloaded
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error durante la precarga: ' . $e->getMessage());
        }
    }
    
    public function ajax_optimize_assets() {
        check_ajax_referer('fsc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        try {
            // Ejecutar optimización de assets
            fsc_run_asset_optimization();
            
            $mode = get_option('fsc_aggressive_optimization', false) ? 'agresivo' : 'seguro';
            
            wp_send_json_success(array(
                'message' => "Optimización de assets completada (modo {$mode})"
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error durante la optimización: ' . $e->getMessage());
        }
    }
}