<?php

namespace ExtendSite\Ajax;

use WP_Query;
use ExtendSite\PostType\ChapterPostType;

defined('ABSPATH') || exit;

/**
 * Handle AJAX & reusable rendering for chapter list.
 */
class LoadChapters
{

    public const ACTION = 'load_chapters';
    public const NONCE = 'load_chapters_nonce';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Handle AJAX request
     */
    public static function handle(): void
    {
        check_ajax_referer(self::NONCE, 'security');

        $story_id = absint($_POST['story_id'] ?? 0);
        $page = max(1, (int)($_POST['page'] ?? 1));
        $per_page = min(50, (int)($_POST['per_page'] ?? 10));

        if (!$story_id || !get_post($story_id)) {
            wp_send_json_error(['message' => __('Invalid story ID.', 'extend-site')]);
        }

        $html = self::render($story_id, $page, $per_page);
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Render HTML (can be reused in template part)
     */
    public static function render(int $story_id, int $page = 1, int $per_page = 10): string
    {
        $query = new WP_Query([
                'post_type' => ChapterPostType::SLUG,
                'meta_query' => [
                    [
                        'key' => ChapterPostType::META_STORY_ID,
                        'value' => $story_id,
                    ],
                ],
                'orderby' => [
                        ChapterPostType::META_NUMBER => 'ASC',
                ],
                'posts_per_page' => $per_page,
                'paged' => $page,
                'no_found_rows' => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'fields' => 'ids', // nếu chỉ cần ID để render sau
        ]);


        return self::render_view($query, $page);
    }

    /**
     * Render the HTML view for chapters
     */
    private static function render_view(WP_Query $query, int $page): string
    {
        ob_start();

        if ($query->have_posts()) :
            ?>
            <div class="chapter-list es-flex es-flex-column es-row-gap-3">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <a href="<?php the_permalink(); ?>"
                       class="chapter-list__item es-flex es-flex-align-center es-flex-justify-space-between es-col-gap-3">
                        <span class="chapter-title"><?php the_title(); ?></span>
                        <span class="chapter-date"><?php echo esc_html(get_the_date('d/m/Y')); ?></span>
                    </a>
                <?php endwhile; ?>
            </div>

            <div class="chapter-pagination es-pagination text-center mt-6">
                <?php
                echo paginate_links([
                        'base' => add_query_arg('chap_page', '%#%'),
                        'format' => '',
                        'current' => $page,
                        'total' => $query->max_num_pages,
                        'prev_text' => '<i class="es-ic-mask es-ic-mask-angle-left"></i>',
                        'next_text' => '<i class="es-ic-mask es-ic-mask-angle-right"></i>',
                        'type' => 'plain',
                ]);
                ?>
            </div>
            <?php
            wp_reset_postdata();
        else :
            ?>
            <p class="chapter-empty text-center py-4">
                <?php esc_html_e('Chưa có chương nào.', 'extend-site'); ?>
            </p>
        <?php
        endif;

        return trim(ob_get_clean());
    }
}