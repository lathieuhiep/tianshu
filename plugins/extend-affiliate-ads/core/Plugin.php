<?php
/**
 * Core Plugin Loader
 *
 * @package ExtendAffiliateAds\Core
 */

namespace ExtendAffiliateAds\Core;

use ExtendAffiliateAds\Admin\SettingsPage;

defined('ABSPATH') || exit;

class Plugin {

    /**
     * Init plugin hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);

        if (is_admin()) {
            // Gọi sớm hơn để admin_enqueue_scripts có hiệu lực
            add_action('init', [SettingsPage::class, 'init']);
        }
    }

    /**
     * Register menu in WP Admin.
     */
    public static function register_admin_menu(): void {
        add_menu_page(
            esc_html__('Affiliate Ads', 'extend-affiliate-ads'),
            esc_html__('Affiliate Ads', 'extend-affiliate-ads'),
            'manage_options',
            SettingsPage::MENU_SLUG, // dùng slug thống nhất
            [SettingsPage::class, 'render_page'], // gọi qua SettingsPage
            'dashicons-megaphone',
            9
        );
    }
}