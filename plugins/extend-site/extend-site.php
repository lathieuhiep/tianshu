<?php
/**
 * Plugin Name: Extend Site
 * Description: Essential toolkit for WordPress: custom post types, widgets, and site extensions.
 * Version:     1.0.0
 * Author:      La Thieu Hiep
 * Text Domain: extend-site
 * Requires at least: 6.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

use ExtendSite\Core\Autoloader;
use ExtendSite\Core\Plugin;
use ExtendSite\PostType\PortfolioPostType;

defined('ABSPATH') || exit;

// Constants
const EXTEND_SITE_VERSION  = '1.0.0';
const EXTEND_SITE_FILE     = __FILE__;
define('EXTEND_SITE_PATH',     plugin_dir_path(EXTEND_SITE_FILE));
define('EXTEND_SITE_URL',      plugin_dir_url(EXTEND_SITE_FILE));
define('EXTEND_SITE_BASENAME', plugin_basename(EXTEND_SITE_FILE));

// Normal runtime boot
add_action('plugins_loaded', static function () {
    require_once EXTEND_SITE_PATH . 'includes/Core/Autoloader.php';
    Autoloader::register();

    (new Plugin())->boot();
});

// Activation: must load autoloader here too
register_activation_hook(EXTEND_SITE_FILE, function () {
    // Ensure autoloader is available during activation request
    require_once EXTEND_SITE_PATH . 'includes/Core/Autoloader.php';
    Autoloader::register();

    // Instantiate CPT classes so we can register them now (before flush)
    $post_types = [
        PortfolioPostType::class
    ];

    $instances = [];
    foreach ($post_types as $cls) {
        $instances[] = new $cls();
    }

    // Call register_ctp() explicitly so rewrite rules exist before flush
    foreach ($instances as $inst) {
        if (method_exists($inst, 'register_ctp')) {
            $inst->register_ctp();
        }
    }

    flush_rewrite_rules();
});

// Deactivation: flush only
register_deactivation_hook(EXTEND_SITE_FILE, function () {
    flush_rewrite_rules();
});