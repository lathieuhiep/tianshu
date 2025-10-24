<?php

namespace ExtendSite\PostType;

defined('ABSPATH') || exit;

class AuthorPostType extends BasePostType
{
    public const SLUG = 'story_author';
    public const SINGULAR = 'tác giả';
    public const PLURAL = 'Tác giả truyện';

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
            // Sau này khi bạn lưu quan hệ ở Story (meta _story_author_ids),
            // có thể đếm nhanh bằng WP_Query meta_query. Tạm thời hiển thị “—”.
            echo '<em>—</em>';
        }, 10, 2);
    }
}