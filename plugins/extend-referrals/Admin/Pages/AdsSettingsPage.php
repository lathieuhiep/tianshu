<?php

namespace ExtendReferrals\Admin\Pages;

use ExtendReferrals\Repository\AdsCache;

defined('ABSPATH') || exit;

class AdsSettingsPage
{
    public const OPTION_GROUP = 'extend_referrals_ads_settings';
    public const OPTION_KEY = 'extend_referrals_ads_data';
    public const TTL_DEFAULT = 10; // phút

    /**
     * Init Ads Settings Page hooks.
     * @return void
     */
    public static function init(): void
    {
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // Xóa cache khi lưu cài đặt Affiliate Ads
        add_action('update_option_' . self::OPTION_KEY, function () {
            if (class_exists('\ExtendReferrals\Repository\AdsCache')) {
                AdsCache::clear();
            }
        }, 10, 0);
    }

    /**
     * register setting options.
     * @return void
     */
    public static function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY,
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
                'default' => ['ttl' => self::TTL_DEFAULT, 'ads' => []],
            ]
        );

    }

    /**
     * filter and sanitize ads data before save.
     *
     * @param array $input .
     * @return array
     */
    public static function sanitize_settings(array $input): array
    {
        $ads = [];

        if (!empty($input['ads']) && is_array($input['ads'])) {
            foreach ($input['ads'] as $index => $ad) {
                $label = sanitize_text_field($ad['label'] ?? '');
                $sub_title = sanitize_text_field($ad['sub_title'] ?? '');
                $link = esc_url_raw($ad['link'] ?? '');
                $image = esc_url_raw($ad['image'] ?? '');
                $image_id = isset($ad['image_id']) ? absint($ad['image_id']) : 0;
                $active = !empty($ad['active']) ? 1 : 0;

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
                        'label' => $label,
                        'sub_title' => $sub_title,
                        'link' => $link,
                        'image' => $image,
                        'image_id' => $image_id,
                        'active' => $active,
                    ];
                }
            }
        }

        return [
            'ttl' => max(1, (int)($input['ttl'] ?? self::TTL_DEFAULT)),
            'ads' => $ads,
        ];
    }

    /**
     * get saved options.
     */
    public static function get_options(): array
    {
        $defaults = ['ttl' => self::TTL_DEFAULT, 'ads' => []];

        $options = get_option(self::OPTION_KEY, $defaults);

        return wp_parse_args($options, $defaults);
    }

    /**
     * create field name for settings array.
     *
     * @param string $path
     * @return string
     */
    public static function field_name(string $path): string
    {
        return self::OPTION_KEY . $path;
    }

    /**
     * Render the Ads Settings Page.
     * @return void
     */
    public static function render_page(): void
    {
        $options = self::get_options();

        include EXTEND_REFERRALS_PATH . 'views/backend/partner-page.php';
    }
}