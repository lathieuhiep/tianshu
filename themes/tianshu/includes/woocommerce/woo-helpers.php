<?php
// setup shop
function tianshu_shop_setup(): void
{
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'tianshu_shop_setup');

// set number of products per row
function tianshu_loop_columns_product()
{
    return tianshu_get_option('opt_shop_cat_per_row', 3);
}
//add_filter('loop_shop_columns', 'tianshu_loop_columns_product');

// set col product list
function tianshu_woo_override_product_list_class($html): array|string
{
    if (is_product() && did_action('woocommerce_after_single_product_summary')) {
        $per_row_classes = tianshu_get_responsive_row_class('opt_shop_single_related_per_row');
    } else {
        $per_row_classes = tianshu_get_responsive_row_class('opt_shop_cat_per_row');
    }

    // remove class columns-x
    $html = preg_replace('/columns-\d+/', '', $html);

    // add class custom
    return str_replace('class="products', 'class="products gap-6 ' . $per_row_classes, $html);
}
add_filter('woocommerce_product_loop_start', 'tianshu_woo_override_product_list_class', 20, 1);

// limit product
function tianshu_show_products_per_page()
{
    return tianshu_get_option('opt_shop_cat_limit', 12);
}
add_filter('loop_shop_per_page', 'tianshu_show_products_per_page');

// set product related
function tianshu_woo_related_products_args($args) {
    $args['posts_per_page'] = tianshu_get_option('opt_shop_single_related_limit', 3);

    return $args;
}
add_filter('woocommerce_output_related_products_args', 'tianshu_woo_related_products_args', 20);

// simple products
function tianshu_woo_quantity_input_args( $args, $product ): array
{
    $args['classes'][] = 'custom-qty-input';

    return $args;
}
add_filter( 'woocommerce_quantity_input_args', 'tianshu_woo_quantity_input_args', 10, 2 );

// variations
function tianshu_woo_available_variation( $args ): array
{
    $args['classes'][] = 'custom-qty-input';

    return $args;
}
add_filter( 'woocommerce_available_variation', 'tianshu_woo_available_variation' );

// add button quantity minus
function tianshu_woo_quantity_minus_button(): void
{
?>
    <button type="button" class="qty-btn qty-minus">
        <i class="ic-mask ic-mask-minus"></i>
    </button>
<?php
}
add_action( 'woocommerce_before_quantity_input_field', 'tianshu_woo_quantity_minus_button' );

// add button quantity plus
function tianshu_woo_quantity_plus_button(): void
{
?>
    <button type="button" class="qty-btn qty-plus">
        <i class="ic-mask ic-mask-plus"></i>
    </button>
<?php
}
add_action( 'woocommerce_after_quantity_input_field', 'tianshu_woo_quantity_plus_button' );