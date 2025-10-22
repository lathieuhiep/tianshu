<?php
/*
 * Theme filters
 * @package tianshu
 * */

defined('ABSPATH') || exit;

// Preload Google Fonts
function tianshu_add_preload_to_google_fonts( $html, $handle ) {
    if ( $handle === 'google-fonts' ) {
        return str_replace(
            "rel='stylesheet'",
            "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
            $html
        );
    }

    return $html;
}
add_filter( 'style_loader_tag', 'tianshu_add_preload_to_google_fonts', 10, 2 );

/** Extra small perf tweaks */
add_filter('emoji_svg_url', '__return_false');
add_filter('wp_resource_hints', function (array $urls, string $relation_type) {
    if ('dns-prefetch' === $relation_type) {
        $urls = array_diff($urls, ['https://s.w.org']);
    }
    return $urls;
}, 10, 2);

/** TinyMCE emoji off */
function tianshu_disable_emojis_tinymce(array $plugins): array
{
    return $plugins ? array_diff($plugins, ['wpemoji']) : [];
}

add_filter('tiny_mce_plugins', 'tianshu_disable_emojis_tinymce');

/** Disable Gutenberg editor */
add_filter('xmlrpc_enabled', '__return_false');
add_filter('use_widgets_block_editor', '__return_false');

/** Block editor off */
function tianshu_disable_gutenberg_editor(bool $current_status, string $post_type): bool
{
    return false;
}

add_filter('use_block_editor_for_post_type', 'tianshu_disable_gutenberg_editor', 10, 2);

/** Walker for the main menu **/
add_filter('walker_nav_menu_start_el', 'tianshu_add_arrow', 10, 4);
function tianshu_add_arrow($output, $item, $depth, $args)
{
    if ('primary' == $args->theme_location && $depth >= 0) {
        if (in_array("menu-item-has-children", $item->classes)) {
            $output .= '<span class="sub-menu-toggle ic-mask"></span>';
        }
    }

    return $output;
}