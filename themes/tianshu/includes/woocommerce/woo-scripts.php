<?php

//Register front end woo
add_action('wp_enqueue_scripts', 'tianshu_register_front_end_woo');

function tianshu_register_front_end_woo(): void
{
    $theme_woo_admin_ajax_url = array(
        'url' => admin_url('admin-ajax.php')
    );

    // include css product archive
    if (is_shop() || is_product_category() || is_product_tag()) {
        wp_enqueue_style('woo-archive', get_theme_file_uri('/includes/woocommerce/assets/css/woo-archive.min.css'), array(), tianshu_get_version_theme());
    }

    // include css product single
    if (is_product()) {
        wp_enqueue_style('woo-single', get_theme_file_uri('/includes/woocommerce/assets/css/woo-single.min.css'), array(), tianshu_get_version_theme());
    }

    if ( is_cart() ) {
        wp_enqueue_style('woo-cart', get_theme_file_uri('/includes/woocommerce/assets/css/woo-cart.min.css'), array(), tianshu_get_version_theme());
    }

    if ( is_checkout() ) {
        wp_enqueue_style('woo-checkout', get_theme_file_uri('/includes/woocommerce/assets/css/woo-checkout.min.css'), array(), tianshu_get_version_theme());
    }

    // include script main
    if ( is_woocommerce() || is_cart() ) {
        wp_enqueue_script('woo-main',
            get_theme_file_uri('/includes/woocommerce/assets/js/woo-main.min.js'),
            array('jquery'),
            tianshu_get_version_theme(),
            true
        );
    }

    // include script all page
    if (!is_cart() || is_checkout()) {
        wp_enqueue_script('woo-mini-cart',
            get_theme_file_uri('/includes/woocommerce/assets/js/woo-mini-cart.min.js'),
            array('jquery'),
            tianshu_get_version_theme(),
            true
        );
    }

    // include script page shop, taxonomy, product
    if ( is_shop() || is_product_category() || is_product_tag() || is_product() ) {
        // js
        wp_enqueue_script('woo-archive',
            get_theme_file_uri('/includes/woocommerce/assets/js/woo-archive.min.js'),
            array('jquery', 'wc-add-to-cart-variation'),
            tianshu_get_version_theme(),
            true);
        wp_localize_script('woo-archive', 'woo_quick_view_product', $theme_woo_admin_ajax_url);
    }
}