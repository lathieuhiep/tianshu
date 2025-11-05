<?php
namespace ExtendSite\DB;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * Manage the lightweight table storing latest chapter per story.
 *
 * Each story_id has exactly one row.
 * Used to query "recently updated stories" extremely fast.
 */
class LatestChapterTable {

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

        $sql = "CREATE TABLE IF NOT EXISTS $table (
              story_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
              chapter_id BIGINT UNSIGNED NOT NULL,
              updated_at DATETIME NOT NULL,
              KEY updated_at (updated_at)
            ) ENGINE=InnoDB $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
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
     * Register all hooks.
     */
    public static function register_hooks(): void {
        add_action('save_post_chapter', [self::class, 'update_on_save_post'], 10, 2);

        add_action('transition_post_status', function ($new, $old, $post) {
            if ($post->post_type === 'chapter' && $new === 'publish') {
                self::update_on_save_post($post->ID, $post);
            }
        }, 10, 3);

        add_action('before_delete_post', [self::class, 'cleanup_on_delete']);
        add_action('updated_post_meta', [self::class, 'fix_on_meta_change'], 10, 4);
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