<?php
/**
 * Clase principal del plugin Fast Static Cache
 */
class Fast_Static_Cache {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_loaded', array($this, 'start_buffering'));
        add_action('shutdown', array($this, 'end_buffering'));
        
        // Regenerar cuando se actualiza contenido
        add_action('save_post', array($this, 'regenerate_static_files'));
        add_action('comment_post', array($this, 'regenerate_page_static'));
        add_action('wp_set_comment_status', array($this, 'regenerate_page_static'));
        
        // Servir estático antes de WordPress
        add_action('template_redirect', array($this, 'serve_static_if_exists'), 1);
        
        // Información de caché
        add_action('wp_footer', array($this, 'add_cache_info'), 999);
    }
    
    public function init() {
        load_plugin_textdomain('fast-static-cache', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Servir archivo estático si existe
     */
    public function serve_static_if_exists() {
        if (!get_option('fsc_enabled', true) || 
            is_user_logged_in() || 
            is_admin() || 
            !empty($_GET) || 
            $_SERVER['REQUEST_METHOD'] !== 'GET' ||
            $this->is_page_excluded()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        
        if ($this->is_static_file_valid($static_file)) {
            $this->serve_static_file($static_file);
            exit;
        }
    }
    
    private function serve_static_file($static_file) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Static-Cache: HIT');
        header('Cache-Control: public, max-age=3600');
        
        $compressed_file = $static_file . '.gz';
        
        if (file_exists($compressed_file) && $this->client_accepts_gzip()) {
            header('Content-Encoding: gzip');
            readfile($compressed_file);
        } else {
            readfile($static_file);
        }
        
        exit;
    }
    
    public function start_buffering() {
        if (!get_option('fsc_enabled', true) || 
            is_user_logged_in() || 
            is_admin() || 
            !empty($_GET) || 
            $_SERVER['REQUEST_METHOD'] !== 'GET' ||
            $this->is_page_excluded()) {
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
     * Generar archivo estático
     */
    public function generate_static_file($buffer) {
        if (!$this->should_generate_static()) {
            return $buffer;
        }
        
        $static_file = $this->get_static_file_path();
        $static_dir = dirname($static_file);
        
        if (!file_exists($static_dir)) {
            wp_mkdir_p($static_dir);
        }
        
        $optimized_html = $this->optimize_html_for_static($buffer);
        
        if (get_option('fsc_show_cache_info', true)) {
            $cache_info = sprintf(
                "\n<!-- Fast Static Cache: Generado el %s -->",
                date('Y-m-d H:i:s')
            );
            $optimized_html .= $cache_info;
        }
        
        file_put_contents($static_file, $optimized_html, LOCK_EX);
        
        if (function_exists('gzencode')) {
            file_put_contents($static_file . '.gz', gzencode($optimized_html, 9), LOCK_EX);
        }
        
        return $buffer;
    }
    
    private function optimize_html_for_static($html) {
        $site_url = get_site_url();
        
        // Convertir URLs relativas a absolutas
        $html = preg_replace('/href="\/([^"]*)"/', 'href="' . $site_url . '/$1"', $html);
        $html = preg_replace('/src="\/([^"]*)"/', 'src="' . $site_url . '/$1"', $html);
        
        return $html;
    }
    
    private function get_static_file_path() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_uri = rtrim($request_uri, '/');
        
        if (empty($request_uri)) {
            $request_uri = '/index';
        }
        
        return FSC_CACHE_DIR . ltrim($request_uri, '/') . '/index.html';
    }
    
    private function is_static_file_valid($static_file) {
        if (!file_exists($static_file)) {
            return false;
        }
        
        $cache_lifetime = get_option('fsc_cache_lifetime', 3600);
        $file_time = filemtime($static_file);
        
        return (time() - $file_time) < $cache_lifetime;
    }
    
    private function client_accepts_gzip() {
        return isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }
    
    private function should_generate_static() {
        return !is_404() && !is_feed() && !is_robots() && !is_trackback();
    }
    
    private function is_page_excluded() {
        $excluded_pages = get_option('fsc_excluded_pages', array());
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
        
        $excluded_user_agents = get_option('fsc_excluded_user_agents', array('bot', 'crawler', 'spider'));
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
        
        $this->clear_static_file_by_url(home_url());
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
        
        $static_file = FSC_CACHE_DIR . ltrim($path, '/') . '/index.html';
        
        if (file_exists($static_file)) {
            unlink($static_file);
        }
        
        if (file_exists($static_file . '.gz')) {
            unlink($static_file . '.gz');
        }
    }
    
    public function add_cache_info() {
        if (!get_option('fsc_show_cache_info', true) || is_admin() || is_user_logged_in()) {
            return;
        }
        
        $static_file = $this->get_static_file_path();
        $is_static = file_exists($static_file);
        
        echo "\n<!-- Fast Static Cache: " . ($is_static ? 'STATIC' : 'GENERATED') . " -->";
        echo "\n<!-- Generated: " . date('Y-m-d H:i:s') . " -->\n";
    }
}