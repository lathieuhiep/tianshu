<?php
namespace ExtendSite\DB;

use ExtendSite\PostType\ChapterPostType;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Manage the lightweight table storing latest chapter per story.
 *
 * Each story_id has exactly one row.
 * Used to query "recently updated stories" extremely fast.
 */
class LatestChapterTable {

    /** @var array<int,bool> Story can dong bo lai o cuoi request. */
    private static array $pending_resync = [];

    /** @var array<int,int> Story cu truoc khi doi lien ket cua chapter. */
    private static array $story_ids_before_meta_update = [];

    /**
     * Get table name with prefix.
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'es_story_latest_chapter';
    }

    /**
     * Create DB table if not exists.
     */
    public static function create(): void {
        global $wpdb;
        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        // dbDelta can add new columns only when using its canonical CREATE TABLE syntax.
        $sql = "CREATE TABLE $table (
              story_id BIGINT UNSIGNED NOT NULL,
              first_chapter_id BIGINT UNSIGNED NULL,
              chapter_id BIGINT UNSIGNED NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY  (story_id),
              KEY first_chapter_id (first_chapter_id),
              KEY chapter_id (chapter_id),
              KEY updated_at (updated_at)
            ) ENGINE=InnoDB $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function has_first_chapter_column(): bool {
        global $wpdb;

        return (bool) $wpdb->get_var(
            "SHOW COLUMNS FROM " . self::get_table_name() . " LIKE 'first_chapter_id'"
        );
    }

    /**
     * Update or insert latest chapter record when chapter saved.
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public static function update_on_save_post(int $post_id, WP_Post $post): void {
        // Only handle published chapters
        if ($post->post_type !== 'chapter' || $post->post_status !== 'publish') {
            return;
        }

        $story_id = absint(get_post_meta($post_id, '_chapter_story_id', true));
        if (!$story_id) return;

        global $wpdb;
        $table = self::get_table_name();

        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT chapter_id, updated_at FROM $table WHERE story_id = %d",
            $story_id
        ));

        // Use modified date if available
        $post_date     = $post->post_date ?: current_time('mysql');
        $modified_date = $post->post_modified ?: $post_date;

        $current_time  = strtotime($current->updated_at ?? '1970-01-01');
        $modified_time = strtotime($modified_date);
        $post_time     = strtotime($post_date);

        $should_update = false;

        if (!$current) {
            $should_update = true; // First record
        } elseif ($post_id === (int) $current->chapter_id) {
            // Same chapter: update only if modified much later (>1h)
            if ($modified_time - $current_time > HOUR_IN_SECONDS) {
                $should_update = true;
            }
        } elseif ($post_time > $current_time) {
            // Newer chapter
            $should_update = true;
        }

        if (!$should_update) return;

        // Transaction-safe replace
        try {
            $wpdb->query('START TRANSACTION');
            $wpdb->replace(
                $table,
                [
                    'story_id'   => $story_id,
                    'chapter_id' => $post_id,
                    'updated_at' => $modified_date,
                ],
                ['%d', '%d', '%s']
            );
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            // Fallback replace if transaction unsupported
            $wpdb->replace(
                $table,
                [
                    'story_id'   => $story_id,
                    'chapter_id' => $post_id,
                    'updated_at' => $modified_date,
                ],
                ['%d', '%d', '%s']
            );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[LatestChapter] Updated story=%d -> chapter=%d (updated_at=%s)',
                $story_id,
                $post_id,
                $modified_date
            ));
        }
    }

    /**
     * Remove record when story or its latest chapter is deleted.
     */
    public static function cleanup_on_delete(int $post_id): void {
        global $wpdb;
        $table = self::get_table_name();
        $type  = get_post_type($post_id);

        if ($type === 'story') {
            $wpdb->delete($table, ['story_id' => $post_id]);
            return;
        }

        if ($type === 'chapter') {
            $story_id = absint(get_post_meta($post_id, '_chapter_story_id', true));
            if (!$story_id) return;

            $latest_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT chapter_id FROM $table WHERE story_id=%d",
                $story_id
            ));

            // If deleting the latest chapter → find previous one
            if ($latest_id === $post_id) {
                $row = $wpdb->get_row($wpdb->prepare("
                    SELECT p.ID, p.post_date
                    FROM {$wpdb->posts} AS p
                    JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
                    WHERE p.post_type='chapter'
                      AND p.post_status='publish'
                      AND pm.meta_key='_chapter_story_id'
                      AND pm.meta_value=%d
                    ORDER BY p.post_date DESC, p.ID DESC
                    LIMIT 1
                ", $story_id));

                if ($row) {
                    $wpdb->replace(
                        $table,
                        [
                            'story_id'   => $story_id,
                            'chapter_id' => $row->ID,
                            'updated_at' => $row->post_date,
                        ],
                        ['%d', '%d', '%s']
                    );
                } else {
                    $wpdb->delete($table, ['story_id' => $story_id]);
                }
            }
        }
    }

    /**
     * Handle when chapter changes story (meta _chapter_story_id updated)
     */
    public static function fix_on_meta_change(int $meta_id, int $post_id, string $meta_key, $meta_value): void {
        if ($meta_key !== '_chapter_story_id') return;
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::get_table_name() . " WHERE chapter_id=%d",
            $post_id
        ));
    }

    /**
     * Rebuild the whole table (for seeding/import fix).
     */
    public static function resync_all(): void {
        global $wpdb;
        $table = self::get_table_name();

        $wpdb->query("
            REPLACE INTO $table (story_id, chapter_id, updated_at)
            SELECT pm.meta_value AS story_id,
                   MAX(p.ID) AS chapter_id,
                   MAX(p.post_date) AS updated_at
            FROM {$wpdb->posts} AS p
            JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
            WHERE p.post_type='chapter'
              AND p.post_status='publish'
              AND pm.meta_key='_chapter_story_id'
            GROUP BY pm.meta_value
        ");

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[LatestChapter] Resynced all latest chapters');
        }
    }

    /**
     * Rebuild latest chapter data for one story after imports or crawler batches.
     *
     * The frontend displays the highest published chapter number for each story,
     * so this keeps the table aligned with the chapter relationship meta model.
     */
    public static function resync_story(int $story_id): ?array {
        global $wpdb;

        if ($story_id <= 0) {
            return null;
        }

        // Neu crawler/job da dong bo truc tiep thi khong lap lai luc shutdown.
        unset(self::$pending_resync[$story_id]);

        $table = self::get_table_name();
        $base_sql = "
            SELECT p.ID AS chapter_id, p.post_date AS updated_at
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} story_meta
                ON story_meta.post_id = p.ID
               AND story_meta.meta_key = %s
               AND story_meta.meta_value = %s
            INNER JOIN {$wpdb->postmeta} number_meta
                ON number_meta.post_id = p.ID
               AND number_meta.meta_key = %s
            WHERE p.post_type = %s
              AND p.post_status = 'publish'
        ";
        $params = [
            ChapterPostType::META_STORY_ID,
            (string) $story_id,
            ChapterPostType::META_NUMBER,
            ChapterPostType::SLUG,
        ];
        $first_row = $wpdb->get_row($wpdb->prepare(
            $base_sql . ' ORDER BY CAST(number_meta.meta_value AS UNSIGNED) ASC, p.ID ASC LIMIT 1',
            ...$params
        ), ARRAY_A);
        $row = $wpdb->get_row($wpdb->prepare(
            $base_sql . ' ORDER BY CAST(number_meta.meta_value AS UNSIGNED) DESC, p.ID DESC LIMIT 1',
            ...$params
        ), ARRAY_A);

        if (!$row || !$first_row) {
            $wpdb->delete($table, ['story_id' => $story_id], ['%d']);

            return null;
        }

        $first_chapter_id = (int) $first_row['chapter_id'];
        $chapter_id = (int) $row['chapter_id'];
        $updated_at = (string) ($row['updated_at'] ?: current_time('mysql'));

        $wpdb->replace(
            $table,
            [
                'story_id' => $story_id,
                'first_chapter_id' => $first_chapter_id,
                'chapter_id' => $chapter_id,
                'updated_at' => $updated_at,
            ],
            ['%d', '%d', '%d', '%s']
        );

        return [
            'story_id' => $story_id,
            'first_chapter_id' => $first_chapter_id,
            'chapter_id' => $chapter_id,
            'updated_at' => $updated_at,
        ];
    }

    /**
     * Lay chuong dau tu bang toi uu. Neu dong cu chua co du lieu thi tu dong
     * dong bo mot lan, cac request sau khong can query lai bang postmeta.
     */
    public static function get_first_chapter(int $story_id): ?array {
        global $wpdb;

        if ($story_id <= 0) {
            return null;
        }

        $chapter_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT first_chapter_id FROM ' . self::get_table_name() . ' WHERE story_id = %d',
            $story_id
        ));

        if ($chapter_id <= 0) {
            $edge = self::resync_story($story_id);
            $chapter_id = (int) ($edge['first_chapter_id'] ?? 0);
        }

        if ($chapter_id <= 0) {
            return null;
        }

        _prime_post_caches([$chapter_id], false, true);
        $chapter = get_post($chapter_id);
        if (!$chapter || $chapter->post_type !== ChapterPostType::SLUG || $chapter->post_status !== 'publish') {
            return null;
        }

        return [
            'id' => $chapter_id,
            'url' => get_permalink($chapter_id),
            'title' => get_the_title($chapter_id),
            'number' => (int) get_post_meta($chapter_id, ChapterPostType::META_NUMBER, true),
        ];
    }

    /** Dem so truyen co chuong publish nhung chua co first_chapter_id. */
    public static function count_missing_first_chapters(): int {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT CAST(sm.meta_value AS UNSIGNED))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = %s
             LEFT JOIN " . self::get_table_name() . " edge ON edge.story_id = CAST(sm.meta_value AS UNSIGNED)
             WHERE p.post_type = %s AND p.post_status = 'publish'
               AND (edge.story_id IS NULL OR edge.first_chapter_id IS NULL OR edge.first_chapter_id = 0)",
            ChapterPostType::META_STORY_ID,
            ChapterPostType::SLUG
        ));
    }

    /** Lay mot lo story con thieu de job xu ly. */
    public static function get_missing_first_story_ids(int $limit = 100): array {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT CAST(sm.meta_value AS UNSIGNED) AS story_id
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = %s
             LEFT JOIN " . self::get_table_name() . " edge ON edge.story_id = CAST(sm.meta_value AS UNSIGNED)
             WHERE p.post_type = %s AND p.post_status = 'publish'
               AND (edge.story_id IS NULL OR edge.first_chapter_id IS NULL OR edge.first_chapter_id = 0)
             ORDER BY story_id ASC LIMIT %d",
            ChapterPostType::META_STORY_ID,
            ChapterPostType::SLUG,
            max(1, $limit)
        ));

        return array_values(array_filter(array_map('absint', $ids ?: [])));
    }

    /** Danh dau story de gom nhieu thay doi chuong thanh mot lan dong bo. */
    public static function queue_resync(int $story_id): void {
        if ($story_id > 0) {
            self::$pending_resync[$story_id] = true;
        }
    }

    public static function queue_resync_on_save(int $post_id, WP_Post $post): void {
        if ($post->post_type === ChapterPostType::SLUG) {
            self::queue_resync((int) get_post_meta($post_id, ChapterPostType::META_STORY_ID, true));
        }
    }

    public static function queue_resync_on_delete(int $post_id): void {
        if (get_post_type($post_id) === ChapterPostType::SLUG) {
            self::queue_resync((int) get_post_meta($post_id, ChapterPostType::META_STORY_ID, true));
        }
    }

    public static function queue_resync_on_meta_change($meta_id, int $post_id, string $meta_key, $meta_value): void {
        if (!in_array($meta_key, [ChapterPostType::META_STORY_ID, ChapterPostType::META_NUMBER], true)) {
            return;
        }

        self::queue_resync((int) get_post_meta($post_id, ChapterPostType::META_STORY_ID, true));
        if ($meta_key === ChapterPostType::META_STORY_ID) {
            self::queue_resync(self::$story_ids_before_meta_update[$post_id] ?? 0);
            unset(self::$story_ids_before_meta_update[$post_id]);
            self::queue_resync((int) $meta_value);
        }
    }

    public static function capture_story_before_meta_update($check, int $post_id, string $meta_key, $meta_value, $prev_value) {
        if ($meta_key === ChapterPostType::META_STORY_ID && get_post_type($post_id) === ChapterPostType::SLUG) {
            self::$story_ids_before_meta_update[$post_id] = (int) get_post_meta(
                $post_id,
                ChapterPostType::META_STORY_ID,
                true
            );
        }

        return $check;
    }

    public static function flush_pending_resync(): void {
        $story_ids = array_keys(self::$pending_resync);
        self::$pending_resync = [];
        foreach ($story_ids as $story_id) {
            self::resync_story((int) $story_id);
        }
    }

    /**
     * Get latest stories list.
     *
     * @param int $limit
     * @return array {story_id, chapter_id, updated_at}
     */
    public static function get_latest_stories(int $limit = 10): array {
        global $wpdb;
        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare("
                SELECT story_id, chapter_id, updated_at
                FROM $table
                ORDER BY updated_at DESC
                LIMIT %d
            ", $limit),
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Get latest chapters for multiple stories.
     *
     * @param array<int> $story_ids Story IDs.
     * @return array<int,array{id:int,url:string,title:string,number:int}>
     */
    public static function get_latest_chapters_by_story_ids(array $story_ids): array {
        global $wpdb;

        $story_ids = array_values(array_unique(array_filter(array_map('absint', $story_ids))));
        if (!$story_ids) {
            return [];
        }

        $table = self::get_table_name();
        $placeholders = implode(',', array_fill(0, count($story_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT story_id, chapter_id
                FROM $table
                WHERE story_id IN ($placeholders)
                ",
                ...$story_ids
            ),
            ARRAY_A
        );

        if (!$rows) {
            return [];
        }

        $chapter_ids = array_values(array_unique(array_map('absint', wp_list_pluck($rows, 'chapter_id'))));
        if ($chapter_ids) {
            $posts = get_posts([
                'post_type' => ChapterPostType::SLUG,
                'post__in' => $chapter_ids,
                'posts_per_page' => count($chapter_ids),
                'post_status' => 'publish',
                'fields' => 'all',
                'no_found_rows' => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]);
            update_post_cache($posts);
        }

        $chapters = [];
        foreach ($rows as $row) {
            $story_id = absint($row['story_id'] ?? 0);
            $chapter_id = absint($row['chapter_id'] ?? 0);
            $chapter = get_post($chapter_id);

            if ($story_id <= 0 || !$chapter || $chapter->post_type !== ChapterPostType::SLUG || $chapter->post_status !== 'publish') {
                continue;
            }

            $chapters[$story_id] = [
                'id' => $chapter_id,
                'url' => get_permalink($chapter_id),
                'title' => get_the_title($chapter_id),
                'number' => (int) get_post_meta($chapter_id, ChapterPostType::META_NUMBER, true),
            ];
        }

        return $chapters;
    }

    /**
     * Lay latest tu bang toi uu, fallback mot query cho cac story chua duoc dong bo.
     *
     * Fallback chi chay voi danh sach ID gioi han cua widget/request, khong query
     * tung story trong vong lap.
     */
    public static function get_latest_chapters_with_fallback(array $story_ids): array
    {
        global $wpdb;

        $story_ids = array_values(array_unique(array_filter(array_map('absint', $story_ids))));
        $chapters = self::get_latest_chapters_by_story_ids($story_ids);
        $missing = array_values(array_diff($story_ids, array_keys($chapters)));
        if (!$missing) {
            return $chapters;
        }

        $placeholders = implode(',', array_fill(0, count($missing), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.ID AS story_id, (
                SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} sm
                    ON sm.post_id=p.ID AND sm.meta_key=%s
                INNER JOIN {$wpdb->postmeta} nm
                    ON nm.post_id=p.ID AND nm.meta_key=%s
                WHERE p.post_type=%s AND p.post_status='publish'
                  AND sm.meta_value=CAST(s.ID AS CHAR)
                ORDER BY CAST(nm.meta_value AS UNSIGNED) DESC, p.ID DESC
                LIMIT 1
             ) AS chapter_id
             FROM {$wpdb->posts} s WHERE s.ID IN ({$placeholders})",
            ChapterPostType::META_STORY_ID,
            ChapterPostType::META_NUMBER,
            ChapterPostType::SLUG,
            ...$missing
        ), ARRAY_A);

        $chapter_ids = array_values(array_filter(array_map('absint', wp_list_pluck($rows ?: [], 'chapter_id'))));
        if ($chapter_ids) {
            _prime_post_caches($chapter_ids, false, true);
        }
        foreach ($rows ?: [] as $row) {
            $story_id = absint($row['story_id'] ?? 0);
            $chapter_id = absint($row['chapter_id'] ?? 0);
            if (!$story_id || !$chapter_id) {
                continue;
            }
            $chapters[$story_id] = [
                'id' => $chapter_id,
                'url' => get_permalink($chapter_id),
                'title' => get_the_title($chapter_id),
                'number' => (int) get_post_meta($chapter_id, ChapterPostType::META_NUMBER, true),
            ];
        }
        return $chapters;
    }

    /**
     * Register all hooks.
     */
    public static function register_hooks(): void {
        add_action('save_post_chapter', [self::class, 'queue_resync_on_save'], 20, 2);
        add_filter('update_post_metadata', [self::class, 'capture_story_before_meta_update'], 20, 5);
        add_action('before_delete_post', [self::class, 'queue_resync_on_delete']);
        add_action('updated_post_meta', [self::class, 'queue_resync_on_meta_change'], 20, 4);
        add_action('added_post_meta', [self::class, 'queue_resync_on_meta_change'], 20, 4);
        add_action('deleted_post_meta', [self::class, 'queue_resync_on_meta_change'], 20, 4);
        add_action('shutdown', [self::class, 'flush_pending_resync'], 5);
    }

    /**
     * Get paginated latest stories (for AJAX load-more).
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_latest_stories_paginated(int $limit = 12, int $offset = 0): array {
        global $wpdb;
        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare("
            SELECT story_id
            FROM $table
            ORDER BY updated_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset),
            ARRAY_A
        );

        return $rows ?: [];
    }
}
