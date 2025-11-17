<?php

namespace ExtendSite\ElementorAddon\Traits;

use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Base\ControlOptions;

defined('ABSPATH') || exit;

/**
 * Trait to add an image size control to Elementor widgets.
 */
trait HasImageSizeControl
{
    /**
     * Adds an image size control to the given Elementor widget.
     *
     * @param object $widget The Elementor widget instance (usually $this).
     * @param string $control_id The ID for the control. Default is 'image_size'.
     * @param string $default The default image size. Default is 'large'.
     * @param array $args Additional arguments to merge with the base args.
     */
    protected function addImageSizeControl(
        object $widget,
        string $control_id = 'image_size',
        string $default = 'large',
        array  $args = []
    ): void
    {
        $base_args = [
            'label' => esc_html__('Độ phân giải ảnh', 'extend-site'),
            'type' => Controls_Manager::SELECT,
            'default' => $default,
            'options' => ControlOptions::image_sizes(),
            'label_block' => true,
        ];

        // $this là chính widget đang dùng trait
        $widget->add_control($control_id, array_merge($base_args, $args));
    }

    /**
     * Adds an image ratio control to the given Elementor widget.
     *
     * @param object $widget The Elementor widget instance (usually $this).
     * @param string $control_id The ID for the control. Default is 'image_ratio'.
     * @param string $default The default image ratio. Default is '4:5'.
     * @param array $args Additional arguments to merge with the base args.
     */
    protected function addImageRatioControl(
        object $widget,
        string $control_id = 'image_ratio',
        string $default = '4:5',
        array  $args = []
    ): void
    {
        $base_args = [
            'label' => esc_html__('Tỷ lệ ảnh', 'extend-site'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                '16-9' => '16:9',
                '4-3' => '4:3',
                '1-1' => '1:1',
                '4-5' => '4:5',
                '3-4' => '3:4',
                '2-3' => '2:3',
                'auto' => esc_html__('Tự động', 'extend-site'),
            ],
            'label_block' => true,
            'default' => $default,
            'prefix_class' => 'es-ratio-', // Tạo class cho wrapper widget.
        ];

        $widget->add_control($control_id, array_merge($base_args, $args));
    }
}