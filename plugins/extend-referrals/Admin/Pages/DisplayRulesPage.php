<?php
namespace ExtendReferrals\Admin\Pages;

use ExtendReferrals\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Class DisplayRulesPage
 *
 * Trang quản lý quy tắc hiển thị quảng cáo đối tác.
 */
class DisplayRulesPage
{
    public const OPTION_GROUP = 'extend_referrals_display_rule_settings';
    public const OPTION_KEY   = 'extend_referrals_display_rules';
    public const RULES_DEFAULT = ['post', 'chapter'];

    /**
     * Init Display Rules Page hooks.
     */
    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register setting options.
     */
    public static function register_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY,
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
                'default'           => self::RULES_DEFAULT,
            ]
        );
    }

    /**
     * Sanitize rule data before save.
     *
     * @param mixed $input User input from form.
     * @return array Sanitized options.
     */
    public static function sanitize_settings($input): array {
        $valid_options = Helpers::get_all_post_types();
        $sanitized = [];

        if (is_array($input)) {
            $sanitized = array_values(array_intersect($input, array_keys($valid_options)));
        } else {
            $sanitized = self::RULES_DEFAULT;
        }


        return $sanitized ?: self::RULES_DEFAULT;
    }

    /**
     * Get saved display rule options.
     */
    public static function get_options(): array {
        return get_option(self::OPTION_KEY, self::RULES_DEFAULT);
    }

    /**
     * Render Display Rules Page.
     */
    public static function render_page(): void {
        $post_types = Helpers::get_all_post_types();
        $selected   = self::get_options();

        include EXTEND_REFERRALS_PATH . 'views/backend/display-rules-page.php';
    }
}