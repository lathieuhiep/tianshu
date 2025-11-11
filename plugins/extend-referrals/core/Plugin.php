<?php
/**
 * Core Plugin Loader
 *
 * @package ExtendReferrals\Core
 */

namespace ExtendReferrals\Core;

use ExtendReferrals\Admin\SettingsPage;
use ExtendReferrals\Ajax\SetTTL;
use ExtendReferrals\Repository\SettingsRepository;

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
            SettingsRepository::init();
        } else {
            // Frontend hooks (nếu có) sẽ đặt ở đây
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend']);

            // Load AJAX handlers
            self::load_ajax();

            // Chèn quảng cáo vào nội dung bài viết
            add_filter('the_content', [AdsManager::class, 'inject_ads_into_content']);
        }
    }

    /**
     * Register menu in WP Admin.
     */
    public static function register_admin_menu(): void {
        add_menu_page(
            esc_html__('Affiliate Ads', 'extend-referrals'),
            esc_html__('Affiliate Ads', 'extend-referrals'),
            'manage_options',
            SettingsPage::MENU_SLUG,
            [SettingsPage::class, 'render_page'],
            'dashicons-megaphone',
            9
        );
    }

    /**
     * Enqueue frontend assets.
     */
    public static function enqueue_frontend(): void {
        // load styles
        wp_enqueue_style(
            'er-frontend',
            EXTEND_REFERRALS_URL . 'assets/css/er-frontend.css',
            [],
            EXTEND_REFERRALS_VERSION
        );

        // load script
        wp_enqueue_script(
            'er-frontend',
            EXTEND_REFERRALS_URL . 'assets/js/er-frontend.js',
            ['jquery'],
            EXTEND_REFERRALS_VERSION,
            true
        );

        wp_localize_script(
            'er-frontend',
            'ExtendReferrals',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('extend_affiliate_nonce'),
            ]
        );
    }

    /**
     * Load AJAX handlers.
     */
    private static function load_ajax(): void
    {
        SetTTL::init();
    }
}