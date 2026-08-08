<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class CrawlerAdmin
{
    public const PAGE_SLUG = 'extend-site-crawler';
    public const PARENT_SLUG = 'extend-site-main';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 20);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Trình crawler', 'extend-site'),
            esc_html__('Crawler truyện', 'extend-site'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'extend-site'));
        }

        self::render_view('crawler-page', [
            'create_template_url' => add_query_arg(
                ['page' => CrawlerTemplateAdmin::PAGE_SLUG, 'action' => 'new'],
                admin_url('admin.php')
            ),
        ]);
    }

    private static function render_view(string $view, array $view_data = []): void
    {
        $path = EXTEND_SITE_PATH . 'includes/Crawler/views/' . $view . '.php';
        if (!is_file($path)) {
            wp_die(esc_html__('Không tìm thấy view admin.', 'extend-site'));
        }

        include $path;
    }
}
