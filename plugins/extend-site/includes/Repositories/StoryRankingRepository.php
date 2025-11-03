<?php
namespace ExtendSite\Repositories;

use ExtendSite\DB\ViewsStoryDailyTable;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;

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

    /**
     * Get top authors by aggregating story views.
     *
     * @param array<int,array{story_id:int,total_views:int}> $story_records List of story records with views.
     * @param int $limit Max number of authors to return.
     * @return array<int,array{author_id:int,total_views:int}>
     */
    public static function top_authors_by_stories(array $story_records, int $limit = 10): array
    {
        $author_views = [];

        foreach ($story_records as $row) {
            $story_id = (int) $row['story_id'];
            $views    = (int) $row['total_views'];

            // Lấy danh sách tác giả của truyện này
            $author_ids = StoryRepository::get_author_ids($story_id);

            foreach ($author_ids as $aid) {
                $aid = (int) $aid;
                if ($aid <= 0) {
                    continue;
                }

                // Cộng dồn lượt xem
                if (!isset($author_views[$aid])) {
                    $author_views[$aid] = 0;
                }
                $author_views[$aid] += $views;
            }
        }

        // Sắp xếp giảm dần theo tổng view
        arsort($author_views);

        // Giới hạn top
        $top = array_slice($author_views, 0, $limit, true);

        // Chuẩn hóa lại mảng kết quả
        $results = [];
        foreach ($top as $author_id => $total_views) {
            $results[] = [
                'author_id'   => $author_id,
                'total_views' => $total_views,
            ];
        }

        return $results;
    }

    /**
     * Get ranking items (stories or authors) for a given period.
     *
     * @param string $type 'story' or 'author'.
     * @param string $period 'day', 'week', 'month', or 'year'.
     * @param int $limit Max number of items to return.
     * @return array<int,array{id:int,title:string,url:string,image:int,views:string}>
     */
    public static function get_ranking_items(string $type, string $period, int $limit = 10): array
    {
        $records = match ($period) {
            'week'  => self::top_7_days($limit),
            'month' => self::top_30_days($limit),
            'year'  => self::top_365_days($limit),
            default => self::top_today($limit),
        };

        if ($type === AuthorPostType::SLUG) {
            $records = self::top_authors_by_stories($records, $limit);
        }

        $items = [];

        foreach ($records as $row) {
            if ($type === StoryPostType::SLUG) {
                $story_id = (int) $row['story_id'];
                $views    = (int) $row['total_views'];
                $post = get_post($story_id);

                if (!$post instanceof \WP_Post || $post->post_type !== StoryPostType::SLUG || $post->post_status !== 'publish') {
                    continue;
                }

                $items[] = [
                    'id'     => $story_id,
                    'title'  => get_the_title($story_id),
                    'url'    => get_permalink($story_id),
                    'image'  => get_post_thumbnail_id($story_id),
                    'views'  => number_format_i18n($views),
                ];
            } else {
                $author_id = (int) $row['author_id'];
                $views     = (int) $row['total_views'];
                $post = get_post($author_id);

                if (!$post instanceof \WP_Post || $post->post_type !== AuthorPostType::SLUG || $post->post_status !== 'publish') {
                    continue;
                }

                $items[] = [
                    'id'     => $author_id,
                    'title'  => get_the_title($author_id),
                    'url'    => get_permalink($author_id),
                    'image'  => get_post_thumbnail_id($author_id),
                    'views'  => number_format_i18n($views),
                ];
            }
        }

        return $items;
    }
}