<?php
namespace ExtendSite\Repositories;

use ExtendSite\DB\ViewsStoryDailyTable;

defined('ABSPATH') || exit;

/**
 * Repository for querying top viewed stories by date range.
 */
class StoryRankingRepository {

    /**
     * Get top stories by date range.
     *
     * @param string $from_date Start date (Y-m-d).
     * @param string|null $to_date End date (Y-m-d), defaults to today.
     * @param int $limit Max number of stories to return.
     * @return array<int,array{story_id:int,total_views:int}>
     */
    public static function get_top_by_range(string $from_date, ?string $to_date = null, int $limit = 10): array {
        global $wpdb;

        $table = ViewsStoryDailyTable::get_table_name();
        $to_date = $to_date ?: es_get_today();

        $query = $wpdb->prepare("
            SELECT story_id, SUM(views) AS total_views
            FROM {$table}
            WHERE view_date BETWEEN %s AND %s
            GROUP BY story_id
            ORDER BY total_views DESC
            LIMIT %d
        ", $from_date, $to_date, $limit);

        $results = $wpdb->get_results($query, ARRAY_A);

        return array_map(static function ($row) {
            return [
                'story_id'     => (int) $row['story_id'],
                'total_views'  => (int) $row['total_views'],
            ];
        }, $results);
    }

    /**
     * Top stories in the last 7 days.
     */
    public static function top_7_days(int $limit = 10): array {
        $to = es_get_today();
        $from = date('Y-m-d', strtotime("$to -7 days"));
        return self::get_top_by_range($from, $to, $limit);
    }

    /**
     * Top stories in the last 30 days.
     */
    public static function top_30_days(int $limit = 10): array {
        $to = es_get_today();
        $from = date('Y-m-d', strtotime("$to -30 days"));
        return self::get_top_by_range($from, $to, $limit);
    }

    /**
     * Top stories in the last 365 days.
     */
    public static function top_365_days(int $limit = 10): array {
        $to = es_get_today();
        $from = date('Y-m-d', strtotime("$to -365 days"));
        return self::get_top_by_range($from, $to, $limit);
    }

    /**
     * Top stories today.
     */
    public static function top_today(int $limit = 10): array {
        $today = es_get_today();
        return self::get_top_by_range($today, $today, $limit);
    }
}