<?php
namespace ExtendSite\ElementorAddon;

use ExtendSite\ElementorAddon\Widgets\CarouselImages;
use ExtendSite\ElementorAddon\Widgets\ContactForm;
use ExtendSite\ElementorAddon\Widgets\HeadingEditor;
use ExtendSite\ElementorAddon\Widgets\InfoBox;
use ExtendSite\ElementorAddon\Widgets\IconTextGrid;
use ExtendSite\ElementorAddon\Widgets\PostCarousel;
use ExtendSite\ElementorAddon\Widgets\PostGrid;
use ExtendSite\ElementorAddon\Widgets\TestimonialSlider;

defined('ABSPATH') || exit;

class ElementorAddon
{
    /**
     * Boot the Elementor Bootstrap class.
     */
    public static function boot(): void
    {
        self::register();
    }

    /**
     * Register the Elementor Bootstrap class.
     */
    private static function register (): void
    {
        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action('admin_notices', [self::class, 'maybe_show_admin_notice']);
        } else {
            add_action('elementor/elements/categories_registered', [self::class, 'register_category']);
            add_action('elementor/widgets/register', [self::class, 'register_widgets']);
        }
    }

    /**
     * Display an admin notice if Elementor is not active.
     */
    public static function maybe_show_admin_notice(): void
    {
    ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('Extend Site: Elementor is not active. Elementor-based widgets will be unavailable.', 'extend-site'); ?>
            </p>
        </div>
    <?php
    }

    /**
     * Register a custom category for Elementor widgets.
     */
    public static function register_category($manager): void
    {
        $manager->add_category(
            'es-addons',
            [
                'title' => esc_html__('Extend Site Addons', 'extend-site'),
                'icon'  => 'fa fa-plug',
            ],
        );
    }

    /**
     * Register Elementor widgets.
     */
    public static function register_widgets($widgets_manager): void
    {
        // register addon elementor here
        $widgets_manager->register(new CarouselImages());

        if (function_exists('wpcf7')) {
            $widgets_manager->register(new ContactForm());
        }

        $widgets_manager->register(new HeadingEditor());
        $widgets_manager->register(new InfoBox());
        $widgets_manager->register(new IconTextGrid());
        $widgets_manager->register(new PostCarousel());
        $widgets_manager->register(new PostGrid());
        $widgets_manager->register(new TestimonialSlider());
    }
}