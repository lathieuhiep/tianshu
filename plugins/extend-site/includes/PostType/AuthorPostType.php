<?php

namespace ExtendSite\PostType;

use ExtendSite\Repositories\StoryRepository;

defined('ABSPATH') || exit;

class AuthorPostType extends BasePostType
{
    public const SLUG = 'story_author';
    public const SINGULAR = 'tác giả';
    public const PLURAL = 'Tác giả truyện';
    public const META_AUTHOR_VIEWS  = '_author_view_count'; // số lượt xem tác giả
    public const TEMPLATE_SINGLE = 'story-author/single-story-author.php';
    public const TEMPLATE_ARCHIVE = 'story-author/archive-story-author.php';

    public function __construct(array $args = [])
    {
        $args = array_replace_recursive([
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'menu_icon' => 'dashicons-admin-users',
            'rewrite' => ['slug' => 'tac-gia', 'with_front' => false],
        ], $args);

        parent::__construct($args);

        // (Tuỳ chọn) cột admin phụ trợ
        add_filter('manage_' . self::SLUG . '_posts_columns', function (array $cols) {
            // chèn sau Title
            $new = [];
            foreach ($cols as $k => $v) {
                $new[$k] = $v;
                if ($k === 'title') {
                    $new['author_stories'] = esc_html__('Số truyện', 'extend-site');
                }
            }
            return $new;
        });

        add_action('manage_' . self::SLUG . '_posts_custom_column', function (string $col, int $post_id) {
            if ($col !== 'author_stories') return;

            $count = StoryRepository::count_by_author($post_id);

            if ($count > 0) {
                echo esc_html($count);
                return;
            }

            echo '<em>—</em>';
        }, 10, 2);
    }
}