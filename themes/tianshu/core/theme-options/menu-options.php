<?php
// Create a section menu
CSF::createSection( BASICTHEME_PREFIX_THEME_OPTIONS, array(
	'title'  => esc_html__( 'Menu', 'tianshu' ),
	'icon'   => 'fas fa-bars',
	'fields' => array(
		// Sticky menu
		array(
			'id'         => 'opt_menu_sticky',
			'type'       => 'switcher',
			'title'      => esc_html__( 'Menu cố định', 'tianshu' ),
			'text_on'    => esc_html__( 'Có', 'tianshu' ),
			'text_off'   => esc_html__( 'Không', 'tianshu' ),
			'text_width' => 80,
			'default'    => true
		),
	)
) );