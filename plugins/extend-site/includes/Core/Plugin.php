<?php

namespace ExtendSite\Core;

use ExtendSite\Ajax\IncrementView;
use ExtendSite\Ajax\LoadChapterNeighbors;
use ExtendSite\Ajax\LoadChapters;
use ExtendSite\Ajax\LoadLatestStories;
use ExtendSite\Ajax\LoadRanking;
use ExtendSite\DB\LatestChapterTable;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\ElementorAddon\ElementorAddon;
use ExtendSite\PostType\TemplateLoader;
use ExtendSite\Search\AjaxHandler;
use ExtendSite\Search\SearchController;
use ExtendSite\Widgets\Register;

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
        self::register_widget();

        // Register hooks for LatestChapterTable
        LatestChapterTable::register_hooks();

        // Initialize search controller
        SearchController::init();

        // Ensure rewrite rules are flushed on init
        add_action('init', [__CLASS__, 'maybe_flush_rewrite'], 999);
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
        require_once EXTEND_SITE_PATH . 'hooks/sidebar.php';
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
        LoadRanking::init();
        LoadLatestStories::init();
        AjaxHandler::init();
    }

    /**
     * Register widgets.
     * @return void
     */
    private static function register_widget(): void
    {
        Register::init();
    }

    /**
     * Flush rewrite rules if the option is set.
     * @return void
     */
    public static function maybe_flush_rewrite(): void
    {
        if (get_option('extend_site_flush_rewrite')) {
            flush_rewrite_rules();
            delete_option('extend_site_flush_rewrite');
        }
    }
}