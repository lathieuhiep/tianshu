<?php
namespace ExtendSite\Admin;

use ExtendSite\Services\Tools\ChapterSyncTool;
use ExtendSite\Services\Tools\ToolManager;

defined('ABSPATH') || exit;

class MenuPage
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_main_menu']);
    }

    public static function register_main_menu(): void
    {
        add_menu_page(
            esc_html__('Chung', 'extend-site'),
            esc_html__('Extend Site', 'extend-site'),
            'edit_posts',
            'extend-site-main',
            [__CLASS__, 'render_dashboard'],
            'dashicons-admin-multisite',
            5
        );

        add_submenu_page(
            'extend-site-main',
            esc_html__('Công cụ', 'extend-site'),
            esc_html__('Công cụ', 'extend-site'),
            'manage_options',
            'extend-site-tools',
            [__CLASS__, 'render_tools']
        );
    }

    /** View loader */
    private static function load_view(string $view, array $data = []): void
    {
        $file = plugin_dir_path(__FILE__) . 'views/' . $view . '.php';
        if (file_exists($file)) {
            extract($data);
            include $file;
        } else {
            echo '<div class="error"><p>'. esc_html__('View file not found', 'extend-site') . ': ' . esc_html($view) .'</p></div>';
        }
    }

    /** Trang dashboard */
    public static function render_dashboard(): void
    {
        // Kiểm tra submit
        if (isset($_POST['extend_site_last_chapter_facebook_url'])) {

            check_admin_referer('extend_site_save_options', 'extend_site_nonce');

            // Lấy giá trị (dù trống vẫn cho lưu)
            $value = sanitize_text_field($_POST['extend_site_last_chapter_facebook_url']);

            update_option('extend_site_last_chapter_facebook_url', $value);

            echo '<div class="updated"><p>Lưu thành công.</p></div>';
        }

        // Lấy option để hiển thị trong view
        $fb_url = get_option('extend_site_last_chapter_facebook_url', '');

        self::load_view('dashboard', [
            'title'  => esc_html__('Hệ thống Truyện', 'extend-site'),
            'fb_url' => $fb_url,
        ]);
    }

    /** Trang công cụ */
    public static function render_tools(): void
    {
        $message = null;
        $tools = [
            'chapter_sync' => ChapterSyncTool::class,
            // thêm tool khác sau này ở đây
        ];

        if (!empty($_POST['run_tool'])) {
            check_admin_referer('run_tool_action', 'run_tool_nonce');

            $key = sanitize_text_field($_POST['run_tool']);
            $tool_class = $tools[$key] ?? null;

            if ($tool_class && class_exists($tool_class)) {
                $result = ToolManager::run_tool($tool_class);
                $message = $result['message'] ?? esc_html__('Tool executed.', 'extend-site');
            } else {
                $message = esc_html__('Tool not found.', 'extend-site');
                error_log("[TOOLS PAGE] Invalid tool key: $key");
            }
        }

        self::load_view('tools', compact('tools', 'message'));
    }

    /** Logic đồng bộ */
    public static function recount_all_stories(): array
    {
        // ... (giữ nguyên hàm đếm chương)
    }
}