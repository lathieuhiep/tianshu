<?php
namespace ExtendSite\Search;

defined('ABSPATH') || exit;

/**
 * Shortcode: [es_search_story_form]
 *
 * Example:
 * [es_search_story_form
 *     placeholder="Nhập tên truyện..."
 *     button_label="Tìm"
 *     button_display="icon"
 *     icon_class="ic-search"
 *     show_button="true"
 *     class="is-compact"
 * ]
 */
class SearchShortcode {

    /**
     * Register shortcode on init.
     */
    public static function init(): void {
        add_shortcode('es_search_story_form', [__CLASS__, 'render']);
    }

    /**
     * Render search story form shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render(array $atts = []): string {
        // Enqueue script chỉ khi ở frontend
        if ( ! is_admin() ) {
            wp_enqueue_script('es-widget');
        }

        // Merge defaults from SearchForm
        $atts = shortcode_atts(SearchForm::get_defaults(), $atts, 'es_search_story_form');

        // Sanitize and prepare
        $atts = SearchForm::parse_args($atts);

        ob_start();
        SearchForm::autocomplete($atts);
        return ob_get_clean();
    }
}