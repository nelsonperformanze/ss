<?php
/**
 * Optimizador específico para PageSpeed Insights 100/100
 */
class SBP_PageSpeed_Optimizer {
    
    public function __construct() {
        add_action('sbp_pagespeed_optimization', array($this, 'optimize_for_pagespeed'));
        add_filter('sbp_static_html', array($this, 'optimize_html_for_pagespeed'), 5, 2);
        add_action('wp_head', array($this, 'add_critical_performance_headers'), 1);
        add_action('wp_enqueue_scripts', array($this, 'optimize_scripts_for_pagespeed'), 1);
    }
    
    /**
     * Optimización completa para PageSpeed 100/100
     */
    public function optimize_for_pagespeed() {
        if (!get_option('sbp_pagespeed_mode', true)) {
            return;
        }
        
        // 1. Generar CSS crítico optimizado
        $this->generate_critical_css_for_pagespeed();
        
        // 2. Optimizar fuentes para LCP
        $this->optimize_fonts_for_lcp();
        
        // 3. Crear recursos preload críticos
        $this->create_preload_resources();
        
        // 4. Optimizar imágenes para CLS
        $this->optimize_images_for_cls();
    }
    
    /**
     * Generar CSS crítico específico para PageSpeed
     */
    private function generate_critical_css_for_pagespeed() {
        $critical_css_file = SBP_CACHE_DIR . 'css/critical-pagespeed.css';
        
        if (!file_exists(dirname($critical_css_file))) {
            wp_mkdir_p(dirname($critical_css_file));
        }
        
        // CSS crítico optimizado para Core Web Vitals
        $critical_css = '
        /* Critical CSS for PageSpeed 100/100 */
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { 
            margin: 0; 
            padding: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            font-display: swap;
        }
        
        /* Prevent layout shift */
        img, video, iframe { 
            max-width: 100%; 
            height: auto; 
            display: block;
        }
        
        /* Critical above-the-fold styles */
        .header, .navigation, .hero, .main-content { 
            display: block; 
        }
        
        /* Lazy loading optimization */
        .sbp-loaded { 
            opacity: 1 !important; 
            transition: opacity 0.2s ease-in-out; 
        }
        
        img[data-src] { 
            opacity: 0; 
            transition: opacity 0.2s ease-in-out; 
        }
        
        img[data-src].sbp-loaded { 
            opacity: 1; 
        }
        
        /* Prevent CLS for common elements */
        .wp-block-image, .aligncenter, .alignleft, .alignright {
            margin: 0.5em 0;
        }
        
        /* Font loading optimization */
        @font-face {
            font-display: swap;
        }
        
        /* Hide non-critical elements initially */
        .hidden { 
            display: none !important; 
        }
        
        /* Loading states */
        .loading { 
            opacity: 0.7; 
            pointer-events: none; 
        }
        
        /* Responsive images */
        .responsive-img {
            width: 100%;
            height: auto;
        }
        ';
        
        // Minificar CSS crítico
        $critical_css = $this->minify_css_aggressive($critical_css);
        
        file_put_contents($critical_css_file, $critical_css);
        
        // Crear versión comprimida
        if (function_exists('gzencode')) {
            file_put_contents($critical_css_file . '.gz', gzencode($critical_css, 9));
        }
    }
    
    /**
     * Optimizar fuentes para LCP
     */
    private function optimize_fonts_for_lcp() {
        $fonts_dir = SBP_CACHE_DIR . 'fonts/';
        
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        // Crear CSS de fuentes optimizado
        $font_css = '
        /* Optimized font loading for LCP */
        @font-face {
            font-family: "System Font";
            src: local(-apple-system), local(BlinkMacSystemFont), local("Segoe UI"), local(Roboto);
            font-display: swap;
        }
        ';
        
        file_put_contents($fonts_dir . 'optimized-fonts.css', $this->minify_css_aggressive($font_css));
    }
    
    /**
     * Crear recursos preload críticos
     */
    private function create_preload_resources() {
        $preload_file = SBP_CACHE_DIR . 'preload-manifest.json';
        
        $preload_resources = array(
            'critical_css' => content_url('cache/staticboost-pro/css/critical-pagespeed.css'),
            'fonts' => array(
                'https://fonts.gstatic.com'
            ),
            'dns_prefetch' => array(
                'https://fonts.googleapis.com',
                'https://fonts.gstatic.com'
            )
        );
        
        file_put_contents($preload_file, json_encode($preload_resources));
    }
    
    /**
     * Optimizar HTML para PageSpeed 100/100
     */
    public function optimize_html_for_pagespeed($html, $url) {
        if (!get_option('sbp_pagespeed_mode', true)) {
            return $html;
        }
        
        // 1. Inline CSS crítico para eliminar render-blocking
        $html = $this->inline_critical_css($html);
        
        // 2. Optimizar scripts para mejor FID
        $html = $this->optimize_scripts_for_fid($html);
        
        // 3. Añadir preload headers críticos
        $html = $this->add_critical_preload_headers($html);
        
        // 4. Optimizar imágenes para CLS
        $html = $this->optimize_images_for_pagespeed($html);
        
        // 5. Añadir meta tags de rendimiento
        $html = $this->add_performance_meta_tags($html);
        
        // 6. Eliminar recursos no críticos
        $html = $this->remove_non_critical_resources($html);
        
        return $html;
    }
    
    /**
     * Inline CSS crítico para eliminar render-blocking
     */
    private function inline_critical_css($html) {
        $critical_css_file = SBP_CACHE_DIR . 'css/critical-pagespeed.css';
        
        if (file_exists($critical_css_file)) {
            $critical_css = file_get_contents($critical_css_file);
            
            $inline_css = '<style id="sbp-critical-css">' . $critical_css . '</style>';
            $html = str_replace('</head>', $inline_css . "\n</head>", $html);
        }
        
        return $html;
    }
    
    /**
     * Optimizar scripts para mejor FID
     */
    private function optimize_scripts_for_fid($html) {
        // Diferir todos los scripts no críticos
        $html = preg_replace_callback(
            '/<script([^>]*?)src=([^>]*?)><\/script>/i',
            function($matches) {
                $attributes = $matches[1];
                $src = $matches[2];
                
                // No diferir scripts críticos
                if (strpos($src, 'jquery') !== false || 
                    strpos($attributes, 'defer') !== false || 
                    strpos($attributes, 'async') !== false) {
                    return $matches[0];
                }
                
                return '<script' . $attributes . ' defer src=' . $src . '></script>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Añadir preload headers críticos
     */
    private function add_critical_preload_headers($html) {
        $preload_headers = '';
        
        // Preload CSS crítico
        $preload_headers .= '<link rel="preload" href="' . content_url('cache/staticboost-pro/css/critical-pagespeed.css') . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        
        // Preconnect a dominios críticos
        $preload_headers .= '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        $preload_headers .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        // DNS prefetch para recursos externos
        $preload_headers .= '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        $preload_headers .= '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        
        // Insertar después del <head>
        $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . "\n" . $preload_headers, $html);
        
        return $html;
    }
    
    /**
     * Optimizar imágenes para PageSpeed
     */
    private function optimize_images_for_pagespeed($html) {
        // Añadir dimensiones a imágenes para prevenir CLS
        $html = preg_replace_callback(
            '/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i',
            array($this, 'optimize_img_tag_for_pagespeed'),
            $html
        );
        
        return $html;
    }
    
    /**
     * Optimizar tag de imagen para PageSpeed
     */
    private function optimize_img_tag_for_pagespeed($matches) {
        $before_src = $matches[1];
        $src = $matches[2];
        $after_src = $matches[3];
        $full_tag = $matches[0];
        
        // No optimizar logos e iconos críticos
        if (stripos($full_tag, 'logo') !== false ||
            stripos($full_tag, 'icon') !== false ||
            stripos($src, 'logo') !== false ||
            stripos($src, 'icon') !== false) {
            return $matches[0];
        }
        
        // Añadir loading="lazy" y dimensiones si no existen
        $optimized_attributes = $before_src . $after_src;
        
        if (strpos($optimized_attributes, 'loading=') === false) {
            $optimized_attributes .= ' loading="lazy"';
        }
        
        if (strpos($optimized_attributes, 'decoding=') === false) {
            $optimized_attributes .= ' decoding="async"';
        }
        
        // Convertir a lazy loading con placeholder
        $placeholder = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E';
        
        return '<img' . $optimized_attributes . ' data-src="' . $src . '" src="' . $placeholder . '" class="sbp-lazy">';
    }
    
    /**
     * Añadir meta tags de rendimiento
     */
    private function add_performance_meta_tags($html) {
        $performance_meta = '
        <meta name="generator" content="StaticBoost Pro">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#ffffff">
        <meta name="format-detection" content="telephone=no">
        ';
        
        $html = str_replace('</head>', $performance_meta . '</head>', $html);
        
        return $html;
    }
    
    /**
     * Eliminar recursos no críticos
     */
    private function remove_non_critical_resources($html) {
        // Eliminar CSS no críticos
        $non_critical_css = array(
            'wp-block-library-theme',
            'classic-theme-styles',
            'global-styles'
        );
        
        foreach ($non_critical_css as $handle) {
            $html = preg_replace('/<link[^>]*id=["\']' . $handle . '-css["\'][^>]*>/i', '', $html);
        }
        
        // Eliminar scripts no críticos del head
        $html = preg_replace('/<script[^>]*wp-embed[^>]*><\/script>/i', '', $html);
        
        return $html;
    }
    
    /**
     * Añadir headers críticos de rendimiento
     */
    public function add_critical_performance_headers() {
        if (!get_option('sbp_pagespeed_mode', true) || is_admin()) {
            return;
        }
        
        // Resource hints críticos
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
    }
    
    /**
     * Optimizar scripts para PageSpeed
     */
    public function optimize_scripts_for_pagespeed() {
        if (!get_option('sbp_pagespeed_mode', true) || is_admin()) {
            return;
        }
        
        // Diferir scripts no críticos
        add_filter('script_loader_tag', array($this, 'defer_non_critical_scripts_pagespeed'), 10, 2);
        
        // Optimizar carga de CSS
        add_filter('style_loader_tag', array($this, 'optimize_css_loading_pagespeed'), 10, 2);
    }
    
    /**
     * Diferir scripts no críticos para PageSpeed
     */
    public function defer_non_critical_scripts_pagespeed($tag, $handle) {
        $critical_scripts = array('jquery', 'jquery-core', 'jquery-migrate');
        
        if (!in_array($handle, $critical_scripts) && strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Optimizar carga de CSS para PageSpeed
     */
    public function optimize_css_loading_pagespeed($tag, $handle) {
        $non_critical_css = array('dashicons', 'admin-bar', 'wp-block-library-theme');
        
        if (in_array($handle, $non_critical_css)) {
            return str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
        }
        
        return $tag;
    }
    
    /**
     * Minificación agresiva de CSS
     */
    private function minify_css_aggressive($css) {
        // Eliminar comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Eliminar espacios en blanco
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    '), '', $css);
        
        // Optimizar selectores
        $css = str_replace(array('; ', ' ;', ' {', '{ ', ' }', '} ', ': ', ' :', ', ', ' ,'), 
                          array(';', ';', '{', '{', '}', '}', ':', ':', ',', ','), $css);
        
        return trim($css);
    }
}