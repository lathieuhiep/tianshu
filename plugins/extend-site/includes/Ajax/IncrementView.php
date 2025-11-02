<?php

namespace ExtendSite\Ajax;

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
        $chapter_id  = (int) $_POST['chapter_id'];
        $fingerprint = sanitize_text_field($_POST['fingerprint'] ?? '');
        $uid         = sanitize_text_field($_POST['uid'] ?? '');
        $ip          = $_SERVER['REMOTE_ADDR'] ?? '';

        $key = sprintf('es_view_%d_%s_%s', $chapter_id, md5($uid), md5($ip));
        $last_time = get_transient($key);

        if ($last_time && time() - $last_time < HOUR_IN_SECONDS) {
            wp_send_json_error(['message' => 'View recently recorded']);
        }

        set_transient($key, time(), HOUR_IN_SECONDS);
        ViewTracker::increment_views($chapter_id);

        // Ghi log phục vụ test
//        if (defined('WP_DEBUG') && WP_DEBUG) {
//            error_log(sprintf(
//                '[ViewTracker] +1 view for chapter=%d | uid=%s | ip=%s | fp=%s',
//                $chapter_id,
//                substr($uid, 0, 8),
//                $ip,
//                substr($fingerprint, 0, 12)
//            ));
//        }

        wp_send_json_success(['message' => 'View accepted']);
    }
}