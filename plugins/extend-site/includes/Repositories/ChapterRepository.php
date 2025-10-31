<?php

namespace ExtendSite\Repositories;

use ExtendSite\PostType\ChapterPostType;

defined('ABSPATH') || exit;

class ChapterRepository
{
    /**
     * Lấy ID chương đầu hoặc chương mới nhất của 1 truyện.
     *
     * @param int $story_id
     * @param string $order
     * @return array|null
     */
    public static function get_edge_chapter(int $story_id, string $order = 'ASC'): ?array
    {
        $q = new \WP_Query([
            'post_type' => ChapterPostType::SLUG,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => ChapterPostType::META_STORY_ID,
                    'value' => $story_id,
                    'type' => 'NUMERIC',
                ],
            ],
            'meta_key' => ChapterPostType::META_NUMBER,
            'orderby' => 'meta_value_num',
            'order' => $order,
        ]);

        if ( empty( $q->posts ) ) {
            return null;
        }

        $chapter_id = $q->posts[0];

        return [
            'id'     => $chapter_id,
            'url'    => get_permalink( $chapter_id ),
            'title'  => get_the_title( $chapter_id ),
            'number' => self::get_chapter_number( $chapter_id ),
        ];
    }

    /**
     * Lấy URL chương đầu tiên.
     */
    public static function get_first_chapter( int $story_id ): ?array
    {
        return self::get_edge_chapter( $story_id );
    }

    /**
     * Lấy URL chương mới nhất.
     */
    public static function get_latest_chapter( int $story_id ): ?array {
        return self::get_edge_chapter( $story_id, 'DESC' );
    }

    /**
     * Lấy chương liền kề (prev hoặc next) trong cùng truyện
     */
    public static function get_adjacent_chapter(int $current_chapter_id, string $direction = 'next'): ?int
    {
        $story_id = (int)get_post_meta($current_chapter_id, ChapterPostType::META_STORY_ID, true);
        $current_number = (int)get_post_meta($current_chapter_id, ChapterPostType::META_NUMBER, true);

        if (!$story_id || !$current_number) {
            return null;
        }

        $compare = $direction === 'prev' ? '<' : '>';
        $order = $direction === 'prev' ? 'DESC' : 'ASC';

        $query = new \WP_Query([
            'post_type' => ChapterPostType::SLUG,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => ChapterPostType::META_STORY_ID,
                    'value' => $story_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => ChapterPostType::META_NUMBER,
                    'value' => $current_number,
                    'compare' => $compare,
                    'type' => 'NUMERIC',
                ],
            ],
            'orderby' => [
                'meta_value_num' => $order,
            ],
            'meta_key' => ChapterPostType::META_NUMBER,
        ]);

        return $query->posts[0] ?? null;
    }

    /**
     * Lấy toàn bộ chương trong 1 truyện (sắp xếp theo số chương ASC)
     */
    public static function get_all_by_story(int $story_id): array {
        if (!$story_id) {
            return [];
        }

        $query = new \WP_Query([
            'post_type'      => ChapterPostType::SLUG,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'orderby'        => 'meta_value_num',
            'meta_key'       => ChapterPostType::META_NUMBER,
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => ChapterPostType::META_STORY_ID,
                    'value'   => $story_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        return $query->posts ?? [];
    }

    /**
     * Lấy ID truyện cha của một chương.
     */
    public static function get_story_id(int $chapter_id): int
    {
        return (int)get_post_meta($chapter_id, ChapterPostType::META_STORY_ID, true);
    }

    /**
     * Lấy số chương từ ID chương.
     *
     * @param int $chapter_id ID của chương.
     * @return int Số chương, hoặc 0 nếu không tìm thấy.
     */
    public static function get_chapter_number( int $chapter_id ): int {
        if ( $chapter_id <= 0 ) {
            return 0;
        }

        $number = get_post_meta( $chapter_id, '_chapter_number', true );

        return $number ? (int) $number : 0;
    }

    /**
     * Lấy các chương lân cận trong phạm vi nhất định.
     *
     * @param int $story_id ID của truyện.
     * @param int $current_number Số chương hiện tại.
     * @param int $range Phạm vi lân cận (mặc định 10).
     * @return array Mảng các chương lân cận.
     */
    public static function get_neighbors(int $story_id, int $current_number, int $range = 10): array {
        global $wpdb;

        $min = max(1, $current_number - $range);
        $max = $current_number + $range;

        $query = new \WP_Query([
            'post_type' => ChapterPostType::SLUG,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => ChapterPostType::META_STORY_ID,
                    'value' => $story_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => ChapterPostType::META_NUMBER,
                    'value' => [$min, $max],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC',
                ],
            ],
            'orderby' => 'meta_value_num',
            'meta_key' => ChapterPostType::META_NUMBER,
            'order' => 'ASC',
            'posts_per_page' => 21,
            'no_found_rows' => true,
            'fields' => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $chapters = [];

        foreach ($query->posts as $post_id) {
            $chapters[] = [
                'id'     => $post_id,
                'title'  => get_the_title($post_id),
                'url'    => get_permalink($post_id),
                'number' => (int) get_post_meta($post_id, ChapterPostType::META_NUMBER, true),
            ];
        }

        return $chapters;
    }
}