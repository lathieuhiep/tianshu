<?php

namespace ExtendSite\Core;

use ExtendSite\Admin\MenuPage;
use ExtendSite\Admin\PermalinkSettings;
use ExtendSite\Admin\StoryChapterLink;
use ExtendSite\Ajax\IncrementView;
use ExtendSite\Ajax\LoadChapters;
use ExtendSite\Ajax\LoadLatestStories;
use ExtendSite\Ajax\LoadRanking;
use ExtendSite\Ajax\SearchSelect2;
use ExtendSite\Crawler\CrawlerAdmin;
use ExtendSite\Crawler\CrawlerAjax;
use ExtendSite\DB\LatestChapterTable;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\ElementorAddon\ElementorAddon;
use ExtendSite\PostType\TemplateLoader;
use ExtendSite\Search\AjaxHandler;
use ExtendSite\Search\SearchController;
use ExtendSite\Search\SearchShortcode;
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
        self::active_menu_page_admin();
        self::active_crawler();
        self::active_elementor_addon();
        self::active_custom_post_types();
        self::load_ajax();
        self::register_widget();

        // Register hooks for LatestChapterTable
        LatestChapterTable::register_hooks();

        // Initialize search controller
        SearchController::init();

        // Register shortcode
        add_action('init', [__CLASS__, 'register_shortcode']);

        // Ensure rewrite rules are flushed on init
        add_action('init', [__CLASS__, 'maybe_flush_rewrite'], 999);

        // Initialize Story-Chapter link in admin
        StoryChapterLink::init();
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
     * Load the admin menu page.
     * @return void
     */
    private static function active_menu_page_admin(): void
    {
        MenuPage::init();
        PermalinkSettings::init();
    }

    /**
     * Bootstrap crawler module stubs.
     */
    private static function active_crawler(): void
    {
        CrawlerAdmin::init();
        CrawlerAjax::init();
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
        // ajax admin
        SearchSelect2::init();

        // ajax frontend
        LoadChapters::init();
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
     * Register shortcodes.
     * @return void
     */
    public static function register_shortcode(): void
    {
        SearchShortcode::init();
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
