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