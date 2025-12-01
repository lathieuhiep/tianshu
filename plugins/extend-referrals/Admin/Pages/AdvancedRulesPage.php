<?php
namespace ExtendReferrals\Admin\Pages;

defined('ABSPATH') || exit;

/**
 * Class AdvancedRulesPage
 *
 * Thiết lập điều kiện hiển thị nâng cao cho CHAPTER.
 */
class AdvancedRulesPage
{
    public const OPTION_GROUP = 'extend_referrals_advanced_rule_settings';
    public const OPTION_KEY_CHAPTER = 'extend_referrals_display_rules_chapter';

    /**
     * Init hooks.
     */
    public static function init(): void
    {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Register setting options for advanced rules.
     */
    public static function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY_CHAPTER,
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_chapter_rules'],
            ]
        );
    }

    /**
     * Sanitize chapter rules.
     */
    public static function sanitize_chapter_rules($input): array
    {
        $default = [
            'enabled'   => true,
            'mode'      => 'from_number',
            'from'      => 2,
            'only_list' => '',
        ];

        if (!is_array($input)) return $default;

        return [
            'enabled'   => !empty($input['enabled']),
            'mode'      => sanitize_text_field($input['mode'] ?? 'from_number'),
            'from'      => absint($input['from'] ?? 2),
            'only_list' => sanitize_text_field($input['only_list'] ?? ''),
        ];
    }

    /**
     * Get rules for chapter.
     */
    public static function get_chapter_rules(): array
    {
        $default = [
            'enabled'   => true,
            'mode'      => 'from_number',
            'from'      => 2,
            'only_list' => '',
        ];

        $opts = get_option(self::OPTION_KEY_CHAPTER, []);

        return wp_parse_args($opts, $default);
    }

    /**
     * Render Advanced Rules Page.
     */
    public static function render_page(): void
    {
        $chapter = self::get_chapter_rules();

        include EXTEND_REFERRALS_PATH . 'views/backend/advanced-rules-page.php';
    }
}
