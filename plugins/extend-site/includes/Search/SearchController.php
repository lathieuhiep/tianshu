<?php
namespace ExtendSite\Search;

use WP_Query;

/**
 * Controller for handling story search page requests.
 */
class SearchController {
    public const QUERY_VAR = 'es_search_story_page';
    public const SLUG      = 'tim-kiem-truyen';

    /**
     * Boot controller hooks.
     */
    public static function init(): void {
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_filter('query_vars', [__CLASS__, 'register_query_var']);
        add_action('template_redirect', [__CLASS__, 'fix_404_flag']);
        add_filter('template_include', [__CLASS__, 'intercept_search_template']);
        add_action('template_redirect', [__CLASS__, 'send_no_cache_header']);
        add_filter('wpseo_canonical', [__CLASS__, 'fix_canonical']);
    }

    /**
     * Add custom rewrite rule for /tim-kiem-truyen/
     */
    public static function register_rewrite(): void {
        // Trang đầu tiên
        add_rewrite_rule(
            '^' . self::SLUG . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );

        // Các trang tiếp theo (/page/2/)
        add_rewrite_rule(
            '^' . self::SLUG . '/page/([0-9]+)/?$',
            'index.php?' . self::QUERY_VAR . '=1&paged=$matches[1]',
            'top'
        );
    }

    /**
     * Register query var for search page detection.
     */
    public static function register_query_var(array $vars): array {
        $vars[] = self::QUERY_VAR;
        $vars[] = 'paged';

        return $vars;
    }

    /**
     * Remove 404 flag and mark as valid page.
     */
    public static function fix_404_flag(): void {
        if (get_query_var(self::QUERY_VAR)) {
            global $wp_query;
            if ($wp_query->is_404) {
                $wp_query->is_404  = false;
                $wp_query->is_page = true;
                $wp_query->is_home = false;
                $wp_query->is_search = true; // for breadcrumb plugins
                status_header(200);
            }
        }
    }

    /**
     * Send no-cache headers for dynamic search results.
     */
    public static function send_no_cache_header(): void {
        if (get_query_var(self::QUERY_VAR)) {
            nocache_headers();
        }
    }

    /**
     * Fix canonical for Yoast/RankMath SEO.
     */
    public static function fix_canonical($canonical): string {
        if (get_query_var(self::QUERY_VAR)) {
            $paged = max(1, get_query_var('paged', 1));
            $canonical = home_url(
                '/' . self::SLUG . ($paged > 1 ? '/page/' . $paged . '/' : '/')
            );
        }
        return $canonical;
    }

    /**
     * Detect and intercept template load for custom search page.
     */
    public static function intercept_search_template($template) {
        if ((int) get_query_var(self::QUERY_VAR) === 1) {
            self::render_page();
            exit;
        }

        return $template;
    }

    /**
     * Render custom search page with pagination.
     */
    public static function render_page(): void {
        $keyword = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $paged   = max(1, get_query_var('paged', 1));

        // Lấy dữ liệu từ repository
        $query = SearchRepository::search_stories_full($keyword, $paged);

        // Gắn vào template
        $template = EXTEND_SITE_PATH . 'templates/search-story.php';

        if (file_exists($template)) {
            include $template;
        } else {
            wp_die(esc_html__('Template file not found.', 'extend-site'));
        }
    }
}