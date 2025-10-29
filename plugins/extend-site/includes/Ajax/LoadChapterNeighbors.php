<?php
/**
 * Ajax handler: Load nearby chapters (neighbors) for Select2 dropdown
 *
 * @package ExtendSite\Ajax
 */

namespace ExtendSite\Ajax;

use ExtendSite\Repositories\ChapterRepository;

defined('ABSPATH') || exit;

class LoadChapterNeighbors {

    /**
     * Ajax action name
     */
    public const ACTION = 'load_chapter_neighbors';

    /**
     * Nonce action key
     */
    public const NONCE = 'load_chapter_neighbors_nonce';

    /**
     * Register hooks
     */
    public static function init(): void {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Handle Ajax request
     */
    public static function handle(): void {
        // 🔐 Verify nonce
        check_ajax_referer(self::NONCE, 'security');

        // 🧹 Sanitize inputs
        $story_id       = absint($_POST['story_id'] ?? 0);
        $current_number = absint($_POST['current_number'] ?? 0);

        if (!$story_id || !$current_number) {
            wp_send_json_error([
                'message' => __('Invalid parameters.', 'extend-site'),
            ]);
        }

        // 🧭 Fetch 10 chapters before and after current
        $chapters = ChapterRepository::get_neighbors($story_id, $current_number, 10);

        if (empty($chapters)) {
            wp_send_json([
                'results'    => [],
                'pagination' => ['more' => false],
            ]);
        }

        // 🧾 Build Select2-compatible result set
        $results = [];
        foreach ($chapters as $chapter) {
            $results[] = [
                'id'   => $chapter['id'],
                'text' => sprintf(
                /* translators: %d: Chapter number, %s: Chapter title */
                    __('Chương %d – %s', 'extend-site'),
                    (int) $chapter['number'],
                    esc_html($chapter['title'])
                ),
                'url'  => esc_url_raw($chapter['url']),
            ];
        }

        // ✅ Send JSON response
        wp_send_json([
            'results'    => $results,
            'pagination' => ['more' => false],
        ]);
    }
}