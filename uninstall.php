<?php
/**
 * Archivo de desinstalación para Fast Static Cache
 */

// Si no se está desinstalando, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Definir directorio de caché
define('FSC_CACHE_DIR', WP_CONTENT_DIR . '/cache/fast-static-cache/');

// Eliminar todas las opciones del plugin
delete_option('fsc_enabled');
delete_option('fsc_cache_lifetime');
delete_option('fsc_excluded_pages');
delete_option('fsc_excluded_user_agents');

// Eliminar directorio de caché completo
function fsc_uninstall_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            fsc_uninstall_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Eliminar directorio de caché
if (is_dir(FSC_CACHE_DIR)) {
    fsc_uninstall_delete_directory(FSC_CACHE_DIR);
}

// Eliminar directorio padre si está vacío
$parent_cache_dir = WP_CONTENT_DIR . '/cache/';
if (is_dir($parent_cache_dir) && count(scandir($parent_cache_dir)) == 2) {
    rmdir($parent_cache_dir);
}

// Limpiar cualquier transiente relacionado
delete_transient('fsc_stats');
delete_transient('fsc_preload_progress');