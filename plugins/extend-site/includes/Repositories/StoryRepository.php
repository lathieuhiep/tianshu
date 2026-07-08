<?php
/**
 * StoryRepository
 *
 * Handle reusable data queries for Story post type.
 *
 * @package ExtendSite\Repositories
 */

namespace ExtendSite\Repositories;

use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;
use WP_Query;

/**
 * Repository class for Story queries.
 */
class StoryRepository
{
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

        foreach ($story_ids as $story_id) {
            $ids = self::get_author_ids($story_id);
            $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
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
