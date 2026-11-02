<?php

namespace ExtendSite\Services;

use ExtendSite\DB\RankingCacheTable;
use RuntimeException;

defined('ABSPATH') || exit;

/** Dieu phoi fresh/stale va lock. Du lieu luu rieng trong es_ranking_cache. */
final class RankingCache
{
    /** Fresh TTL theo gio, quan ly tap trung tai day. */
    private const HOURS = ['day' => 1, 'week' => 2, 'month' => 6, 'year' => 12];
    private const LOCK_TTL = 30;
    private const STALE_TTL = DAY_IN_SECONDS;
    private const JITTER = 300;
    /** Cache rong khong cong jitter de thu lai som khi co luot xem dau tien. */
    private const EMPTY_TTL = 120;
    private static array $runtime = [];
    /** Han fresh cua cac dependency dang duoc callback su dung. */
    private static array $dependencies = [];
    private static bool $busy = false;

    public static function is_busy(): bool
    {
        return self::$busy;
    }

    /** Clear thu cong: reset DB va memoization trong request hien tai. */
    public static function clear(): bool
    {
        $cleared = RankingCacheTable::clear();
        if ($cleared) {
            self::$runtime = [];
            self::$busy = false;
        }
        return $cleared;
    }

    /** Dang ky cleanup hang ngay; moi lan chi don toi da 100 dong. */
    public static function init(): void
    {
        add_action('save_post', [self::class, 'content_changed'], 20);
        add_action('before_delete_post', [self::class, 'content_changed'], 20);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [self::class, 'meta_changed'], 20, 4);
        }
        add_action('extend_site_ranking_cache_cleanup', [RankingCacheTable::class, 'cleanup']);
        if (!wp_next_scheduled('extend_site_ranking_cache_cleanup')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'extend_site_ranking_cache_cleanup');
        }
    }

    public static function content_changed(int $id): void
    {
        if (in_array(get_post_type($id), ['story', 'story_author', 'attachment'], true)) {
            RankingCacheTable::invalidate();
            self::$runtime = [];
        }
    }

    public static function meta_changed($meta_id, int $id, string $key, $value): void
    {
        if (in_array($key, [\ExtendSite\PostType\StoryPostType::META_AUTHOR_IDS, '_thumbnail_id', '_wp_attachment_metadata', '_wp_attached_file'], true)) {
            self::content_changed($id);
        }
    }

    /**
     * Doc cache theo identity on dinh; data_date ngan dung ranking ngay cu.
     *
     * @param string $identity Dinh danh records hoac items, kem period/limit/type.
     * @param string $period Ky day/week/month/year.
     * @param callable $build Tra mang; exception phai truyen len neu cache long nhau ban.
     * @return array Mang rong cung la du lieu hop le.
     * @throws RuntimeException Khi cold cache ban hoac callback bi loi.
     */
    public static function remember(string $identity, string $period, callable $build): array
    {
        self::$busy = false;
        $key = hash('sha256', 'v3|' . $identity);
        $runtime_key = $key . es_get_today();
        if (isset(self::$runtime[$runtime_key])) {
            $cached = self::$runtime[$runtime_key];
            if ($cached['until'] > time()) {
                return self::result($runtime_key, $cached['data'], $cached['until']);
            }
            unset(self::$runtime[$runtime_key]);
        }
        $row = RankingCacheTable::get($key);
        $fresh = self::data($row, 'fresh_until');
        if ($fresh !== null) {
            return self::result($runtime_key, $fresh, (int) $row['fresh_until']);
        }
        $stale = self::data($row, 'stale_until');
        $token = wp_generate_uuid4();
        if (!RankingCacheTable::acquire($key, $identity, $token, self::LOCK_TTL)) {
            // Chu khac co the vua hoan tat giua lan doc va lan lay lock.
            $row = RankingCacheTable::get($key);
            $fresh = self::data($row, 'fresh_until');
            $stale = self::data($row, 'stale_until') ?? $stale;
            if ($fresh !== null || $stale !== null) {
                return self::result($runtime_key, $fresh ?? $stale, $fresh !== null ? (int) $row['fresh_until'] : 0);
            }
            self::$busy = true;
            throw new RuntimeException('es_ranking_cache_busy');
        }
        try {
            self::$dependencies[] = PHP_INT_MAX;
            try {
                $data = $build();
            } finally {
                $dependency_until = array_pop(self::$dependencies);
            }
            /** Filter fresh TTL (giay, truoc jitter), tham so thu hai la period. */
            $ttl = max(1, (int)apply_filters(
                    'extend_site_ranking_cache_ttl',
                    (self::HOURS[$period] ?? 1) * HOUR_IN_SECONDS, $period
                )) + wp_rand(0, self::JITTER);
            if ($data === []) {
                $ttl = min($ttl, self::EMPTY_TTL);
            }
            // Han items khong vuot records; stale dependency khong duoc ghi thanh fresh.
            $until = min(time() + $ttl, $dependency_until);
            $ttl = $until - time();
            if ($ttl > 0) {
                RankingCacheTable::save($key, $token, $data, $ttl, self::STALE_TTL);
            }
            return self::result($runtime_key, $data, $until);
        } catch (RuntimeException $error) {
            if ($stale !== null) {
                return self::result($runtime_key, $stale, 0);
            }
            throw $error;
        } finally {
            // Token ngan request cu giai phong lock cua chu moi sau khi lease het.
            RankingCacheTable::release($key, $token);
        }
    }

    /** Truyen han nguon len callback cha, dong thoi memoize trong request. */
    private static function result(string $key, array $data, int $until): array
    {
        if (self::$dependencies) {
            $index = count(self::$dependencies) - 1;
            self::$dependencies[$index] = min(self::$dependencies[$index], $until);
        }
        self::$runtime[$key] = ['data' => $data, 'until' => $until];
        return $data;
    }

    /** Chi chap nhan payload dung ngay site, con han va JSON la mang. */
    private static function data(?array $row, string $expiry): ?array
    {
        if (!$row || $row['data_date'] !== es_get_today() || (int)$row[$expiry] <= time()) {
            return null;
        }
        $data = json_decode((string)$row['payload'], true);
        return is_array($data) ? $data : null;
    }

}
