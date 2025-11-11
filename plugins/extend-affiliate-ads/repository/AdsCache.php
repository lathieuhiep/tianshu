<?php
namespace ExtendAffiliateAds\Repository;

defined('ABSPATH') || exit;

class AdsCache {
    private const TRANSIENT_KEY = 'extend_affiliate_ads_cached';

    public static function get_ads(): array {
        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $settings = SettingsRepository::get_options();
        $ads = array_filter($settings['ads'] ?? [], fn($ad) => !empty($ad['active']));
        set_transient(self::TRANSIENT_KEY, $ads, MINUTE_IN_SECONDS * (int)($settings['ttl'] ?? 10));
        return $ads;
    }

    public static function clear(): void {
        delete_transient(self::TRANSIENT_KEY);
    }
}