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
}