<?php
defined( 'ABSPATH' ) || exit;

CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'id'    => 'opt_home_section',
	'icon'  => 'fas fa-home',
	'title' => esc_html__( 'Trang chủ', 'tianshu' ),
) );

CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent' => 'opt_home_section',
	'title'  => esc_html__( 'Đề cử hôm nay', 'tianshu' ),
	'fields' => array(
		array(
			'id'      => 'opt_home_featured_query_source',
			'type'    => 'select',
			'title'   => esc_html__( 'Nguồn dữ liệu', 'tianshu' ),
			'options' => array(
				'custom'   => esc_html__( 'Tùy chỉnh', 'tianshu' ),
				'top_week' => esc_html__( 'Top 7 ngày', 'tianshu' ),
			),
			'default' => 'custom',
		),
		array(
			'id'         => 'opt_home_featured_taxonomy',
			'type'       => 'select',
			'title'      => esc_html__( 'Thể loại', 'tianshu' ),
			'options'    => 'categories',
			'query_args' => array( 'taxonomy' => 'story_genre' ),
			'chosen'     => true,
			'multiple'   => true,
			'dependency' => array( 'opt_home_featured_query_source', '==', 'custom' ),
		),
		array(
			'id'         => 'opt_home_featured_tag',
			'type'       => 'select',
			'title'      => esc_html__( 'Tag', 'tianshu' ),
			'options'    => 'categories',
			'query_args' => array( 'taxonomy' => 'story_tag' ),
			'chosen'     => true,
			'multiple'   => true,
			'dependency' => array( 'opt_home_featured_query_source', '==', 'custom' ),
		),
		array(
			'id'      => 'opt_home_featured_limit',
			'type'    => 'number',
			'title'   => esc_html__( 'Số lượng truyện', 'tianshu' ),
			'default' => 18,
			'min'     => 1,
			'max'     => 36,
		),
		array(
			'id'         => 'opt_home_featured_order_by',
			'type'       => 'select',
			'title'      => esc_html__( 'Sắp xếp theo', 'tianshu' ),
			'options'    => array(
				'ID'    => esc_html__( 'ID', 'tianshu' ),
				'title' => esc_html__( 'Tiêu đề', 'tianshu' ),
				'date'  => esc_html__( 'Ngày đăng', 'tianshu' ),
				'views' => esc_html__( 'Lượt xem', 'tianshu' ),
			),
			'default'    => 'ID',
			'dependency' => array( 'opt_home_featured_query_source', '==', 'custom' ),
		),
		array(
			'id'         => 'opt_home_featured_order',
			'type'       => 'select',
			'title'      => esc_html__( 'Thứ tự', 'tianshu' ),
			'options'    => array(
				'DESC' => esc_html__( 'Giảm dần', 'tianshu' ),
				'ASC'  => esc_html__( 'Tăng dần', 'tianshu' ),
			),
			'default'    => 'DESC',
			'dependency' => array( 'opt_home_featured_query_source', '==', 'custom' ),
		),
	),
) );

CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent' => 'opt_home_section',
	'title'  => esc_html__( 'Truyện hot tháng này', 'tianshu' ),
	'fields' => array(
		array(
			'id'      => 'opt_home_hot_query_source',
			'type'    => 'select',
			'title'   => esc_html__( 'Nguồn dữ liệu', 'tianshu' ),
			'options' => array(
				'custom'    => esc_html__( 'Tùy chỉnh', 'tianshu' ),
				'top_month' => esc_html__( 'Top 30 ngày', 'tianshu' ),
			),
			'default' => 'top_month',
		),
		array(
			'id'         => 'opt_home_hot_taxonomy',
			'type'       => 'select',
			'title'      => esc_html__( 'Thể loại', 'tianshu' ),
			'options'    => 'categories',
			'query_args' => array( 'taxonomy' => 'story_genre' ),
			'chosen'     => true,
			'multiple'   => true,
			'dependency' => array( 'opt_home_hot_query_source', '==', 'custom' ),
		),
		array(
			'id'         => 'opt_home_hot_tag',
			'type'       => 'select',
			'title'      => esc_html__( 'Tag', 'tianshu' ),
			'options'    => 'categories',
			'query_args' => array( 'taxonomy' => 'story_tag' ),
			'chosen'     => true,
			'multiple'   => true,
			'dependency' => array( 'opt_home_hot_query_source', '==', 'custom' ),
		),
		array(
			'id'      => 'opt_home_hot_limit',
			'type'    => 'number',
			'title'   => esc_html__( 'Số lượng truyện', 'tianshu' ),
			'default' => 12,
			'min'     => 1,
			'max'     => 36,
		),
		array(
			'id'         => 'opt_home_hot_order_by',
			'type'       => 'select',
			'title'      => esc_html__( 'Sắp xếp theo', 'tianshu' ),
			'options'    => array(
				'ID'    => esc_html__( 'ID', 'tianshu' ),
				'title' => esc_html__( 'Tiêu đề', 'tianshu' ),
				'date'  => esc_html__( 'Ngày đăng', 'tianshu' ),
				'views' => esc_html__( 'Lượt xem', 'tianshu' ),
			),
			'default'    => 'ID',
			'dependency' => array( 'opt_home_hot_query_source', '==', 'custom' ),
		),
		array(
			'id'         => 'opt_home_hot_order',
			'type'       => 'select',
			'title'      => esc_html__( 'Thứ tự', 'tianshu' ),
			'options'    => array(
				'DESC' => esc_html__( 'Giảm dần', 'tianshu' ),
				'ASC'  => esc_html__( 'Tăng dần', 'tianshu' ),
			),
			'default'    => 'DESC',
			'dependency' => array( 'opt_home_hot_query_source', '==', 'custom' ),
		),
	),
) );

CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent' => 'opt_home_section',
	'title'  => esc_html__( 'Truyện mới nhất', 'tianshu' ),
	'fields' => array(
		array(
			'id'      => 'opt_home_latest_limit',
			'type'    => 'number',
			'title'   => esc_html__( 'Số lượng truyện', 'tianshu' ),
			'default' => 12,
			'min'     => 1,
			'max'     => 36,
		),
	),
) );

CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent' => 'opt_home_section',
	'title'  => esc_html__( 'Truyện đã hoàn thành', 'tianshu' ),
	'fields' => array(
		array(
			'id'         => 'opt_home_completed_taxonomy',
			'type'       => 'select',
			'title'      => esc_html__( 'Thể loại', 'tianshu' ),
			'options'    => 'categories',
			'query_args' => array( 'taxonomy' => 'story_genre' ),
			'chosen'     => true,
			'multiple'   => true,
		),
		array(
			'id'         => 'opt_home_completed_status',
			'type'       => 'select',
			'title'      => esc_html__( 'Trạng thái truyện', 'tianshu' ),
			'options'    => 'categories',
			'query_args' => array( 'taxonomy' => 'story_status' ),
			'chosen'     => true,
		),
		array(
			'id'         => 'opt_home_completed_tag',
			'type'       => 'select',
			'title'      => esc_html__( 'Tag', 'tianshu' ),
			'options'    => 'categories',
			'query_args' => array( 'taxonomy' => 'story_tag' ),
			'chosen'     => true,
		),
		array(
			'id'      => 'opt_home_completed_limit',
			'type'    => 'number',
			'title'   => esc_html__( 'Số lượng truyện', 'tianshu' ),
			'default' => 6,
			'min'     => 1,
			'max'     => 36,
		),
		array(
			'id'      => 'opt_home_completed_order_by',
			'type'    => 'select',
			'title'   => esc_html__( 'Sắp xếp theo', 'tianshu' ),
			'options' => array(
				'ID'    => esc_html__( 'ID', 'tianshu' ),
				'title' => esc_html__( 'Tiêu đề', 'tianshu' ),
				'date'  => esc_html__( 'Ngày đăng', 'tianshu' ),
				'views' => esc_html__( 'Lượt xem', 'tianshu' ),
			),
			'default' => 'ID',
		),
		array(
			'id'      => 'opt_home_completed_order',
			'type'    => 'select',
			'title'   => esc_html__( 'Thứ tự', 'tianshu' ),
			'options' => array(
				'DESC' => esc_html__( 'Giảm dần', 'tianshu' ),
				'ASC'  => esc_html__( 'Tăng dần', 'tianshu' ),
			),
			'default' => 'DESC',
		),
	),
) );

CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'parent' => 'opt_home_section',
	'title'  => esc_html__( 'Danh sách thể loại', 'tianshu' ),
	'fields' => array(
		array(
			'id'      => 'opt_home_taxonomy_list_taxonomy',
			'type'    => 'select',
			'title'   => esc_html__( 'Taxonomy', 'tianshu' ),
			'options' => array(
				'story_genre' => esc_html__( 'Danh mục truyện', 'tianshu' ),
				'story_status' => esc_html__( 'Trạng thái truyện', 'tianshu' ),
				'story_tag' => esc_html__( 'Tag truyện', 'tianshu' ),
			),
			'default' => 'story_genre',
		),
	),
) );
