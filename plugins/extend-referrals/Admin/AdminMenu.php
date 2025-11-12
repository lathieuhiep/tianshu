<?php
namespace ExtendReferrals\Admin;

use ExtendReferrals\Admin\Pages\AdsSettingsPage;
use ExtendReferrals\Admin\Pages\DisplayRulesPage;

defined('ABSPATH') || exit;

class AdminMenu {

    public const PARENT_SLUG = 'extend-referrals';

    /**
     * Init Admin Menu hooks.
     * @return void
     */
    public static function init(): void {
        // register menu và submenu
        add_action('admin_menu', [__CLASS__, 'register']);

        // load Page
        AdsSettingsPage::init();
        DisplayRulesPage::init();
    }

    public static function register(): void {
        // Menu chính
        add_menu_page(
            esc_html__('Extend Referrals', 'extend-referrals'),
            esc_html__('Extend Referrals', 'extend-referrals'),
            'manage_options',
            self::PARENT_SLUG,
            [AdsSettingsPage::class, 'render_page'],
            'dashicons-megaphone',
            10
        );

        // Submenus
        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Quảng cáo đối tác', 'extend-referrals'),
            esc_html__('Quảng cáo đối tác', 'extend-referrals'),
            'manage_options',
            self::PARENT_SLUG,
            [AdsSettingsPage::class, 'render_page']
        );

        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Thiết lập hiển thị', 'extend-referrals'),
            esc_html__('Thiết lập hiển thị', 'extend-referrals'),
            'manage_options',
            'extend-referrals-display-rules',
            [DisplayRulesPage::class, 'render_page']
        );
    }
}