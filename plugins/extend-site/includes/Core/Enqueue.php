<?php
namespace ExtendSite\Core;

use ExtendSite\PostType\PortfolioPostType;

defined('ABSPATH') || exit;

class Enqueue
{
    /**
     * Boot the Enqueue class.
     */
    public static function boot(): void
    {
        add_action('login_enqueue_scripts', [self::class, 'enqueue_scripts_login']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts_backend']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts_frontend'], 21);
    }

    /**
     * Enqueue scripts login
     */
    public static function enqueue_scripts_login(): void
    {
        wp_enqueue_style(
            'es-login',
            EXTEND_SITE_URL . 'assets/css/backend/custom-login.min.css',
            [],
            EXTEND_SITE_VERSION
        );
    }

    /**
     * Enqueue scripts backend
     */
    public static function enqueue_scripts_backend()
    {}

    /**
     * Enqueue scripts frontend
     */
    public static function enqueue_scripts_frontend(): void
    {
        // Check if Elementor is used to build the current page
        $page_builder = es_check_elementor_builder();

        if ( $page_builder ) {
            // load frontend style
            wp_enqueue_style('es-addons-elementor',
                EXTEND_SITE_URL . 'assets/css/frontend/addons-elementor.min.css',
                [],
                EXTEND_SITE_VERSION
            );

            // load frontend script
            wp_register_script( 'es-addons-elementor',
                EXTEND_SITE_URL . 'assets/js/frontend/addons-elementor.min.js',
                array( 'jquery', 'swiper' ),
                EXTEND_SITE_VERSION,
                true
            );
        }

        if ( is_singular(PortfolioPostType::SLUG) ) {
            // load portfolio style
            wp_enqueue_style('es-single-portfolio',
                EXTEND_SITE_URL . 'assets/css/frontend/post-type/portfolio/single-portfolio.min.css',
                [],
                EXTEND_SITE_VERSION
            );
        }
    }
}