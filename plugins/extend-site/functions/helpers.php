<?php
// check if Elementor is active
function es_check_elementor_builder()
{
    return get_post_meta( get_the_ID(), '_elementor_edit_mode', true );
}

// get all categories
function es_get_tax_list( string $taxonomy ): array
{
    $options = [];

    if (!taxonomy_exists($taxonomy)) {
        return $options;
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return $options;
    }

    foreach ($terms as $t) {
        $options[$t->term_id] = $t->name;
    }

    return $options;
}

// Get Contact Form 7
function es_get_form_cf7(): array {
    $options = array();

    if ( function_exists( 'wpcf7' ) ) {
        $wpcf7_form_list = get_posts( array(
            'post_type'   => 'wpcf7_contact_form',
            'numberposts' => - 1,
        ) );

        $options[0] = esc_html__( 'Select a Contact Form', 'extend-site' );

        if ( ! empty( $wpcf7_form_list ) && ! is_wp_error( $wpcf7_form_list ) ) :
            foreach ( $wpcf7_form_list as $item ) :
                $options[ $item->ID ] = $item->post_title;
            endforeach;
        else :
            $options[0] = esc_html__( 'Create a Form First', 'extend-site' );
        endif;
    }

    return $options;
}

// pagination
function es_pagination(): void {
    the_posts_pagination( array(
        'type'               => 'list',
        'mid_size'           => 2,
        'prev_text'          => esc_html__( 'Trước', 'extend-site' ),
        'next_text'          => esc_html__( 'Sau', 'extend-site' ),
        'screen_reader_text' => '&nbsp;',
    ) );
}