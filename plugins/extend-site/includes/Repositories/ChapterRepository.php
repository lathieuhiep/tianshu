<?php

namespace ExtendSite\Repositories;

use ExtendSite\PostType\ChapterPostType;

defined('ABSPATH') || exit;

class ChapterRepository
{
    /**
     * Lấy ID chương đầu hoặc chương mới nhất của 1 truyện.
     *
     * @param int    $story_id
     * @param string $edge 'first' | 'latest'
     * @return int|null
     */
    public static function get_edge_chapter_id(int $story_id, string $edge = 'first'): ?int
    {
        $edge  = ($edge === 'latest') ? 'latest' : 'first';
        $order = ($edge === 'latest') ? 'DESC' : 'ASC';

        $q = new \WP_Query([
            'post_type'              => ChapterPostType::SLUG,
            'fields'                 => 'ids',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'post_status'            => 'publish',
            'meta_query'             => [
                [
                    'key'   => ChapterPostType::META_STORY_ID,
                    'value' => $story_id,
                    'type'  => 'NUMERIC',
                ],
            ],
            'meta_key'   => ChapterPostType::META_NUMBER,
            'orderby'    => 'meta_value_num',
            'order'      => $order,
        ]);

        return $q->have_posts() ? (int) $q->posts[0] : null;
    }

    /**
     * Trả về URL của chương đầu và chương mới nhất.
     */
    public static function get_edge_urls(int $story_id): array
    {
        $first_id  = self::get_edge_chapter_id($story_id, 'first');
        $latest_id = self::get_edge_chapter_id($story_id, 'latest');

        return [
            'first'  => $first_id ? get_permalink($first_id) : null,
            'latest' => $latest_id ? get_permalink($latest_id) : null,
        ];
    }
}