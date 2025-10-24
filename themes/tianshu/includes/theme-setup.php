<?php
/*
 * Theme setup
 * @package tianshu
 * */

defined('ABSPATH') || exit;

/** Content width */
function tianshu_set_content_width(): void {
    $GLOBALS['content_width'] = apply_filters('tianshu_content_width', 1200);
}
add_action('after_setup_theme', 'tianshu_set_content_width', 0);

/** i18n (child-friendly) */
function tianshu_load_text_domain(): void {
    load_theme_textdomain('tianshu', get_stylesheet_directory() . '/languages');
}
add_action('after_setup_theme', 'tianshu_load_text_domain');

/** Theme supports */
function tianshu_add_theme_support(): void {
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','script','style']);
    add_theme_support('customize-selective-refresh-widgets');
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    add_theme_support('custom-logo', ['height'=>80,'width'=>240,'flex-height'=>true,'flex-width'=>true]);
}
add_action('after_setup_theme', 'tianshu_add_theme_support');

/** Menus */
function tianshu_register_nav_menus(): void {
    register_nav_menus([
        'primary' => esc_html__('Menu chính', 'tianshu'),
        'footer' => esc_html__('Menu chân trang', 'tianshu'),
    ]);
}
add_action('after_setup_theme', 'tianshu_register_nav_menus');