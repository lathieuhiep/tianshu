<?php

namespace ExtendSite\Ajax;

use ExtendSite\PostType\ChapterPostType;
use ExtendSite\Views\ViewTracker;

defined('ABSPATH') || exit;

/**
 * Handle AJAX view incrementing.
 */
class IncrementView
{
    public const ACTION = 'es_increment_view';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Handle AJAX request
     */
    public static function handle(): void
    {
        check_ajax_referer(EXTEND_SITE_NONCE_ACTION, 'security');

        $chapter_id  = absint($_POST['chapter_id'] ?? 0);
        if ($chapter_id <= 0) {
            wp_send_json_error(['message' => 'Invalid chapter ID']);
        }

        if (get_post_type($chapter_id) !== ChapterPostType::SLUG) {
            wp_send_json_error(['message' => 'Invalid chapter ID']);
        }

        $fingerprint = sanitize_text_field($_POST['fingerprint'] ?? '');
        $uid         = sanitize_text_field($_POST['uid'] ?? '');
        $ip          = $_SERVER['REMOTE_ADDR'] ?? '';

        $key = sprintf('es_view_%d_%s', $chapter_id, md5($ip));
        $last_time = get_transient($key);

        if ($last_time && time() - $last_time < HOUR_IN_SECONDS) {
            wp_send_json_error(['message' => 'View recently recorded']);
        }

        set_transient($key, time(), HOUR_IN_SECONDS);
        ViewTracker::increment_views($chapter_id);

        wp_send_json_success(['message' => 'View accepted']);
    }
}
