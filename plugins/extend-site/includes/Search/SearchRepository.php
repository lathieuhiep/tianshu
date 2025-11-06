<?php

namespace ExtendSite\Search;

use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;
use WP_Query;

/**
 * Handles all data queries for story, chapter, and author search.
 */
class SearchRepository
{

    /**
     * Search stories (and optionally by author name).
     *
     * @param string $keyword
     * @param int $paged
     * @param int $per_page
     * @return WP_Query Story IDs
     */
    public static function search_stories_full(string $keyword, int $paged = 1, int $per_page = 12): WP_Query {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return new WP_Query(['post__in' => [0]]);
        }

        // Truy vấn theo tiêu đề truyện
        $args = [
            'post_type'      => StoryPostType::SLUG,
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            's'              => $keyword,
            'post_status'    => 'publish',
        ];

        $query = new WP_Query($args);

        // Nếu chưa có, tìm tác giả gần khớp
        if (!$query->have_posts()) {
            $authors = get_posts([
                'post_type'      => AuthorPostType::SLUG,
                's'              => $keyword,
                'fields'         => 'ids',
                'posts_per_page' => 5,
            ]);

            if ($authors) {
                $meta_query = ['relation' => 'OR'];
                foreach ($authors as $author_id) {
                    $meta_query[] = [
                        'key'     => StoryPostType::META_AUTHOR_IDS,
                        'value'   => '"' . $author_id . '"',
                        'compare' => 'LIKE',
                    ];
                }

                $args['meta_query'] = $meta_query;
                $query = new WP_Query($args);
            }
        }

        return $query;
    }

    /**
     * Light search for stories with caching.
     *
     * @param string $keyword
     * @param int $limit
     * @return array<int> Story IDs
     */
    public static function search_stories_light(string $keyword, int $limit = 12): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        // Kiểm tra cache tạm
        $cache_key = 'es_autocomplete_' . md5($keyword);
        if ($cached = get_transient($cache_key)) {
            return $cached;
        }

        // Tìm truyện theo tiêu đề
        $query = new WP_Query([
            'post_type'      => StoryPostType::SLUG,
            'posts_per_page' => $limit,
            's'              => $keyword,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post_status'    => 'publish',
        ]);

        $story_ids = $query->posts;

        // Nếu chưa có, tìm theo tác giả
        if (empty($story_ids)) {
            $authors = get_posts([
                'post_type'      => AuthorPostType::SLUG,
                's'              => $keyword,
                'fields'         => 'ids',
                'posts_per_page' => 3,
                'post_status'    => 'publish',
            ]);

            if ($authors) {
                $meta_query = ['relation' => 'OR'];

                foreach ($authors as $author_id) {
                    $meta_query[] = [
                        'key'     => StoryPostType::META_AUTHOR_IDS,
                        'value'   => '"' . $author_id . '"',
                        'compare' => 'LIKE',
                    ];
                }

                $query2 = new \WP_Query([
                    'post_type'      => StoryPostType::SLUG,
                    'posts_per_page' => $limit,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                    'post_status'    => 'publish',
                    'meta_query'     => $meta_query,
                ]);

                $story_ids = $query2->posts;
            }
        }

        $story_ids = array_unique($story_ids);

        // Cache 30 giây để giảm tải server
        set_transient($cache_key, $story_ids, 30);

        return $story_ids;
    }
}