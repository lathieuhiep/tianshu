<?php
defined( 'ABSPATH' ) || exit;

// cmb for post type 'post'
function tianshu_cmb_post(): void {
    $cmb = new_cmb2_box([
        'id' => 'tianshu_cmb_post',
        'title' => esc_html__('Tùy chọn metabox', 'tianshu'),
        'object_types' => array('post'),
        'context' => 'normal',
        'priority' => 'low',
        'show_names' => true,
    ]);

    $cmb->add_field([
        'id'   => 'tianshu_cmb_post_title',
        'name' => esc_html__( 'Tiêu đề', 'tianshu' ),
        'type' => 'title',
        'desc' => esc_html__( 'Đây là mô tả tiêu đề', 'tianshu' ),
    ]);
}

add_action('cmb2_admin_init', 'tianshu_cmb_post');
