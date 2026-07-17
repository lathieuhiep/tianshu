<?php

namespace ExtendSite\Services;

use ExtendSite\DB\LatestChapterTable;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;
use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

class StoryCloneService
{
    public const JOB_TYPE_CLONE_CHAPTERS = 'clone_story_chapters';
    private const BATCH_SIZE = 100;

    private const EXCLUDED_META_KEYS = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
    ];

    public static function init(): void
    {
        add_action(
            'extend_site_system_job_process_' . self::JOB_TYPE_CLONE_CHAPTERS,
            [__CLASS__, 'process_clone_chapters_job'],
            10,
            2
        );
    }

    /**
     * @return array{story_id:int,job_id:string,chapter_total:int}|WP_Error
     */
    public static function clone_story(int $source_story_id, int $user_id = 0)
    {
        $source = get_post($source_story_id);
        if (!$source instanceof WP_Post || $source->post_type !== StoryPostType::SLUG) {
            return new WP_Error('invalid_story', __('Truyện không hợp lệ.', 'extend-site'));
        }

        $new_story_id = wp_insert_post(self::build_post_data($source, StoryPostType::SLUG, 'draft', $user_id), true);
        if (is_wp_error($new_story_id)) {
            return $new_story_id;
        }

        self::copy_post_meta($source->ID, (int) $new_story_id, [
            StoryPostType::META_STORY_VIEWS,
            StoryPostType::META_CHAPTER_COUNT,
        ]);
        self::copy_taxonomies($source->ID, (int) $new_story_id, StoryPostType::SLUG);

        update_post_meta((int) $new_story_id, StoryPostType::META_STORY_VIEWS, 0);
        update_post_meta((int) $new_story_id, StoryPostType::META_CHAPTER_COUNT, 0);

        $chapter_total = self::count_source_chapters($source->ID);
        $job_id = '';
        if ($chapter_total > 0) {
            $job_id = SystemJobQueue::create_job(self::JOB_TYPE_CLONE_CHAPTERS, [
                'source_story_id' => $source->ID,
                'target_story_id' => (int) $new_story_id,
                'target_status' => 'draft',
            ], $chapter_total);
        }

        return [
            'story_id' => (int) $new_story_id,
            'job_id' => $job_id,
            'chapter_total' => $chapter_total,
        ];
    }

    public static function process_clone_chapters_job(string $job_id, array $job): void
    {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $source_story_id = absint($payload['source_story_id'] ?? 0);
        $target_story_id = absint($payload['target_story_id'] ?? 0);
        $target_status = sanitize_key((string) ($payload['target_status'] ?? 'draft'));
        $last_item_id = absint($job['last_item_id'] ?? 0);
        $processed = absint($job['processed'] ?? 0);

        if ($source_story_id <= 0 || $target_story_id <= 0 || !in_array($target_status, ['publish', 'draft'], true)) {
            SystemJobQueue::update_job($job_id, [
                'status' => 'failed',
                'message' => __('Dữ liệu job clone không hợp lệ.', 'extend-site'),
            ]);
            return;
        }

        $chapter_ids = self::get_source_chapter_batch($source_story_id, $last_item_id);

        foreach ($chapter_ids as $source_chapter_id) {
            $source_chapter_id = (int) $source_chapter_id;
            $new_chapter_id = self::clone_chapter($source_chapter_id, $target_story_id, $target_status);
            $last_item_id = $source_chapter_id;

            if (!is_wp_error($new_chapter_id)) {
                $processed++;
            }
        }

        $updates = [
            'last_item_id' => $last_item_id,
            'processed' => $processed,
            'message' => sprintf(__('Đã clone %d chương.', 'extend-site'), $processed),
        ];

        if (count($chapter_ids) >= self::BATCH_SIZE) {
            $updates['status'] = 'pending';
            SystemJobQueue::update_job($job_id, $updates);
            SystemJobQueue::schedule_job($job_id);
            return;
        }

        ChapterRepository::sync_count_for_story($target_story_id);
        LatestChapterTable::resync_story($target_story_id);

        $updates['status'] = 'done';
        $updates['message'] = sprintf(__('Clone hoàn tất. Đã clone %d chương.', 'extend-site'), $processed);
        SystemJobQueue::update_job($job_id, $updates);
    }

    private static function build_post_data(WP_Post $source, string $post_type, string $status, int $user_id = 0): array
    {
        return [
            'post_author' => $user_id > 0 ? $user_id : (int) $source->post_author,
            'post_content' => $source->post_content,
            'post_title' => $post_type === StoryPostType::SLUG ? $source->post_title . ' (Bản sao)' : $source->post_title,
            'post_excerpt' => $source->post_excerpt,
            'post_status' => $status,
            'post_type' => $post_type,
            'comment_status' => $source->comment_status,
            'ping_status' => $source->ping_status,
            'post_password' => $source->post_password,
            'menu_order' => (int) $source->menu_order,
        ];
    }

    private static function clone_chapter(int $source_chapter_id, int $target_story_id, string $target_status)
    {
        $source = get_post($source_chapter_id);
        if (!$source instanceof WP_Post || $source->post_type !== ChapterPostType::SLUG) {
            return new WP_Error('invalid_chapter', __('Chương không hợp lệ.', 'extend-site'));
        }

        $new_chapter_id = wp_insert_post(self::build_post_data($source, ChapterPostType::SLUG, $target_status), true);
        if (is_wp_error($new_chapter_id)) {
            return $new_chapter_id;
        }

        self::copy_post_meta($source->ID, (int) $new_chapter_id, [
            ChapterPostType::META_STORY_ID,
            ChapterPostType::META_CHAPTER_VIEWS,
        ]);

        update_post_meta((int) $new_chapter_id, ChapterPostType::META_STORY_ID, $target_story_id);
        update_post_meta((int) $new_chapter_id, ChapterPostType::META_CHAPTER_VIEWS, 0);

        return (int) $new_chapter_id;
    }

    private static function copy_post_meta(int $source_id, int $target_id, array $extra_excluded_keys = []): void
    {
        $excluded = array_merge(self::EXCLUDED_META_KEYS, $extra_excluded_keys);
        foreach (get_post_meta($source_id) as $key => $values) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            delete_post_meta($target_id, $key);
            foreach ((array) $values as $value) {
                add_post_meta($target_id, $key, maybe_unserialize($value));
            }
        }
    }

    private static function copy_taxonomies(int $source_id, int $target_id, string $post_type): void
    {
        foreach (get_object_taxonomies($post_type) as $taxonomy) {
            $term_ids = wp_get_object_terms($source_id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($term_ids)) {
                continue;
            }

            wp_set_object_terms($target_id, array_map('intval', $term_ids), $taxonomy, false);
        }
    }

    private static function count_source_chapters(int $story_id): int
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

    private static function get_source_chapter_batch(int $story_id, int $last_item_id): array
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
}
