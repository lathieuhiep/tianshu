<?php

namespace ExtendSite\Admin;

use ExtendSite\Services\StoryChapterStatusSyncJob;
use ExtendSite\Services\SystemJobQueue;

defined('ABSPATH') || exit;

class SystemJobAjax
{
    public const ACTION_STATUS = 'es_system_jobs_status';
    public const ACTION_CREATE_STATUS_SYNC = 'es_create_status_sync_job';
    public const NONCE_ACTION = 'es_system_jobs_status';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::ACTION_STATUS, [__CLASS__, 'status']);
        add_action('wp_ajax_' . self::ACTION_CREATE_STATUS_SYNC, [__CLASS__, 'create_status_sync']);
    }

    public static function status(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Bạn không có quyền xem tiến trình job.', 'extend-site')], 403);
        }

        wp_send_json_success([
            'jobs' => SystemJobQueue::get_formatted_jobs(),
            'has_active_jobs' => SystemJobQueue::has_active_jobs(),
        ]);
    }

    public static function create_status_sync(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Bạn không có quyền tạo job đồng bộ.', 'extend-site')], 403);
        }

        $story_id = absint($_POST['story_id'] ?? 0);
        $status_mode = sanitize_key((string) ($_POST['status_mode'] ?? 'story'));
        $result = StoryChapterStatusSyncJob::create($story_id, $status_mode);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        wp_send_json_success([
            'message' => sprintf(__('Đã tạo job đồng bộ trạng thái chương: %s', 'extend-site'), $result),
            'job_id' => $result,
            'jobs' => SystemJobQueue::get_formatted_jobs(),
            'has_active_jobs' => SystemJobQueue::has_active_jobs(),
        ]);
    }
}
