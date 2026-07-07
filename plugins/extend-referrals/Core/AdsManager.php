<?php

namespace ExtendReferrals\Core;

use ExtendReferrals\Admin\Pages\AdsSettingsPage;
use ExtendReferrals\Repository\AdsCache;

defined('ABSPATH') || exit;

class AdsManager
{
    public static function inject_ads_into_content(string $content): string
    {
        if (is_admin() || !is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // Không hiển thị quảng cáo ở các trang không đủ điều kiện
        if (!DisplayRules::should_display()) {
            return $content;
        }

        // Lấy quảng cáo active
        $ads = AdsCache::get_ads();
        if (empty($ads)) {
            return $content;
        }

        $locked_class = 'er-locked';

        ob_start();

        $settings = AdsSettingsPage::get_options();

        // Chọn ngẫu nhiên 1 quảng cáo
        $ad = $ads[array_rand($ads)];

        $ad = [
            'ttl' => (int)($settings['ttl'] ?? AdsSettingsPage::TTL_DEFAULT),
            'sub_title' => esc_html($ad['sub_title']),
            'link' => esc_url($ad['link']),
            'image' => esc_url($ad['image']),
            'label' => esc_html($ad['label']),
            'content' => $content,
        ];
        // Load template partner-info
        $template = locate_template('extend-referrals/partner-info.php');

        if (!$template || !file_exists($template)) {
            $template = EXTEND_REFERRALS_PATH . 'views/frontend/partner-info.php';
        }

        include $template;

        return ob_get_clean();
    }
}
