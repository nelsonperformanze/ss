<?php
/**
 * Clase principal de StaticBoost Pro
 */
class StaticBoost_Core {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_loaded', array($this, 'start_buffering'));
        add_action('shutdown', array($this, 'end_buffering'));
        
        // Regenerar cuando se actualiza contenido
        add_action('save_post', array($this, 'regenerate_static_files'));
        add_action('comment_post', array($this, 'regenerate_page_static'));
        add_action('wp_set_comment_status', array($this, 'regenerate_page_static'));
        
        // Hooks para constructores visuales
        add_action('elementor/editor/after_save', array($this, 'regenerate_static_files'));
        add_action('fl_builder_after_save_layout', array($this, 'regenerate_static_files'));
        add_action('vc_after_save', array($this, 'regenerate_static_files'));
        add_action('fusion_builder_after_save', array($this, 'regenerate_static_files'));
        
        // Servir estático antes de WordPress
        add_action('template_redirect', array($this, 'serve_static_if_exists'), 1);
        
        // Información de caché
        add_action('wp_footer', array($this, 'add_cache_info'), 999);
        
        // Filtros para optimización
        add_filter('sbp_should_cache_page', array($this, 'should_cache_current_page'), 10, 2);
    }
    
    public function init() {
        load_plugin_textdomain('staticboost-pro', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Servir archivo estático si existe
     */
    public function serve_static_if_exists() {
        if (!$this->should_serve_static()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        
        if ($this->is_static_file_valid($static_file)) {
            $this->serve_static_file($static_file);
            exit;
        }
    }
    
    /**
     * Verificar si debe servir archivo estático
     */
    private function should_serve_static() {
        // No servir si está deshabilitado
        if (!get_option('sbp_enabled', true)) {
            return false;
        }
        
        // No servir para usuarios logueados
        if (is_user_logged_in()) {
            return false;
        }
        
        // No servir en admin
        if (is_admin()) {
            return false;
        }
        
        // Solo GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        
        // No servir si hay parámetros GET
        if (!empty($_GET)) {
            return false;
        }
        
        // Verificar exclusiones
        if ($this->is_page_excluded()) {
            return false;
        }
        
        // Verificar compatibilidad WooCommerce
        if (class_exists('WooCommerce')) {
            if (is_cart() || is_checkout() || is_account_page() || is_product()) {
                return false;
            }
            
            // Verificar cookies de WooCommerce
            if (isset($_COOKIE['woocommerce_cart_hash']) || 
                isset($_COOKIE['woocommerce_items_in_cart'])) {
                return false;
            }
        }
        
        return true;
    }
    
    private function serve_static_file($static_file) {
        // Headers optimizados para máximo rendimiento y PageSpeed
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Static-Cache: HIT');
        header('X-Cache-Status: STATIC');
        header('Cache-Control: public, max-age=3600');
        header('Vary: Accept-Encoding');
        
        // Headers adicionales para PageSpeed
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Servir versión comprimida si el cliente la acepta
        $compressed_file = $static_file . '.gz';
        
        if (file_exists($compressed_file) && $this->client_accepts_gzip()) {
            header('Content-Encoding: gzip');
            header('Content-Length: ' . filesize($compressed_file));
            readfile($compressed_file);
        } else {
            header('Content-Length: ' . filesize($static_file));
            readfile($static_file);
        }
        
        exit;
    }
    
    public function start_buffering() {
        if (!$this->should_generate_static()) {
            return;
        }
        
        ob_start(array($this, 'generate_static_file'));
    }
    
    public function end_buffering() {
        if (ob_get_level()) {
            ob_end_flush();
        }
    }
    
    /**
     * Verificar si debe generar archivo estático
     */
    private function should_generate_static() {
        // Aplicar filtro personalizable
        $should_cache = apply_filters('sbp_should_cache_page', true, $_SERVER['REQUEST_URI']);
        
        if (!$should_cache) {
            return false;
        }
        
        return $this->should_serve_static();
    }
    
    /**
     * Filtro para verificar si la página actual debe ser cacheada
     */
    public function should_cache_current_page($should_cache, $url) {
        // No cachear páginas especiales
        if (is_404() || is_feed() || is_robots() || is_trackback()) {
            return false;
        }
        
        // No cachear búsquedas
        if (is_search()) {
            return false;
        }
        
        // No cachear páginas con formularios
        if (is_page() && $this->page_has_forms()) {
            return false;
        }
        
        return $should_cache;
    }
    
    /**
     * Verificar si la página tiene formularios
     */
    private function page_has_forms() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Buscar formularios comunes en el contenido
        $form_patterns = array(
            '<form',
            'contact-form',
            'wpcf7-form',
            'gform_wrapper',
            'ninja-forms'
        );
        
        foreach ($form_patterns as $pattern) {
            if (strpos($post->post_content, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generar archivo estático
     */
    public function generate_static_file($buffer) {
        if (!$this->should_generate_static_from_buffer($buffer)) {
            return $buffer;
        }
        
        $static_file = $this->get_static_file_path();
        $static_dir = dirname($static_file);
        
        if (!file_exists($static_dir)) {
            wp_mkdir_p($static_dir);
        }
        
        // Optimizar HTML antes de guardar
        $optimized_html = $this->optimize_html_for_static($buffer);
        
        // Aplicar filtro para optimizaciones adicionales
        $optimized_html = apply_filters('sbp_static_html', $optimized_html, $_SERVER['REQUEST_URI']);
        
        // Añadir información de caché si está habilitado
        if (get_option('sbp_show_cache_info', true)) {
            $cache_info = sprintf(
                "\n<!-- StaticBoost Pro: Generado el %s -->",
                date('Y-m-d H:i:s')
            );
            $optimized_html .= $cache_info;
        }
        
        // Guardar archivo estático
        $result = file_put_contents($static_file, $optimized_html, LOCK_EX);
        
        if ($result) {
            // Crear versión comprimida
            if (function_exists('gzencode')) {
                file_put_contents($static_file . '.gz', gzencode($optimized_html, 9), LOCK_EX);
            }
        }
        
        return $buffer;
    }
    
    /**
     * Verificar si debe generar estático desde buffer
     */
    private function should_generate_static_from_buffer($buffer) {
        // No generar si el buffer está vacío
        if (empty(trim($buffer))) {
            return false;
        }
        
        // No generar si no es HTML válido
        if (strpos($buffer, '<html') === false && strpos($buffer, '<!DOCTYPE') === false) {
            return false;
        }
        
        // No generar si hay errores PHP
        if (strpos($buffer, 'Fatal error') !== false || 
            strpos($buffer, 'Parse error') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Optimizar HTML para archivo estático
     */
    private function optimize_html_for_static($html) {
        $site_url = get_site_url();
        
        // Convertir URLs relativas a absolutas para mejor compatibilidad
        $html = preg_replace('/href="\/([^"]*)"/', 'href="' . $site_url . '/$1"', $html);
        $html = preg_replace('/src="\/([^"]*)"/', 'src="' . $site_url . '/$1"', $html);
        
        // Optimizar espacios en blanco (conservando legibilidad)
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Añadir meta tags para mejor rendimiento y PageSpeed
        $performance_meta = '
        <meta name="generator" content="StaticBoost Pro">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#ffffff">
        <meta name="format-detection" content="telephone=no">
        ';
        
        $html = str_replace('</head>', $performance_meta . '</head>', $html);
        
        return $html;
    }
    
    private function get_static_file_path() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_uri = rtrim($request_uri, '/');
        
        if (empty($request_uri)) {
            $request_uri = '/index';
        }
        
        return SBP_CACHE_DIR . ltrim($request_uri, '/') . '/index.html';
    }
    
    private function is_static_file_valid($static_file) {
        if (!file_exists($static_file)) {
            return false;
        }
        
        $cache_lifetime = get_option('sbp_cache_lifetime', 3600);
        $file_time = filemtime($static_file);
        
        return (time() - $file_time) < $cache_lifetime;
    }
    
    private function client_accepts_gzip() {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }
    
    private function is_page_excluded() {
        $excluded_pages = get_option('sbp_excluded_pages', array());
        if (is_string($excluded_pages)) {
            $excluded_pages = explode("\n", $excluded_pages);
        }
        
        $current_url = $_SERVER['REQUEST_URI'];
        
        foreach ($excluded_pages as $excluded_page) {
            $excluded_page = trim($excluded_page);
            if (!empty($excluded_page) && strpos($current_url, $excluded_page) !== false) {
                return true;
            }
        }
        
        // Verificar user agents excluidos
        $excluded_user_agents = get_option('sbp_excluded_user_agents', array('bot', 'crawler', 'spider'));
        if (is_string($excluded_user_agents)) {
            $excluded_user_agents = explode("\n", $excluded_user_agents);
        }
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        foreach ($excluded_user_agents as $excluded_ua) {
            $excluded_ua = trim($excluded_ua);
            if (!empty($excluded_ua) && stripos($user_agent, $excluded_ua) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function regenerate_static_files($post_id = null) {
        if ($post_id) {
            $post_url = get_permalink($post_id);
            $this->clear_static_file_by_url($post_url);
        }
        
        // También limpiar página principal
        $this->clear_static_file_by_url(home_url());
        
        // Limpiar páginas relacionadas si es un post
        if ($post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_type === 'post') {
                // Limpiar archivo de categorías
                $categories = get_the_category($post_id);
                foreach ($categories as $category) {
                    $this->clear_static_file_by_url(get_category_link($category->term_id));
                }
                
                // Limpiar archivo de tags
                $tags = get_the_tags($post_id);
                if ($tags) {
                    foreach ($tags as $tag) {
                        $this->clear_static_file_by_url(get_tag_link($tag->term_id));
                    }
                }
            }
        }
    }
    
    public function regenerate_page_static($post_id = null) {
        if ($post_id) {
            $post_url = get_permalink($post_id);
            $this->clear_static_file_by_url($post_url);
        }
    }
    
    private function clear_static_file_by_url($url) {
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '/';
        $path = rtrim($path, '/');
        
        if (empty($path)) {
            $path = '/index';
        }
        
        $static_file = SBP_CACHE_DIR . ltrim($path, '/') . '/index.html';
        
        if (file_exists($static_file)) {
            unlink($static_file);
        }
        
        if (file_exists($static_file . '.gz')) {
            unlink($static_file . '.gz');
        }
    }
    
    public function add_cache_info() {
        if (!get_option('sbp_show_cache_info', true) || is_admin() || is_user_logged_in()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        $is_static = file_exists($static_file);
        
        echo "\n<!-- StaticBoost Pro: " . ($is_static ? 'STATIC' : 'GENERATED') . " -->";
        echo "\n<!-- Generated: " . date('Y-m-d H:i:s') . " -->";
        echo "\n<!-- BoostAI Optimization: " . (get_option('sbp_boostai_enabled', true) ? 'ENABLED' : 'DISABLED') . " -->\n";
    }
}