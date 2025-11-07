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
     * Get default options
     */
    public static function get_defaults(): array {
        return [
            'placeholder' => esc_attr__('Tìm truyện...', 'extend-site'),
            'button_label' => esc_html__('Tìm kiếm', 'extend-site'),
            'allow_html_label' => false,
            'show_button' => true,
            'button_display' => 'text', // 'text' | 'icon'
            'action' => home_url('/' . SearchController::SLUG . '/'),
            'value' => isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '',
            'class' => '',
        ];
    }

    /**
     * Merge with defaults + sanitize
     */
    public static function parse_args(array $args = []): array {
        $args = wp_parse_args($args, self::get_defaults());
        $args['button_display'] = in_array($args['button_display'], ['text', 'icon'], true)
                ? $args['button_display']
                : 'text';

        return $args;
    }

    /**
     * Render the search form.
     *
     * @param string $variant
     * @param array $args Customization options.
     * @return void
     */
    public static function render(string $variant = 'autocomplete', array $args = []): void
    {
        $args = self::parse_args($args);

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
        $form_classes = ['es-search-form'];
        $form_classes[] = 'has-' . sanitize_html_class($args['button_display']);

        if (!empty($args['class'])) {
            $form_classes[] = sanitize_html_class($args['class']);
        }
    ?>
        <div class="es-search-autocomplete-wrapper">
            <form class="<?php echo esc_attr(implode(' ', $form_classes)); ?>"
                  role="search" method="get"
                  action="<?php echo esc_url($args['action']); ?>"
            >
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
                        <?php if ( $args['button_display'] == 'text' ) : ?>
                            <?php echo esc_html($args['button_label']); ?>
                        <?php else : ?>
                            <i class="es-ic-mask es-ic-mask-magnifying-glass"></i>
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