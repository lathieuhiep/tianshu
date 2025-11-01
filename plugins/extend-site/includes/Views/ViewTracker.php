<?php
namespace ExtendSite\Views;

use ExtendSite\DB\ViewsStoryDailyTable;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;
use ExtendSite\Repositories\StoryRepository;

defined('ABSPATH') || exit;

class ViewTracker {

    /**
     * Increment view counts for chapter, story, and authors.
     *
     * @param int $chapter_id
     * @return void
     */
    public static function increment_views(int $chapter_id): void {
        $chapter_id = absint($chapter_id);
        if (!$chapter_id || !get_post($chapter_id)) {
            return;
        }

        // Chapter views
        self::increment_meta($chapter_id, ChapterPostType::META_CHAPTER_VIEWS);

        // Story views
        $story_id = ChapterRepository::get_story_id($chapter_id);
        if ( StoryRepository::is_active($story_id) ) {
            self::increment_meta($story_id, StoryPostType::META_STORY_VIEWS);

            // Daily story views table
            ViewsStoryDailyTable::increment($story_id);

            // Author views
            $author_ids = StoryRepository::get_author_ids($story_id);
            foreach ($author_ids as $author_id) {
                self::increment_meta((int) $author_id, AuthorPostType::META_AUTHOR_VIEWS);
            }
        }
    }

    /**
     * Increment meta by post ID and meta key.
     *
     * @param int $post_id
     * @param string $meta_key
     * @return void
     */
    private static function increment_meta(int $post_id, string $meta_key): void {
        $current = (int) get_post_meta($post_id, $meta_key, true);
        update_post_meta($post_id, $meta_key, $current + 1);
    }

    /**
     * Get view count by post ID and meta key.
     *
     * @param int $post_id
     * @param string $meta_key
     * @return int
     */
    public static function get_views(int $post_id, string $meta_key): int {
        return max(0, (int) get_post_meta($post_id, $meta_key, true));
    }

    /**
     * Get chapter view count.
     *
     * @param int $chapter_id
     * @return int
     */
    public static function get_chapter_views(int $chapter_id): int {
        return self::get_views($chapter_id, ChapterPostType::META_CHAPTER_VIEWS);
    }

    /**
     * Get story view count.
     *
     * @param int $story_id
     * @return int
     */
    public static function get_story_views(int $story_id): int {
        return self::get_views($story_id, StoryPostType::META_STORY_VIEWS);
    }

    /**
     * Get author view count.
     *
     * @param int $author_id
     * @return int
     */
    public static function get_author_views(int $author_id): int {
        return self::get_views($author_id, AuthorPostType::META_AUTHOR_VIEWS);
    }

    /**
     * Format view count (short style: 1.2K / 3.4M).
     *
     * @param int $views
     * @return string
     */
    public static function format_short(int $views): string {
        if ($views >= 1_000_000) {
            $val = round($views / 1_000_000, 1);
            return rtrim(rtrim(number_format($val, 1, '.', ''), '0'), '.') . 'M';
        }

        if ($views >= 1_000) {
            $val = round($views / 1_000, 1);
            return rtrim(rtrim(number_format($val, 1, '.', ''), '0'), '.') . 'K';
        }

        return number_format($views);
    }

    /**
     * Format view count (full style: 1,234,567).
     *
     * @param int $views
     * @return string
     */
    public static function format_full(int $views): string {
        return number_format_i18n($views);
    }
}