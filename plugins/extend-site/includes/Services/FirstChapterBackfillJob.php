<?php

namespace ExtendSite\Services;

use ExtendSite\DB\LatestChapterTable;

defined('ABSPATH') || exit;

/** Dong bo first_chapter_id cho du lieu co san theo tung lo nho. */
class FirstChapterBackfillJob
{
    public const JOB_TYPE = 'backfill_first_chapter';
    public const REQUIRED_OPTION = 'extend_site_first_chapter_backfill_required';
    private const BATCH_SIZE = 100;

    public static function init(): void
    {
        add_action('extend_site_system_job_process_' . self::JOB_TYPE, [__CLASS__, 'process_job'], 10, 2);
        add_action('admin_notices', [__CLASS__, 'render_admin_notice']);
    }

    public static function create(): string
    {
        foreach (SystemJobQueue::get_jobs(100) as $job) {
            if (
                ($job['type'] ?? '') === self::JOB_TYPE
                && in_array(($job['status'] ?? ''), ['pending', 'running'], true)
            ) {
                return (string) $job['id'];
            }
        }

        $total = LatestChapterTable::count_missing_first_chapters();
        if ($total <= 0) {
            delete_option(self::REQUIRED_OPTION);
        }

        return SystemJobQueue::create_job(self::JOB_TYPE, [], $total);
    }

    public static function process_job(string $job_id, array $job): void
    {
        $processed = absint($job['processed'] ?? 0);
        $story_ids = LatestChapterTable::get_missing_first_story_ids(self::BATCH_SIZE);

        foreach ($story_ids as $story_id) {
            LatestChapterTable::resync_story($story_id);
        }
        $processed += count($story_ids);

        if (count($story_ids) >= self::BATCH_SIZE) {
            SystemJobQueue::update_job($job_id, [
                'status' => 'pending',
                'processed' => $processed,
                'message' => sprintf(__('Da dong bo %d truyen.', 'extend-site'), $processed),
            ]);
            SystemJobQueue::schedule_job($job_id);
            return;
        }

        delete_option(self::REQUIRED_OPTION);
        SystemJobQueue::update_job($job_id, [
            'status' => 'done',
            'processed' => $processed,
            'message' => sprintf(__('Hoan tat dong bo chuong dau cho %d truyen.', 'extend-site'), $processed),
        ]);
    }

    public static function render_admin_notice(): void
    {
        if (!current_user_can('manage_options') || !get_option(self::REQUIRED_OPTION)) {
            return;
        }

        $url = admin_url('admin.php?page=extend-site-tools#es-first-chapter-sync');
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Bang chuong dau can duoc dong bo cho du lieu cu. ', 'extend-site');
        echo '<a href="' . esc_url($url) . '">' . esc_html__('Mo cong cu dong bo', 'extend-site') . '</a>';
        echo '</p></div>';
    }
}
