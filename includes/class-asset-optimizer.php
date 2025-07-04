<?php
/**
 * Optimizador de Assets Estáticos - StaticBoost Pro
 */
class SBP_Asset_Optimizer {
    
    private $optimized_assets = array();
    private $critical_scripts = array();
    private $critical_styles = array();
    
    public function __construct() {
        add_action('sbp_asset_optimization', array($this, 'optimize_all_assets'));
        add_filter('sbp_static_html', array($this, 'optimize_html_assets'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'optimize_frontend_assets'));
        
        // Definir assets críticos que NO deben ser modificados
        $this->critical_scripts = array(
            'jquery',
            'jquery-core', 
            'jquery-migrate',
            'wp-embed',
            'admin-bar'
        );
        
        $this->critical_styles = array(
            'admin-bar',
            'dashicons',
            'wp-block-library'
        );
    }
    
    /**
     * Optimizar todos los assets (ejecutado por cron) - MODO CONSERVADOR
     */
    public function optimize_all_assets() {
        if (!get_option('sbp_asset_optimization', true)) {
            return;
        }
        
        // Solo optimizar si está explícitamente habilitado
        if (!get_option('sbp_aggressive_optimization', false)) {
            // Modo conservador - solo optimizaciones básicas
            $this->optimize_images_only();
            $this->generate_critical_css_basic();
            return;
        }
        
        // Modo agresivo (solo si está habilitado)
        $this->optimize_css_files();
        $this->optimize_js_files();
        $this->optimize_images();
        $this->generate_critical_css();
    }
    
    /**
     * Optimización conservadora - solo imágenes
     */
    private function optimize_images_only() {
        if (!get_option('sbp_image_optimization', true)) {
            return;
        }
        
        $images_dir = SBP_CACHE_DIR . 'images/';
        
        if (!file_exists($images_dir)) {
            wp_mkdir_p($images_dir);
        }
        
        // Solo optimizar imágenes de uploads (no del tema)
        $upload_dir = wp_upload_dir();
        $images = $this->get_upload_images($upload_dir['basedir']);
        
        foreach (array_slice($images, 0, 50) as $image_path) { // Limitar a 50
            $this->optimize_single_image($image_path, $images_dir);
        }
    }
    
    /**
     * Obtener solo imágenes de uploads (no del tema)
     */
    private function get_upload_images($uploads_path) {
        $images = array();
        
        if (!is_dir($uploads_path)) {
            return $images;
        }
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
                    $images[] = $file->getPathname();
                }
            }
        } catch (Exception $e) {
            error_log('SBP Error scanning images: ' . $e->getMessage());
        }
        
        return $images;
    }
    
    /**
     * CSS crítico básico (sin modificar archivos existentes)
     */
    private function generate_critical_css_basic() {
        $critical_css_file = SBP_CACHE_DIR . 'css/critical.css';
        
        if (!file_exists(dirname($critical_css_file))) {
            wp_mkdir_p(dirname($critical_css_file));
        }
        
        // CSS crítico muy básico y seguro
        $critical_css = '
        /* Critical CSS - Safe optimizations only */
        img { max-width: 100%; height: auto; }
        .sbp-loaded { opacity: 1 !important; transition: opacity 0.3s ease-in-out; }
        img[data-src] { opacity: 0; transition: opacity 0.3s ease-in-out; }
        img[data-src].sbp-loaded { opacity: 1; }
        ';
        
        $critical_css = $this->minify_css($critical_css);
        file_put_contents($critical_css_file, $critical_css);
    }
    
    /**
     * Optimizar HTML con assets - MODO CONSERVADOR
     */
    public function optimize_html_assets($html, $url) {
        if (!get_option('sbp_asset_optimization', true)) {
            return $html;
        }
        
        // Solo aplicar optimizaciones seguras
        $html = $this->add_safe_preload_headers($html);
        $html = $this->add_safe_lazy_loading($html);
        $html = $this->optimize_images_in_html_safe($html);
        
        // Solo si está en modo agresivo
        if (get_option('sbp_aggressive_optimization', false)) {
            if (isset($this->optimized_assets['css'])) {
                $css_url = str_replace(SBP_CACHE_DIR, content_url('cache/staticboost-pro/'), $this->optimized_assets['css']);
                $html = $this->replace_css_links_safe($html, $css_url);
            }
            
            if (isset($this->optimized_assets['js'])) {
                $js_url = str_replace(SBP_CACHE_DIR, content_url('cache/staticboost-pro/'), $this->optimized_assets['js']);
                $html = $this->replace_js_scripts_safe($html, $js_url);
            }
        }
        
        return $html;
    }
    
    /**
     * Añadir headers de preload seguros
     */
    private function add_safe_preload_headers($html) {
        $preload_links = '';
        
        // Solo preload de fuentes críticas
        $preload_links .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $preload_links .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // Insertar en el head
        $html = str_replace('</head>', $preload_links . '</head>', $html);
        
        return $html;
    }
    
    /**
     * Lazy loading seguro - solo para imágenes de contenido
     */
    private function add_safe_lazy_loading($html) {
        // Solo aplicar lazy loading a imágenes que NO sean críticas
        $html = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
            array($this, 'optimize_img_tag_safe'),
            $html
        );
        
        return $html;
    }
    
    /**
     * Optimizar tag de imagen de forma segura
     */
    private function optimize_img_tag_safe($matches) {
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];
        
        // NO aplicar lazy loading a:
        // - Logos (contienen 'logo' en la URL o alt)
        // - Iconos (contienen 'icon' en la URL o alt)
        // - Imágenes pequeñas (probablemente iconos)
        // - Imágenes en el header
        $full_tag = $matches[0];
        
        if (stripos($full_tag, 'logo') !== false ||
            stripos($full_tag, 'icon') !== false ||
            stripos($src, 'logo') !== false ||
            stripos($src, 'icon') !== false ||
            stripos($src, 'header') !== false ||
            preg_match('/width=["\']?(\d+)["\']?/i', $full_tag, $width_match) && isset($width_match[1]) && $width_match[1] < 100) {
            
            // Devolver imagen original sin modificar
            return $matches[0];
        }
        
        // Solo aplicar lazy loading a imágenes de contenido
        $optimized_tag = '<img' . $before_src . 
                        'data-src="' . $src . '"' .
                        ' src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E"' .
                        $after_src . 
                        ' loading="lazy">';
        
        return $optimized_tag;
    }
    
    /**
     * Optimizar imágenes en HTML de forma segura
     */
    private function optimize_images_in_html_safe($html) {
        // Solo añadir srcset a imágenes que ya están optimizadas
        return $html;
    }
    
    /**
     * Reemplazar CSS de forma segura (solo en modo agresivo)
     */
    private function replace_css_links_safe($html, $optimized_css_url) {
        // Solo remover CSS no críticos
        $non_critical_css = array(
            'wp-block-library-theme',
            'classic-theme-styles'
        );
        
        foreach ($non_critical_css as $handle) {
            $html = preg_replace('/<link[^>]*id=["\']' . $handle . '-css["\'][^>]*>/i', '', $html);
        }
        
        // Añadir CSS optimizado
        $css_link = '<link rel="stylesheet" href="' . $optimized_css_url . '" data-optimized="true">';
        $html = str_replace('</head>', $css_link . "\n</head>", $html);
        
        return $html;
    }
    
    /**
     * Reemplazar JS de forma segura (solo en modo agresivo)
     */
    private function replace_js_scripts_safe($html, $optimized_js_url) {
        // Solo remover scripts no críticos
        $non_critical_js = array(
            'wp-embed'
        );
        
        foreach ($non_critical_js as $handle) {
            $html = preg_replace('/<script[^>]*id=["\']' . $handle . '-js["\'][^>]*><\/script>/i', '', $html);
        }
        
        // Añadir JS optimizado
        $js_script = '<script src="' . $optimized_js_url . '" data-optimized="true"></script>';
        $html = str_replace('</body>', $js_script . "\n</body>", $html);
        
        return $html;
    }
    
    /**
     * Optimizar una imagen individual
     */
    private function optimize_single_image($image_path, $output_dir) {
        $image_info = pathinfo($image_path);
        $extension = strtolower($image_info['extension']);
        
        if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
            return;
        }
        
        $base_name = $image_info['filename'];
        
        // Solo crear WebP (más compatible que AVIF)
        $webp_file = $output_dir . $base_name . '.webp';
        if (!file_exists($webp_file)) {
            $this->convert_to_webp($image_path, $webp_file);
        }
    }
    
    /**
     * Convertir imagen a WebP
     */
    private function convert_to_webp($source, $destination) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $image_info = getimagesize($source);
        if (!$image_info) {
            return false;
        }
        
        try {
            switch ($image_info['mime']) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($source);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($source);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($source);
                    break;
                default:
                    return false;
            }
            
            if ($image) {
                $result = imagewebp($image, $destination, 85);
                imagedestroy($image);
                return $result;
            }
        } catch (Exception $e) {
            error_log('SBP Error converting to WebP: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Optimizar CSS files (solo en modo agresivo)
     */
    private function optimize_css_files() {
        // Solo ejecutar si está en modo agresivo
        $css_dir = SBP_CACHE_DIR . 'css/';
        
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        // Obtener solo CSS no críticos del tema
        $theme_css = $this->get_non_critical_theme_css();
        
        if (empty($theme_css)) {
            return;
        }
        
        $combined_css = '';
        foreach ($theme_css as $css_file) {
            if (file_exists($css_file)) {
                $css_content = file_get_contents($css_file);
                $combined_css .= $this->minify_css($css_content);
            }
        }
        
        if (!empty($combined_css)) {
            $combined_file = $css_dir . 'combined.min.css';
            file_put_contents($combined_file, $combined_css);
            $this->optimized_assets['css'] = $combined_file;
        }
    }
    
    /**
     * Obtener solo CSS no críticos del tema
     */
    private function get_non_critical_theme_css() {
        $css_files = array();
        $theme_dir = get_template_directory();
        
        // Solo buscar archivos CSS específicos y seguros
        $safe_css_files = array(
            $theme_dir . '/style.css',
            $theme_dir . '/assets/css/main.css',
            $theme_dir . '/css/style.css'
        );
        
        foreach ($safe_css_files as $file) {
            if (file_exists($file)) {
                $css_files[] = $file;
            }
        }
        
        return $css_files;
    }
    
    /**
     * Optimizar JS files (solo en modo agresivo)
     */
    private function optimize_js_files() {
        $js_dir = SBP_CACHE_DIR . 'js/';
        
        if (!file_exists($js_dir)) {
            wp_mkdir_p($js_dir);
        }
        
        // Obtener solo JS no críticos del tema
        $theme_js = $this->get_non_critical_theme_js();
        
        if (empty($theme_js)) {
            return;
        }
        
        $combined_js = '';
        foreach ($theme_js as $js_file) {
            if (file_exists($js_file)) {
                $js_content = file_get_contents($js_file);
                $combined_js .= $this->minify_js($js_content) . ";\n";
            }
        }
        
        if (!empty($combined_js)) {
            $combined_file = $js_dir . 'combined.min.js';
            file_put_contents($combined_file, $combined_js);
            $this->optimized_assets['js'] = $combined_file;
        }
    }
    
    /**
     * Obtener solo JS no críticos del tema
     */
    private function get_non_critical_theme_js() {
        $js_files = array();
        $theme_dir = get_template_directory();
        
        // Solo buscar archivos JS específicos y seguros
        $safe_js_files = array(
            $theme_dir . '/assets/js/main.js',
            $theme_dir . '/js/main.js',
            $theme_dir . '/assets/js/theme.js'
        );
        
        foreach ($safe_js_files as $file) {
            if (file_exists($file)) {
                $js_files[] = $file;
            }
        }
        
        return $js_files;
    }
    
    /**
     * Minificar CSS
     */
    private function minify_css($css) {
        // Minificación muy conservadora
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $css = str_replace(array("\r\n", "\r", "\n", "\t"), '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        return trim($css);
    }
    
    /**
     * Minificar JavaScript
     */
    private function minify_js($js) {
        // Minificación muy básica y segura
        $js = preg_replace('/\/\/.*$/m', '', $js);
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        return trim($js);
    }
    
    /**
     * Optimizar assets del frontend
     */
    public function optimize_frontend_assets() {
        if (!get_option('sbp_asset_optimization', true) || is_admin()) {
            return;
        }
        
        // Solo optimizaciones muy seguras
        add_filter('script_loader_tag', array($this, 'defer_safe_scripts'), 10, 2);
    }
    
    /**
     * Diferir solo scripts seguros
     */
    public function defer_safe_scripts($tag, $handle) {
        $safe_to_defer = array(
            'wp-embed',
            'comment-reply'
        );
        
        if (in_array($handle, $safe_to_defer)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
}