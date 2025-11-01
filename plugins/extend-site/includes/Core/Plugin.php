<?php

namespace ExtendSite\Core;

use ExtendSite\Ajax\IncrementView;
use ExtendSite\Ajax\LoadChapterNeighbors;
use ExtendSite\Ajax\LoadChapters;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\ElementorAddon\ElementorAddon;
use ExtendSite\PostType\TemplateLoader;

defined('ABSPATH') || exit;

class Plugin
{
    /**
     * Boot the plugin by initializing all components.
     * @return void
     */
    public function boot(): void
    {
        self::load_text_domain();
        self::active_core();
        self::include_files();
        self::active_elementor_addon();
        self::active_custom_post_types();
        self::load_ajax();
    }

    /**
     * Load the plugin text domain for translations.
     * @return void
     */
    private static function load_text_domain(): void
    {
        load_plugin_textdomain(
            'extend-site',
            false,
            dirname( EXTEND_SITE_BASENAME ) . '/languages'
        );
    }

    /**
     * Load core functionalities.
     * @return void
     */
    private static function active_core(): void
    {
        Enqueue::boot();
    }

    /**
     * Include necessary files.
     * @return void
     */
    private static function include_files(): void
    {
        // Helpers
        require_once EXTEND_SITE_PATH . 'functions/helpers.php';
        require_once EXTEND_SITE_PATH . 'functions/cpt-helpers.php';
        require_once EXTEND_SITE_PATH . 'functions/breadcrumbs.php';

        // hooks
        require_once EXTEND_SITE_PATH . 'hooks/cpt-hooks.php';
    }

    /**
     * Load the Elementor addon.
     * @return void
     */
    private static function active_elementor_addon(): void
    {
        ElementorAddon::boot();
    }

    /**
     * Load custom post types.
     * @return void
     */
    private static function active_custom_post_types(): void
    {
        new StoryPostType();
        new ChapterPostType();
        new AuthorPostType();

        TemplateLoader::boot();
    }

    /**
     * Load AJAX handlers.
     * @return void
     */
    private static function load_ajax(): void
    {
        LoadChapters::init();
//        LoadChapterNeighbors::init();
        IncrementView::init();
    }
}