<?php
/**
 * Admin logic: link Story ↔ Chapter management
 *
 * @package ExtendSite\Admin
 */

namespace ExtendSite\Admin;

use ExtendSite\PostType\ChapterPostType;
use ExtendSite\Repositories\ChapterRepository;
use WP_Post;
use WP_Query;

defined('ABSPATH') || exit;

class StoryChapterLink {

    public static function init(): void {
        // Trong danh sách truyện
        add_filter('manage_story_posts_columns', [__CLASS__, 'add_story_column']);
        add_action('manage_story_posts_custom_column', [__CLASS__, 'render_story_column'], 10, 2);

        // Trong sidebar của truyện
        add_action('post_submitbox_misc_actions', [__CLASS__, 'show_story_actions_in_sidebar']);

        // Trong danh sách chương
        add_action('pre_get_posts', [__CLASS__, 'filter_chapters_by_story']);
        add_action('all_admin_notices', [__CLASS__, 'show_story_filter_notice']);

        // Khi tạo chương mới từ URL có story_id
        add_action('load-post-new.php', [__CLASS__, 'handle_new_chapter_story_id']);
        add_action('save_post_chapter', [__CLASS__, 'assign_story_to_new_chapter'], 10, 3);

        // Trong sidebar của chương
        add_action('post_submitbox_misc_actions', [__CLASS__, 'show_chapter_story_box']);

        // Preserve story_id filter in search box
        add_action('restrict_manage_posts', [__CLASS__, 'preserve_story_id_in_search']);

        add_action('admin_head', [__CLASS__, 'hide_add_new_button_everywhere']);
    }

    /** -------------------------
     *  Danh sách truyện (admin)
     * ------------------------- */
    public static function add_story_column(array $columns): array {
        $columns['chapters'] = esc_html__('Chương', 'extend-site');
        return $columns;
    }

    public static function render_story_column(string $column, int $post_id): void {
        if ($column !== 'chapters') {
            return;
        }

        self::load_view('story-chapter-actions', [
            'add_url' => admin_url("post-new.php?post_type=chapter&story_id={$post_id}"),
            'list_url' => admin_url("edit.php?post_type=chapter&story_id={$post_id}"),
            'wrapper_class' => 'story-chapter-actions',
            'add_label' => __('Thêm', 'extend-site'),
            'list_label' => __('Danh sách', 'extend-site'),
            'button_size_class' => 'button-small',
        ]);
    }
    /**
     * Hiển thị 2 nút hành động trong sidebar khi chỉnh sửa truyện
     * (Thêm chương mới / Danh sách chương)
     */
    public static function show_story_actions_in_sidebar(): void {
        global $post;

        if ( !isset($post)
            || $post->post_type !== 'story'
            || empty($post->ID)
            || $post->post_status === 'auto-draft'
        ) {
            return;
        }

        $story_id = $post->ID;

        self::load_view('story-chapter-actions', [
            'add_url' => admin_url("post-new.php?post_type=chapter&story_id={$story_id}"),
            'list_url' => admin_url("edit.php?post_type=chapter&story_id={$story_id}"),
            'wrapper_class' => 'misc-pub-section story-chapter-actions',
            'add_label' => __('Thêm chương', 'extend-site'),
            'list_label' => __('Danh sách chương', 'extend-site'),
            'button_size_class' => '',
        ]);
    }
    /** -------------------------
     *  Danh sách chương (admin)
     * ------------------------- */
    public static function filter_chapters_by_story(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'edit.php' || $query->get('post_type') !== 'chapter') {
            return;
        }

        $story_id = isset($_GET['story_id']) ? (int) $_GET['story_id'] : 0;

        if ($story_id > 0) {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'key'     => ChapterPostType::META_STORY_ID,
                'value'   => $story_id,
                'compare' => '=',
            ];
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Hiển thị thông báo khi đang lọc theo truyện trong danh sách chương (admin)
     * Hiển thị tách riêng hẳn khỏi filter bar, dùng hook all_admin_notices.
     */
    public static function show_story_filter_notice(): void {
        global $pagenow, $typenow;

        if ($pagenow !== 'edit.php' || $typenow !== 'chapter' || empty($_GET['story_id'])) {
            return;
        }

        $story_id = absint(wp_unslash($_GET['story_id']));
        $story = get_post($story_id);

        if (!$story) {
            return;
        }

        self::load_view('story-filter-notice', [
            'story_title' => get_the_title($story),
            'story_edit_url' => get_edit_post_link($story_id),
            'add_url' => admin_url("post-new.php?post_type=chapter&story_id={$story_id}"),
            'all_url' => admin_url('edit.php?post_type=chapter'),
            'has_search' => !empty($_GET['s']),
        ]);
    }
    /** -------------------------
     *  Tạo chương mới từ truyện
     * ------------------------- */
    public static function handle_new_chapter_story_id(): void {
        if (empty($_GET['post_type']) || $_GET['post_type'] !== 'chapter') {
            return;
        }

        if (empty($_GET['story_id'])) {
            return;
        }

        $story_id = absint(wp_unslash($_GET['story_id']));
        if ($story_id > 0) {
            set_transient('es_chapter_new_story_id', $story_id, 30);
        }
    }

    /**
     * Gán truyện cho chương mới tạo
     * @param int     $post_id ID của chương mới tạo
     * @param WP_Post $post    Đối tượng WP_Post của chương mới tạo
     * @param bool    $update  Có phải đang cập nhật hay không
     * @return void
    */
    public static function assign_story_to_new_chapter(int $post_id, WP_Post $post, bool $update): void {
        if ($update) {
            return;
        }

        $story_id = (int) get_transient('es_chapter_new_story_id');
        if ($story_id <= 0) {
            return;
        }

        // Gán truyện
        update_post_meta($post_id, ChapterPostType::META_STORY_ID, $story_id);
        delete_transient('es_chapter_new_story_id');

        // Lấy số chương kế tiếp từ Repository
        $next_number = ChapterRepository::get_next_number_by_story($story_id);
        update_post_meta($post_id, ChapterPostType::META_NUMBER, $next_number);
    }

    /** -------------------------
     *  Hiển thị “Thuộc truyện” trong sidebar
     * ------------------------- */
    public static function show_chapter_story_box(): void {
        global $post;

        if ($post->post_type !== 'chapter') {
            return;
        }

        $story_id = ChapterRepository::get_story_id($post->ID);
        if ($story_id <= 0) {
            return;
        }

        $story = get_post($story_id);
        if (!$story) {
            return;
        }

        self::load_view('chapter-story-box', [
            'story_title' => get_the_title($story),
            'story_url' => get_edit_post_link($story_id),
            'add_url' => admin_url("post-new.php?post_type=chapter&story_id={$story_id}"),
            'list_url' => admin_url("edit.php?post_type=chapter&story_id={$story_id}"),
            'is_draft' => in_array($post->post_status, ['auto-draft', 'draft'], true),
        ]);
    }
    /**
     * Giữ lại story_id trong form tìm kiếm để không bị mất khi search.
     */
    public static function preserve_story_id_in_search(string $post_type): void {
        if ($post_type !== 'chapter' || empty($_GET['story_id'])) {
            return;
        }

        $story_id = absint(wp_unslash($_GET['story_id']));
        echo '<input type="hidden" name="story_id" value="' . esc_attr($story_id) . '">';
    }

    /**
     * Ẩn nút "Thêm mới" ở các màn chương có flow tạo chương qua truyện.
     */
    public static function hide_add_new_button_everywhere(): void {
        $screen = get_current_screen();
        if (! $screen || $screen->post_type !== 'chapter') {
            return;
        }

        if ($screen->base === 'post' || ($screen->base === 'edit' && !empty($_GET['story_id']))) {
            self::load_view('hide-chapter-add-new-style');
        }
    }

    private static function load_view(string $view, array $data = []): void
    {
        $file = plugin_dir_path(__FILE__) . 'views/' . $view . '.php';
        if (!is_file($file)) {
            return;
        }

        extract($data);
        include $file;
    }
}
