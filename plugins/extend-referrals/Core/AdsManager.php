<?php

namespace ExtendReferrals\Core;

use ExtendReferrals\Repository\AdsCache;

defined('ABSPATH') || exit;

class AdsManager
{
    public static function inject_ads_into_content(string $content): string
    {
        // Không hiển thị quảng cáo ở các trang không đủ điều kiện
        if (!DisplayRules::should_display()) {
            return $content;
        }

        // Lấy quảng cáo active
        $ads = AdsCache::get_ads();
        if (empty($ads)) {
            return $content;
        }

        // TTL check
        $is_expired = TTLManager::is_expired(); // true = SHOW ads, false = HIDE ads
        $locked_class = $is_expired ? 'er-locked' : '';

        ob_start();

        /**
         *  render quảng cáo khi TTL đã hết (cần yêu cầu user click)
         */
        if ($is_expired) {

            // Chọn ngẫu nhiên 1 quảng cáo
            $ad = $ads[array_rand($ads)];

            $ad = [
                'sub_title' => esc_html($ad['sub_title']),
                'link'      => esc_url($ad['link']),
                'image'     => esc_url($ad['image']),
                'label'     => esc_html($ad['label']),
            ];

            // Load template partner-info
            $template = locate_template('extend-referrals/partner-info.php');
            if (!$template || !file_exists($template)) {
                $template = EXTEND_REFERRALS_PATH . 'views/frontend/partner-info.php';
            }

            include $template;
        }

        /**
         *  render content
         */
        ?>
        <div id="er-partner-content-wrapper" class="<?php echo $locked_class; ?>">
            <?php echo $content; ?>
        </div>
        <?php

        return ob_get_clean();
    }
}