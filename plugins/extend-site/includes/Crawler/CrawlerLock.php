<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class CrawlerLock
{
    public const OPTION_KEY = 'es_crawler_active_lock';
    public const DEFAULT_TTL = 300;

    public static function get(): ?array
    {
        $lock = get_option(self::OPTION_KEY, null);

        return is_array($lock) ? $lock : null;
    }

    public static function is_expired(?array $lock = null): bool
    {
        $lock = $lock ?? self::get();
        if (!$lock) {
            return true;
        }

        return empty($lock['expires_at']) || strtotime((string) $lock['expires_at']) <= time();
    }

    public static function clear(): void
    {
        delete_option(self::OPTION_KEY);
    }
}
