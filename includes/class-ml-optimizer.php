<?php
/**
 * Optimizador ML con TensorFlow.js
 */
class FSC_ML_Optimizer {
    
    private $model_path;
    private $analytics_threshold = 100; // Mínimo de datos para análisis
    
    public function __construct() {
        $this->model_path = FSC_ML_DIR . 'scroll-prediction-model.json';
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_ml_scripts'));
        add_action('wp_ajax_fsc_track_metrics', array($this, 'track_user_metrics'));
        add_action('wp_ajax_nopriv_fsc_track_metrics', array($this, 'track_user_metrics'));
        add_action('fsc_ml_analysis', array($this, 'run_adaptive_analysis'));
        add_action('wp_footer', array($this, 'inject_ml_tracker'), 999);
    }
    
    /**
     * Cargar scripts de TensorFlow.js y modelo
     */
    public function enqueue_ml_scripts() {
        if (!get_option('fsc_ml_enabled', true) || is_admin()) {
            return;
        }
        
        // TensorFlow.js (versión ligera)
        wp_enqueue_script(
            'tensorflow-js',
            'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.10.0/dist/tf.min.js',
            array(),
            '4.10.0',
            true
        );
        
        // Nuestro optimizador ML
        wp_enqueue_script(
            'fsc-ml-optimizer',
            FSC_PLUGIN_URL . 'assets/ml-optimizer.js',
            array('tensorflow-js'),
            FSC_VERSION,
            true
        );
        
        // Configuración adaptativa
        $adaptive_config = $this->get_adaptive_config();
        
        wp_localize_script('fsc-ml-optimizer', 'fscML', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fsc_ml_nonce'),
            'model_url' => FSC_PLUGIN_URL . 'ml/scroll-prediction-model.json',
            'config' => $adaptive_config,
            'session_id' => $this->get_session_id(),
            'page_url' => get_permalink(),
            'device_type' => wp_is_mobile() ? 'mobile' : 'desktop'
        ));
    }
    
    /**
     * Obtener configuración adaptativa para la página actual
     */
    private function get_adaptive_config() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fsc_adaptive_config';
        $current_url = $_SERVER['REQUEST_URI'];
        $device_type = wp_is_mobile() ? 'mobile' : 'desktop';
        
        // Obtener configuración específica para esta página/dispositivo
        $configs = $wpdb->get_results($wpdb->prepare("
            SELECT config_key, config_value 
            FROM $table 
            WHERE (page_pattern IS NULL OR %s LIKE CONCAT('%%', page_pattern, '%%'))
            AND (device_type IS NULL OR device_type = %s)
            ORDER BY page_pattern DESC, device_type DESC
        ", $current_url, $device_type));
        
        $config = array(
            'scroll_prediction_threshold' => 0.7,
            'preload_distance' => 200,
            'lazy_load_threshold' => 300,
            'critical_css_inline' => true,
            'prefetch_next_page' => false
        );
        
        foreach ($configs as $row) {
            $config[$row->config_key] = json_decode($row->config_value, true);
        }
        
        return $config;
    }
    
    /**
     * Generar ID de sesión único
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['fsc_session_id'])) {
            $_SESSION['fsc_session_id'] = wp_generate_password(32, false);
        }
        
        return $_SESSION['fsc_session_id'];
    }
    
    /**
     * Rastrear métricas de usuario (AJAX)
     */
    public function track_user_metrics() {
        check_ajax_referer('fsc_ml_nonce', 'nonce');
        
        global $wpdb;
        
        $data = array(
            'session_id' => sanitize_text_field($_POST['session_id']),
            'page_url' => esc_url_raw($_POST['page_url']),
            'viewport_width' => intval($_POST['viewport_width']),
            'viewport_height' => intval($_POST['viewport_height']),
            'scroll_depth' => floatval($_POST['scroll_depth']),
            'time_on_page' => intval($_POST['time_on_page']),
            'lcp_time' => isset($_POST['lcp_time']) ? floatval($_POST['lcp_time']) : null,
            'fid_time' => isset($_POST['fid_time']) ? floatval($_POST['fid_time']) : null,
            'cls_score' => isset($_POST['cls_score']) ? floatval($_POST['cls_score']) : null,
            'device_type' => sanitize_text_field($_POST['device_type']),
            'connection_type' => isset($_POST['connection_type']) ? sanitize_text_field($_POST['connection_type']) : null
        );
        
        $table = $wpdb->prefix . 'fsc_user_metrics';
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            wp_send_json_success('Métricas guardadas');
        } else {
            wp_send_json_error('Error al guardar métricas');
        }
    }
    
    /**
     * Ejecutar análisis adaptativo
     */
    public function run_adaptive_analysis() {
        global $wpdb;
        
        $metrics_table = $wpdb->prefix . 'fsc_user_metrics';
        $config_table = $wpdb->prefix . 'fsc_adaptive_config';
        
        // Verificar si tenemos suficientes datos
        $total_metrics = $wpdb->get_var("SELECT COUNT(*) FROM $metrics_table WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        if ($total_metrics < $this->analytics_threshold) {
            return;
        }
        
        // Análisis por tipo de dispositivo
        $device_types = array('mobile', 'desktop');
        
        foreach ($device_types as $device_type) {
            $this->analyze_device_metrics($device_type);
        }
        
        // Regenerar páginas estáticas con nueva configuración
        $this->trigger_static_regeneration();
    }
    
    /**
     * Analizar métricas por tipo de dispositivo
     */
    private function analyze_device_metrics($device_type) {
        global $wpdb;
        
        $metrics_table = $wpdb->prefix . 'fsc_user_metrics';
        $config_table = $wpdb->prefix . 'fsc_adaptive_config';
        
        // Obtener métricas promedio de los últimos 7 días
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(scroll_depth) as avg_scroll_depth,
                AVG(time_on_page) as avg_time_on_page,
                AVG(lcp_time) as avg_lcp,
                AVG(viewport_height) as avg_viewport_height,
                COUNT(*) as total_sessions
            FROM $metrics_table 
            WHERE device_type = %s 
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $device_type));
        
        if (!$metrics || $metrics->total_sessions < 10) {
            return;
        }
        
        // Calcular configuraciones adaptativas basadas en datos reales
        $new_configs = array();
        
        // Ajustar umbral de predicción de scroll basado en comportamiento real
        if ($metrics->avg_scroll_depth > 0.8) {
            $new_configs['scroll_prediction_threshold'] = 0.6; // Más agresivo
            $new_configs['preload_distance'] = 300;
        } elseif ($metrics->avg_scroll_depth < 0.3) {
            $new_configs['scroll_prediction_threshold'] = 0.9; // Más conservador
            $new_configs['preload_distance'] = 100;
        }
        
        // Ajustar lazy loading basado en viewport
        if ($metrics->avg_viewport_height < 600) {
            $new_configs['lazy_load_threshold'] = 200; // Pantallas pequeñas
        } else {
            $new_configs['lazy_load_threshold'] = 400; // Pantallas grandes
        }
        
        // Ajustar CSS crítico basado en LCP
        if ($metrics->avg_lcp > 2500) { // LCP > 2.5s
            $new_configs['critical_css_inline'] = true;
            $new_configs['prefetch_next_page'] = false;
        } else {
            $new_configs['critical_css_inline'] = false;
            $new_configs['prefetch_next_page'] = true;
        }
        
        // Guardar configuraciones adaptativas
        foreach ($new_configs as $key => $value) {
            $wpdb->replace($config_table, array(
                'config_key' => $key,
                'config_value' => json_encode($value),
                'device_type' => $device_type,
                'page_pattern' => null
            ));
        }
    }
    
    /**
     * Disparar regeneración de páginas estáticas
     */
    private function trigger_static_regeneration() {
        // Programar regeneración en background
        wp_schedule_single_event(time() + 60, 'fsc_regenerate_with_new_config');
    }
    
    /**
     * Inyectar tracker ML en el footer
     */
    public function inject_ml_tracker() {
        if (!get_option('fsc_ml_enabled', true) || is_admin() || is_user_logged_in()) {
            return;
        }
        
        echo '<script id="fsc-ml-tracker">
        // Inicializar tracker ML cuando el DOM esté listo
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof FSCMLOptimizer !== "undefined") {
                FSCMLOptimizer.init();
            }
        });
        </script>';
    }
}