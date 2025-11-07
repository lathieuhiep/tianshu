<?php

namespace ExtendSite\Search;

defined('ABSPATH') || exit;

/**
 * Renders a reusable search form component.
 *
 * Can be used in shortcode, widget, or directly in templates.
 */
class SearchForm
{

    /**
     * Render the search form.
     *
     * @param string $variant
     * @param array $args Customization options.
     * @return void
     */
    public static function render(string $variant = 'autocomplete', array $args = []): void
    {
        $defaults = [
            'placeholder' => esc_attr__('Tìm truyện...', 'extend-site'),
            'button_label' => esc_html__('Tìm kiếm', 'extend-site'),
            'allow_html_label' => false,
            'show_button' => true,
            'action' => home_url('/' . SearchController::SLUG . '/'),
            'value' => isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '',
        ];

        $args = wp_parse_args($args, $defaults);

        switch ($variant) :
            case 'autocomplete':
            default:
                self::autocomplete($args);
                break;
        endswitch;
    }

    /**
     * Render the autocomplete search form variant.
     *
     * @param array $args Customization options.
     * @return void
     */
    public static function autocomplete(array $args): void
    {
        ?>
        <div class="es-search-autocomplete-wrapper">
            <form class="es-search-form" role="search" method="get" action="<?php echo esc_url($args['action']); ?>">
                <input type="text"
                       name="q"
                       class="es-search-input es-input"
                       placeholder="<?php echo esc_attr($args['placeholder']); ?>"
                       value="<?php echo esc_attr($args['value']); ?>"
                       aria-label="<?php echo esc_attr($args['placeholder']); ?>"
                       autocomplete="off"
                />

                <?php if ($args['show_button']) : ?>
                    <button type="submit" class="es-search-button">
                        <?php if ($args['allow_html_label']) : ?>
                            <?php echo wp_kses_post($args['button_label']); ?>
                        <?php else : ?>
                            <span><?php echo esc_html($args['button_label']); ?></span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </form>

            <div class="results-autocomplete es-custom-scrollbar">
                <div class="es-loading es-flex es-flex-column es-flex-align-center es-row-gap-2" hidden>
                    <span class="es-spinner"></span>
                    <span class="text-load"><?php esc_html_e('Đang tìm...', 'extend-site'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
}