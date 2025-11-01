<?php

namespace ExtendSite\DB;

defined('ABSPATH') || exit;

class ViewsStoryDailyTable {

    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'es_views_story_daily';
    }

    public static function create(): void {
        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            story_id BIGINT UNSIGNED NOT NULL,
            view_date DATE NOT NULL,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY story_day (story_id, view_date),
            KEY idx_story (story_id),
            KEY idx_view_date (view_date)
        ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Increment daily views for a story.
     *
     * @param int $story_id Story ID.
     * @param int $increment Amount to increase (default = 1).
     * @return void
     */
    public static function increment(int $story_id, int $increment = 1): void {
        global $wpdb;

        if ($story_id <= 0) {
            return;
        }

        $table = self::get_table_name();
        $today = es_get_today();

        $sql = $wpdb->prepare("
            INSERT INTO {$table} (story_id, view_date, views)
            VALUES (%d, %s, %d)
            ON DUPLICATE KEY UPDATE views = views + VALUES(views)
        ", $story_id, $today, $increment);

        $wpdb->query($sql);
    }
}