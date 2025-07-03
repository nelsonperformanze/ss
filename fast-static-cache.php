<?php
/**
 * Plugin Name: Fast Static Cache
 * Plugin URI: https://github.com/yourname/fast-static-cache
 * Description: Convierte tu sitio WordPress en páginas estáticas para máxima velocidad y rendimiento.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://tusitio.com
 * License: GPL v2 or later
 * Text Domain: fast-static-cache
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('FSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FSC_CACHE_DIR', WP_CONTENT_DIR . '/cache/fast-static-cache/');
define('FSC_VERSION', '1.0.0');

// Incluir archivos necesarios
require_once FSC_PLUGIN_PATH . 'includes/class-fast-static-cache.php';
require_once FSC_PLUGIN_PATH . 'includes/class-admin.php';
require_once FSC_PLUGIN_PATH . 'includes/functions.php';

// Inicializar el plugin
function fsc_init() {
    new Fast_Static_Cache();
    
    if (is_admin()) {
        new FSC_Admin();
    }
}
add_action('plugins_loaded', 'fsc_init');

// Activación del plugin
register_activation_hook(__FILE__, 'fsc_activate');
function fsc_activate() {
    // Crear directorio de caché
    if (!file_exists(FSC_CACHE_DIR)) {
        wp_mkdir_p(FSC_CACHE_DIR);
    }
    
    // Crear archivo .htaccess para servir archivos estáticos
    fsc_create_htaccess();
    
    // Configuración por defecto
    add_option('fsc_enabled', true);
    add_option('fsc_cache_lifetime', 3600); // 1 hora
    add_option('fsc_excluded_pages', array());
    add_option('fsc_excluded_user_agents', array('bot', 'crawler', 'spider'));
    add_option('fsc_show_cache_info', true);
}

// Desactivación del plugin
register_deactivation_hook(__FILE__, 'fsc_deactivate');
function fsc_deactivate() {
    // Limpiar caché
    fsc_clear_all_cache();
    
    // Eliminar .htaccess
    $htaccess_path = FSC_CACHE_DIR . '.htaccess';
    if (file_exists($htaccess_path)) {
        unlink($htaccess_path);
    }
}

// Crear archivo .htaccess para servir archivos estáticos DIRECTAMENTE
function fsc_create_htaccess() {
    $htaccess_content = '
# Fast Static Cache Rules - SERVIR ARCHIVOS ESTÁTICOS DIRECTAMENTE
<IfModule mod_rewrite.c>
RewriteEngine On

# Servir archivos estáticos HTML directamente (SIN procesar PHP)
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Verificar si existe archivo estático y servirlo DIRECTAMENTE
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ /wp-content/cache/fast-static-cache%{REQUEST_URI}/index.html [L]

# Si no hay archivo estático, servir desde raíz si existe
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache/index/index.html -f
RewriteRule ^$ /wp-content/cache/fast-static-cache/index/index.html [L]
</IfModule>

# Headers para máximo rendimiento de archivos estáticos
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/html "access plus 1 hour"
ExpiresByType text/css "access plus 1 month"
ExpiresByType application/javascript "access plus 1 month"
ExpiresByType image/png "access plus 1 year"
ExpiresByType image/jpg "access plus 1 year"
ExpiresByType image/jpeg "access plus 1 year"
ExpiresByType image/gif "access plus 1 year"
</IfModule>

# Comprimir archivos estáticos para máxima velocidad
<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json
</IfModule>

# Headers de caché para archivos estáticos
<IfModule mod_headers.c>
Header set X-Static-Cache "HIT"
Header set Cache-Control "public, max-age=3600"
</IfModule>
';
    
    file_put_contents(FSC_CACHE_DIR . '.htaccess', $htaccess_content);
    
    // También crear .htaccess en la raíz para redirigir a archivos estáticos
    $root_htaccess = ABSPATH . '.htaccess';
    $existing_content = file_exists($root_htaccess) ? file_get_contents($root_htaccess) : '';
    
    // Solo agregar si no existe ya
    if (strpos($existing_content, '# Fast Static Cache') === false) {
        $static_rules = '
# Fast Static Cache - Servir archivos estáticos ANTES que WordPress
<IfModule mod_rewrite.c>
RewriteEngine On

# Servir archivos estáticos HTML directamente (MÁXIMA VELOCIDAD)
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP_COOKIE} !comment_author_
RewriteCond %{HTTP_COOKIE} !wp-postpass_
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_
RewriteCond %{REQUEST_URI} !^/wp-admin/
RewriteCond %{REQUEST_URI} !^/wp-content/
RewriteCond %{REQUEST_URI} !^/wp-includes/

# Para página principal
RewriteCond %{REQUEST_URI} ^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache/index/index.html -f
RewriteRule ^$ wp-content/cache/fast-static-cache/index/index.html [L]

# Para otras páginas
RewriteCond %{REQUEST_URI} !^/$
RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/fast-static-cache%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ wp-content/cache/fast-static-cache/$1/index.html [L]
</IfModule>

# Fin Fast Static Cache

';
        
        file_put_contents($root_htaccess, $static_rules . $existing_content);
    }
}