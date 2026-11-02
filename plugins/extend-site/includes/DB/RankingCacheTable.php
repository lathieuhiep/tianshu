<?php
namespace ExtendSite\DB;

defined('ABSPATH') || exit;

/** Luu payload va lock ranking; moi identity co mot dong, doc bang primary key. */
final class RankingCacheTable
{
    public static function name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'es_ranking_cache';
    }

    public static function create(): void
    {
        global $wpdb;
        $table = self::name();
        $collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$table} (
            cache_key varchar(64) NOT NULL,
            identity_label varchar(191) NOT NULL,
            data_date date DEFAULT NULL,
            payload longtext DEFAULT NULL,
            fresh_until bigint unsigned NOT NULL DEFAULT 0,
            stale_until bigint unsigned NOT NULL DEFAULT 0,
            lock_token varchar(36) NOT NULL DEFAULT '',
            lock_until bigint unsigned NOT NULL DEFAULT 0,
            updated_at bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (cache_key),
            KEY stale_until (stale_until)
        ) {$collate};");
    }

    public static function exists(): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(self::name()))) === self::name();
    }

    public static function get(string $key): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::name() . ' WHERE cache_key = %s', $key
        ), ARRAY_A);
    }

    /** Tao placeholder neu thieu, roi lay lock bang UPDATE co dieu kien nguyen tu. */
    public static function acquire(string $key, string $identity, string $token, int $lease): bool
    {
        global $wpdb;
        $table = self::name();
        $now = time();
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (cache_key, identity_label) VALUES (%s, %s)",
            $key, substr($identity, 0, 191)
        ));
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET lock_token = %s, lock_until = %d
             WHERE cache_key = %s AND lock_until <= %d
             AND (fresh_until <= %d OR data_date IS NULL OR data_date <> %s)",
            $token, $now + $lease, $key, $now, $now, es_get_today()
        )) === 1;
    }

    /** Chi chu con lease moi duoc ghi; payload va han cache cap nhat cung mot lan. */
    public static function save(string $key, string $token, array $data, int $ttl, int $stale): bool
    {
        global $wpdb;
        $json = wp_json_encode($data);
        if ($json === false) {
            return false;
        }
        $now = time();
        return $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::name() . " SET payload = %s, data_date = %s,
             fresh_until = %d, stale_until = %d, updated_at = %d
             WHERE cache_key = %s AND lock_token = %s AND lock_until > %d",
            $json, es_get_today(), $now + $ttl, $now + max($ttl, $stale), $now,
            $key, $token, $now
        )) === 1;
    }

    public static function release(string $key, string $token): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::name() . " SET lock_token = '', lock_until = 0
             WHERE cache_key = %s AND lock_token = %s", $key, $token
        ));
    }

    /** Don theo batch; khong xoa dong dang co request giu lock. */
    public static function cleanup(): void
    {
        global $wpdb;
        $now = time();
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::name() . ' WHERE stale_until < %d AND lock_until <= %d LIMIT 100',
            $now, $now
        ));
    }

    /** Content thay doi: vo hieu payload va token de builder cu khong ghi lai du lieu cu. */
    public static function invalidate(): void
    {
        global $wpdb;
        $wpdb->query('UPDATE ' . self::name() . " SET fresh_until=0, stale_until=0, lock_token='', lock_until=0");
    }

    /** Xoa payload fresh/stale va huy token cu; giu identity de tai su dung dong. */
    public static function clear(): bool
    {
        global $wpdb;
        return $wpdb->query('UPDATE ' . self::name() . " SET payload=NULL, data_date=NULL,
            fresh_until=0, stale_until=0, lock_token='', lock_until=0, updated_at=0") !== false;
    }
}
