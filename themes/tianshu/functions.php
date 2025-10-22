<?php
/**
 * Theme functions.
 *
 * @package tianshu
 */
defined( 'ABSPATH' ) || exit;

/*
 * load core files
 * */

// TGMPA
require get_template_directory() . '/core/tgm/class-tgm-plugin-activation.php';
require get_template_directory() . '/core/tgm/plugin-activation.php';

// Theme options
require get_template_directory() . '/core/theme-options.php';

// Meta box options
if ( ! class_exists( 'CMB2' ) ) {
    require get_template_directory() . '/core/cmb/cmb-post.php';
}

/*
 * load includes files
 * */
require get_template_directory() . '/includes/theme-setup.php';
require get_template_directory() . '/includes/theme-action.php';
require get_template_directory() . '/includes/theme-filter.php';
require get_template_directory() . '/includes/theme-functions.php';
require get_template_directory() . '/includes/theme-scripts.php';
require get_template_directory() . '/includes/theme-sidebar.php';

// Widgets
require get_template_directory() . '/includes/widgets/contact-info-widget.php';
require get_template_directory() . '/includes/widgets/recent-post.php';
require get_template_directory() . '/includes/widgets/social-widget.php';

// Woocommerce
if ( class_exists( 'Woocommerce' ) ) {
    require get_template_directory() . '/includes/woocommerce/woo-helpers.php';
    require get_template_directory() . '/includes/woocommerce/woo-scripts.php';
    require get_template_directory() . '/includes/woocommerce/woo-quick-view.php';
    require get_template_directory() . '/includes/woocommerce/woo-template-hooks.php';
    require get_template_directory() . '/includes/woocommerce/woo-template-functions.php';
}