<?php

namespace ExtendSite\Admin;

use ExtendSite\PostType\ChapterPostType;

defined('ABSPATH') || exit;

class PermalinkSettings
{
    public static function init(): void
    {
        add_action('admin_init', [__CLASS__, 'register_fields']);
        add_action('admin_init', [__CLASS__, 'save_fields']);
    }

    public static function register_fields(): void
    {
        add_settings_section(
            'extend_site_story_permalinks',
            esc_html__('Liên kết tĩnh truyện', 'extend-site'),
            [__CLASS__, 'render_section_description'],
            'permalink'
        );

        add_settings_field(
            ChapterPostType::OPTION_PERMALINK_STRUCTURE,
            esc_html__('Cấu trúc URL chương', 'extend-site'),
            [__CLASS__, 'render_chapter_permalink_field'],
            'permalink',
            'extend_site_story_permalinks'
        );
    }

    public static function render_section_description(): void
    {
        self::load_view('permalink-section-description');
    }

    public static function render_chapter_permalink_field(): void
    {
        self::load_view('chapter-permalink-field', [
            'option_name' => ChapterPostType::OPTION_PERMALINK_STRUCTURE,
            'value' => self::get_chapter_permalink_suffix(ChapterPostType::get_permalink_structure()),
        ]);
    }
    public static function save_fields(): void
    {
        global $pagenow;

        if ($pagenow !== 'options-permalink.php') {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST[ChapterPostType::OPTION_PERMALINK_STRUCTURE])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('update-permalink');

        $raw = sanitize_text_field(wp_unslash($_POST[ChapterPostType::OPTION_PERMALINK_STRUCTURE]));
        $structure = ChapterPostType::sanitize_permalink_structure(self::build_chapter_permalink_structure($raw));
        $old_structure = ChapterPostType::get_permalink_structure();

        update_option(ChapterPostType::OPTION_PERMALINK_STRUCTURE, $structure);

        if ($structure !== $old_structure) {
            update_option('extend_site_flush_rewrite', 1);
        }
    }

    private static function get_chapter_permalink_suffix(string $structure): string
    {
        $structure = trim($structure, '/');
        $prefix = '%story%/';

        if (strpos($structure, $prefix) === 0) {
            return substr($structure, strlen($prefix));
        }

        return 'chuong/%postname%/';
    }

    private static function build_chapter_permalink_structure(string $suffix): string
    {
        $suffix = trim($suffix);
        $suffix = trim($suffix, '/');

        if ($suffix === '') {
            $suffix = 'chuong/%postname%';
        }

        return '/%story%/' . $suffix . '/';
    }

    private static function load_view(string $view, array $data = []): void
    {
        $file = plugin_dir_path(__FILE__) . 'views/' . $view . '.php';
        if (!is_file($file)) {
            return;
        }

        extract($data);
        include $file;
    }
}
