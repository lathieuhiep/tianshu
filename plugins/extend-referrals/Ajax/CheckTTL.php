<?php
namespace ExtendReferrals\Ajax;

use ExtendReferrals\Core\TTLManager;

defined('ABSPATH') || exit;

class CheckTTL {
    public const ACTION = 'extend_referrals_check_ttl';

    /**
     * Init AJAX handlers.
     */
    public static function init(): void {
        add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [__CLASS__, 'handle']);
    }

    /**
     * Handle AJAX request
     */
    public static function handle(): void {
        check_ajax_referer('extend_referrals_nonce', 'nonce');

        // TTL expired? true/false
        $expired = TTLManager::is_expired();

        wp_send_json([
            'expired' => $expired,
            'timestamp' => time()
        ]);
    }
}