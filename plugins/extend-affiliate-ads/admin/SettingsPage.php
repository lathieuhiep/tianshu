<?php
/**
 * Admin Settings Page
 *
 * @package ExtendAffiliateAds\Admin
 */

namespace ExtendAffiliateAds\Admin;

defined('ABSPATH') || exit;

class SettingsPage {

    public const MENU_SLUG = 'extend-affiliate-ads';
    public const OPTION_KEY = 'extend_affiliate_ads_settings';

    /**
     * Init admin hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Register submenu page.
     */
    public static function register_menu(): void {
        add_submenu_page(
            self::MENU_SLUG,
            esc_html__('Affiliate Ads Settings', 'extend-affiliate-ads'),
            esc_html__('Settings', 'extend-affiliate-ads'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Register option & section.
     */
    public static function register_settings(): void {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
        ]);
    }

    /**
     * Sanitize before save.
     */
    public static function sanitize_options(array $input): array {
        $clean = [];
        if (!empty($input['ads']) && is_array($input['ads'])) {
            foreach ($input['ads'] as $ad) {
                $clean['ads'][] = [
                    'image' => esc_url_raw($ad['image'] ?? ''),
                    'link'  => esc_url_raw($ad['link'] ?? ''),
                    'label' => sanitize_text_field($ad['label'] ?? ''),
                ];
            }
        }
        return $clean;
    }

    /**
     * Enqueue admin CSS/JS for repeater.
     */
    public static function enqueue_assets(string $hook_suffix): void {
        error_log('Affiliate Ads hook: ' . $hook_suffix);

        if (str_contains($hook_suffix, self::MENU_SLUG)) {
            wp_enqueue_style(
                'extend-affiliate-ads-admin',
                EXTEND_AFFILIATE_ADS_URL . 'admin/css/admin.css',
                [],
                EXTEND_AFFILIATE_ADS_VERSION
            );

            wp_enqueue_script(
                'extend-affiliate-ads-admin',
                EXTEND_AFFILIATE_ADS_URL . 'admin/js/repeater.js',
                ['jquery'],
                EXTEND_AFFILIATE_ADS_VERSION,
                true
            );

            wp_localize_script('extend-affiliate-ads-admin', 'ExtendAffiliateAds', [
                'addItemText' => esc_html__('Add new Ad', 'extend-affiliate-ads'),
            ]);
        }
    }

    /**
     * Render settings page.
     */
    public static function render_page(): void {
        $options = get_option(self::OPTION_KEY, []);
        $ads     = $options['ads'] ?? [];
        include EXTEND_AFFILIATE_ADS_PATH . 'admin/views/settings-page.php';
    }
}