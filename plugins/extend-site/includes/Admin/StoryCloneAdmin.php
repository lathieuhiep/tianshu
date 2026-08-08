<?php

namespace ExtendSite\Admin;

use ExtendSite\PostType\StoryPostType;
use ExtendSite\Services\StoryCloneService;

defined('ABSPATH') || exit;

class StoryCloneAdmin
{
    public static function init(): void
    {
        add_filter('post_row_actions', [__CLASS__, 'add_clone_row_action'], 10, 2);
        add_action('admin_action_es_clone_story', [__CLASS__, 'handle_clone_action']);
        add_action('admin_notices', [__CLASS__, 'show_admin_notice']);
    }

    public static function add_clone_row_action(array $actions, \WP_Post $post): array
    {
        if ($post->post_type !== StoryPostType::SLUG || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            add_query_arg([
                'action' => 'es_clone_story',
                'post' => $post->ID,
            ], admin_url('admin.php')),
            'es_clone_story_' . $post->ID
        );

        $actions['es_clone_story'] = '<a href="' . esc_url($url) . '">' . esc_html__('Nhân bản', 'extend-site') . '</a>';

        return $actions;
    }

    public static function handle_clone_action(): void
    {
        $story_id = absint($_GET['post'] ?? 0);

        if ($story_id <= 0 || !current_user_can('edit_post', $story_id)) {
            wp_die(esc_html__('Bạn không có quyền nhân bản truyện này.', 'extend-site'));
        }

        check_admin_referer('es_clone_story_' . $story_id);

        $result = StoryCloneService::clone_story($story_id, get_current_user_id());
        if (is_wp_error($result)) {
            self::redirect_with_notice('error', $result->get_error_message());
        }

        $new_story_id = absint($result['story_id'] ?? 0);
        $job_id = sanitize_text_field((string) ($result['job_id'] ?? ''));
        $chapter_total = absint($result['chapter_total'] ?? 0);

        $redirect = $new_story_id > 0 ? get_edit_post_link($new_story_id, 'raw') : admin_url('edit.php?post_type=' . StoryPostType::SLUG);
        $redirect = add_query_arg([
            'es_clone_notice' => 'success',
            'es_clone_chapters' => $chapter_total,
            'es_clone_job' => $job_id,
        ], $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function show_admin_notice(): void
    {
        $notice = self::current_notice();
        if (!$notice) {
            return;
        }

        self::load_view('story-clone-notice', ['notice' => $notice]);
    }

    private static function current_notice(): array
    {
        if (empty($_GET['es_clone_notice'])) {
            return [];
        }

        $type = sanitize_key((string) wp_unslash($_GET['es_clone_notice']));
        if ($type === 'success') {
            $chapter_total = absint($_GET['es_clone_chapters'] ?? 0);
            $job_id = sanitize_text_field((string) wp_unslash($_GET['es_clone_job'] ?? ''));

            if ($chapter_total > 0 && $job_id !== '') {
                return [
                    'type' => 'success',
                    'title' => __('Đã nhân bản truyện thành bản nháp.', 'extend-site'),
                    'message' => sprintf(
                        __('Tổng %d chương của truyện đang được clone ngầm. Nếu chưa thấy đủ chương trong truyện mới, hãy đợi job chạy tiếp hoặc mở trang theo dõi tiến trình.', 'extend-site'),
                        $chapter_total
                    ),
                    'action_url' => admin_url('admin.php?page=extend-site-tools'),
                    'action_label' => __('Mở trang theo dõi tiến trình', 'extend-site'),
                ];
            }

            return [
                'type' => 'success',
                'message' => __('Đã nhân bản truyện thành bản nháp. Truyện gốc không có chương để clone.', 'extend-site'),
            ];
        }

        if ($type === 'error') {
            return [
                'type' => 'error',
                'message' => sanitize_text_field((string) wp_unslash($_GET['es_clone_message'] ?? __('Nhân bản thất bại.', 'extend-site'))),
            ];
        }

        return [];
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
    private static function redirect_with_notice(string $type, string $message): void
    {
        wp_safe_redirect(add_query_arg([
            'post_type' => StoryPostType::SLUG,
            'es_clone_notice' => sanitize_key($type),
            'es_clone_message' => rawurlencode($message),
        ], admin_url('edit.php')));
        exit;
    }
}
