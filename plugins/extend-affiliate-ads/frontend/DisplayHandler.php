<?php
/**
 * Frontend: hiển thị danh sách quảng cáo Affiliate (có TTL cache)
 *
 * @package ExtendAffiliateAds\Frontend
 */

namespace ExtendAffiliateAds\Frontend;

use ExtendAffiliateAds\Helper\ImageHelper;

defined('ABSPATH') || exit;

class DisplayHandler {

    /**
     * Khởi tạo frontend logic.
     */
    public static function init(): void {
        add_shortcode('extend_affiliate_ads', [__CLASS__, 'render_shortcode']);
    }

    /**
     * Render shortcode [extend_affiliate_ads]
     *
     * @return string
     */
    public static function render_shortcode(): string {
        $ads = self::get_cached_ads();

        if (empty($ads)) {
            return ''; // Không có quảng cáo
        }

        ob_start();
        ?>
        <div class="extend-affiliate-ads-wrap">
            <?php foreach ($ads as $ad): ?>
                <?php if (empty($ad['active'])) continue; ?>

                <div class="affiliate-ad-item">
                    <a href="<?php echo esc_url($ad['link']); ?>"
                       class="affiliate-ad-link"
                       target="_blank"
                       rel="nofollow noopener">
                        <?php
                        echo ImageHelper::render(
                            (int) ($ad['image_id'] ?? 0),
                            $ad['image'] ?? '',
                            'medium',
                            [
                                'alt'   => $ad['label'] ?? '',
                                'class' => 'affiliate-ad-image',
                            ]
                        );
                        ?>
                    </a>
                    <?php if (!empty($ad['label'])): ?>
                        <p class="affiliate-ad-label">
                            <?php echo esc_html($ad['label']); ?>
                        </p>
                    <?php endif; ?>
                </div>

            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Lấy danh sách quảng cáo từ cache (TTL).
     *
     * @return array
     */
    protected static function get_cached_ads(): array {
        $cache_key = 'extend_affiliate_ads_cached';
        $cache_group = 'extend_affiliate_ads';

        // Thử lấy cache trước
        $cached = wp_cache_get($cache_key, $cache_group);
        if ($cached !== false) {
            return $cached;
        }

        // Lấy option gốc
        $settings = get_option('extend_affiliate_ads_data', []);
        $ads = $settings['ads'] ?? [];

        // TTL cache (phút → giây)
        $ttl_minutes = (int) get_option('extend_affiliate_ads_ttl', 10);
        $ttl_seconds = max(60, $ttl_minutes * 60);

        // Lưu cache
        wp_cache_set($cache_key, $ads, $cache_group, $ttl_seconds);

        return $ads;
    }
}