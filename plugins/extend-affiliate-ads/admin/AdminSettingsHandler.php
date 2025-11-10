<?php
/**
 * Xử lý lưu dữ liệu thiết lập Affiliate Ads.
 *
 * @package ExtendAffiliateAds\Admin
 */

namespace ExtendAffiliateAds\Admin;

defined('ABSPATH') || exit;

class AdminSettingsHandler {

    public static function init(): void {
        // Hook chạy khi admin lưu form
        add_action('admin_init', [__CLASS__, 'save_settings']);
    }

    public static function save_settings(): void {
        if (
            ! isset($_POST['option_page'], $_POST['action'])
            || $_POST['option_page'] !== 'extend_affiliate_ads_settings'
            || $_POST['action'] !== 'update'
        ) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('extend_affiliate_ads_settings-options');

        if (isset($_POST['extend_affiliate_ads_settings']['ads'])) {
            $ads = [];

            foreach ($_POST['extend_affiliate_ads_settings']['ads'] as $index => $ad) {
                $label = sanitize_text_field($ad['label'] ?? '');
                $link  = esc_url_raw($ad['link'] ?? '');
                $url   = esc_url_raw($ad['image'] ?? '');
                $id    = isset($ad['image_id']) ? absint($ad['image_id']) : 0;

                // Nếu URL nội bộ mà chưa có ID → tự map lại
                if ($url && ! $id) {
                    $maybe_id = attachment_url_to_postid($url);
                    if ($maybe_id) {
                        $id = $maybe_id;
                    }
                }

                // Luôn có key 'active' dù checkbox unchecked
                $active = isset($ad['active']) && (string)$ad['active'] === '1' ? 1 : 0;

                $ads[$index] = [
                    'label'    => $label,
                    'link'     => $link,
                    'image'    => $url,
                    'image_id' => $id,
                    'active'   => $active,
                ];
            }

            error_log('=== FINAL ADS TO SAVE ===');
            error_log(print_r($ads, true));

            update_option('extend_affiliate_ads_settings', ['ads' => $ads]);
            wp_cache_delete('extend_affiliate_ads_settings', 'options');
        }


        if (isset($_POST['extend_affiliate_ads_ttl'])) {
            $ttl = max(1, (int) $_POST['extend_affiliate_ads_ttl']);
            update_option('extend_affiliate_ads_ttl', $ttl);
        }

        wp_cache_delete('extend_affiliate_ads_cached', 'extend_affiliate_ads');
    }
}