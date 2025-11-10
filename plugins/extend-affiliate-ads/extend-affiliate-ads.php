<?php
/**
 * Plugin Name: ExtendSite Affiliate Ads
 * Description: Hiển thị quảng cáo affiliate thông minh (Shopee, Tiki, Lazada, custom link) với TTL theo hành vi người dùng.
 * Version:     1.0.0
 * Author:      La Thieu Hiep
 * Text Domain: extend-affiliate-ads
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.6
 * Requires PHP: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
if (!defined('EXTEND_AFFILIATE_ADS_PATH')) {
    define('EXTEND_AFFILIATE_ADS_PATH', plugin_dir_path(__FILE__));
}

if (!defined('EXTEND_AFFILIATE_ADS_URL')) {
    define('EXTEND_AFFILIATE_ADS_URL', plugin_dir_url(__FILE__));
}

if (!defined('EXTEND_AFFILIATE_ADS_VERSION')) {
    define('EXTEND_AFFILIATE_ADS_VERSION', '1.0.0');
}

// -----------------------------------------------------------------------------
// Autoloader (PSR-4 style)
// -----------------------------------------------------------------------------
spl_autoload_register(function ($class) {
    $prefix   = 'ExtendAffiliateAds\\';
    $base_dir = EXTEND_AFFILIATE_ADS_PATH;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        // Dev notice if missing class file.
        error_log('[ExtendAffiliateAds] Missing class file: ' . $file);
    }
});

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------
if (class_exists('ExtendAffiliateAds\Core\Plugin', false)) {
    // Prevent double loading (safety for MU or require_once)
    return;
}

/**
 * Main plugin bootstrap.
 */
function extend_affiliate_ads_boot(): void {
    if (class_exists('ExtendAffiliateAds\Core\Plugin')) {
        ExtendAffiliateAds\Core\Plugin::init();
    }
}
add_action('plugins_loaded', 'extend_affiliate_ads_boot');

// -----------------------------------------------------------------------------
// Text domain Fallback
// -----------------------------------------------------------------------------
add_action('init', function () {
    load_plugin_textdomain(
        'extend-affiliate-ads',
        false,
        basename(dirname(__FILE__)) . '/languages'
    );
});