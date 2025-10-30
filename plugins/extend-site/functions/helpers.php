<?php
// check if Elementor is active
function es_check_elementor_builder()
{
    return get_post_meta(get_the_ID(), '_elementor_edit_mode', true);
}

// get all categories
function es_get_tax_list(string $taxonomy): array
{
    $options = [];

    if (!taxonomy_exists($taxonomy)) {
        return $options;
    }

    $terms = get_terms([
        'taxonomy' => $taxonomy,
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
function es_get_form_cf7(): array
{
    $options = array();

    if (function_exists('wpcf7')) {
        $wpcf7_form_list = get_posts(array(
            'post_type' => 'wpcf7_contact_form',
            'numberposts' => -1,
        ));

        $options[0] = esc_html__('Select a Contact Form', 'extend-site');

        if (!empty($wpcf7_form_list) && !is_wp_error($wpcf7_form_list)) :
            foreach ($wpcf7_form_list as $item) :
                $options[$item->ID] = $item->post_title;
            endforeach;
        else :
            $options[0] = esc_html__('Create a Form First', 'extend-site');
        endif;
    }

    return $options;
}

// pagination
function es_pagination(): void
{
    the_posts_pagination(array(
        'type' => 'list',
        'mid_size' => 2,
        'prev_text' => '<i class="es-ic-mask es-ic-mask-angle-left"></i>',
        'next_text' => '<i class="es-ic-mask es-ic-mask-angle-right"></i>',
        'screen_reader_text' => '&nbsp;',
    ));
}

/**
 * Chuyển đổi thời gian từ ngày tháng thành định dạng thân thiện với người dùng
 * (ví dụ: 5 phút trước, 1 năm trước).
 *
 * @param int|string $time Thời gian đầu vào (Timestamp hoặc chuỗi ngày tháng).
 * @return string Chuỗi thời gian đã định dạng.
 */
function es_display_time_ago(int|string $time ): string
{

    if ( is_string( $time ) ) {
        $timestamp = strtotime( $time );
        var_dump($timestamp);
    } elseif ( is_numeric( $time ) ) {
        $timestamp = (int) $time;
    } else {
        return '';
    }

    if ( $timestamp === false || $timestamp <= 0 ) {
        return '';
    }

    $current_time = current_time( 'timestamp' );
    $time_difference = human_time_diff( $timestamp, $current_time );

    return sprintf( '%s trước', $time_difference );
}