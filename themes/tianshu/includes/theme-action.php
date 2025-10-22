<?php
/*
 * Theme actions
 * @package tianshu
 * */

defined('ABSPATH') || exit;

/** Cleanup */
function tianshu_remove_unnecessary_assets(): void {
    // Emojis
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    // REST / RSD / WLW / generator
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('template_redirect', 'rest_output_link_header', 11);
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');

    // oEmbed & shortlink (optional)
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    remove_action('wp_head', 'wp_shortlink_wp_head', 10);
}
add_action('init', 'tianshu_remove_unnecessary_assets');

/** Remove dashicons for guests */
add_action('wp_enqueue_scripts', function() {
    if ( ! is_user_logged_in() ) {
        wp_deregister_style('dashicons');
    }
}, 20);

/** Favicon fallback (front only) */
function tianshu_output_fallback_favicon(): void {
    if ( is_admin() || has_site_icon() ) { return; }
    $base_url = get_theme_file_uri('/assets/images/favicons/');
?>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url($base_url . 'apple-touch-icon.png'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url($base_url . 'favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url($base_url . 'favicon-16x16.png'); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo esc_url($base_url . 'favicon.svg'); ?>">
    <link rel="manifest" href="<?php echo esc_url($base_url . 'site.webmanifest'); ?>">
<?php
}
add_action('wp_head', 'tianshu_output_fallback_favicon');

// add code to head
function tianshu_custom_header_code(): void {
	$header_code = tianshu_get_option( 'opt_header_code' );

	if ($header_code) {
		echo $header_code;
	}
}
add_action('wp_head', 'tianshu_custom_header_code');

// add code to body
function tianshu_custom_body_code(): void {
	$body_code = tianshu_get_option( 'opt_body_code' );

	if ($body_code) {
		echo $body_code;
	}
}
add_action('wp_body_open', 'tianshu_custom_body_code');

// add code to footer
function tianshu_custom_footer_code(): void {
	$footer_code = tianshu_get_option( 'opt_footer_code' );

	if ($footer_code) {
		echo $footer_code;
	}
}
add_action('wp_footer', 'tianshu_custom_footer_code');