<?php

namespace ExtendSite\Core;

use ExtendSite\PostType\PortfolioPostType;
use ExtendSite\ElementorAddon\ElementorAddon;
use ExtendSite\PostType\TemplateLoader;

defined('ABSPATH') || exit;

class Plugin
{
    public function boot(): void
    {
        self::load_text_domain();
        self::active_core();
        self::include_files();
        self::active_elementor_addon();
        self::active_custom_post_types();
    }

    /**
     * Load the plugin text domain for translations.
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
     */
    private static function active_core(): void
    {
        Enqueue::boot();
    }

    /**
     * Include necessary files.
     */
    private static function include_files(): void
    {
        require_once EXTEND_SITE_PATH . 'functions/helpers.php';
        require_once EXTEND_SITE_PATH . 'functions/cpt-helpers.php';
    }

    /**
     * Load the Elementor addon.
     */
    private static function active_elementor_addon(): void
    {
        ElementorAddon::boot();
    }

    /**
     * Load custom post types.
     */
    private static function active_custom_post_types(): void
    {
        new PortfolioPostType();
        TemplateLoader::boot();
    }
}