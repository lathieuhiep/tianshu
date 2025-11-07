<?php

namespace ExtendSite\Search;

use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;
use WP_Query;

defined('ABSPATH') || exit;

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

    /**
     * Build and return search query advanced
     *
     * @param int $limit
     * @return WP_Query
     */
    public static function search_stories_advanced(int $limit = 12): WP_Query {
        $paged = max(1, get_query_var('paged') ?: 1);

        $args = [
            'post_type'              => StoryPostType::SLUG,
            'posts_per_page'         => $limit,
            'paged'                  => $paged,
            'post_status'            => 'publish',
            'tax_query'              => ['relation' => 'AND'],
            'meta_query'             => [],
            'no_found_rows'          => false,  // enable pagination
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        // 1. Keyword
        if (!empty($_GET['q'])) {
            $args['s'] = sanitize_text_field(wp_unslash($_GET['q']));
        }

        // 2. Genres
        if (!empty($_GET['genre']) && is_array($_GET['genre'])) {
            $args['tax_query'][] = [
                'taxonomy' => StoryPostType::TAX_SLUG,
                'field'    => 'term_id',
                'terms'    => array_map('intval', $_GET['genre']),
                'operator' => 'IN',
            ];
        }

        // 3. Status
        if (!empty($_GET['status'])) {
            $args['tax_query'][] = [
                'taxonomy' => StoryPostType::STATUS_TAX,
                'field'    => 'term_id',
                'terms'    => [(int) $_GET['status']],
            ];
        }

        // 4. Chapter range
        if (!empty($_GET['chapters'])) {
            $range = SearchHelper::parse_chapter_range(sanitize_text_field($_GET['chapters']));
            $args['meta_query'][] = $range['max']
                ? [
                    'key'     => StoryPostType::META_CHAPTER_COUNT,
                    'value'   => [$range['min'], $range['max']],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ]
                : [
                    'key'     => StoryPostType::META_CHAPTER_COUNT,
                    'value'   => $range['min'],
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
        }

        // 5. Sort
        $sort_key  = sanitize_text_field($_GET['sort'] ?? 'latest');
        $sort_args = SearchHelper::parse_sort_option($sort_key);
        $args      = array_merge($args, $sort_args);

        // Clean empty queries
        if (isset($args['tax_query']['relation']) && count($args['tax_query']) === 1) {
            unset($args['tax_query']);
        }

        return new WP_Query($args);
    }
}