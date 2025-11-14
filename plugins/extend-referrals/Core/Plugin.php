<?php
/**
 * Core Plugin Loader
 *
 * @package ExtendReferrals\Core
 */

namespace ExtendReferrals\Core;

use ExtendReferrals\Admin\AdminMenu;
use ExtendReferrals\Admin\Pages\DisplayRulesPage;

defined('ABSPATH') || exit;

class Plugin {

    /**
     * Init plugin hooks.
     */
    public static function init(): void {
        add_action('init', [AdminMenu::class, 'init']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);

        if (! is_admin()) {
            // Frontend hooks
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend']);
            add_filter('the_content', [AdsManager::class, 'inject_ads_into_content']);
        }
    }

    /**
     * Enqueue admin assets.
     */
    public static function enqueue_admin(string $hook_suffix): void
    {
        if (str_contains($hook_suffix, AdminMenu::PARENT_SLUG)) {
            wp_enqueue_style(
                'extend-referrals-admin',
                EXTEND_REFERRALS_URL . 'assets/css/backend/er-admin.min.css',
                [],
                EXTEND_REFERRALS_VERSION
            );


            // load media uploader
            wp_enqueue_media();

            // load repeater JS
            wp_enqueue_script(
                'extend-referrals-admin',
                EXTEND_REFERRALS_URL . 'assets/js/backend/er-admin.min.js',
                ['jquery'],
                EXTEND_REFERRALS_VERSION,
                true
            );

            // Truyền biến PHP → JS (dùng để phân biệt ảnh nội bộ / ảnh ngoài)
            $upload_dir = wp_upload_dir();
            wp_localize_script(
                'extend-referrals-admin',
                'ExtendReferrals',
                [
                    'uploadsBaseUrl' => trailingslashit( $upload_dir['baseurl'] ),
                ]
            );
        }
    }

    /**
     * Enqueue frontend assets.
     */
    public static function enqueue_frontend(): void {
        if (!is_singular()) {
            return;
        }

        // Lấy post type hiện tại
        $post_type = get_post_type();

        // Lấy danh sách post type được phép hiển thị quảng cáo
        $allowed_types = DisplayRulesPage::get_options();

        // Nếu post type không nằm trong danh sách, bỏ qua luôn
        if (!in_array($post_type, $allowed_types, true)) {
            return;
        }

        // load styles
        wp_enqueue_style(
            'er-frontend',
            EXTEND_REFERRALS_URL . 'assets/css/frontend/er-frontend.min.css',
            [],
            EXTEND_REFERRALS_VERSION
        );

        // load script
        wp_enqueue_script(
            'er-frontend',
            EXTEND_REFERRALS_URL . 'assets/js/frontend/er-frontend.min.js',
            ['jquery'],
            EXTEND_REFERRALS_VERSION,
            true
        );

        wp_localize_script(
            'er-frontend',
            'ExtendReferrals',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('extend_referrals_nonce'),
            ]
        );
    }
}