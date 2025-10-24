<?php

namespace ExtendSite\PostType;

defined('ABSPATH') || exit;

class ChapterPostType extends BasePostType
{
    public const SLUG = 'chapter'; // CPT key nội bộ
    public const SINGULAR = 'chương';
    public const PLURAL = 'Chương';
    public const META_STORY_ID = '_chapter_story_id';   // liên kết tới story (post ID)
    public const META_NUMBER = '_chapter_number';     // số thứ tự chương

    // (tuỳ chọn) tên file template nếu bạn dùng TemplateLoader
    public const TEMPLATE_SINGLE = 'chapter/single-chapter.php';
    public const TEMPLATE_ARCHIVE = 'chapter/archive-chapter.php';

    public function __construct(array $args = [])
    {
        $args = array_replace_recursive([
            'label' => esc_html__('Chương', 'extend-site'),
            'has_archive' => false,
            'hierarchical' => false, // KHÔNG dùng post_parent
            'supports' => ['title', 'editor', 'author', 'revisions'],
            'menu_icon' => 'dashicons-media-text',
            'rewrite' => ['slug' => 'chuong', 'with_front' => false],
        ], $args);

        parent::__construct($args);

        // Meta box: chọn Truyện + số chương
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::SLUG, [$this, 'save_meta'], 10, 2);

        // Cột admin: Truyện + Số chương
        add_filter('manage_' . self::SLUG . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::SLUG . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-' . self::SLUG . '_sortable_columns', [$this, 'sortable_columns']);
        add_action('pre_get_posts', [$this, 'handle_admin_sorting']);
    }

    /** Meta boxes */
    public function add_meta_boxes(): void
    {
        add_meta_box(
            'chapter_meta',
            esc_html__('Thông tin chương', 'extend-site'),
            [$this, 'render_meta_box'],
            self::SLUG,
            'side',
            'default'
        );
    }

    public function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('chapter_meta_save', 'chapter_meta_nonce');

        $story_id = (int)get_post_meta($post->ID, self::META_STORY_ID, true);
        $number = (string)get_post_meta($post->ID, self::META_NUMBER, true);

        // Lấy danh sách truyện (giới hạn 200, có thể nâng/đổi sang AJAX select2 sau)
        $stories = get_posts([
            'post_type' => 'story',
            'posts_per_page' => 200,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        echo '<p><label for="chapter_story_id"><strong>' . esc_html__('Thuộc truyện', 'extend-site') . '</strong></label></p>';
        echo '<select id="chapter_story_id" name="chapter_story_id" class="widefat">';
        echo '<option value="0">' . esc_html__('— Chọn truyện —', 'extend-site') . '</option>';
        foreach ($stories as $sid) {
            $title = get_the_title($sid);
            printf(
                '<option value="%d"%s>%s</option>',
                (int)$sid,
                selected($story_id, $sid, false),
                esc_html($title ?: ('#' . $sid))
            );
        }
        echo '</select>';

        echo '<p style="margin-top:10px;"><label for="chapter_number"><strong>' . esc_html__('Số chương', 'extend-site') . '</strong></label></p>';
        printf(
            '<input type="number" min="1" step="1" id="chapter_number" name="chapter_number" class="small-text" value="%s" />',
            esc_attr($number)
        );

        echo '<p class="description">' . esc_html__('Dùng để sắp xếp chương theo thứ tự trong truyện. Có thể trùng tiêu đề nhưng hạn chế trùng số.', 'extend-site') . '</p>';
    }

    public function save_meta(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['chapter_meta_nonce']) || !wp_verify_nonce($_POST['chapter_meta_nonce'], 'chapter_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // story_id
        $story_id = isset($_POST['chapter_story_id']) ? (int)$_POST['chapter_story_id'] : 0;
        if ($story_id > 0 && get_post_type($story_id) !== 'story') {
            $story_id = 0; // bảo vệ: chỉ nhận ID là post type 'story'
        }
        update_post_meta($post_id, self::META_STORY_ID, $story_id);

        // number
        $number = isset($_POST['chapter_number']) ? (int)$_POST['chapter_number'] : 0;
        if ($number < 0) {
            $number = 0;
        }
        update_post_meta($post_id, self::META_NUMBER, $number);
    }

    /** Admin columns */
    public function add_admin_columns(array $cols): array
    {
        // Chèn sau cột Title
        $new = [];
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['chapter_story'] = esc_html__('Thuộc truyện', 'extend-site');
                $new['chapter_number'] = esc_html__('Số chương', 'extend-site');
            }
        }
        return $new;
    }

    public function render_admin_columns(string $col, int $post_id): void
    {
        if ($col === 'chapter_story') {
            $sid = (int)get_post_meta($post_id, self::META_STORY_ID, true);
            if ($sid) {
                $title = get_the_title($sid);
                $link = get_edit_post_link($sid);
                echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>' : esc_html($title);
            } else {
                echo '<em>' . esc_html__('(Chưa chọn)', 'extend-site') . '</em>';
            }
        } elseif ($col === 'chapter_number') {
            $n = get_post_meta($post_id, self::META_NUMBER, true);
            echo $n !== '' ? esc_html((string)$n) : '<em>—</em>';
        }
    }

    public function sortable_columns(array $cols): array
    {
        $cols['chapter_number'] = 'chapter_number';
        return $cols;
    }

    public function handle_admin_sorting(\WP_Query $q): void
    {
        if (!is_admin() || !$q->is_main_query() || $q->get('post_type') !== self::SLUG) {
            return;
        }
        if ($q->get('orderby') === 'chapter_number') {
            $q->set('meta_key', self::META_NUMBER);
            $q->set('orderby', 'meta_value_num');
        }
    }
}