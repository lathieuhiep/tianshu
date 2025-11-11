<?php
/**
 * Admin Settings Page
 *
 * @package ExtendReferrals\Admin
 */

namespace ExtendReferrals\Admin;

use ExtendReferrals\Repository\SettingsRepository;

defined('ABSPATH') || exit;

class SettingsPage {

    public const MENU_SLUG = 'extend-referrals';

    /**
     * Init admin hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Register submenu page.
     */
    public static function register_menu(): void {
        add_submenu_page(
            self::MENU_SLUG,
            esc_html__('Affiliate Ads Settings', 'extend-referrals'),
            esc_html__('Settings', 'extend-referrals'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Enqueue admin CSS/JS for repeater.
     */
    public static function enqueue_assets(string $hook_suffix): void {
        if (str_contains($hook_suffix, self::MENU_SLUG)) {
            wp_enqueue_style(
                'extend-referrals-admin',
                EXTEND_REFERRALS_URL . 'admin/css/admin.css',
                [],
                EXTEND_REFERRALS_VERSION
            );


            // load media uploader
            wp_enqueue_media();

            // load repeater JS
            wp_enqueue_script(
                'extend-referrals-admin',
                EXTEND_REFERRALS_URL . 'admin/js/affiliate-ads-admin.js',
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
     * Render settings page.
     */
    public static function render_page(): void {
        $options = SettingsRepository::get_options();

        include EXTEND_REFERRALS_PATH . 'admin/views/settings-page.php';
    }

}