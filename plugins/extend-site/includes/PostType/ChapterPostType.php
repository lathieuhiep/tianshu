<?php

namespace ExtendSite\PostType;

use ExtendSite\Repositories\ChapterRepository;
use ExtendSite\Repositories\StoryRepository;
use WP_Post;

defined('ABSPATH') || exit;

class ChapterPostType extends BasePostType
{
    public const SLUG = 'chapter'; // CPT key nội bộ
    public const SINGULAR = 'chương';
    public const PLURAL = 'Chương';
    public const META_STORY_ID = '_chapter_story_id';   // liên kết tới story (post ID)
    public const META_NUMBER = '_chapter_number';       // số thứ tự chương
    public const META_CHAPTER_VIEWS = '_chapter_view_count'; // số lượt xem chương

    // (tuỳ chọn) tên file template nếu bạn dùng TemplateLoader
    public const OPTION_PERMALINK_STRUCTURE = 'extend_site_chapter_permalink';
    public const DEFAULT_PERMALINK_STRUCTURE = '/%story%/chuong/%postname%/';
    public const TEMPLATE_SINGLE = 'chapter/single-chapter.php';

    public function __construct(array $args = [])
    {
        $args = array_replace_recursive([
            'label' => self::PLURAL,
            'has_archive' => false,
            'hierarchical' => false, // KHÔNG dùng post_parent
            'show_in_menu' => 'edit.php?post_type=story',
            'supports' => ['title', 'editor', 'author', 'revisions'],
            'menu_icon' => 'dashicons-media-text',
            'rewrite' => [
                'slug' => 'chuong',
                'with_front' => false
            ],
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

        // Permalink tùy chỉnh để có dạng /story-slug/chuong/chapter-slug
        add_filter('post_type_link', [$this, 'filter_permalink'], 10, 2);

        // Thay thế %story% trong permalink chương
        add_action('init', [$this, 'register_chapter_rewrite_rule']);

        // Cập nhật tổng chương cho truyện khi có thay đổi
        add_action('save_post_' . self::SLUG, [$this, 'update_story_chapter_count_on_save'], 20, 2);
        add_action('transition_post_status', [$this, 'handle_chapter_status_change'], 10, 3);
        add_action('before_delete_post', [$this, 'capture_deleted_chapter_story']);
        add_action('deleted_post', [$this, 'sync_deleted_chapter_story']);
        add_filter('update_post_metadata', [$this, 'capture_story_id_before_meta_update'], 10, 5);
        add_action('updated_post_meta', [$this, 'sync_story_count_on_meta_change'], 10, 4);
        add_action('added_post_meta', [$this, 'sync_story_count_on_meta_change'], 10, 4);
        add_action('deleted_post_meta', [$this, 'sync_story_count_on_meta_delete'], 10, 4);
    }

    /** Đăng ký rewrite rule cho permalink chương */
    public function register_chapter_rewrite_rule(): void
    {
        $structure = self::get_permalink_structure();
        $tokens = self::get_permalink_tokens($structure);
        $chapter_token = in_array('%post_id%', $tokens, true) ? '%post_id%' : '%postname%';
        $chapter_match_index = array_search($chapter_token, $tokens, true);

        if ($chapter_match_index !== false) {
            $regex = self::permalink_structure_to_regex($structure);
            $query = $chapter_token === '%post_id%'
                ? 'index.php?post_type=' . self::SLUG . '&p=$matches[' . ($chapter_match_index + 1) . ']'
                : 'index.php?' . self::SLUG . '=$matches[' . ($chapter_match_index + 1) . ']';

            add_rewrite_rule('^' . $regex . '/?$', $query, 'top');
        }

        // Keep the historical URL working even if the admin switches to %post_id%.
        add_rewrite_rule(
            '^([^/]+)/chuong/([^/]+)/?$',
            'index.php?' . self::SLUG . '=$matches[2]',
            'bottom'
        );
    }

    /** Thay thế %story% trong permalink chương */
    public function filter_permalink(string $permalink, WP_Post $post): string {
        if ($post->post_type !== self::SLUG) {
            return $permalink;
        }

        $story_id = (int) get_post_meta($post->ID, self::META_STORY_ID, true);
        if (!$story_id) {
            return $permalink;
        }

        $story = get_post($story_id);
        if (!$story) {
            return $permalink;
        }

        $path = str_replace(
            ['%story%', '%postname%', '%post_id%'],
            [$story->post_name, $post->post_name, (string) $post->ID],
            self::get_permalink_structure()
        );

        return home_url(user_trailingslashit(trim($path, '/')));
    }

    public static function get_permalink_structure(): string
    {
        $structure = (string) get_option(self::OPTION_PERMALINK_STRUCTURE, self::DEFAULT_PERMALINK_STRUCTURE);

        return self::sanitize_permalink_structure($structure);
    }

    public static function sanitize_permalink_structure(string $structure): string
    {
        $structure = trim($structure);

        if ($structure === '') {
            return self::DEFAULT_PERMALINK_STRUCTURE;
        }

        $structure = '/' . trim($structure, '/') . '/';

        if (strpos($structure, '%story%') === false) {
            return self::DEFAULT_PERMALINK_STRUCTURE;
        }

        $has_postname = strpos($structure, '%postname%') !== false;
        $has_post_id = strpos($structure, '%post_id%') !== false;

        if ($has_postname === $has_post_id) {
            return self::DEFAULT_PERMALINK_STRUCTURE;
        }

        foreach (self::get_permalink_tokens($structure) as $token) {
            if (!in_array($token, ['%story%', '%postname%', '%post_id%'], true)) {
                return self::DEFAULT_PERMALINK_STRUCTURE;
            }
        }

        return $structure;
    }

    private static function get_permalink_tokens(string $structure): array
    {
        preg_match_all('/%[^%]+%/', $structure, $matches);

        return $matches[0] ?? [];
    }

    private static function permalink_structure_to_regex(string $structure): string
    {
        $regex = preg_quote(trim($structure, '/'), '#');

        return str_replace(
            ['%story%', '%postname%', '%post_id%'],
            ['([^/]+)', '([^/]+)', '([0-9]+)'],
            $regex
        );
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

    /** Hiển thị meta box */
    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('chapter_meta_save', 'chapter_meta_nonce');

        $story_id = (int) get_post_meta($post->ID, self::META_STORY_ID, true);
        $number   = (string) get_post_meta($post->ID, self::META_NUMBER, true);
        $selected_story = $story_id ? get_post($story_id) : null;
        $placeholder = esc_attr__('Nhập tên truyện...', 'extend-site');
        ?>
        <p>
            <label for="chapter_story_id">
                <strong><?php esc_html_e('Thuộc truyện', 'extend-site'); ?></strong>
            </label>
        </p>

        <select
            id="chapter_story_id"
            name="chapter_story_id"
            class="widefat"
            data-es-ajax-select
            data-es-type="story"
            data-placeholder="<?php echo $placeholder; ?>"
            aria-label="<?php echo $placeholder; ?>"
        >
            <?php if ($selected_story): ?>
                <?php
                $title = get_the_title($selected_story->ID);
                $count = StoryRepository::get_chapter_total($selected_story->ID);
                ?>
                <option value="<?php echo esc_attr($selected_story->ID); ?>" selected>
                    <?php echo esc_html($title ?: ('#' . $selected_story->ID)); ?>
                    — (<?php echo esc_html($count); ?> <?php esc_html_e('chương', 'extend-site'); ?>)
                </option>
            <?php endif; ?>
        </select>

        <p>
            <label for="chapter_number">
                <strong><?php esc_html_e('Số chương', 'extend-site'); ?></strong>
            </label>
        </p>

        <input
            type="number"
            min="1"
            step="1"
            id="chapter_number"
            name="chapter_number"
            class="small-text"
            value="<?php echo esc_attr($number); ?>"
        />

        <p class="description">
            <?php esc_html_e('Dùng để sắp xếp chương theo thứ tự trong truyện.', 'extend-site'); ?>
        </p>
        <?php
    }

    /** Lưu meta box */
    public function save_meta(int $post_id, WP_Post $post): void
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
        $story_id = isset($_POST['chapter_story_id']) ? (int) $_POST['chapter_story_id'] : 0;
        if ($story_id > 0 && get_post_type($story_id) !== 'story') {
            $story_id = 0;
        }
        update_post_meta($post_id, self::META_STORY_ID, $story_id);

        // number
        $number = isset($_POST['chapter_number']) ? (int) $_POST['chapter_number'] : 0;
        update_post_meta($post_id, self::META_NUMBER, max(0, $number));
    }

    /** Admin columns */
    public function add_admin_columns(array $cols): array
    {
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

    /** Hiển thị nội dung cột admin */
    public function render_admin_columns(string $col, int $post_id): void
    {
        if ($col === 'chapter_story') {
            $sid = (int) get_post_meta($post_id, self::META_STORY_ID, true);
            if ($sid) {
                $title = get_the_title($sid);
                $link = get_edit_post_link($sid);
                echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>' : esc_html($title);
            } else {
                echo '<em>' . esc_html__('(Chưa chọn)', 'extend-site') . '</em>';
            }
        } elseif ($col === 'chapter_number') {
            $n = get_post_meta($post_id, self::META_NUMBER, true);
            echo $n !== '' ? esc_html((string) $n) : '<em>—</em>';
        }
    }

    /** Sắp xếp cột Số chương */
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

    /* ==========================================================
     * CAP NHAT _chapter_count CHO TRUYEN
     * ========================================================== */

    /**
     * @var array<int, int>
     */
    private array $story_ids_before_meta_update = [];

    /**
     * @var array<int, int>
     */
    private array $story_ids_before_delete = [];

    /** Sync story chapter count from actual published chapter data after a chapter save. */
    public function update_story_chapter_count_on_save(int $chapter_id, WP_Post $post): void
    {
        if ($post->post_type !== self::SLUG || wp_is_post_autosave($chapter_id) || wp_is_post_revision($chapter_id)) {
            return;
        }

        $story_id = (int) get_post_meta($chapter_id, self::META_STORY_ID, true);
        if ($story_id > 0) {
            ChapterRepository::sync_count_for_story($story_id);
        }
    }

    /** Sync when a chapter enters or leaves publish status. */
    public function handle_chapter_status_change(string $new_status, string $old_status, WP_Post $post): void
    {
        if ($post->post_type !== self::SLUG || $new_status === $old_status) {
            return;
        }

        if ($new_status !== 'publish' && $old_status !== 'publish') {
            return;
        }

        $story_id = (int) get_post_meta($post->ID, self::META_STORY_ID, true);
        if ($story_id > 0) {
            ChapterRepository::sync_count_for_story($story_id);
        }
    }

    /** Capture the story ID before WordPress deletes chapter meta. */
    public function capture_deleted_chapter_story(int $post_id): void
    {
        if (get_post_type($post_id) !== self::SLUG) {
            return;
        }

        $story_id = (int) get_post_meta($post_id, self::META_STORY_ID, true);
        if ($story_id > 0) {
            $this->story_ids_before_delete[$post_id] = $story_id;
        }
    }

    /** Sync after the chapter has been deleted so the deleted post is not counted. */
    public function sync_deleted_chapter_story(int $post_id): void
    {
        $story_id = $this->story_ids_before_delete[$post_id] ?? 0;
        unset($this->story_ids_before_delete[$post_id]);

        if ($story_id > 0) {
            ChapterRepository::sync_count_for_story($story_id);
        }
    }

    /** Capture the previous story ID before _chapter_story_id is updated. */
    public function capture_story_id_before_meta_update($check, int $post_id, string $meta_key, $meta_value, $prev_value)
    {
        if ($meta_key === self::META_STORY_ID && get_post_type($post_id) === self::SLUG) {
            $this->story_ids_before_meta_update[$post_id] = (int) get_post_meta($post_id, self::META_STORY_ID, true);
        }

        return $check;
    }

    /** Sync old and new stories when _chapter_story_id changes. */
    public function sync_story_count_on_meta_change(int $meta_id, int $post_id, string $meta_key, $meta_value): void
    {
        if ($meta_key !== self::META_STORY_ID || get_post_type($post_id) !== self::SLUG) {
            return;
        }

        $old_story_id = $this->story_ids_before_meta_update[$post_id] ?? 0;
        unset($this->story_ids_before_meta_update[$post_id]);

        $new_story_id = (int) $meta_value;
        foreach (array_unique(array_filter([$old_story_id, $new_story_id])) as $story_id) {
            ChapterRepository::sync_count_for_story((int) $story_id);
        }
    }

    /** Sync the old story when _chapter_story_id is deleted. */
    public function sync_story_count_on_meta_delete(array $meta_ids, int $post_id, string $meta_key, $meta_value): void
    {
        if ($meta_key !== self::META_STORY_ID || get_post_type($post_id) !== self::SLUG) {
            return;
        }

        $story_id = (int) $meta_value;
        if ($story_id > 0) {
            ChapterRepository::sync_count_for_story($story_id);
        }
    }
}
