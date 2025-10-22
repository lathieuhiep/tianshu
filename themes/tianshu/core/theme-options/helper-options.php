<?php
defined( 'ABSPATH' ) || exit;

// prefix option
const BASICTHEME_PREFIX_THEME_OPTIONS = 'tianshu_options';

// A Custom function for get an option
if ( ! function_exists( 'tianshu_get_option' ) ) {
    function tianshu_get_option( $option = '', $default = null ) {
        $options = get_option( BASICTHEME_PREFIX_THEME_OPTIONS );

        return ( isset( $options[ $option ] ) ) ? $options[ $option ] : $default;
    }
}

// column width
function tianshu_column_width_fields($min = 1, $max = 12, $sm = 12, $md = 6, $lg = 3, $xl = 3): array
{
    return [
        [
            'id'      => 'sm',
            'type'    => 'slider',
            'title'   => esc_html__( 'sm: ≥576px', 'tianshu' ),
            'default' => $sm,
            'min'     => $min,
            'max'     => $max,
        ],

        [
            'id'      => 'md',
            'type'    => 'slider',
            'title'   => esc_html__( 'md: ≥768px', 'tianshu' ),
            'default' => $md,
            'min'     => $min,
            'max'     => $max,
        ],

        [
            'id'      => 'lg',
            'type'    => 'slider',
            'title'   => esc_html__( 'lg: ≥992px', 'tianshu' ),
            'default' => $lg,
            'min'     => $min,
            'max'     => $max,
        ],

        [
            'id'      => 'xl',
            'type'    => 'slider',
            'title'   => esc_html__( 'xl: ≥1200px', 'tianshu' ),
            'default' => $xl,
            'min'     => $min,
            'max'     => $max,
        ],
    ];
}

//
function tianshu_get_responsive_row_class($option_key): string
{
    $per_row = tianshu_get_option($option_key);

    if ( empty( $per_row ) || ! is_array( $per_row ) ) {
        $per_row = [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 3
        ];
    }

    return sprintf(
        'theme-row-cols-sm-%s theme-row-cols-md-%s theme-row-cols-lg-%s theme-row-cols-xl-%s',
        $per_row['sm'],
        $per_row['md'],
        $per_row['lg'],
        $per_row['xl']
    );
}