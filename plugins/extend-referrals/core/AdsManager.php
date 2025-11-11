<?php
namespace ExtendReferrals\Core;

use ExtendReferrals\Repository\AdsCache;

defined('ABSPATH') || exit;

class AdsManager {

    public static function inject_ads_into_content(string $content): string {
        // Kiểm tra điều kiện hiển thị
        if (! DisplayRules::should_display()) {
            return $content;
        }

        // Nếu TTL còn hiệu lực → không hiển thị
        if (! TTLManager::is_expired()) {
            return $content;
        }

        // Lấy quảng cáo active
        $ads = AdsCache::get_ads();
        if (empty($ads)) {
            return $content;
        }

        // Chọn ngẫu nhiên 1 quảng cáo
        $ad = $ads[array_rand($ads)];

        // Render template view (có hỗ trợ override từ theme)
        ob_start();

        /**
         * Cho phép theme override file view
         * Tìm file trong: yourtheme/extend-referrals/ad-item.php
         */
        $template = locate_template('extend-referrals/ad-item.php');

        if (! $template || ! file_exists($template)) {
            $template = EXTEND_REFERRALS_PATH . 'views/ad-item.php';
        }

        // Biến $ad sẽ được sử dụng bên trong file view
        $ad = [
            'link'  => esc_url($ad['link']),
            'image' => esc_url($ad['image']),
            'label' => esc_html($ad['label']),
        ];

        include $template;

        return ob_get_clean();
    }
}