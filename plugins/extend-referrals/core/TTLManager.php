<?php
/**
 * TTL (Time To Live) Manager
 *
 * Quản lý thời gian tạm ẩn quảng cáo sau khi người dùng đã click.
 * Sử dụng cookie ở client + transient cache ở server.
 *
 * @package ExtendReferrals\Core
 */

namespace ExtendReferrals\Core;

use ExtendReferrals\Admin\Pages\AdsSettingsPage;

defined('ABSPATH') || exit;

class TTLManager {

    /** @var string Tên cookie dùng để lưu TTL */
    private const COOKIE_KEY = 'extend_affiliate_ads_ttl';

    /**
     * Lấy TTL (phút) từ cài đặt admin.
     *
     * @return int
     */
    public static function get_ttl(): int {
        $options = AdsSettingsPage::get_options();
        $ttl = isset($options['ttl']) ? (int) $options['ttl'] : AdsSettingsPage::TTL_DEFAULT;

        return $ttl > 0 ? $ttl : AdsSettingsPage::TTL_DEFAULT; // fallback 10 phút
    }

    /**
     * Kiểm tra xem TTL đã hết hạn chưa (hoặc chưa từng set).
     *
     * @return bool True nếu cookie không tồn tại hoặc đã hết hạn.
     */
    public static function is_expired(): bool {
        if (empty($_COOKIE[self::COOKIE_KEY])) {
            return true;
        }

        $expire = (int) $_COOKIE[self::COOKIE_KEY];
        return $expire < time();
    }

    /**
     * Đặt cookie TTL khi người dùng click quảng cáo.
     *
     * @return void
     */
    public static function set_cookie(): void {
        $expire = time() + (self::get_ttl() * MINUTE_IN_SECONDS);

        setcookie(
            self::COOKIE_KEY,
            (string) $expire,
            [
                'expires'  => $expire,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        // Đồng thời set vào $_COOKIE để tác động ngay lập tức trong request hiện tại
        $_COOKIE[self::COOKIE_KEY] = (string) $expire;
    }

    /**
     * Xóa cookie TTL.
     *
     * @return void
     */
    public static function clear_cookie(): void {
        setcookie(
            self::COOKIE_KEY,
            '',
            [
                'expires'  => time() - HOUR_IN_SECONDS,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        unset($_COOKIE[self::COOKIE_KEY]);
    }
}