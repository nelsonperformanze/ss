<?php
/**
 * Optimizador de Assets Estáticos
 */
class FSC_Asset_Optimizer {
    
    private $optimized_assets = array();
    
    public function __construct() {
        add_action('fsc_asset_optimization', array($this, 'optimize_all_assets'));
        add_filter('fsc_static_html', array($this, 'optimize_html_assets'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'optimize_frontend_assets'));
    }
    
    /**
     * Optimizar todos los assets (ejecutado por cron)
     */
    public function optimize_all_assets() {
        if (!get_option('fsc_asset_optimization', true)) {
            return;
        }
        
        // Optimizar CSS
        $this->optimize_css_files();
        
        // Optimizar JavaScript
        $this->optimize_js_files();
        
        // Optimizar imágenes
        if (get_option('fsc_image_optimization', true)) {
            $this->optimize_images();
        }
        
        // Generar CSS crítico
        if (get_option('fsc_critical_css', true)) {
            $this->generate_critical_css();
        }
    }
    
    /**
     * Optimizar archivos CSS
     */
    private function optimize_css_files() {
        $css_dir = FSC_CACHE_DIR . 'css/';
        
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        
        // Obtener todos los archivos CSS del tema
        $theme_css = $this->get_theme_css_files();
        
        // Combinar y minificar CSS
        $combined_css = '';
        foreach ($theme_css as $css_file) {
            $css_content = file_get_contents($css_file);
            $combined_css .= $this->minify_css($css_content);
        }
        
        // Guardar CSS combinado
        $combined_file = $css_dir . 'combined.min.css';
        file_put_contents($combined_file, $combined_css);
        
        // Crear versión comprimida
        if (function_exists('gzencode')) {
            file_put_contents($combined_file . '.gz', gzencode($combined_css, 9));
        }
        
        $this->optimized_assets['css'] = $combined_file;
    }
    
    /**
     * Optimizar archivos JavaScript
     */
    private function optimize_js_files() {
        $js_dir = FSC_CACHE_DIR . 'js/';
        
        if (!file_exists($js_dir)) {
            wp_mkdir_p($js_dir);
        }
        
        // Obtener archivos JS del tema
        $theme_js = $this->get_theme_js_files();
        
        // Combinar y minificar JS
        $combined_js = '';
        foreach ($theme_js as $js_file) {
            $js_content = file_get_contents($js_file);
            $combined_js .= $this->minify_js($js_content) . ";\n";
        }
        
        // Guardar JS combinado
        $combined_file = $js_dir . 'combined.min.js';
        file_put_contents($combined_file, $combined_js);
        
        // Crear versión comprimida
        if (function_exists('gzencode')) {
            file_put_contents($combined_file . '.gz', gzencode($combined_js, 9));
        }
        
        $this->optimized_assets['js'] = $combined_file;
    }
    
    /**
     * Optimizar imágenes
     */
    private function optimize_images() {
        $images_dir = FSC_CACHE_DIR . 'images/';
        
        if (!file_exists($images_dir)) {
            wp_mkdir_p($images_dir);
        }
        
        // Obtener imágenes del sitio
        $images = $this->get_site_images();
        
        foreach ($images as $image_path) {
            $this->optimize_single_image($image_path, $images_dir);
        }
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
        
        // Crear versiones optimizadas
        $base_name = $image_info['filename'];
        
        // WebP
        $webp_file = $output_dir . $base_name . '.webp';
        $this->convert_to_webp($image_path, $webp_file);
        
        // AVIF (si está disponible)
        if (function_exists('imageavif')) {
            $avif_file = $output_dir . $base_name . '.avif';
            $this->convert_to_avif($image_path, $avif_file);
        }
        
        // Versión comprimida original
        $compressed_file = $output_dir . $base_name . '_compressed.' . $extension;
        $this->compress_image($image_path, $compressed_file);
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
        
        return false;
    }
    
    /**
     * Convertir imagen a AVIF
     */
    private function convert_to_avif($source, $destination) {
        if (!function_exists('imageavif')) {
            return false;
        }
        
        $image_info = getimagesize($source);
        if (!$image_info) {
            return false;
        }
        
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                break;
            default:
                return false;
        }
        
        if ($image) {
            $result = imageavif($image, $destination, 85);
            imagedestroy($image);
            return $result;
        }
        
        return false;
    }
    
    /**
     * Comprimir imagen manteniendo formato original
     */
    private function compress_image($source, $destination) {
        $image_info = getimagesize($source);
        if (!$image_info) {
            return false;
        }
        
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                if ($image) {
                    $result = imagejpeg($image, $destination, 85);
                    imagedestroy($image);
                    return $result;
                }
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                if ($image) {
                    imagepng($image, $destination, 6);
                    imagedestroy($image);
                    return true;
                }
                break;
        }
        
        return false;
    }
    
    /**
     * Generar CSS crítico
     */
    private function generate_critical_css() {
        $critical_css_file = FSC_CACHE_DIR . 'css/critical.css';
        
        // CSS crítico básico (above-the-fold)
        $critical_css = '
        /* Critical CSS - Above the fold */
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .header, .navigation, .hero, .main-content { display: block; }
        img { max-width: 100%; height: auto; }
        .hidden { display: none !important; }
        .loading { opacity: 0.5; }
        ';
        
        // Minificar CSS crítico
        $critical_css = $this->minify_css($critical_css);
        
        file_put_contents($critical_css_file, $critical_css);
        
        // Crear versión comprimida
        if (function_exists('gzencode')) {
            file_put_contents($critical_css_file . '.gz', gzencode($critical_css, 9));
        }
    }
    
    /**
     * Optimizar HTML con assets optimizados
     */
    public function optimize_html_assets($html, $url) {
        if (!get_option('fsc_asset_optimization', true)) {
            return $html;
        }
        
        // Reemplazar CSS con versión optimizada
        if (isset($this->optimized_assets['css'])) {
            $css_url = str_replace(FSC_CACHE_DIR, content_url('cache/fast-static-cache/'), $this->optimized_assets['css']);
            $html = $this->replace_css_links($html, $css_url);
        }
        
        // Reemplazar JS con versión optimizada
        if (isset($this->optimized_assets['js'])) {
            $js_url = str_replace(FSC_CACHE_DIR, content_url('cache/fast-static-cache/'), $this->optimized_assets['js']);
            $html = $this->replace_js_scripts($html, $js_url);
        }
        
        // Optimizar imágenes en HTML
        $html = $this->optimize_images_in_html($html);
        
        // Añadir preload headers
        $html = $this->add_preload_headers($html);
        
        // Añadir lazy loading
        $html = $this->add_lazy_loading($html);
        
        return $html;
    }
    
    /**
     * Reemplazar enlaces CSS con versión optimizada
     */
    private function replace_css_links($html, $optimized_css_url) {
        // Remover enlaces CSS existentes del tema
        $html = preg_replace('/<link[^>]*rel=["\']stylesheet["\'][^>]*>/i', '', $html);
        
        // Añadir CSS optimizado en el head
        $css_link = '<link rel="stylesheet" href="' . $optimized_css_url . '" data-optimized="true">';
        $html = str_replace('</head>', $css_link . "\n</head>", $html);
        
        return $html;
    }
    
    /**
     * Reemplazar scripts JS con versión optimizada
     */
    private function replace_js_scripts($html, $optimized_js_url) {
        // Remover scripts JS del tema (mantener jQuery y scripts críticos)
        $html = preg_replace('/<script[^>]*src=["\'][^"\']*themes\/[^"\']*["\'][^>]*><\/script>/i', '', $html);
        
        // Añadir JS optimizado antes del cierre del body
        $js_script = '<script src="' . $optimized_js_url . '" data-optimized="true"></script>';
        $html = str_replace('</body>', $js_script . "\n</body>", $html);
        
        return $html;
    }
    
    /**
     * Optimizar imágenes en HTML
     */
    private function optimize_images_in_html($html) {
        // Convertir imágenes a lazy loading con srcset optimizado
        $html = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
            array($this, 'optimize_img_tag'),
            $html
        );
        
        return $html;
    }
    
    /**
     * Optimizar tag de imagen individual
     */
    private function optimize_img_tag($matches) {
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];
        
        // Crear srcset con versiones optimizadas
        $srcset = $this->generate_responsive_srcset($src);
        
        // Convertir a lazy loading
        $optimized_tag = '<img' . $before_src . 
                        'data-src="' . $src . '"' .
                        ($srcset ? ' data-srcset="' . $srcset . '"' : '') .
                        ' src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E"' .
                        $after_src . 
                        ' loading="lazy">';
        
        return $optimized_tag;
    }
    
    /**
     * Generar srcset responsivo
     */
    private function generate_responsive_srcset($original_src) {
        $srcset_parts = array();
        
        // Buscar versiones optimizadas
        $base_name = pathinfo($original_src, PATHINFO_FILENAME);
        $cache_url = content_url('cache/fast-static-cache/images/');
        
        // WebP
        $webp_url = $cache_url . $base_name . '.webp';
        if (file_exists(FSC_CACHE_DIR . 'images/' . $base_name . '.webp')) {
            $srcset_parts[] = $webp_url . ' 1x';
        }
        
        // AVIF
        $avif_url = $cache_url . $base_name . '.avif';
        if (file_exists(FSC_CACHE_DIR . 'images/' . $base_name . '.avif')) {
            $srcset_parts[] = $avif_url . ' 1x';
        }
        
        return implode(', ', $srcset_parts);
    }
    
    /**
     * Añadir headers de preload
     */
    private function add_preload_headers($html) {
        $preload_links = '';
        
        // Preload CSS crítico
        $critical_css_url = content_url('cache/fast-static-cache/css/critical.css');
        $preload_links .= '<link rel="preload" href="' . $critical_css_url . '" as="style">' . "\n";
        
        // Preload fuentes críticas
        $preload_links .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $preload_links .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // Insertar en el head
        $html = str_replace('</head>', $preload_links . '</head>', $html);
        
        return $html;
    }
    
    /**
     * Añadir lazy loading a elementos
     */
    private function add_lazy_loading($html) {
        // Lazy loading para iframes
        $html = preg_replace(
            '/<iframe([^>]*?)src=/i',
            '<iframe$1loading="lazy" data-src=',
            $html
        );
        
        return $html;
    }
    
    /**
     * Minificar CSS
     */
    private function minify_css($css) {
        // Remover comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remover espacios en blanco innecesarios
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
        $css = str_replace(array('; ', ' ;', ' {', '{ ', ' }', '} ', ': ', ' :', ', ', ' ,'), array(';', ';', '{', '{', '}', '}', ':', ':', ',', ','), $css);
        
        return trim($css);
    }
    
    /**
     * Minificar JavaScript
     */
    private function minify_js($js) {
        // Minificación básica de JS
        // Remover comentarios de línea
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remover comentarios de bloque
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remover espacios en blanco innecesarios
        $js = preg_replace('/\s+/', ' ', $js);
        
        return trim($js);
    }
    
    /**
     * Obtener archivos CSS del tema
     */
    private function get_theme_css_files() {
        $css_files = array();
        $theme_dir = get_template_directory();
        
        // Buscar archivos CSS en el tema
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme_dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'css') {
                $css_files[] = $file->getPathname();
            }
        }
        
        return $css_files;
    }
    
    /**
     * Obtener archivos JS del tema
     */
    private function get_theme_js_files() {
        $js_files = array();
        $theme_dir = get_template_directory();
        
        // Buscar archivos JS en el tema
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme_dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'js') {
                $js_files[] = $file->getPathname();
            }
        }
        
        return $js_files;
    }
    
    /**
     * Obtener imágenes del sitio
     */
    private function get_site_images() {
        $images = array();
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        if (is_dir($uploads_path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_path)
            );
            
            foreach ($iterator as $file) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif'))) {
                    $images[] = $file->getPathname();
                }
            }
        }
        
        return array_slice($images, 0, 100); // Limitar para evitar sobrecarga
    }
    
    /**
     * Optimizar assets del frontend
     */
    public function optimize_frontend_assets() {
        if (!get_option('fsc_asset_optimization', true) || is_admin()) {
            return;
        }
        
        // Diferir scripts no críticos
        add_filter('script_loader_tag', array($this, 'defer_non_critical_scripts'), 10, 2);
        
        // Optimizar carga de CSS
        add_filter('style_loader_tag', array($this, 'optimize_css_loading'), 10, 2);
    }
    
    /**
     * Diferir scripts no críticos
     */
    public function defer_non_critical_scripts($tag, $handle) {
        $critical_scripts = array('jquery', 'jquery-core', 'jquery-migrate');
        
        if (!in_array($handle, $critical_scripts)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimizar carga de CSS
     */
    public function optimize_css_loading($tag, $handle) {
        // Cargar CSS no crítico de forma asíncrona
        $non_critical_css = array('dashicons', 'admin-bar');
        
        if (in_array($handle, $non_critical_css)) {
            return str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
        }
        
        return $tag;
    }
}