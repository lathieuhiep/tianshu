<?php
namespace ExtendSite\Services\Tools;

use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;

defined('ABSPATH') || exit;

class ChapterSyncTool implements ToolInterface {

    public static function get_title(): string {
        return 'Đồng bộ tổng chương truyện';
    }

    public static function get_description(): string {
        return 'Cập nhật lại tổng số chương cho từng truyện.';
    }

    public static function run(): array {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TEST] ChapterSyncTool file loaded');
        }

        $stories = get_posts([
            'post_type' => StoryPostType::SLUG,
            'fields'    => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);

        foreach ($stories as $story_id) {
            $chapters = get_posts([
                'post_type'   => ChapterPostType::SLUG,
                'meta_key'    => ChapterPostType::META_STORY_ID,
                'meta_value'  => $story_id,
                'fields'      => 'ids',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            update_post_meta($story_id, StoryPostType::META_CHAPTER_COUNT, count($chapters));
        }

        return ['message' => 'Đã đồng bộ tổng chương thành công!'];
    }
}