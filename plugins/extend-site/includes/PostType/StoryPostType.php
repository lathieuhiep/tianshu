<?php

namespace ExtendSite\PostType;

use WP_Post;
use function es_add_custom_taxonomy_filter_to_cpt;

defined('ABSPATH') || exit;

class StoryPostType extends BasePostType
{
    // slug post type
    public const SLUG = 'story';
    public const TAX_SLUG = 'story_genre';
    public const TAG_SLUG = 'story_tag';
    public const STATUS_TAX = 'story_status';
    public const SINGULAR = 'truyện';
    public const PLURAL = 'Truyện';
    public const TAX_NAME = 'Danh mục truyện';
    public const META_STORY_VIEWS   = '_story_view_count'; // số lượt xem truyện
    public const META_CHAPTER_COUNT = '_chapter_count';    // tổng số chương

    // name file template
    public const TEMPLATE_SINGLE = 'story/single-story.php';
    public const TEMPLATE_ARCHIVE = 'story/archive-story.php';
    public const TEMPLATE_TAX_CAT = 'story/taxonomy-story-genre.php';
    public const TEMPLATE_TAX_TAG = 'story/taxonomy-story-tag.php';
    public const TEMPLATE_TAX_STATUS = 'story/taxonomy-story-status.php';

    // meta keys
    public const META_AUTHOR_IDS = '_story_author_ids'; // array<int>

    public function __construct(array $args = [])
    {
        $args = array_replace_recursive([
            'rewrite' => [
                'slug' => 'truyen',
                'with_front' => false
            ],
            'menu_icon' => 'dashicons-book',
        ], $args);

        parent::__construct($args);

        // Meta box
        add_action('add_meta_boxes', [$this, 'add_author_meta_box']);
        add_action('save_post_' . self::SLUG, [$this, 'save_author_meta'], 10, 2);

        // Admin columns
        add_filter('manage_' . self::SLUG . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::SLUG . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        // register taxonomy
        add_action('init', function () {
            // story_genre
            $this->register_taxonomy(
                self::TAX_SLUG,
                esc_html__('Danh mục', 'extend-site'),
                esc_html__('Danh mục', 'extend-site'), [
                    'labels' => [
                        'name' => esc_html__('Danh mục truyện', 'extend-site'),
                    ],
                    'hierarchical' => true,
                    'rewrite' => ['slug' => 'the-loai'],
                ]
            );

            // story_tag
            $this->register_taxonomy(
                self::TAG_SLUG,
                esc_html__('Thẻ', 'extend-site'),
                esc_html__('Thẻ', 'extend-site'), [
                    'labels' => [
                        'name' => esc_html__('Thẻ truyện', 'extend-site'),
                    ],
                    'hierarchical' => false,
                    'rewrite' => ['slug' => 'tu-khoa'],
                ]
            );

            // story_status
            $this->register_taxonomy(
                self::STATUS_TAX,
                esc_html__('Trạng thái', 'extend-site'),
                esc_html__('Trạng thái', 'extend-site'), [
                    'labels' => [
                        'name' => esc_html__('Trạng thái truyện', 'extend-site'),
                    ],
                    'hierarchical' => false,
                    'rewrite' => ['slug' => 'trang-thai-truyen'],
                    'show_in_rest' => true,
                ]
            );

            // filter by taxonomy in admin list
            if (function_exists('es_add_custom_taxonomy_filter_to_cpt')) {
                es_add_custom_taxonomy_filter_to_cpt(self::SLUG, self::TAX_SLUG);
                es_add_custom_taxonomy_filter_to_cpt(self::SLUG, 'story_status');
            }
        });

        // set default metas
        add_action('save_post_' . self::SLUG, [$this, 'init_default_views'], 10, 1);
        add_action('save_post_' . self::SLUG, [$this, 'init_default_chapter_count'], 10, 1);
    }

    /** Meta box chọn tác giả **/
    public function add_author_meta_box(): void
    {
        add_meta_box(
            'story_authors',
            esc_html__('Tác giả truyện', 'extend-site'),
            [$this, 'render_author_meta_box'],
            self::SLUG,
            'side',
            'default'
        );
    }

    /**
     * Render meta box chọn tác giả cho truyện
     *
     * @param WP_Post $post
     */
    public function render_author_meta_box(WP_Post $post): void {
        wp_nonce_field('story_authors_save', 'story_authors_nonce');

        // Lấy danh sách ID tác giả đã lưu
        $selected_ids = get_post_meta($post->ID, self::META_AUTHOR_IDS, true);
        $selected_ids = is_array($selected_ids) ? array_map('intval', $selected_ids) : [];

        // Lấy danh sách tác giả đã chọn để hiển thị trong select
        $selected_posts = [];
        if ($selected_ids) {
            $selected_posts = get_posts([
                'post_type'      => 'story_author',
                'post__in'       => $selected_ids,
                'orderby'        => 'post__in',
                'fields'         => ['ID', 'post_title'],
                'no_found_rows'  => true,
            ]);
        }

        // Biến placeholder
        $placeholder = esc_attr__('Nhập tên tác giả...', 'extend-site');
        ?>

        <div class="es-meta-field es-meta-authors">
            <p class="description">
                <?php esc_html_e('Chọn một hoặc nhiều tác giả. Bạn có thể nhập để tìm kiếm.', 'extend-site'); ?>
            </p>

            <select
                name="story_author_ids[]"
                id="story_author_ids"
                class="widefat"
                multiple
                data-es-ajax-select
                data-es-type="author"
                data-placeholder="<?php echo $placeholder; ?>"
                aria-label="<?php echo $placeholder; ?>"
            >
                <?php if (!empty($selected_posts)) : ?>
                    <?php foreach ($selected_posts as $author) : ?>
                        <option value="<?php echo esc_attr($author->ID); ?>" selected>
                            <?php echo esc_html($author->post_title ?: ('#' . $author->ID)); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <?php
    }

    /**
     * Lưu meta box tác giả cho truyện
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public function save_author_meta(int $post_id, WP_Post $post): void
    {
        if (
            empty($_POST['story_authors_nonce']) ||
            !wp_verify_nonce($_POST['story_authors_nonce'], 'story_authors_save') ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !current_user_can('edit_post', $post_id) ||
            $post->post_type !== self::SLUG
        ) {
            return;
        }

        $ids = isset($_POST['story_author_ids']) && is_array($_POST['story_author_ids'])
            ? array_map('intval', $_POST['story_author_ids'])
            : [];
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids) {
            $valid = get_posts([
                'post_type' => 'story_author',
                'post__in' => $ids,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'cache_results' => false,
            ]);

            $ids = array_values(array_intersect($ids, array_map('intval', $valid)));
        }

        if (empty($ids)) {
            delete_post_meta($post_id, self::META_AUTHOR_IDS);
        } else {
            update_post_meta($post_id, self::META_AUTHOR_IDS, $ids);
        }
    }

    /** Thêm cột tác giả & tổng chương **/
    public function add_admin_columns(array $cols): array
    {
        $new = [];
        foreach ($cols as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['story_authors'] = esc_html__('Tác giả truyện', 'extend-site');
                $new['chapter_count'] = esc_html__('Tổng chương', 'extend-site');
            }
        }
        return $new;
    }

    /** Hiển thị cột tác giả & tổng chương **/
    public function render_admin_columns(string $col, int $post_id): void
    {
        switch ($col) {
            case 'story_authors':
                $ids = get_post_meta($post_id, self::META_AUTHOR_IDS, true);
                $ids = is_array($ids) ? $ids : [];
                if (empty($ids)) {
                    echo '<em>—</em>';
                    return;
                }
                $titles = [];
                foreach ($ids as $aid) {
                    if (get_post_type($aid) !== 'story_author') continue;
                    $title = get_the_title($aid) ?: ('#' . (int)$aid);
                    $link = get_edit_post_link($aid);
                    $titles[] = $link
                        ? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>'
                        : esc_html($title);
                }
                echo $titles ? implode(', ', $titles) : '<em>—</em>';
                break;

            case 'chapter_count':
                echo (int) get_post_meta($post_id, self::META_CHAPTER_COUNT, true);
                break;
        }
    }

    /** Mặc định meta view = 0 **/
    public function init_default_views(int $post_id): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

        if (!metadata_exists('post', $post_id, self::META_STORY_VIEWS)) {
            update_post_meta($post_id, self::META_STORY_VIEWS, 0);
        }
    }

    /** Mặc định meta tổng chương = 0 **/
    public function init_default_chapter_count(int $post_id): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

        if (!metadata_exists('post', $post_id, self::META_CHAPTER_COUNT)) {
            update_post_meta($post_id, self::META_CHAPTER_COUNT, 0);
        }
    }
}