<?php

namespace ExtendSite\ElementorAddon\Traits;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined('ABSPATH') || exit;

trait HasPostItemControls
{
    /**
     * Thêm nhóm control "Post Item" cho widget.
     * - $widget: instance Widget_Base (truyền $this)
     * - $section_id: id section control (mặc định content_post_item)
     * - $args: các tuỳ chọn enable/disable control
     *      + show_category (bool): hiện chuyên mục
     *      + show_excerpt (bool): hiện trích đoạn
     *      + read_more (bool): hiện nút đọc tiếp
     *      + heading_tag (bool): chọn thẻ HTML tiêu đề
     * - $after_controls: callback($widget) để chèn control bổ sung
     */
    public function addPostItemControls(
        Widget_Base $widget,
        ?string     $section_id = null,
        array       $args = [],
        ?callable   $after_controls = null
    ): void
    {
        $section_id = $section_id ?: 'content_post_item';

        // Default enable states
        $defaults = [
            'show_category' => true,
            'show_excerpt' => true,
            'read_more' => true,
            'heading_tag' => true,
        ];
        $args = wp_parse_args($args, $defaults);

        // Post Item Section
        $widget->start_controls_section(
            $section_id,
            [
                'label' => esc_html__('Post Item', 'extend-site'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        if ($args['show_category']) {
            $widget->add_control(
                'post_show_category',
                [
                    'label' => esc_html__('Show Category', 'extend-site'),
                    'type' => Controls_Manager::SWITCHER,
                    'return_value' => 'yes',
                    'default' => 'yes',
                    'toggle' => true,
                ]
            );
        }

        if ($args['show_excerpt']) {
            $widget->add_control(
                'post_show_excerpt',
                [
                    'label' => esc_html__('Show Excerpt', 'extend-site'),
                    'type' => Controls_Manager::SWITCHER,
                    'return_value' => 'yes',
                    'default' => 'yes',
                    'toggle' => true,
                ]
            );

            $widget->add_control(
                'post_excerpt_length',
                [
                    'label' => esc_html__('Excerpt Words', 'extend-site'),
                    'type' => Controls_Manager::NUMBER,
                    'min' => 5,
                    'max' => 80,
                    'step' => 1,
                    'default' => 18,
                    'condition' => ['post_show_excerpt' => 'yes'],
                ]
            );
        }

        if ($args['read_more']) {
            $widget->add_control('post_show_read_more', [
                'label' => esc_html__('Show Read More', 'extend-site'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'toggle' => true,
            ]);

            $widget->add_control('post_read_more_label', [
                'label' => esc_html__('Read More Label', 'extend-site'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Read more', 'extend-site'),
                'placeholder' => esc_html__('Read more', 'extend-site'),
                'condition' => ['post_show_read_more' => 'yes'],
            ]);
        }

        if ($args['heading_tag']) {
            $widget->add_control(
                'post_heading_tag',
                [
                    'label' => esc_html__('Title HTML Tag', 'extend-site'),
                    'type' => Controls_Manager::SELECT,
                    'options' => [
                        'h2' => 'H2',
                        'h3' => 'H3',
                        'h4' => 'H4',
                    ],
                    'default' => 'h3',
                ]
            );
        }

        if (is_callable($after_controls)) {
            call_user_func($after_controls, $widget);
        }

        $widget->end_controls_section();
    }
}