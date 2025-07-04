<?php
/**
 * Plugin Name: Fast Static Cache Pro
 * Plugin URI: https://github.com/yourname/fast-static-cache
 * Description: Sistema avanzado de caché estático con optimización inteligente usando TensorFlow.js y análisis adaptativo.
 * Version: 2.0.0
 * Author: Tu Nombre
 * Author URI: https://tusitio.com
 * License: GPL v2 or later
 * Text Domain: fast-static-cache
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('FSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FSC_CACHE_DIR', WP_CONTENT_DIR . '/cache/fast-static-cache/');
define('FSC_ASSETS_DIR', FSC_CACHE_DIR . 'assets/');
define('FSC_ML_DIR', FSC_PLUGIN_PATH . 'ml/');
define('FSC_VERSION', '2.0.0');

// Incluir archivos necesarios
require_once FSC_PLUGIN_PATH . 'includes/class-fast-static-cache.php';
require_once FSC_PLUGIN_PATH . 'includes/class-admin.php';
require_once FSC_PLUGIN_PATH . 'includes/class-woocommerce-compat.php';
require_once FSC_PLUGIN_PATH . 'includes/class-ml-optimizer.php';
require_once FSC_PLUGIN_PATH . 'includes/class-asset-optimizer.php';
require_once FSC_PLUGIN_PATH . 'includes/functions.php';

// Inicializar el plugin
function fsc_init() {
    new Fast_Static_Cache();
    
    if (is_admin()) {
        new FSC_Admin();
    }
    
    // Compatibilidad WooCommerce
    if (class_exists('WooCommerce')) {
        new FSC_WooCommerce_Compat();
    }
    
    // Optimizador ML
    new FSC_ML_Optimizer();
    
    // Optimizador de assets
    new FSC_Asset_Optimizer();
}
add_action('plugins_loaded', 'fsc_init');

// Activación del plugin
register_activation_hook(__FILE__, 'fsc_activate');
function fsc_activate() {
    // Crear directorios necesarios
    $directories = [
        FSC_CACHE_DIR,
        FSC_ASSETS_DIR,
        FSC_CACHE_DIR . 'css/',
        FSC_CACHE_DIR . 'js/',
        FSC_CACHE_DIR . 'images/',
        FSC_CACHE_DIR . 'fonts/'
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Crear tablas para ML analytics
    fsc_create_analytics_tables();
    
    // Crear archivo .htaccess optimizado
    fsc_create_optimized_htaccess();
    
    // Configuración por defecto
    add_option('fsc_enabled', true);
    add_option('fsc_cache_lifetime', 3600);
    add_option('fsc_excluded_pages', array('/cart', '/checkout', '/my-account'));
    add_option('fsc_excluded_user_agents', array('bot', 'crawler', 'spider'));
    add_option('fsc_show_cache_info', true);
    add_option('fsc_ml_enabled', true);
    add_option('fsc_asset_optimization', true);
    add_option('fsc_image_optimization', true);
    add_option('fsc_critical_css', true);
    
    // Programar tareas de optimización
    if (!wp_next_scheduled('fsc_ml_analysis')) {
        wp_schedule_event(time(), 'hourly', 'fsc_ml_analysis');
    }
    
    if (!wp_next_scheduled('fsc_asset_optimization')) {
        wp_schedule_event(time(), 'daily', 'fsc_asset_optimization');
    }
}

// Desactivación del plugin
register_deactivation_hook(__FILE__, 'fsc_deactivate');
function fsc_deactivate() {
    // Limpiar caché
    fsc_clear_all_cache();
    
    // Eliminar tareas programadas
    wp_clear_scheduled_hook('fsc_ml_analysis');
    wp_clear_scheduled_hook('fsc_asset_optimization');
    
    // Eliminar .htaccess
    $htaccess_path = FSC_CACHE_DIR . '.htaccess';
    if (file_exists($htaccess_path)) {
        unlink($htaccess_path);
    }
}

// Crear tablas para analytics ML
function fsc_create_analytics_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla para métricas de usuario
    $table_metrics = $wpdb->prefix . 'fsc_user_metrics';
    $sql_metrics = "CREATE TABLE $table_metrics (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        page_url varchar(500) NOT NULL,
        viewport_width int(11) NOT NULL,
        viewport_height int(11) NOT NULL,
        scroll_depth float NOT NULL,
        time_on_page int(11) NOT NULL,
        lcp_time float DEFAULT NULL,
        fid_time float DEFAULT NULL,
        cls_score float DEFAULT NULL,
        device_type varchar(20) NOT NULL,
        connection_type varchar(20) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY page_url (page_url(191)),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Tabla para configuraciones adaptativas
    $table_config = $wpdb->prefix . 'fsc_adaptive_config';
    $sql_config = "CREATE TABLE $table_config (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        config_key varchar(100) NOT NULL,
        config_value text NOT NULL,
        page_pattern varchar(200) DEFAULT NULL,
        device_type varchar(20) DEFAULT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY config_key_pattern (config_key, page_pattern, device_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_metrics);
    dbDelta($sql_config);
}

// Crear .htaccess optimizado para máximo rendimiento
function fsc_create_optimized_htaccess() {
    $htaccess_content = '
# Fast Static Cache Pro - Ultra Performance Rules
<IfModule mod_rewrite.c>
RewriteEngine On

# Servir archivos estáticos HTML directamente (MÁXIMA VELOCIDAD)
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{HTTP_COOKIE} !woocommerce_cart_hash
RewriteCond %{HTTP_COOKIE} !woocommerce_items_in_cart
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/
RewriteCond %{REQUEST_URI} !^/cart/
RewriteCond %{REQUEST_URI} !^/checkout/
RewriteCond %{REQUEST_URI} !^/my-account/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Para página principal
RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache/index/index.html -f
RewriteRule ^$ wp-content/cache/fast-static-cache/index/index.html [L]

# Para otras páginas
RewriteCond %{REQUEST_URI} !^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ wp-content/cache/fast-static-cache/$1/index.html [L]
</IfModule>

# Headers para máximo rendimiento
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/html "access plus 1 hour"
ExpiresByType text/css "access plus 1 year"
ExpiresByType application/javascript "access plus 1 year"
ExpiresByType image/png "access plus 1 year"
ExpiresByType image/jpg "access plus 1 year"
ExpiresByType image/jpeg "access plus 1 year"
ExpiresByType image/gif "access plus 1 year"
ExpiresByType image/webp "access plus 1 year"
ExpiresByType image/avif "access plus 1 year"
ExpiresByType font/woff "access plus 1 year"
ExpiresByType font/woff2 "access plus 1 year"
</IfModule>

# Compresión avanzada
<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml
</IfModule>

# Brotli compression (si está disponible)
<IfModule mod_brotli.c>
AddOutputFilterByType BROTLI_COMPRESS text/html text/css text/javascript application/javascript application/json
</IfModule>

# Headers de caché optimizados
<IfModule mod_headers.c>
Header set X-Static-Cache "HIT"
Header set Cache-Control "public, max-age=31536000, immutable" "expr=%{REQUEST_URI} =~ m#\.(css|js|png|jpg|jpeg|gif|webp|avif|woff|woff2)$#"
Header set Cache-Control "public, max-age=3600" "expr=%{REQUEST_URI} =~ m#\.html$#"

# Preload headers para recursos críticos
Header add Link "</wp-content/cache/fast-static-cache/css/critical.css>; rel=preload; as=style"
Header add Link "</wp-content/cache/fast-static-cache/js/ml-optimizer.js>; rel=preload; as=script"
</IfModule>

# Seguridad adicional
<Files "*.json">
Order allow,deny
Deny from all
</Files>
';
    
    file_put_contents(FSC_CACHE_DIR . '.htaccess', $htaccess_content);
    
    // También crear .htaccess en la raíz para redirigir a archivos estáticos
    $root_htaccess = ABSPATH . '.htaccess';
    $existing_content = file_exists($root_htaccess) ? file_get_contents($root_htaccess) : '';
    
    // Solo agregar si no existe ya
    if (strpos($existing_content, '# Fast Static Cache Pro') === false) {
        $static_rules = '
# Fast Static Cache Pro - Servir archivos estáticos ANTES que WordPress
<IfModule mod_rewrite.c>
RewriteEngine On

# Servir archivos estáticos HTML directamente (MÁXIMA VELOCIDAD)
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{HTTP_COOKIE} !woocommerce_cart_hash
RewriteCond %{HTTP_COOKIE} !woocommerce_items_in_cart
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/
RewriteCond %{REQUEST_URI} !^/cart/
RewriteCond %{REQUEST_URI} !^/checkout/
RewriteCond %{REQUEST_URI} !^/my-account/

# Para página principal
RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache/index/index.html -f
RewriteRule ^$ wp-content/cache/fast-static-cache/index/index.html [L]

# Para otras páginas
RewriteCond %{REQUEST_URI} !^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ wp-content/cache/fast-static-cache/$1/index.html [L]
</IfModule>

# Fin Fast Static Cache Pro

';
        
        file_put_contents($root_htaccess, $static_rules . $existing_content);
    }
}

// Hooks para análisis ML
add_action('fsc_ml_analysis', 'fsc_run_ml_analysis');
add_action('fsc_asset_optimization', 'fsc_run_asset_optimization');