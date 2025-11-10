<?php
/**
 * Quản lý lưu dữ liệu thiết lập Affiliate Ads bằng Settings API.
 *
 * @package ExtendAffiliateAds\Admin
 */

namespace ExtendAffiliateAds\Admin;

defined('ABSPATH') || exit;

class AdminSettingsHandler {

    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Đăng ký setting theo chuẩn WordPress.
     */
    public static function register_settings(): void {
        register_setting(
            'extend_affiliate_ads_settings',
            'extend_affiliate_ads_data',
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
                'default' => ['ttl' => 10, 'ads' => []],
            ]
        );

    }

    /**
     * Lọc và chuẩn hóa dữ liệu quảng cáo trước khi lưu.
     *
     * @param array $input Dữ liệu gửi từ form.
     * @return array
     */
    public static function sanitize_settings($input): array {
        error_log('=== RAW $_POST ===');
        error_log(print_r($input, true));
        $ads = [];

        if (!empty($input['ads']) && is_array($input['ads'])) {
            foreach ($input['ads'] as $index => $ad) {
                $label    = sanitize_text_field($ad['label'] ?? '');
                $link     = esc_url_raw($ad['link'] ?? '');
                $image    = esc_url_raw($ad['image'] ?? '');
                $image_id = isset($ad['image_id']) ? absint($ad['image_id']) : 0;
                $active   = !empty($ad['active']) ? 1 : 0;

                // Nếu URL nội bộ mà chưa có ID → tự tìm ID
                if ($image && !$image_id) {
                    $maybe_id = attachment_url_to_postid($image);
                    if ($maybe_id) {
                        $image_id = $maybe_id;
                    }
                }

                // Chỉ lưu những quảng cáo có dữ liệu hợp lệ
                if ($label || $link || $image) {
                    $ads[$index] = [
                        'label'    => $label,
                        'link'     => $link,
                        'image'    => $image,
                        'image_id' => $image_id,
                        'active'   => $active,
                    ];
                }
            }
        }

        error_log('=== SANITIZE RESULT ===');
        error_log(print_r(
            [
                'ttl' => max(1, (int)($input['ttl'] ?? 10)),
                'ads' => $ads,
            ], true));

        return [
            'ttl' => max(1, (int)($input['ttl'] ?? 10)),
            'ads' => $ads,
        ];
    }

    /**
     * Chuẩn hóa giá trị TTL (phải >= 1).
     */
    public static function sanitize_ttl($ttl): int {
        $ttl = (int) $ttl;
        return max(1, $ttl);
    }
}