<?php
namespace ExtendSite\Admin;

use ExtendSite\Services\StoryChapterStatusSyncJob;
use ExtendSite\Services\SystemJobQueue;
use ExtendSite\Services\Tools\ChapterSyncTool;
use ExtendSite\Services\Tools\SystemJobCleanupTool;
use ExtendSite\Services\Tools\SystemJobRunnerTool;
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
        $message = self::handle_dashboard_submission();
        $fb_url = get_option('extend_site_last_chapter_facebook_url', '');

        self::load_view('dashboard', [
            'title'  => esc_html__('Hệ thống Truyện', 'extend-site'),
            'fb_url' => $fb_url,
            'message' => $message,
        ]);
    }

    /** Trang công cụ */
    public static function render_tools(): void
    {
        $tools = self::tools();
        $message = self::handle_tools_submission($tools);

        self::load_view('tools', [
            'tool_rows' => self::tool_rows($tools),
            'message' => $message,
            'formatted_jobs' => SystemJobQueue::get_formatted_jobs(),
        ]);
    }

    private static function handle_dashboard_submission(): ?string
    {
        if (!isset($_POST['extend_site_last_chapter_facebook_url'])) {
            return null;
        }

        check_admin_referer('extend_site_save_options', 'extend_site_nonce');

        $value = sanitize_text_field((string) wp_unslash($_POST['extend_site_last_chapter_facebook_url']));
        update_option('extend_site_last_chapter_facebook_url', $value);

        return __('Lưu thành công.', 'extend-site');
    }

    private static function tools(): array
    {
        return [
            'chapter_sync' => ChapterSyncTool::class,
            'system_job_runner' => SystemJobRunnerTool::class,
            'system_job_cleanup' => SystemJobCleanupTool::class,
        ];
    }

    private static function tool_rows(array $tools): array
    {
        $rows = [];
        foreach ($tools as $key => $tool_class) {
            if (!class_exists($tool_class)) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'title' => (string) $tool_class::get_title(),
                'description' => (string) $tool_class::get_description(),
            ];
        }

        return $rows;
    }

    private static function handle_tools_submission(array $tools): ?string
    {
        if (!empty($_POST['create_status_sync_job'])) {
            check_admin_referer('create_status_sync_job_action', 'create_status_sync_job_nonce');

            $story_id = absint($_POST['sync_story_id'] ?? 0);
            $status_mode = sanitize_key((string) wp_unslash($_POST['sync_status_mode'] ?? 'story'));
            $result = StoryChapterStatusSyncJob::create($story_id, $status_mode);

            if (is_wp_error($result)) {
                return $result->get_error_message();
            }

            return sprintf(
                __('Đã tạo job đồng bộ trạng thái chương: %s', 'extend-site'),
                $result
            );
        }

        if (empty($_POST['run_tool'])) {
            return null;
        }

        check_admin_referer('run_tool_action', 'run_tool_nonce');

        $key = sanitize_text_field((string) wp_unslash($_POST['run_tool']));
        $tool_class = $tools[$key] ?? null;

        if ($tool_class && class_exists($tool_class)) {
            $result = ToolManager::run_tool($tool_class);
            return $result['message'] ?? esc_html__('Tool executed.', 'extend-site');
        }

        error_log("[TOOLS PAGE] Invalid tool key: $key");

        return esc_html__('Tool not found.', 'extend-site');
    }

    /** Logic đồng bộ */
    public static function recount_all_stories(): array
    {
        // ... (giữ nguyên hàm đếm chương)
    }
}
