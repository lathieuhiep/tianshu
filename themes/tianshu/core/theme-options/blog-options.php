<?php
//
// -> Create a section blog (parent)
CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'id'    => 'opt_post_section',
	'icon'  => 'fas fa-blog',
	'title' => esc_html__( 'Bài viết', 'tianshu' ),
) );

// Category Post
CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent'      => 'opt_post_section',
	'title'       => esc_html__( 'Danh mục', 'tianshu' ),
	'description' => esc_html__( 'Sử dụng cho các trang archive, index, tìm kiếm', 'tianshu' ),
	'fields'      => array(
		// Sidebar
		array(
			'id'      => 'opt_post_cat_sidebar_position',
			'type'    => 'select',
			'title'   => esc_html__( 'Vị trí sidebar', 'tianshu' ),
			'options' => array(
				'hide'  => esc_html__( 'Ẩn', 'tianshu' ),
				'left'  => esc_html__( 'Trái', 'tianshu' ),
				'right' => esc_html__( 'Phải', 'tianshu' ),
			),
			'default' => 'right'
		),

		// Per Row
        array(
            'id'         => 'opt_post_cat_per_row',
            'type'       => 'fieldset',
            'title'      => esc_html__( 'Số bài viết trên mỗi hàng', 'tianshu' ),
            'fields'     => tianshu_column_width_fields(1, 4, 1, 2, 3, 3),
        ),
	)
) );

// Single Post
CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent' => 'opt_post_section',
	'title'  => esc_html__( 'Bài viết chi tiết', 'tianshu' ),
	'fields' => array(
		array(
			'id'      => 'opt_post_single_sidebar_position',
			'type'    => 'select',
			'title'   => esc_html__( 'Vị trí sidebar', 'tianshu' ),
			'options' => array(
				'hide'  => esc_html__( 'Ẩn', 'tianshu' ),
				'left'  => esc_html__( 'Trái', 'tianshu' ),
				'right' => esc_html__( 'Phải', 'tianshu' ),
			),
			'default' => 'right'
		),

        // heading related
        array(
            'type'    => 'heading',
            'content' => esc_html__('Bài viết liên quan', 'tianshu'),
        ),

		// Show related post
		array(
			'id'         => 'opt_post_single_related',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Hiện thị', 'tianshu' ),
			'text_on'    => esc_html__( 'Có', 'tianshu' ),
			'text_off'   => esc_html__( 'Không', 'tianshu' ),
			'default'    => true,
			'text_width' => 80
		),

		// Limit related post
		array(
			'id'      => 'opt_post_single_related_limit',
			'type'    => 'number',
			'title'   => esc_html__( 'Số lượng', 'tianshu' ),
			'default' => 3,
		),
	)
) );