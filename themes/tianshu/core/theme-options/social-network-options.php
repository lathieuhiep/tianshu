<?php
// Create a section social network
$max_social_networks = count( tianshu_list_social_network() );

CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'title'  => esc_html__( 'Mạng xã hội', 'tianshu' ),
	'icon'   => 'fab fa-hive',
	'fields' => array(
		array(
			'id'      => 'opt_social_networks',
			'type'    => 'repeater',
			'title'   => esc_html__( 'Mạng xã hội', 'tianshu' ),
			'max'     => $max_social_networks,
			'fields'  => array(
				array(
					'id'          => 'item',
					'type'        => 'select',
					'title'       => esc_html__( 'Chọn mạng xã hội', 'tianshu' ),
					'placeholder' => esc_html__( '--Chọn mạng xã hội--', 'tianshu' ),
					'options'     => tianshu_list_social_network(),
				),

				array(
					'id'      => 'url',
					'type'    => 'text',
					'title'   => esc_html__( 'URL', 'tianshu' ),
					'default' => '#'
				),
			),
			'default' => array(
				array(
					'item' => 'facebook-f',
					'url'  => '#',
				),

				array(
					'item' => 'youtube',
					'url'  => '#',
				),
			)
		),
	)
) );