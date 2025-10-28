<?php

namespace ExtendSite\Repositories;

use ExtendSite\PostType\ChapterPostType;

defined('ABSPATH') || exit;

class ChapterRepository
{
    public const CACHE_GROUP = 'es_story_chapter_edges';
    public const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    /**
     * Lấy ID chương đầu/ cuối theo _chapter_number cho 1 truyện.
     *
     * @param int $story_id
     * @param string $edge 'first'|'latest'
     * @return int|null
     */
    public static function get_edge_chapter_id(int $story_id, string $edge = 'first'): ?int
    {
        $edge = ($edge === 'latest') ? 'latest' : 'first';
        $cache_key = "edge:{$edge}:{$story_id}";
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached ?: null; // 0 => null
        }

        $order = ($edge === 'latest') ? 'DESC' : 'ASC';

        $q = new \WP_Query([
            'post_type' => ChapterPostType::SLUG,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
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
            'post_status' => 'publish', // chỉ lấy chương đã xuất bản
        ]);

        $id = $q->have_posts() ? (int)$q->posts[0] : null;

        wp_cache_set($cache_key, $id ?: 0, self::CACHE_GROUP, self::CACHE_TTL);
        return $id;
    }

    /** Trả về URL của first & latest */
    public static function get_edge_urls(int $story_id): array
    {
        $first_id = self::get_edge_chapter_id($story_id, 'first');
        $latest_id = self::get_edge_chapter_id($story_id, 'latest');

        return [
            'first' => $first_id ? get_permalink($first_id) : null,
            'latest' => $latest_id ? get_permalink($latest_id) : null,
        ];
    }

    /** Hook xoá cache khi chapter thay đổi */
    public static function hook_invalidations(): void
    {
        add_action('save_post_' . ChapterPostType::SLUG, [self::class, 'flush_edges_by_chapter'], 10, 3);
        add_action('deleted_postmeta', [self::class, 'maybe_flush_edges_on_meta'], 10, 4);
        add_action('updated_postmeta', [self::class, 'maybe_flush_edges_on_meta'], 10, 4);
        add_action('added_postmeta', [self::class, 'maybe_flush_edges_on_meta'], 10, 4);
    }

    public static function flush_edges_by_chapter(int $post_id, \WP_Post $post, bool $update): void
    {
        $story_id = (int)get_post_meta($post_id, ChapterPostType::META_STORY_ID, true);
        if ($story_id > 0) {
            self::flush_edges($story_id);
        }
    }

    public static function maybe_flush_edges_on_meta($meta_id_or_ids, $object_id, $meta_key, $_meta_value): void
    {
        if (get_post_type($object_id) !== ChapterPostType::SLUG) {
            return;
        }
        if ($meta_key === ChapterPostType::META_STORY_ID || $meta_key === ChapterPostType::META_NUMBER) {
            $story_id = (int)get_post_meta($object_id, ChapterPostType::META_STORY_ID, true);
            if ($story_id > 0) {
                self::flush_edges($story_id);
            }
        }
    }

    public static function flush_edges(int $story_id): void
    {
        wp_cache_delete("edge:first:{$story_id}", self::CACHE_GROUP);
        wp_cache_delete("edge:latest:{$story_id}", self::CACHE_GROUP);
    }
}