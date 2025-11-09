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

        $add_url  = admin_url("post-new.php?post_type=chapter&story_id={$post_id}");
        $list_url = admin_url("edit.php?post_type=chapter&story_id={$post_id}");
        ?>
        <div class="story-chapter-actions">
            <a href="<?= esc_url($add_url); ?>" class="button button-small button-primary">
                <?= esc_html__('Thêm', 'extend-site'); ?>
            </a>
            <a href="<?= esc_url($list_url); ?>" class="button button-small">
                <?= esc_html__('Danh sách', 'extend-site'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Hiển thị 2 nút hành động trong sidebar khi chỉnh sửa truyện
     * (Thêm chương mới / Danh sách chương)
     */
    public static function show_story_actions_in_sidebar(): void {
        global $post;

        // Chỉ áp dụng cho CPT story
        if ( !isset($post)
            || $post->post_type !== 'story'
            || empty($post->ID)
            || $post->post_status === 'auto-draft'
        ) {
            // Chưa có ID hoặc đang ở trạng thái bản nháp tự động
            return;
        }

        $story_id = $post->ID;

        $add_url  = admin_url("post-new.php?post_type=chapter&story_id={$story_id}");
        $list_url = admin_url("edit.php?post_type=chapter&story_id={$story_id}");
        ?>
        <div class="misc-pub-section story-chapter-actions">
            <a href="<?= esc_url($add_url); ?>" class="button button-primary">
                <?= esc_html__('Thêm chương', 'extend-site'); ?>
            </a>
            <a href="<?= esc_url($list_url); ?>" class="button">
                <?= esc_html__('Danh sách chương', 'extend-site'); ?>
            </a>
        </div>
        <?php
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

        // Chỉ hiển thị trong trang danh sách chương, có story_id trên URL
        if ($pagenow !== 'edit.php' || $typenow !== 'chapter' || empty($_GET['story_id'])) {
            return;
        }

        $story_id = (int) $_GET['story_id'];
        $story    = get_post($story_id);

        if (!$story) {
            return;
        }

        $story_title = esc_html(get_the_title($story));
        $story_edit_url = get_edit_post_link($story_id);
        $add_url     = admin_url("post-new.php?post_type=chapter&story_id={$story_id}");
        $all_url     = admin_url('edit.php?post_type=chapter');
        ?>
        <div class="wrap">
            <div class="story-filter-notice">
                <div class="notice notice-info inline">
                    <div class="notice-info__data">
                        <p>
                            <span class="dashicons dashicons-book-alt"></span>
                            <?= sprintf(
                                __('Danh sách chương của truyện: <a href="%s"><strong>%s</strong></a>', 'extend-site'),
                                esc_url($story_edit_url),
                                $story_title
                            ); ?>
                        </p>

                        <p>
                            <a href="<?= esc_url($add_url); ?>" class="button button-primary">
                                <?= esc_html__('Thêm chương mới', 'extend-site'); ?>
                            </a>
                            <a href="<?= esc_url($all_url); ?>" class="button">
                                <?= esc_html__('Xem tất cả chương', 'extend-site'); ?>
                            </a>
                        </p>
                    </div>

                    <?php
                    if (!empty($_GET['s'])) {
                        printf(
                            '<p class="notice-search">%s</p>',
                            sprintf(__('Kết quả tìm kiếm trong truyện: “%s”', 'extend-site'), $story_title)
                        );
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
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

        $story_id = (int) $_GET['story_id'];
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
    public static function assign_story_to_new_chapter(int $post_id, \WP_Post $post, bool $update): void {
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

        $story_title = esc_html(get_the_title($story));
        $story_url   = get_edit_post_link($story_id);
        ?>
        <div class="misc-pub-section">
            <p>
                <span class="dashicons dashicons-book-alt"></span>
                <?= sprintf(__('Thuộc truyện: <a href="%s"><strong>%s</strong></a>', 'extend-site'), esc_url($story_url), $story_title); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Giữ lại story_id trong form tìm kiếm để không bị mất khi search.
     */
    public static function preserve_story_id_in_search(string $post_type): void {
        if ($post_type !== 'chapter' || empty($_GET['story_id'])) {
            return;
        }

        $story_id = (int) $_GET['story_id'];
        echo '<input type="hidden" name="story_id" value="' . esc_attr($story_id) . '">';
    }
}