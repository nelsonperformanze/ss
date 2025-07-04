<?php
/**
 * Compatibilidad con Constructores Visuales
 */
class SBP_Visual_Builder_Compat {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_filter('sbp_should_cache_page', array($this, 'exclude_builder_pages'), 10, 2);
        
        // Hooks específicos para cada constructor
        $this->setup_elementor_hooks();
        $this->setup_beaver_builder_hooks();
        $this->setup_visual_composer_hooks();
        $this->setup_fusion_builder_hooks();
        $this->setup_divi_hooks();
        $this->setup_gutenberg_hooks();
    }
    
    public function init() {
        // Detectar modo de edición y desactivar caché
        add_action('wp', array($this, 'detect_builder_mode'));
    }
    
    /**
     * Detectar si estamos en modo de edición de constructor
     */
    public function detect_builder_mode() {
        $is_builder_mode = false;
        
        // Elementor
        if (isset($_GET['elementor-preview']) || 
            (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode())) {
            $is_builder_mode = true;
        }
        
        // Beaver Builder
        if (isset($_GET['fl_builder']) || 
            (class_exists('FLBuilderModel') && FLBuilderModel::is_builder_active())) {
            $is_builder_mode = true;
        }
        
        // Visual Composer
        if (isset($_GET['vc_editable']) || 
            (function_exists('vc_is_inline') && vc_is_inline())) {
            $is_builder_mode = true;
        }
        
        // Fusion Builder (Avada)
        if (isset($_GET['fb-edit']) || 
            (class_exists('FusionBuilder') && function_exists('fusion_is_preview_frame') && fusion_is_preview_frame())) {
            $is_builder_mode = true;
        }
        
        // Divi Builder
        if (isset($_GET['et_fb']) || 
            (function_exists('et_fb_is_enabled') && et_fb_is_enabled())) {
            $is_builder_mode = true;
        }
        
        // Gutenberg (Block Editor)
        if (is_admin() && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->is_block_editor()) {
                $is_builder_mode = true;
            }
        }
        
        // Si estamos en modo constructor, desactivar caché temporalmente
        if ($is_builder_mode) {
            add_filter('sbp_should_cache_page', '__return_false', 999);
        }
    }
    
    /**
     * Excluir páginas en modo de edición
     */
    public function exclude_builder_pages($should_cache, $url) {
        // Parámetros que indican modo de edición
        $builder_params = array(
            'elementor-preview',
            'fl_builder',
            'vc_editable',
            'fb-edit',
            'et_fb',
            'preview',
            'customize_changeset_uuid'
        );
        
        foreach ($builder_params as $param) {
            if (isset($_GET[$param])) {
                return false;
            }
        }
        
        return $should_cache;
    }
    
    /**
     * Configurar hooks para Elementor
     */
    private function setup_elementor_hooks() {
        // Limpiar caché cuando se guarda en Elementor
        add_action('elementor/editor/after_save', array($this, 'clear_page_cache_by_post_id'));
        add_action('elementor/core/files/clear_cache', array($this, 'clear_all_cache'));
        
        // Asegurar que Elementor funcione correctamente
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'ensure_elementor_compatibility'));
    }
    
    /**
     * Configurar hooks para Beaver Builder
     */
    private function setup_beaver_builder_hooks() {
        add_action('fl_builder_after_save_layout', array($this, 'clear_page_cache_by_post_id'));
        add_action('fl_builder_cache_cleared', array($this, 'clear_all_cache'));
    }
    
    /**
     * Configurar hooks para Visual Composer
     */
    private function setup_visual_composer_hooks() {
        add_action('vc_after_save', array($this, 'clear_page_cache_by_post_id'));
    }
    
    /**
     * Configurar hooks para Fusion Builder (Avada)
     */
    private function setup_fusion_builder_hooks() {
        add_action('fusion_builder_after_save', array($this, 'clear_page_cache_by_post_id'));
        add_action('avada_clear_dynamic_css_cache', array($this, 'clear_all_cache'));
    }
    
    /**
     * Configurar hooks para Divi
     */
    private function setup_divi_hooks() {
        add_action('et_builder_after_save', array($this, 'clear_page_cache_by_post_id'));
        add_action('et_core_page_resource_auto_clear', array($this, 'clear_all_cache'));
    }
    
    /**
     * Configurar hooks para Gutenberg
     */
    private function setup_gutenberg_hooks() {
        add_action('rest_after_save_post', array($this, 'clear_page_cache_by_post_id'));
    }
    
    /**
     * Limpiar caché de una página específica
     */
    public function clear_page_cache_by_post_id($post_id) {
        if (!$post_id) {
            return;
        }
        
        $post_url = get_permalink($post_id);
        if ($post_url) {
            sbp_clear_static_file_by_url($post_url);
            
            // También limpiar página principal si es la página de inicio
            if (get_option('page_on_front') == $post_id) {
                sbp_clear_static_file_by_url(home_url());
            }
        }
    }
    
    /**
     * Limpiar todo el caché
     */
    public function clear_all_cache() {
        sbp_clear_all_cache();
    }
    
    /**
     * Asegurar compatibilidad con Elementor
     */
    public function ensure_elementor_compatibility() {
        // Asegurar que los estilos de Elementor se carguen correctamente
        if (class_exists('\Elementor\Plugin')) {
            // Forzar regeneración de CSS de Elementor si es necesario
            if (get_option('sbp_elementor_css_regenerate', false)) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
                delete_option('sbp_elementor_css_regenerate');
            }
        }
    }
}