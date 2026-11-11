<?php
/**
 * StoryRepository
 *
 * Handle reusable data queries for Story post type.
 *
 * @package ExtendSite\Repositories
 */

namespace ExtendSite\Repositories;

use ExtendSite\DB\LatestChapterTable;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;
use WP_Query;

/**
 * Repository class for Story queries.
 */
class StoryRepository
{
    /**
     * Query published stories for featured story displays.
     *
     * @param array<string,mixed> $options Query options.
     * @return WP_Query
     */
    public static function get_featured(array $options = []): WP_Query
    {
        $options = wp_parse_args($options, [
            'query_source' => 'custom',
            'taxonomy' => [],
            'status' => [],
            'tag' => [],
            'limit' => 18,
            'order_by' => 'ID',
            'order' => 'DESC',
        ]);

        $limit = max(1, min(36, absint($options['limit'])));

        if (in_array($options['query_source'], ['top_week', 'top_month'], true)) {
            $ranked = 'top_month' === $options['query_source']
                ? StoryRankingRepository::top_30_days($limit)
                : StoryRankingRepository::top_7_days($limit);
            $story_ids = array_values(array_filter(array_map(
                'absint',
                wp_list_pluck($ranked, 'story_id')
            )));

            if (!$story_ids) {
                return new WP_Query();
            }

            return new WP_Query([
                'post_type' => StoryPostType::SLUG,
                'post_status' => 'publish',
                'post__in' => $story_ids,
                'orderby' => 'post__in',
                'posts_per_page' => count($story_ids),
                'no_found_rows' => true,
            ]);
        }

        $order_by = sanitize_key((string) $options['order_by']);
        $allowed_order_by = ['ID', 'title', 'date', 'views'];
        if (!in_array($order_by, $allowed_order_by, true)) {
            $order_by = 'ID';
        }

        $order = strtoupper((string) $options['order']);
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

        $args = [
            'post_type' => StoryPostType::SLUG,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'order' => $order,
            'no_found_rows' => true,
        ];

        if ('views' === $order_by) {
            $args['meta_key'] = StoryPostType::META_STORY_VIEWS;
            $args['meta_type'] = 'NUMERIC';
            $args['orderby'] = 'meta_value_num';
        } else {
            $args['orderby'] = $order_by;
        }

        $tax_query = [];
        $taxonomy_ids = array_values(array_filter(array_map('absint', (array) $options['taxonomy'])));
        if ($taxonomy_ids) {
            $tax_query[] = [
                'taxonomy' => StoryPostType::TAX_SLUG,
                'field' => 'term_id',
                'terms' => $taxonomy_ids,
                'operator' => 'IN',
            ];
        }

        $tag_ids = array_values(array_filter(array_map('absint', (array) $options['tag'])));
        if ($tag_ids) {
            $tax_query[] = [
                'taxonomy' => StoryPostType::TAG_SLUG,
                'field' => 'term_id',
                'terms' => $tag_ids,
                'operator' => 'IN',
            ];
        }

        $status_ids = array_values(array_filter(array_map('absint', (array) $options['status'])));
        if ($status_ids) {
            $tax_query[] = [
                'taxonomy' => StoryPostType::STATUS_TAX,
                'field' => 'term_id',
                'terms' => $status_ids,
                'operator' => 'IN',
            ];
        }

        if ($tax_query) {
            if (count($tax_query) > 1) {
                $tax_query = array_merge(['relation' => 'AND'], $tax_query);
            }
            $args['tax_query'] = $tax_query;
        }

        return new WP_Query($args);
    }

    /**
     * Query published stories for the story carousel.
     *
     * @param array<string,mixed> $options Query options.
     * @return WP_Query
     */
    public static function get_carousel(array $options = []): WP_Query
    {
        return self::get_featured(wp_parse_args($options, [
            'query_source' => 'top_month',
            'limit' => 12,
        ]));
    }

    /**
     * Query published stories for grid displays.
     *
     * @param array<string,mixed> $options Query options.
     * @return WP_Query
     */
    public static function get_grid(array $options = []): WP_Query
    {
        return self::get_featured($options);
    }

    /**
     * Query stories ordered by their latest chapter update.
     *
     * @param int $limit Maximum number of stories.
     * @return WP_Query
     */
    public static function get_latest(int $limit = 12): WP_Query
    {
        $limit = max(1, min(36, $limit));
        $rows = LatestChapterTable::get_latest_stories($limit);
        $story_ids = array_values(array_filter(array_map(
            'absint',
            wp_list_pluck($rows, 'story_id')
        )));

        if (!$story_ids) {
            return new WP_Query();
        }

        return new WP_Query([
            'post_type' => StoryPostType::SLUG,
            'post_status' => 'publish',
            'post__in' => $story_ids,
            'orderby' => 'post__in',
            'posts_per_page' => count($story_ids),
            'no_found_rows' => true,
        ]);
    }

    /**
     * Get the latest published chapter for multiple stories.
     *
     * @param array<int> $story_ids Story IDs.
     * @return array<int,array{id:int,url:string,title:string,number:int}>
     */
    public static function get_latest_chapters(array $story_ids): array
    {
        return LatestChapterTable::get_latest_chapters_with_fallback($story_ids);
    }

    /**
     * Get terms from a supported story taxonomy.
     *
     * @param string $taxonomy  Taxonomy slug.
     * @param bool   $hide_empty Whether to exclude unused terms.
     * @return array<int,\WP_Term>
     */
    public static function get_taxonomy_terms(string $taxonomy, bool $hide_empty = true): array
    {
        $allowed_taxonomies = [
            StoryPostType::TAX_SLUG,
            StoryPostType::STATUS_TAX,
            StoryPostType::TAG_SLUG,
        ];
        $taxonomy = sanitize_key($taxonomy);

        if (!in_array($taxonomy, $allowed_taxonomies, true) || !taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => $hide_empty,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Check if a story is active (published).
     *
     * @param int $story_id Story post ID.
     * @return bool True if active, false otherwise.
     */
    public static function is_active(int $story_id): bool {
        $post = get_post($story_id);
        return $post && $post->post_type === StoryPostType::SLUG && $post->post_status === 'publish';
    }

    /**
     * Get author IDs associated with a story.
     *
     * @param int $story_id Story post ID.
     * @return array<int> Array of author IDs.
     */
    public static function get_author_ids(int $story_id): array
    {
        return (array) get_post_meta($story_id, StoryPostType::META_AUTHOR_IDS, true);
    }

    /**
     * Get author IDs associated with multiple stories.
     *
     * @param array<int> $story_ids Story post IDs.
     * @return array<int,array<int>> Author IDs keyed by story ID.
     */
    public static function get_author_ids_by_story_ids(array $story_ids): array
    {
        global $wpdb;

        $story_ids = array_values(array_unique(array_filter(array_map('absint', $story_ids))));
        if (!$story_ids) {
            return [];
        }

        $results = array_fill_keys($story_ids, []);
        $placeholders = implode(',', array_fill(0, count($story_ids), '%d'));
        $params = array_merge([StoryPostType::META_AUTHOR_IDS], $story_ids);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT post_id, meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = %s
                  AND post_id IN ({$placeholders})
                ",
                ...$params
            ),
            ARRAY_A
        );

        foreach ($rows ?: [] as $row) {
            $story_id = absint($row['post_id'] ?? 0);
            if (!$story_id || !array_key_exists($story_id, $results)) {
                continue;
            }

            $ids = maybe_unserialize($row['meta_value'] ?? '');
            $results[$story_id] = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
        }

        return $results;
    }

    /**
     * Get total number of stories written by an author.
     *
     * @param int $author_id Author post (or term) ID.
     * @return int Total published stories.
     */
    public static function count_by_author(int $author_id): int
    {
        if ($author_id <= 0) {
            return 0;
        }

        $query = new \WP_Query([
            'post_type' => StoryPostType::SLUG,
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => false,
            'meta_query' => [
                [
                    'key'     => StoryPostType::META_AUTHOR_IDS,
                    'value'   => 'i:' . $author_id . ';',
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        return $query->found_posts;
    }

    /**
     * Get total published stories for multiple authors.
     *
     * @param array<int> $author_ids Author post IDs.
     * @return array<int,int> Counts keyed by author ID.
     */
    public static function count_by_authors(array $author_ids): array
    {
        global $wpdb;

        $author_ids = array_values(array_unique(array_filter(array_map('absint', $author_ids))));
        if (!$author_ids) {
            return [];
        }

        $counts = array_fill_keys($author_ids, 0);
        $likes = [];
        $params = [StoryPostType::SLUG, StoryPostType::META_AUTHOR_IDS];

        foreach ($author_ids as $author_id) {
            $likes[] = 'pm.meta_value LIKE %s';
            $params[] = '%' . $wpdb->esc_like('i:' . $author_id . ';') . '%';
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = %s
                  AND p.post_status = 'publish'
                  AND pm.meta_key = %s
                  AND (" . implode(' OR ', $likes) . ")
                ",
                ...$params
            ),
            ARRAY_A
        );

        foreach ($rows ?: [] as $row) {
            $ids = maybe_unserialize($row['meta_value'] ?? '');
            if (!is_array($ids)) {
                continue;
            }

            foreach (array_unique(array_map('absint', $ids)) as $id) {
                if (isset($counts[$id])) {
                    $counts[$id]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Get stories written by a specific author.
     *
     * @param int $author_id Author post ID.
     * @param int $paged     Current page.
     * @param int $per_page  Items per page.
     * @return WP_Query
     */
    public static function get_by_author( int $author_id, int $paged = 1, int $per_page = 12 ): \WP_Query {
        if ( $author_id <= 0 ) {
            return new \WP_Query();
        }

        return new \WP_Query([
            'post_type'      => StoryPostType::SLUG,
            'post_status'    => 'publish',
            'paged'          => max( 1, $paged ),
            'posts_per_page' => $per_page,
            'meta_query'     => [
                [
                    'key'     => StoryPostType::META_AUTHOR_IDS,
                    'value'   => 'i:' . $author_id . ';',
                    'compare' => 'LIKE',
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
    }

    /**
     * Get authors.
     *
     * @param int $story_id
     * @return array
     */
    public static function get_authors(int $story_id): array {
        $ids = self::get_author_ids($story_id);
        if (empty($ids)) {
            return [];
        }

        $authors = get_posts([
            'post_type'   => AuthorPostType::SLUG,
            'post__in'    => $ids,
            'numberposts' => -1,
            'orderby'     => 'post__in', // giữ thứ tự
        ]);

        $results = [];
        foreach ($authors as $post) {
            $results[] = [
                'id'   => $post->ID,
                'name' => $post->post_title,
                'url'  => get_permalink($post),
            ];
        }

        return $results;
    }

    /**
     * Get authors for multiple stories.
     *
     * @param array<int> $story_ids Story IDs.
     * @return array<int,array<int,array{id:int,name:string,url:string}>> Authors keyed by story ID.
     */
    public static function get_authors_by_story_ids(array $story_ids): array
    {
        $story_ids = array_values(array_unique(array_filter(array_map('absint', $story_ids))));
        if (!$story_ids) {
            return [];
        }

        $story_author_ids = [];
        $all_author_ids = [];

        $batched_author_ids = self::get_author_ids_by_story_ids($story_ids);
        foreach ($story_ids as $story_id) {
            $ids = $batched_author_ids[$story_id] ?? [];
            $story_author_ids[$story_id] = $ids;
            $all_author_ids = array_merge($all_author_ids, $ids);
        }

        $all_author_ids = array_values(array_unique(array_filter($all_author_ids)));
        if (!$all_author_ids) {
            return array_fill_keys($story_ids, []);
        }

        $authors = get_posts([
            'post_type' => AuthorPostType::SLUG,
            'post__in' => $all_author_ids,
            'numberposts' => count($all_author_ids),
            'orderby' => 'post__in',
        ]);

        $author_map = [];
        foreach ($authors as $post) {
            $author_map[$post->ID] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'url' => get_permalink($post),
            ];
        }

        $results = [];
        foreach ($story_author_ids as $story_id => $author_ids) {
            $results[$story_id] = [];

            foreach ($author_ids as $author_id) {
                if (isset($author_map[$author_id])) {
                    $results[$story_id][] = $author_map[$author_id];
                }
            }
        }

        return $results;
    }

    /**
     * Get total chapter count for the current story.
     *
     * @param int $story_id Story post ID.
     * @return int Chapter count.
     */
    public static function get_chapter_total(int $story_id): int
    {
        return (int) get_post_meta($story_id, StoryPostType::META_CHAPTER_COUNT, true);
    }
}
