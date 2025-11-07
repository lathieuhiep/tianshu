<?php

namespace ExtendSite\Widgets;

use ExtendSite\Search\SearchForm;
use WP_Widget;

defined('ABSPATH') || exit;

/**
 * Widget: Search Story Form
 *
 * Hiển thị form tìm kiếm truyện trong khu vực widget.
 *
 * @package ExtendSite
 */
class SearchStoryWidget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'es_search_story_widget',
            esc_html__('Extend Site: Tìm kiếm truyện', 'extend-site'),
            [
                'classname' => 'es-search-story-widget',
                'description' => esc_html__('Hiển thị form tìm kiếm truyện.', 'extend-site'),
            ]
        );
    }

    /**
     * Render widget content on frontend.
     *
     * @param array $args
     * @param array $instance
     * @return void
     */
    public function widget($args, $instance): void
    {
        // Enqueue script chỉ khi ở frontend
        if ( ! is_admin() ) {
            wp_enqueue_script('es-widget');
        }

        echo $args['before_widget'];

        // get title
        $title = apply_filters('widget_title', $instance['title'] ?? '');

        // display title
        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        // Render form search
        SearchForm::render('autocomplete', [
            'placeholder' => $instance['placeholder'] ?? '',
            'show_button' => !empty($instance['show_button']),
            'button_display' => $instance['button_display'] ?? 'text'
        ]);

        echo $args['after_widget'];
    }

    /**
     * Form hiển thị trong admin.
     *
     * @param array $instance
     * @return void
     */
    public function form($instance): void
    {
        $title = $instance['title'] ?? esc_html__('Tìm kiếm truyện', 'extend-site');
        $placeholder = $instance['placeholder'] ?? esc_attr__('Tìm kiếm...', 'extend-site');
        $show_button = !isset($instance['show_button']) || (bool)$instance['show_button'];
        ?>
            <!--title field-->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                    <?php esc_html_e('Tiêu đề:', 'extend-site'); ?>
                </label>

                <input class="widefat"
                       id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                       type="text"
                       value="<?php echo esc_attr($title); ?>">
            </p>

            <!--placeholder field-->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('placeholder')); ?>">
                    <?php esc_html_e('Placeholder:', 'extend-site'); ?>
                </label>

                <input class="widefat"
                       id="<?php echo esc_attr($this->get_field_id('placeholder')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('placeholder')); ?>"
                       type="text"
                       value="<?php echo esc_attr($placeholder); ?>"
                >
            </p>

            <!--show_button field-->
            <p>
                <input class="checkbox"
                       type="checkbox"
                       id="<?php echo esc_attr($this->get_field_id('show_button')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('show_button')); ?>"
                    <?php checked($show_button); ?>
                />

                <label for="<?php echo esc_attr($this->get_field_id('show_button')); ?>">
                    <?php esc_html_e('Hiển thị nút tìm kiếm', 'extend-site'); ?>
                </label>
            </p>

            <!--button_display field-->
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('button_display')); ?>">
                    <?php esc_html_e('Kiểu nút:', 'extend-site'); ?>
                </label><br>

                <select id="<?php echo esc_attr($this->get_field_id('button_display')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('button_display')); ?>">
                    <option value="text" <?php selected(($instance['button_display'] ?? 'text'), 'text'); ?>>
                        <?php esc_html_e('Chỉ hiện chữ', 'extend-site'); ?>
                    </option>
                    <option value="icon" <?php selected(($instance['button_display'] ?? 'text'), 'icon'); ?>>
                        <?php esc_html_e('Chỉ hiện icon', 'extend-site'); ?>
                    </option>
                </select>
            </p>
        <?php
    }

    /**
     * Lưu dữ liệu khi update widget.
     *
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    public function update($new_instance, $old_instance): array
    {
        $instance = [];

        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');
        $instance['placeholder'] = sanitize_text_field($new_instance['placeholder'] ?? '');
        $instance['show_button'] = !empty($new_instance['show_button']);
        $instance['button_display'] = in_array($new_instance['button_display'] ?? 'text', ['text', 'icon'], true)
                ? $new_instance['button_display']
                : 'text';

        return $instance;
    }
}