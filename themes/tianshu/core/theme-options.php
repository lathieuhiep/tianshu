<?php
defined( 'ABSPATH' ) || exit;

// Gọi file helpers/functions nếu có
require get_template_directory() . '/core/theme-options/helper-options.php';

// Hàm chính để khởi tạo Codestar Framework
function tianshu_options_init(): void {
    if ( is_admin() && class_exists( 'CSF' ) ) {
        // Khởi tạo Codestar Options
        CSF::createOptions( BASICTHEME_PREFIX_THEME_OPTIONS, tianshu_get_options_config() );

        // Tải các file tùy chọn
        tianshu_load_options();
    }
}
add_action( 'after_setup_theme', 'tianshu_options_init' );

// Tách hàm cấu hình chính
function tianshu_get_options_config(): array {
    $facebook_url = esc_url( 'https://www.facebook.com/lathieuhiep' );
    $menu_title = esc_html__( 'Cài đặt theme', 'tianshu' );

    return array(
        'menu_title'          => $menu_title,
        'menu_slug'           => 'theme-options',
        'menu_position'       => 2,
        'admin_bar_menu_icon' => 'dashicons-admin-generic',
        'framework_title'     => $menu_title,
        'footer_text'         => esc_html__( 'Cảm ơn bạn đã sử dụng theme của tôi', 'tianshu' ),
        'footer_after'        => sprintf(
            '<pre>Liên hệ:<br />Zalo/Phone: 0975458209 - facebook: <a href="%s" target="_blank">lathieuhiep</a></pre>',
            $facebook_url
        ),
    );
}

// Tách hàm load các file tùy chọn
function tianshu_load_options(): void {
    require get_template_directory() . '/core/theme-options/general-options.php';
    require get_template_directory() . '/core/theme-options/menu-options.php';
    require get_template_directory() . '/core/theme-options/blog-options.php';
    require get_template_directory() . '/core/theme-options/social-network-options.php';
    require get_template_directory() . '/core/theme-options/footer-options.php';
    require get_template_directory() . '/core/theme-options/custom-code-options.php';
}