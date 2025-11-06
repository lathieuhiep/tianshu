<?php
/**
 * Register sidebars for story site.
 */
add_action('widgets_init', function () {
    // Sidebar chung (fallback)
    register_sidebar([
        'name'          => esc_html__('Extend Site Sidebar', 'extend-site'),
        'id'            => 'es-sidebar',
        'description'   => esc_html__('Widgets shown on all pages except story or chapter.', 'extend-site'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);
});
