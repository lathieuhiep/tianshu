<?php
namespace ExtendSite\Repositories;

use ExtendSite\DB\ViewsStoryDailyTable;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Services\RankingCache;

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
        try {
            return self::cached_top_by_range($from_date, $to_date, $limit);
        } catch (\RuntimeException $error) {
            return [];
        }
    }

    private static function cached_top_by_range(string $from_date, ?string $to_date, int $limit): array {
        $to_date = $to_date ?: es_get_today();
        $limit = max(1, min(50, $limit));
        $days = (strtotime($to_date) - strtotime($from_date)) / DAY_IN_SECONDS;
        $period = $days >= 364 ? 'year' : ($days >= 29 ? 'month' : ($days >= 6 ? 'week' : 'day'));
        $identity = $to_date === es_get_today()
            ? "records|rolling|{$days}|{$limit}"
            : "records|{$from_date}|{$to_date}|{$limit}";
        return RankingCache::remember(
            $identity, $period,
            static fn() => self::query_top_by_range($from_date, $to_date, $limit)
        );
    }

    private static function query_top_by_range(string $from_date, string $to_date, int $limit): array {
        global $wpdb;

        $table = ViewsStoryDailyTable::get_table_name();
        $to_date = $to_date ?: es_get_today();

        $query = $wpdb->prepare("
            SELECT v.story_id, SUM(v.views) AS total_views
            FROM {$table} v
            INNER JOIN {$wpdb->posts} p ON p.ID = v.story_id
            WHERE view_date BETWEEN %s AND %s
              AND p.post_type = 'story' AND p.post_status = 'publish'
            GROUP BY v.story_id
            ORDER BY total_views DESC, v.story_id ASC
            LIMIT %d
        ", $from_date, $to_date, $limit);

        $results = $wpdb->get_results($query, ARRAY_A);

        if ($wpdb->last_error !== '' || !is_array($results)) {
            throw new \RuntimeException('es_ranking_database_error');
        }


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
        $from = date('Y-m-d', strtotime("$to -6 days"));
        return self::get_top_by_range($from, $to, $limit);
    }

    /**
     * Top stories in the last 30 days.
     */
    public static function top_30_days(int $limit = 10): array {
        $to = es_get_today();
        $from = date('Y-m-d', strtotime("$to -29 days"));
        return self::get_top_by_range($from, $to, $limit);
    }

    /**
     * Top stories in the last 365 days.
     */
    public static function top_365_days(int $limit = 10): array {
        $to = es_get_today();
        $from = date('Y-m-d', strtotime("$to -364 days"));
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
        $story_author_ids = StoryRepository::get_author_ids_by_story_ids(wp_list_pluck($story_records, 'story_id'));

        foreach ($story_records as $row) {
            $story_id = (int) $row['story_id'];
            $views    = (int) $row['total_views'];

            // Lấy danh sách tác giả của truyện này
            $author_ids = $story_author_ids[$story_id] ?? [];

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
        $type = $type === AuthorPostType::SLUG ? $type : StoryPostType::SLUG;
        $period = in_array($period, ['day', 'week', 'month', 'year'], true) ? $period : 'day';
        $limit = max(1, min(50, $limit));
        try {
            return RankingCache::remember(
                "items|{$type}|{$period}|{$limit}", $period,
                static fn() => self::build_ranking_items($type, $period, $limit)
            );
        } catch (\RuntimeException $error) {
            return [];
        }
    }

    private static function build_ranking_items(string $type, string $period, int $limit): array
    {
        $to = es_get_today();
        $days = ['day' => 0, 'week' => 6, 'month' => 29, 'year' => 364][$period];
        $from = date('Y-m-d', strtotime("$to -{$days} days"));
        $records = $type === AuthorPostType::SLUG
            ? self::author_records($from, $to, $limit, $period)
            : self::cached_top_by_range($from, $to, $limit);

        $items = [];

        // Nap post/meta theo batch truoc vong lap, tranh get_post/get_thumbnail N+1.
        $id_column = $type === StoryPostType::SLUG ? 'story_id' : 'author_id';
        $ids = array_map('intval', wp_list_pluck($records, $id_column));
        $posts_by_id = [];
        if ($ids) {
            $posts = get_posts([
                'post_type' => $type,
                'post_status' => 'publish',
                'post__in' => $ids,
                'posts_per_page' => count($ids),
                'no_found_rows' => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => true,
            ]);
            foreach ($posts as $post) {
                $posts_by_id[$post->ID] = $post;
            }
            $thumbnail_ids = array_filter(array_map('get_post_thumbnail_id', wp_list_pluck($posts, 'ID')));
            if ($thumbnail_ids) {
                _prime_post_caches($thumbnail_ids, false, true);
            }
        }

        foreach ($records as $row) {
            if ($type === StoryPostType::SLUG) {
                $story_id = (int) $row['story_id'];
                $views    = (int) $row['total_views'];
                $post = $posts_by_id[$story_id] ?? null;

                if (!$post instanceof \WP_Post || $post->post_type !== StoryPostType::SLUG || $post->post_status !== 'publish') {
                    continue;
                }

                $items[] = [
                    'id'     => $story_id,
                    'title'  => get_the_title($story_id),
                    'url'    => get_permalink($story_id),
                    'image'  => get_post_thumbnail_id($story_id),
                    'views'  => $views,
                ];
            } else {
                $author_id = (int) $row['author_id'];
                $views     = (int) $row['total_views'];
                $post = $posts_by_id[$author_id] ?? null;

                if (!$post instanceof \WP_Post || $post->post_type !== AuthorPostType::SLUG || $post->post_status !== 'publish') {
                    continue;
                }

                $items[] = [
                    'id'     => $author_id,
                    'title'  => get_the_title($author_id),
                    'url'    => get_permalink($author_id),
                    'image'  => get_post_thumbnail_id($author_id),
                    'views'  => $views,
                ];
            }
        }

        return $items;
    }

    /** Tong hop tren tat ca truyen publish; khong cat top truyen truoc khi cong tac gia. */
    private static function author_records(string $from, string $to, int $limit, string $period): array
    {
        return RankingCache::remember("authors|{$period}|{$limit}", $period, static function () use ($from, $to, $limit) {
            global $wpdb;
            $table = ViewsStoryDailyTable::get_table_name();
            // EXISTS tranh nhan doi views neu meta quan he bi trung dong.
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT a.ID AS author_id, SUM(v.views) AS total_views
                 FROM {$wpdb->posts} a
                 INNER JOIN {$table} v ON EXISTS (
                     SELECT 1 FROM {$wpdb->postmeta} m
                     WHERE m.post_id = v.story_id AND m.meta_key = %s
                     AND (m.meta_value LIKE CONCAT('%%;i:', a.ID, ';%%')
                          OR m.meta_value LIKE CONCAT('%%;s:', CHAR_LENGTH(CAST(a.ID AS CHAR)), ':', CHAR(34), a.ID, CHAR(34), ';%%'))
                 )
                 INNER JOIN {$wpdb->posts} s ON s.ID = v.story_id
                 WHERE a.post_type = %s AND a.post_status = 'publish'
                   AND s.post_type = %s AND s.post_status = 'publish'
                   AND v.view_date BETWEEN %s AND %s
                 GROUP BY a.ID ORDER BY total_views DESC, a.ID ASC LIMIT %d",
                StoryPostType::META_AUTHOR_IDS, AuthorPostType::SLUG, StoryPostType::SLUG, $from, $to, $limit
            ), ARRAY_A);
            if ($wpdb->last_error || !is_array($rows)) {
                throw new \RuntimeException('es_ranking_author_database_error');
            }
            return $rows;
        });
    }

}
