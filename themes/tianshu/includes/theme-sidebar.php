<?php
/**
 * Theme sidebar
 *
 * @package tianshu
 */

/* Better way to add multiple widgets areas */
function tianshu_register_sidebar( $name, $id, $description = '' ): void {
	register_sidebar( array(
		'name'          => $name,
		'id'            => $id,
		'description'   => $description,
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}

const BASICTHEME_PREFIX_SIDEBAR_FOOTER_COLUMN = 'sidebar-footer-column-';
function tianshu_multiple_widget_init(): void {
    // sidebar main
	tianshu_register_sidebar(
        esc_html__( 'Sidebar chính', 'tianshu' ),
        'sidebar-main',
        esc_html__('Dùng ở các trang bài viết', 'tianshu' )
    );

	// sidebar footer
	$opt_number_columns = tianshu_get_option( 'opt_footer_columns', '4' );

	for ( $i = 1; $i <= $opt_number_columns; $i ++ ) {
		tianshu_register_sidebar(
            sprintf( esc_html__( 'Sidebar chân trang cột %d', 'tianshu' ), $i ),
            BASICTHEME_PREFIX_SIDEBAR_FOOTER_COLUMN . $i,
			esc_html__( 'Dùng ở chân trang', 'tianshu' )
        );
	}
}
add_action( 'widgets_init', 'tianshu_multiple_widget_init' );