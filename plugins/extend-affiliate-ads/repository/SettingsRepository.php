<?php
/**
 * Quản lý lưu dữ liệu thiết lập Affiliate Ads bằng Settings API.
 *
 * @package ExtendAffiliateAds\Admin
 */

namespace ExtendAffiliateAds\Repository;

defined('ABSPATH') || exit;

class SettingsRepository {

    public const OPTION_GROUP = 'extend_affiliate_ads_settings';
    public const OPTION_KEY = 'extend_affiliate_ads_data';

    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Xóa cache khi lưu cài đặt Affiliate Ads
        add_action('update_option_' . self::OPTION_KEY, function () {
            if (class_exists('\ExtendAffiliateAds\Repository\AdsCache')) {
                AdsCache::clear();
            }
        }, 10, 0);
    }

    /**
     * Đăng ký setting theo chuẩn WordPress.
     */
    public static function register_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY,
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
    public static function sanitize_settings(array $input): array {
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

        return [
            'ttl' => max(1, (int)($input['ttl'] ?? 10)),
            'ads' => $ads,
        ];
    }

    /**
     * Lấy thiết lập hiện tại.
     */
    public static function get_options(): array
    {
        $defaults = ['ttl' => 10, 'ads' => []];

        $options  = get_option(self::OPTION_KEY, $defaults);

        return wp_parse_args($options, $defaults);
    }

    /**
     * Tạo tên trường input dựa trên đường dẫn.
     *
     * @param string $path Đường dẫn con trong mảng thiết lập.
     * @return string
     */
    public static function field_name(string $path): string {
        return self::OPTION_KEY . $path;
    }
}