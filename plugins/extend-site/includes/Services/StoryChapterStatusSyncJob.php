<?php

namespace ExtendSite\Services;

use ExtendSite\DB\LatestChapterTable;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

class StoryChapterStatusSyncJob
{
    public const JOB_TYPE = 'sync_story_chapter_status';
    private const BATCH_SIZE = 200;

    public static function init(): void
    {
        add_action('extend_site_system_job_process_' . self::JOB_TYPE, [__CLASS__, 'process_job'], 10, 2);
    }

    /**
     * @return string|WP_Error
     */
    public static function create(int $story_id, string $status_mode)
    {
        $story = get_post($story_id);
        if (!$story instanceof WP_Post || $story->post_type !== StoryPostType::SLUG) {
            return new WP_Error('invalid_story', __('Truyện không hợp lệ.', 'extend-site'));
        }

        $target_status = self::resolve_target_status($story, $status_mode);
        if ($target_status === '') {
            return new WP_Error('invalid_status', __('Trạng thái đồng bộ không hợp lệ.', 'extend-site'));
        }

        return SystemJobQueue::create_job(self::JOB_TYPE, [
            'story_id' => $story_id,
            'story_title' => get_the_title($story_id),
            'target_status' => $target_status,
        ], self::count_chapters($story_id));
    }

    public static function process_job(string $job_id, array $job): void
    {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $story_id = absint($payload['story_id'] ?? 0);
        $story_title = sanitize_text_field((string) ($payload['story_title'] ?? ''));
        $target_status = sanitize_key((string) ($payload['target_status'] ?? ''));
        $last_item_id = absint($job['last_item_id'] ?? 0);
        $processed = absint($job['processed'] ?? 0);

        if ($story_id <= 0 || !in_array($target_status, ['publish', 'draft'], true)) {
            SystemJobQueue::update_job($job_id, [
                'status' => 'failed',
                'message' => __('Dữ liệu job đồng bộ trạng thái không hợp lệ.', 'extend-site'),
            ]);
            return;
        }

        $chapter_ids = self::get_chapter_batch($story_id, $last_item_id);
        if ($chapter_ids) {
            self::update_chapter_statuses($chapter_ids, $target_status);
            $last_item_id = max($chapter_ids);
            $processed += count($chapter_ids);
        }

        $updates = [
            'last_item_id' => $last_item_id,
            'processed' => $processed,
            'message' => sprintf(
                __('Đã đồng bộ %1$d chương của truyện %2$s sang trạng thái %3$s.', 'extend-site'),
                $processed,
                $story_title !== '' ? $story_title : ('#' . $story_id),
                self::status_label($target_status)
            ),
        ];

        if (count($chapter_ids) >= self::BATCH_SIZE) {
            $updates['status'] = 'pending';
            SystemJobQueue::update_job($job_id, $updates);
            SystemJobQueue::schedule_job($job_id);
            return;
        }

        ChapterRepository::sync_count_for_story($story_id);
        LatestChapterTable::resync_story($story_id);

        $updates['status'] = 'done';
        $updates['message'] = sprintf(
            __('Đồng bộ hoàn tất cho truyện %1$s. Đã xử lý %2$d chương.', 'extend-site'),
            $story_title !== '' ? $story_title : ('#' . $story_id),
            $processed
        );
        SystemJobQueue::update_job($job_id, $updates);
    }

    private static function resolve_target_status(WP_Post $story, string $status_mode): string
    {
        $status_mode = sanitize_key($status_mode);

        if ($status_mode === 'publish' || $status_mode === 'draft') {
            return $status_mode;
        }

        if ($status_mode === 'story') {
            return $story->post_status === 'publish' ? 'publish' : 'draft';
        }

        return '';
    }

    private static function count_chapters(int $story_id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status IN ('publish', 'draft', 'pending', 'private', 'future')
              AND pm.meta_key = %s
              AND pm.meta_value = %s
            ",
            ChapterPostType::SLUG,
            ChapterPostType::META_STORY_ID,
            (string) $story_id
        ));
    }

    private static function get_chapter_batch(int $story_id, int $last_item_id): array
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.ID > %d
              AND p.post_type = %s
              AND p.post_status IN ('publish', 'draft', 'pending', 'private', 'future')
              AND pm.meta_key = %s
              AND pm.meta_value = %s
            ORDER BY p.ID ASC
            LIMIT %d
            ",
            $last_item_id,
            ChapterPostType::SLUG,
            ChapterPostType::META_STORY_ID,
            (string) $story_id,
            self::BATCH_SIZE
        ));

        return array_map('intval', $ids ?: []);
    }

    private static function update_chapter_statuses(array $chapter_ids, string $target_status): void
    {
        global $wpdb;

        $chapter_ids = array_values(array_unique(array_filter(array_map('absint', $chapter_ids))));
        if (!$chapter_ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($chapter_ids), '%d'));
        $params = array_merge([
            $target_status,
            current_time('mysql'),
            current_time('mysql', true),
        ], $chapter_ids, [
            ChapterPostType::SLUG,
        ]);

        $wpdb->query($wpdb->prepare(
            "
            UPDATE {$wpdb->posts}
            SET post_status = %s,
                post_modified = %s,
                post_modified_gmt = %s
            WHERE ID IN ($placeholders)
              AND post_type = %s
            ",
            ...$params
        ));

        foreach ($chapter_ids as $chapter_id) {
            clean_post_cache($chapter_id);
        }
    }

    private static function status_label(string $status): string
    {
        return $status === 'publish'
            ? __('xuất bản', 'extend-site')
            : __('bản nháp', 'extend-site');
    }
}
