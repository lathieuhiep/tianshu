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

    public static function acquire(int $story_id, int $user_id, int $ttl = self::DEFAULT_TTL, int $expected_total = 0): array
    {
        $existing = self::get();
        if ($existing && !self::is_expired($existing)) {
            return [
                'acquired' => false,
                'lock' => $existing,
            ];
        }

        if ($existing && self::is_expired($existing)) {
            self::clear();
        }

        $now = current_time('timestamp');
        $lock = [
            'batch_id' => wp_generate_uuid4(),
            'user_id' => $user_id,
            'story_id' => $story_id,
            'expected_total' => max(0, $expected_total),
            'started_at' => date('Y-m-d H:i:s', $now),
            'last_heartbeat' => date('Y-m-d H:i:s', $now),
            'expires_at' => date('Y-m-d H:i:s', $now + $ttl),
        ];

        if (!add_option(self::OPTION_KEY, $lock, '', 'no')) {
            $current = self::get();
            if ($current && !self::is_expired($current)) {
                return [
                    'acquired' => false,
                    'lock' => $current,
                ];
            }

            update_option(self::OPTION_KEY, $lock, false);
        }

        return [
            'acquired' => true,
            'lock' => $lock,
        ];
    }

    public static function heartbeat(string $batch_id, int $ttl = self::DEFAULT_TTL): ?array
    {
        $lock = self::get();
        if (!self::matches($batch_id, $lock)) {
            return null;
        }

        $now = current_time('timestamp');
        $lock['last_heartbeat'] = date('Y-m-d H:i:s', $now);
        $lock['expires_at'] = date('Y-m-d H:i:s', $now + $ttl);
        update_option(self::OPTION_KEY, $lock, false);

        return $lock;
    }

    public static function release(string $batch_id): bool
    {
        $lock = self::get();
        if (!self::matches($batch_id, $lock)) {
            return false;
        }

        self::clear();

        return true;
    }

    public static function matches(string $batch_id, ?array $lock = null): bool
    {
        $lock = $lock ?? self::get();
        if (!$lock || self::is_expired($lock)) {
            return false;
        }

        return !empty($lock['batch_id']) && hash_equals((string) $lock['batch_id'], $batch_id);
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
