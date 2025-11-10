<?php
namespace ExtendSite\Ajax;

use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;

defined('ABSPATH') || exit;

/**
 * Handles AJAX Select2 search requests for admin metaboxes.
 */
class SearchSelect2 {

    /** Map of allowed search targets */
    private const SEARCH_MAP = [
        'story' => StoryPostType::SLUG,
        'author' => AuthorPostType::SLUG,
        // có thể thêm nữa sau này
    ];

    /**
     * Register all AJAX actions related to Select2.
     */
    public static function init(): void {
        foreach (array_keys(self::SEARCH_MAP) as $type) {
            $action = 'es_search_' . $type;

            add_action('wp_ajax_' . $action, [__CLASS__, 'handle_ajax']);
        }
    }

    /**
     * Central handler — dispatch search by type (story, author, etc.)
     */
    public static function handle_ajax(): void {
        check_ajax_referer('es_admin_common', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json([]);
        }

        $type = sanitize_key($_GET['action'] ?? ''); // ex: es_search_story
        $key  = str_replace('es_search_', '', $type);

        if (!isset(self::SEARCH_MAP[$key])) {
            wp_send_json([]);
        }

        $post_type = self::SEARCH_MAP[$key];
        $q = sanitize_text_field($_GET['q'] ?? '');

        $posts = get_posts([
            'post_type'      => $post_type,
            's'              => $q,
            'posts_per_page' => 20,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => ['ID', 'post_title'],
            'no_found_rows'  => true,
        ]);

        $results = array_map(static fn($p) => [
            'id'   => $p->ID,
            'text' => $p->post_title ?: ('#' . $p->ID),
        ], $posts);

        wp_send_json($results);
    }
}