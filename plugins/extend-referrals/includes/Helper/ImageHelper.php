<?php
/**
 * Helper: render ảnh an toàn với fallback URL.
 *
 * @package ExtendReferrals\Helper
 */

namespace ExtendReferrals\Helper;

defined('ABSPATH') || exit;

class ImageHelper {

    /**
     * Render ảnh affiliate với fallback.
     *
     * @param int    $image_id  ID ảnh trong Media Library.
     * @param string $url       URL ảnh ngoài (fallback).
     * @param string $size      Cỡ ảnh (thumbnail, medium, full...).
     * @param array  $attrs     Thuộc tính HTML bổ sung.
     * @return string HTML ảnh đã escape hoặc rỗng.
     */
    public static function render(int $image_id = 0, string $url = '', string $size = 'medium', array $attrs = []): string {
        // Ưu tiên ảnh trong Media Library
        if ($image_id && wp_attachment_is_image($image_id)) {
            $html = wp_get_attachment_image($image_id, $size, false, $attrs);
            if ($html) {
                return $html;
            }
        }

        // Fallback sang URL ngoài
        if ($url) {
            $attr_html = '';
            foreach ($attrs as $key => $val) {
                $attr_html .= sprintf(' %s="%s"', esc_attr($key), esc_attr($val));
            }
            return sprintf('<img src="%s"%s loading="lazy" />', esc_url($url), $attr_html);
        }

        // Không có ảnh → trả rỗng
        return '';
    }
}