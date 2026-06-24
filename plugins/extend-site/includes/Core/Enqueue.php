<?php
namespace ExtendSite\Core;

use ExtendSite\Crawler\CrawlerAdmin;
use ExtendSite\Crawler\CrawlerAjax;
use ExtendSite\Crawler\CrawlerTemplateAdmin;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;

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
    public static function enqueue_scripts_backend(): void
    {
        $screen = get_current_screen();
        $is_crawler_page = $screen && $screen->id === 'extend-site_page_' . CrawlerAdmin::PAGE_SLUG;
        $is_crawler_template_page = $screen && $screen->id === 'extend-site_page_' . CrawlerTemplateAdmin::PAGE_SLUG;

        // Kiểm tra an toàn
        if (!$screen || (!$is_crawler_page && !$is_crawler_template_page && !in_array($screen->post_type, ['story', 'chapter'], true))) {
            return;
        }

        // Enqueue Select2
        wp_enqueue_style(
            'select2',
            EXTEND_SITE_URL . 'assets/vendor/select2/select2.min.css',
            [],
            '4.0.13'
        );

        wp_enqueue_script(
            'select2',
            EXTEND_SITE_URL . 'assets/vendor/select2/select2.min.js',
            ['jquery'],
            '4.0.13',
            true
        );

        if ($is_crawler_page) {
            wp_enqueue_style(
                'es-story-crawler',
                EXTEND_SITE_URL . 'assets/css/backend/story-crawler.css',
                [],
                EXTEND_SITE_VERSION
            );

            wp_enqueue_script(
                'es-story-crawler',
                EXTEND_SITE_URL . 'assets/js/backend/story-crawler.js',
                ['jquery', 'select2'],
                EXTEND_SITE_VERSION,
                true
            );

            wp_localize_script('es-story-crawler', 'esStoryCrawler', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(CrawlerAjax::NONCE_ACTION),
                'story_search_nonce' => wp_create_nonce('es_admin_common'),
                'story_search_action' => 'es_search_story',
                'process_action' => CrawlerAjax::ACTION_PROCESS,
                'template_prepare_action' => CrawlerAjax::ACTION_TEMPLATE_PREPARE_BATCH,
                'preview_action' => CrawlerAjax::ACTION_PREVIEW,
                'start_batch_action' => CrawlerAjax::ACTION_START,
                'heartbeat_action' => CrawlerAjax::ACTION_HEARTBEAT,
                'stop_batch_action' => CrawlerAjax::ACTION_STOP,
                'finalize_action' => CrawlerAjax::ACTION_FINALIZE,
                'default_delay' => 5000,
                'retry_delay' => 3000,
                'max_retries' => 3,
                'max_batch_size' => 200,
                'heartbeat_interval' => 30000,
            ]);

            return;
        }

        if ($is_crawler_template_page) {
            wp_enqueue_style(
                'es-crawler-template',
                EXTEND_SITE_URL . 'assets/css/backend/crawler-template.css',
                [],
                EXTEND_SITE_VERSION
            );

            wp_enqueue_script(
                'es-crawler-template',
                EXTEND_SITE_URL . 'assets/js/backend/crawler-template.js',
                ['jquery'],
                EXTEND_SITE_VERSION,
                true
            );

            wp_localize_script('es-crawler-template', 'esCrawlerTemplate', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(CrawlerAjax::NONCE_ACTION),
                'preview_proxy_action' => CrawlerAjax::ACTION_TEMPLATE_PREVIEW_PROXY,
                'test_parse_action' => CrawlerAjax::ACTION_TEMPLATE_TEST_PARSE,
                'save_action' => CrawlerAjax::ACTION_TEMPLATE_SAVE,
                'load_action' => CrawlerAjax::ACTION_TEMPLATE_LOAD,
                'delete_action' => CrawlerAjax::ACTION_TEMPLATE_DELETE,
                'i18n' => [
                    'loading' => esc_html__('Đang tải...', 'extend-site'),
                    'preview_loaded' => esc_html__('Đã tải xem trước.', 'extend-site'),
                    'test_loading' => esc_html__('Đang test selector...', 'extend-site'),
                    'missing_url' => esc_html__('Nhập URL truyện mẫu trước.', 'extend-site'),
                    'request_failed' => esc_html__('Request lỗi.', 'extend-site'),
                ],
            ]);

            return;
        }

        // Enqueue style backend story/chapter
        wp_enqueue_style(
            'es-admin-story-chapter',
            EXTEND_SITE_URL . 'assets/css/backend/story-chapter.css',
            [],
            EXTEND_SITE_VERSION
        );

        // Enqueue script backend
        wp_enqueue_script(
            'es-admin-story-chapter',
            EXTEND_SITE_URL . 'assets/js/extend-site.min.js',
            ['jquery', 'select2'],
            EXTEND_SITE_VERSION,
            true
        );

        // Localize script cho AJAX
        wp_localize_script('es-admin-story-chapter', 'esAdminStoryChapter', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('es_admin_common')
        ]);
    }

    /**
     * Enqueue scripts frontend
     */
    public static function enqueue_scripts_frontend(): void
    {
        // load main style plugin es
        wp_enqueue_style('es-extend-site',
            EXTEND_SITE_URL . 'assets/css/frontend/extend-site.min.css',
            [],
            EXTEND_SITE_VERSION
        );

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

            wp_localize_script('es-addons-elementor', 'esAddons', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(EXTEND_SITE_NONCE_ACTION),
                'i18n'     => [
                    'error_message' => esc_html__('Đã xảy ra lỗi khi tải dữ liệu, vui lòng thử lại.', 'extend-site')
                ],
            ]);
        }

        // register script for widget
        wp_register_script('es-widget',
            EXTEND_SITE_URL . 'assets/js/frontend/es-widget.min.js',
            ['jquery'],
            EXTEND_SITE_VERSION,
            true
        );

        wp_localize_script('es-widget', 'esWidget', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(EXTEND_SITE_NONCE_ACTION),
            'i18n'     => [
                'error_message' => esc_html__('Đã xảy ra lỗi khi tải dữ liệu, vui lòng thử lại.', 'extend-site')
            ],
        ]);

        // load ajax chapters for Story Post Type and Chapter Post Type
        if ( is_singular(StoryPostType::SLUG) || is_singular(ChapterPostType::SLUG) ) {
            // load single script
            wp_enqueue_script('es-load-chapters',
                EXTEND_SITE_URL . 'assets/js/frontend/load-chapters.min.js',
                ['jquery'],
                EXTEND_SITE_VERSION,
                true
            );

            wp_localize_script('es-load-chapters', 'esLoadChapters', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(EXTEND_SITE_NONCE_ACTION),
            ]);
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
                'nonce' => wp_create_nonce(EXTEND_SITE_NONCE_ACTION),
                'ttl_valid' => !class_exists('\ExtendReferrals\Core\TTLManager') || !\ExtendReferrals\Core\TTLManager::is_expired(),
                'chapter_number' => ChapterRepository::get_chapter_number(get_the_ID()),
            ]);
        }

        // Author Post Type
        if ( is_singular(AuthorPostType::SLUG) ) {
            // load single style
            wp_enqueue_style('es-single-' . AuthorPostType::SLUG,
                EXTEND_SITE_URL . 'assets/css/frontend/post-type/author/single-author.min.css',
                [],
                EXTEND_SITE_VERSION
            );
        }
    }
}
