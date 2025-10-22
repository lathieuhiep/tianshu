<?php
//
//  Create a section shop
CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'id'    => 'opt_shop_section',
	'title' => esc_html__( 'Của hàng', 'tianshu' ),
	'icon'  => 'fas fa-shopping-cart',
) );

// Category product
CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent'      => 'opt_shop_section',
	'title'       => esc_html__( 'Danh mục', 'tianshu' ),
	'description' => esc_html__( 'Sử dụng cho danh mục và thẻ cửa hàng', 'tianshu' ),
	'fields'      => array(
		// Sidebar
		array(
			'id'      => 'opt_shop_cat_sidebar_position',
			'type'    => 'select',
			'title'   => esc_html__( 'Vị trí sidebar', 'tianshu' ),
			'options' => array(
				'hide'  => esc_html__( 'Ẩn', 'tianshu' ),
				'left'  => esc_html__( 'Trái', 'tianshu' ),
				'right' => esc_html__( 'Phải', 'tianshu' ),
			),
			'default' => 'left'
		),

		// Limit
		array(
			'id'      => 'opt_shop_cat_limit',
			'type'    => 'number',
			'title'   => esc_html__( 'Số lượng sản phẩm hiển thị', 'tianshu' ),
			'default' => 12,
		),

		// Per Row
        array(
            'id' => 'opt_shop_cat_per_row',
            'type' => 'fieldset',
            'title' => esc_html__('Số sản phẩm trên một hàng', 'tianshu'),
            'fields' => tianshu_column_width_fields(1, 4, 1, 2, 3, 3),
        ),
	)
) );

// Single product
CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent'      => 'opt_shop_section',
	'title'       => esc_html__( 'Chi tiết', 'tianshu' ),
	'description' => esc_html__( 'Sử dụng cho chi tiết sản phẩm', 'tianshu' ),
	'fields'      => array(
		// Sidebar
		array(
			'id'      => 'opt_shop_single_sidebar_position',
			'type'    => 'select',
			'title'   => esc_html__( 'Vị trí sidebar', 'tianshu' ),
			'options' => array(
				'hide'  => esc_html__( 'Ẩn', 'tianshu' ),
				'left'  => esc_html__( 'Trái', 'tianshu' ),
				'right' => esc_html__( 'Phải', 'tianshu' ),
			),
			'default' => 'left'
		),

        // heading related
        array(
            'type'    => 'heading',
            'content' => esc_html__('Sản phẩm liên quan', 'tianshu'),
        ),

        // Limit related
		array(
            'id'      => 'opt_shop_single_related_limit',
            'type'    => 'number',
            'title'   => esc_html__( 'Số lượng sản phẩm hiển thị', 'tianshu' ),
            'default' => 3,
        ),

        // Per Row related
        array(
            'id' => 'opt_shop_single_related_per_row',
            'type' => 'fieldset',
            'title' => esc_html__('Số sản phẩm trên một hàng', 'tianshu'),
            'fields' => tianshu_column_width_fields(1, 4, 1, 2, 3, 3),
        ),
	)
) );