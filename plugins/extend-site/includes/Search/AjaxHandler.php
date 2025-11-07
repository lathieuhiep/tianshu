<?php
namespace ExtendSite\Search;

defined('ABSPATH') || exit;

class AjaxHandler {
    public const ACTION = 'es_search_story';

    /**
     * Initialize AJAX handlers.
     * @return void
     */
    public static function init(): void {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Handle AJAX search requests.
     * @return void
     */
    public static function handle(): void {
        check_ajax_referer(EXTEND_SITE_NONCE_ACTION, 'security');

        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

        if ( empty($keyword) ) {
            wp_send_json_error(['message' => 'Empty keyword']);
        }

        // search stories
        $story_ids = SearchRepository::search_stories_light($keyword);

        $html = self::render_view($story_ids);

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Render search results view.
     * @param array $story_ids
     * @return string
     */
    public static function render_view(array $story_ids): string
    {
        ob_start();
    ?>
        <ul class="es-search-results es-list-style-none">
            <?php
            if ( !empty($story_ids) ) :
                foreach ($story_ids as $story_id) :

                    $title = get_the_title($story_id);
                    $link  = get_permalink($story_id);
                    $thumb = get_the_post_thumbnail( $story_id, 'thumbnail', [
                        'alt'   => esc_attr( get_the_title( $story_id ) ),
                    ] );
            ?>
                <li class="es-search-results__item es-flex es-gap-2">
                    <a class="story-image" href="<?php echo esc_url($link); ?>">
                        <?php if ( $thumb ) : ?>
                            <?php echo wp_kses_post( $thumb ); ?>
                        <?php else : ?>
                            <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                 alt="<?php echo esc_html( $title ); ?>">
                        <?php endif; ?>
                    </a>

                    <a class="story-link es-fs-sm" href="<?php echo esc_url($link); ?>">
                        <?php echo esc_html($title); ?>
                    </a>
                </li>
            <?php
                endforeach;
            else:
            ?>
                <li class="es-search-no-results">
                    <?php esc_html_e('Không tìm thấy kết quả phù hợp.', 'extend-site'); ?>
                </li>
            <?php endif; ?>
        </ul>
    <?php
        return trim(ob_get_clean());
    }
}
