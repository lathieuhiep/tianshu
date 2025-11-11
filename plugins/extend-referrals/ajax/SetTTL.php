<?php
namespace ExtendReferrals\Ajax;

use ExtendReferrals\Core\TTLManager;

defined('ABSPATH') || exit;

class SetTTL {
    public const ACTION = 'extend_referrals_set_ttl';

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

        error_log('=== extend_referrals_set_ttl triggered ===');
        error_log(print_r($_POST, true));

        TTLManager::set_cookie();

        wp_send_json_success(['message' => 'TTL set']);
    }
}