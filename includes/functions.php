<?php
/**
 * Funciones auxiliares para Fast Static Cache
 */

/**
 * Limpiar todos los archivos estáticos
 */
function fsc_clear_all_cache() {
    if (!is_dir(FSC_CACHE_DIR)) {
        return true;
    }
    
    return fsc_delete_directory_contents(FSC_CACHE_DIR);
}

/**
 * Eliminar contenido de directorio recursivamente (mantener directorio raíz)
 */
function fsc_delete_directory_contents($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            fsc_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return true;
}

/**
 * Eliminar directorio recursivamente
 */
function fsc_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            fsc_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * GENERAR ARCHIVOS ESTÁTICOS PARA TODAS LAS PÁGINAS DEL SITIO
 */
function fsc_generate_all_static_pages() {
    $results = array(
        'total' => 0,
        'success' => 0,
        'errors' => 0,
        'urls' => array()
    );
    
    $urls = array();
    
    // 1. PÁGINA PRINCIPAL
    $urls[] = home_url();
    
    // 2. TODAS LAS PÁGINAS (incluyendo las 3k que tienes)
    $pages = get_pages(array(
        'post_status' => 'publish',
        'number' => 0, // Sin límite - TODAS las páginas
        'hierarchical' => false
    ));
    
    foreach ($pages as $page) {
        $urls[] = get_permalink($page->ID);
    }
    
    // 3. TODOS LOS POSTS
    $posts = get_posts(array(
        'post_status' => 'publish',
        'numberposts' => -1, // Sin límite - TODOS los posts
        'post_type' => 'post'
    ));
    
    foreach ($posts as $post) {
        $urls[] = get_permalink($post->ID);
    }
    
    // 4. CUSTOM POST TYPES
    $post_types = get_post_types(array(
        'public' => true,
        '_builtin' => false
    ));
    
    foreach ($post_types as $post_type) {
        $custom_posts = get_posts(array(
            'post_status' => 'publish',
            'numberposts' => -1,
            'post_type' => $post_type
        ));
        
        foreach ($custom_posts as $custom_post) {
            $urls[] = get_permalink($custom_post->ID);
        }
    }
    
    // 5. CATEGORÍAS
    $categories = get_categories(array(
        'hide_empty' => false
    ));
    
    foreach ($categories as $category) {
        $urls[] = get_category_link($category->term_id);
    }
    
    // 6. TAGS
    $tags = get_tags(array(
        'hide_empty' => false
    ));
    
    foreach ($tags as $tag) {
        $urls[] = get_tag_link($tag->term_id);
    }
    
    // 7. ARCHIVOS DE FECHA
    $years = $wpdb->get_results("
        SELECT DISTINCT YEAR(post_date) as year 
        FROM {$wpdb->posts} 
        WHERE post_status = 'publish' 
        AND post_type = 'post'
        ORDER BY year DESC
    ");
    
    foreach ($years as $year) {
        $urls[] = get_year_link($year->year);
        
        // Meses del año
        $months = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT MONTH(post_date) as month 
            FROM {$wpdb->posts} 
            WHERE post_status = 'publish' 
            AND post_type = 'post'
            AND YEAR(post_date) = %d
            ORDER BY month
        ", $year->year));
        
        foreach ($months as $month) {
            $urls[] = get_month_link($year->year, $month->month);
        }
    }
    
    // Eliminar duplicados y URLs vacías
    $urls = array_unique(array_filter($urls));
    
    $results['total'] = count($urls);
    
    // GENERAR archivo estático para cada URL
    foreach ($urls as $url) {
        $success = fsc_generate_static_file_for_url($url);
        
        if ($success) {
            $results['success']++;
        } else {
            $results['errors']++;
        }
        
        $results['urls'][] = array(
            'url' => $url,
            'success' => $success
        );
        
        // Pequeña pausa para no sobrecargar el servidor
        usleep(100000); // 0.1 segundos
    }
    
    return $results;
}

/**
 * GENERAR archivo estático para una URL específica
 */
function fsc_generate_static_file_for_url($url) {
    $response = wp_remote_get($url, array(
        'timeout' => 60, // Aumentar timeout para páginas complejas
        'user-agent' => 'FastStaticCache/1.0 (Generator)',
        'cookies' => array(), // Sin cookies para generar versión estática
        'headers' => array(
            'Cache-Control' => 'no-cache'
        )
    ));
    
    if (is_wp_error($response)) {
        error_log('FSC Error generando ' . $url . ': ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        error_log('FSC Error HTTP ' . $response_code . ' para ' . $url);
        return false;
    }
    
    return true;
}

/**
 * PRECARGAR caché de páginas principales (versión rápida)
 */
function fsc_preload_cache() {
    // Obtener páginas principales (limitado para precarga rápida)
    $pages = get_pages(array(
        'post_status' => 'publish',
        'number' => 100 // Limitar a 100 para precarga rápida
    ));
    
    $posts = get_posts(array(
        'post_status' => 'publish',
        'numberposts' => 100 // Limitar a 100 para precarga rápida
    ));
    
    $urls = array();
    
    // Agregar página principal
    $urls[] = home_url();
    
    // Agregar páginas
    foreach ($pages as $page) {
        $urls[] = get_permalink($page->ID);
    }
    
    // Agregar posts
    foreach ($posts as $post) {
        $urls[] = get_permalink($post->ID);
    }
    
    // GENERAR archivo estático para cada URL
    foreach ($urls as $url) {
        fsc_generate_static_file_for_url($url);
    }
    
    return count($urls);
}

/**
 * Obtener estadísticas de archivos estáticos
 */
function fsc_get_cache_stats() {
    $stats = array(
        'files' => 0,
        'size' => 0,
        'last_generated' => null
    );
    
    if (!is_dir(FSC_CACHE_DIR)) {
        return $stats;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(FSC_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $latest_time = 0;
    
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'index.html') {
            $stats['files']++;
            $stats['size'] += $file->getSize();
            
            $mtime = $file->getMTime();
            if ($mtime > $latest_time) {
                $latest_time = $mtime;
            }
        }
    }
    
    if ($latest_time > 0) {
        $stats['last_generated'] = date('Y-m-d H:i:s', $latest_time);
    }
    
    return $stats;
}

/**
 * Verificar si una página debe generar archivo estático
 */
function fsc_should_generate_static_current_page() {
    // No generar si está deshabilitado
    if (!get_option('fsc_enabled', true)) {
        return false;
    }
    
    // No generar para usuarios logueados
    if (is_user_logged_in()) {
        return false;
    }
    
    // No generar páginas de administración
    if (is_admin()) {
        return false;
    }
    
    // No generar si hay parámetros GET
    if (!empty($_GET)) {
        return false;
    }
    
    // No generar páginas especiales
    if (is_404() || is_feed() || is_robots() || is_trackback()) {
        return false;
    }
    
    return true;
}

/**
 * Limpiar archivo estático de una página específica
 */
function fsc_clear_static_file_by_url($url) {
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '/';
    $path = rtrim($path, '/');
    
    if (empty($path)) {
        $path = '/index';
    }
    
    $static_file = FSC_CACHE_DIR . ltrim($path, '/') . '/index.html';
    
    if (file_exists($static_file)) {
        unlink($static_file);
        
        // También eliminar versión comprimida
        if (file_exists($static_file . '.gz')) {
            unlink($static_file . '.gz');
        }
        
        return true;
    }
    
    return false;
}

/**
 * Obtener la URL del archivo estático para la página actual
 */
function fsc_get_current_static_file_url() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $request_uri = rtrim($request_uri, '/');
    
    if (empty($request_uri)) {
        $request_uri = '/index';
    }
    
    return FSC_CACHE_DIR . ltrim($request_uri, '/') . '/index.html';
}

/**
 * REGENERAR todos los archivos estáticos
 */
function fsc_regenerate_all_static_files() {
    // Primero limpiar todos los archivos existentes
    fsc_clear_all_cache();
    
    // Luego regenerar TODAS las páginas
    return fsc_generate_all_static_pages();
}

/**
 * Verificar si un archivo estático existe y es válido
 */
function fsc_static_file_exists_and_valid($url) {
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'] ?? '/';
    $path = rtrim($path, '/');
    
    if (empty($path)) {
        $path = '/index';
    }
    
    $static_file = FSC_CACHE_DIR . ltrim($path, '/') . '/index.html';
    
    if (!file_exists($static_file)) {
        return false;
    }
    
    $cache_lifetime = get_option('fsc_cache_lifetime', 3600);
    $file_time = filemtime($static_file);
    
    return (time() - $file_time) < $cache_lifetime;
}

/**
 * Obtener conteo total de páginas en el sitio
 */
function fsc_get_total_pages_count() {
    global $wpdb;
    
    // Contar páginas
    $pages_count = wp_count_posts('page')->publish;
    
    // Contar posts
    $posts_count = wp_count_posts('post')->publish;
    
    // Contar custom post types
    $custom_post_types = get_post_types(array(
        'public' => true,
        '_builtin' => false
    ));
    
    $custom_posts_count = 0;
    foreach ($custom_post_types as $post_type) {
        $custom_posts_count += wp_count_posts($post_type)->publish;
    }
    
    // Contar categorías y tags
    $categories_count = wp_count_terms('category');
    $tags_count = wp_count_terms('post_tag');
    
    return array(
        'pages' => $pages_count,
        'posts' => $posts_count,
        'custom_posts' => $custom_posts_count,
        'categories' => $categories_count,
        'tags' => $tags_count,
        'total' => $pages_count + $posts_count + $custom_posts_count + $categories_count + $tags_count + 1 // +1 para home
    );
}