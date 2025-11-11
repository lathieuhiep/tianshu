<?php
namespace ExtendReferrals\Core;

use WP_Post;

defined('ABSPATH') || exit;

/**
 * Class DisplayRules
 *
 * Xác định điều kiện hiển thị quảng cáo referral.
 * - Mặc định: chỉ hiển thị từ chương 2 trở đi nếu CPT = "chapter".
 * - Cho phép plugin/theme khác override qua filter `extend_referrals_should_display`.
 */
class DisplayRules {

    /**
     * Kiểm tra có nên hiển thị quảng cáo không.
     *
     * @param WP_Post|null $post
     * @return bool
     */
    public static function should_display(WP_Post $post = null): bool {
        $post = $post ?: get_post();
        if (! $post instanceof WP_Post) {
            return false;
        }

        // Ưu tiên: nếu là CPT "chapter" của plugin extend-site
        if ($post->post_type === 'chapter') {
            $chapter_number = (int) get_post_meta($post->ID, '_chapter_number', true);

            // Nếu meta không tồn tại, vẫn cho hiển thị (tùy trường hợp plugin khác)
            if ($chapter_number === 0) {
                return apply_filters('extend_referrals_should_display', true, $post);
            }

            // Mặc định: chỉ hiển thị từ chương 2 trở lên
            return $chapter_number >= 2;
        }

        // Cho phép plugin/theme khác tự quy định hiển thị
        $should_display = apply_filters('extend_referrals_should_display', null, $post);

        // Nếu filter trả null → fallback = true (hiển thị mặc định)
        if ($should_display === null) {
            return true;
        }

        return (bool) $should_display;
    }
}