<?php
namespace ExtendSite\Core;

use ExtendSite\Ajax\LoadChapterNeighbors;
use ExtendSite\Ajax\LoadChapters;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;

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

        // check load libs
        if ( is_singular(ChapterPostType::SLUG) ) {
            // load select2
            wp_enqueue_style('select2',
                EXTEND_SITE_URL . 'assets/libs/select2/select2.min.css',
                [],
                '4.0.13'
            );

            wp_enqueue_script('select2',
                EXTEND_SITE_URL . 'assets/libs/select2/select2.min.js',
                ['jquery'],
                '4.0.13',
                true
            );
        }

        if ( is_singular(StoryPostType::SLUG)
            || is_post_type_archive(StoryPostType::SLUG)
            || is_tax('story_genre')
            || is_tax('story_status')
            || is_singular(ChapterPostType::SLUG)
        ) {
            wp_enqueue_style('es-extend-site',
                EXTEND_SITE_URL . 'assets/css/frontend/extend-site.min.css',
                [],
                EXTEND_SITE_VERSION
            );
        }

        // Story Post Type
        if ( is_singular(StoryPostType::SLUG) ) {
            // load single style
            wp_enqueue_style('es-single-' . StoryPostType::SLUG,
                EXTEND_SITE_URL . 'assets/css/frontend/post-type/story/single-story.min.css',
                [],
                EXTEND_SITE_VERSION
            );

            // load single script
            wp_enqueue_script('es-single-' . StoryPostType::SLUG,
                EXTEND_SITE_URL . 'assets/js/frontend/single-story.min.js',
                ['jquery'],
                EXTEND_SITE_VERSION,
                true
            );

            wp_localize_script('es-single-' . StoryPostType::SLUG, 'extendSite', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(LoadChapters::NONCE),
            ]);
        }

        // Chapter Post Type
        if ( is_singular(ChapterPostType::SLUG) ) {
            // load single style
            wp_enqueue_style('es-single-' . ChapterPostType::SLUG,
                EXTEND_SITE_URL . 'assets/css/frontend/post-type/chapter/single-chapter.min.css',
                [],
                EXTEND_SITE_VERSION
            );

            // load single script
            wp_enqueue_script('es-single-' . ChapterPostType::SLUG,
                EXTEND_SITE_URL . 'assets/js/frontend/single-chapter.min.js',
                ['jquery'],
                EXTEND_SITE_VERSION,
                true
            );

            wp_localize_script('es-single-' . ChapterPostType::SLUG, 'esSingleChapterAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(LoadChapterNeighbors::NONCE),
            ]);
        }
    }
}