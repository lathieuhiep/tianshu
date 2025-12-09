<?php
namespace ExtendReferrals\Core;

use ExtendReferrals\Admin\Pages\AdvancedRulesPage;
use WP_Post;
use ExtendReferrals\Admin\Pages\DisplayRulesPage;

defined('ABSPATH') || exit;

/**
 * Class DisplayRules
 *
 * Xác định điều kiện hiển thị quảng cáo đối tác (referral ads).
 *
 * - Dựa trên thiết lập hiển thị trong admin (DisplayRulesPage).
 * - Nếu là CPT "chapter" → chỉ hiển thị từ chương 2 trở lên.
 * - Cho phép plugin/theme khác override qua filter `extend_referrals_should_display`.
 */
class DisplayRules {

    /**
     * Kiểm tra có nên hiển thị quảng cáo không.
     *
     * @param WP_Post|null $post Đối tượng bài viết hiện tại.
     * @return bool
     */
    public static function should_display(WP_Post $post = null): bool {
        $post = $post ?: get_post();

        if (! $post instanceof WP_Post) {
            return false;
        }

        /**
         * Bước 1: Kiểm tra loại nội dung có được phép hiển thị hay không.
         */
        $enabled_types = DisplayRulesPage::get_options();

        if (! in_array($post->post_type, $enabled_types, true)) {
            return false;
        }

        /**
         * Bước 2: Logic riêng cho CPT "chapter".
         */
        if ($post->post_type === 'chapter') {

            $chapter_number = (int) get_post_meta($post->ID, '_chapter_number', true);

            if ($chapter_number <= 0) {
                return apply_filters('extend_referrals_should_display', true, $post);
            }

            // Lấy rule (an toàn khi plugin bị tắt)
            $opts = AdvancedRulesPage::get_chapter_rules();

            // Không có rule → fallback hiển thị
            if (empty($opts) || empty($opts['enabled'])) {
                return apply_filters('extend_referrals_should_display', true, $post);
            }

            $mode = $opts['mode'] ?? 'from_number';
            $should_display = true;

            switch ($mode) {
                case 'odd':
                    $should_display = ($chapter_number % 2 !== 0);
                    break;
                case 'even':
                    $should_display = ($chapter_number % 2 === 0);
                    break;
                case 'from_number':
                    $min = (int) ($opts['from'] ?? 2);
                    $should_display = ($chapter_number >= $min);
                    break;
                case 'only_list':
                    $list = array_filter(array_map('intval', explode(',', $opts['only_list'] ?? '')));
                    $should_display = in_array($chapter_number, $list, true);
                    break;
            }

            return apply_filters('extend_referrals_should_display', $should_display, $post);
        }

        /**
         * Bước 3: Mặc định cho các CPT khác (post, story, page, ...)
         */
        $should_display = true;

        /**
         * Cho phép filter override kết quả.
         * Nếu filter trả null, fallback = true.
         */
        $filtered = apply_filters('extend_referrals_should_display', $should_display, $post);

        return (bool) ($filtered ?? true);
    }
}