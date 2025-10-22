<?php
namespace ExtendSite\ElementorAddon\Base;

class ControlOptions
{
    /**
     * Image size options
     */
    public static function image_sizes(): array
    {
        return [
            'thumbnail'    => esc_html__('Thumbnail (150 x 150)', 'extend-site'),
            'medium'       => esc_html__('Medium (300 x 300)', 'extend-site'),
            'medium_large' => esc_html__('Medium Large (768 x auto)', 'extend-site'),
            'large'        => esc_html__('Large (1024 x 1024)', 'extend-site'),
            'full'         => esc_html__('Full size (original)', 'extend-site'),
        ];
    }

    /**
     * Image object-position options
     */
    public static function image_object_positions(): array
    {
        return [
            'center center' => esc_html__('Chính giữa', 'extend-site'),
            'center left'   => esc_html__('Giữa bên trái', 'extend-site'),
            'center right'  => esc_html__('Giữa bên phải', 'extend-site'),
            'top center'    => esc_html__('Trên giữa', 'extend-site'),
            'top left'      => esc_html__('Trên trái', 'extend-site'),
            'top right'     => esc_html__('Trên phải', 'extend-site'),
            'bottom center' => esc_html__('Dưới giữa', 'extend-site'),
            'bottom left'   => esc_html__('Dưới trái', 'extend-site'),
            'bottom right'  => esc_html__('Dưới phải', 'extend-site'),
        ];
    }

    /**
     * Heading tags
     */
    public static function heading_tags(): array
    {
        return [
            'h1' => 'H1',
            'h2' => 'H2',
            'h3' => 'H3',
            'h4' => 'H4',
            'h5' => 'H5',
            'h6' => 'H6',
        ];
    }

    /**
     * Text wrapper tags
     */
    public static function text_wrappers(): array
    {
        return [
            'h1'   => 'H1',
            'h2'   => 'H2',
            'h3'   => 'H3',
            'h4'   => 'H4',
            'h5'   => 'H5',
            'h6'   => 'H6',
            'div'  => 'div',
            'span' => 'span',
            'p'    => 'p',
        ];
    }
}