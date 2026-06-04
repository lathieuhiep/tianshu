<?php
namespace ExtendSite\Services\Tools;

use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;

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
            ChapterRepository::sync_count_for_story((int) $story_id);
        }

        return ['message' => 'Đã đồng bộ tổng chương thành công!'];
    }
}
