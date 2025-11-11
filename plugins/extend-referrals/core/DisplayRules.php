<?php
namespace ExtendReferrals\Core;

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
         * Chỉ hiển thị nếu chương >= 2.
         */
        if ($post->post_type === 'chapter') {
            $chapter_number = (int) get_post_meta($post->ID, '_chapter_number', true);

            // Nếu meta không tồn tại → fallback cho phép hiển thị
            if ($chapter_number === 0) {
                return apply_filters('extend_referrals_should_display', true, $post);
            }

            $should_display = $chapter_number >= 2;

            /**
             * Cho phép plugin khác override.
             * @param bool $should_display  Kết quả mặc định.
             * @param WP_Post $post         Bài viết hiện tại.
             */
            return (bool) apply_filters('extend_referrals_should_display', $should_display, $post);
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