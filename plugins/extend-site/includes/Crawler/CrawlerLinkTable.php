<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class CrawlerLinkTable
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_DUPLICATE = 'duplicate';

    public static function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'es_crawler_links';
    }

    public static function create(): void
    {
        global $wpdb;

        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_url_hash CHAR(32) NOT NULL,
            source_url TEXT NOT NULL,
            clean_url TEXT NOT NULL,
            batch_id VARCHAR(64) DEFAULT NULL,
            story_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            chapter_id BIGINT UNSIGNED DEFAULT NULL,
            chapter_number INT UNSIGNED DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            error_log TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_url_hash (source_url_hash),
            KEY batch_id (batch_id),
            KEY story_id (story_id),
            KEY chapter_id (chapter_id),
            KEY status (status),
            KEY chapter_number (chapter_number)
        ) ENGINE=InnoDB {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function clean_url_for_hash(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (array_keys($query) as $key) {
                $normalized = strtolower((string) $key);
                if ($normalized === 'fbclid' || $normalized === 'gclid' || strpos($normalized, 'utm_') === 0) {
                    unset($query[$key]);
                }
            }
        }

        $clean = '';
        if (!empty($parts['scheme'])) {
            $clean .= strtolower($parts['scheme']) . '://';
        }

        if (!empty($parts['user'])) {
            $clean .= $parts['user'];
            if (!empty($parts['pass'])) {
                $clean .= ':' . $parts['pass'];
            }
            $clean .= '@';
        }

        $clean .= strtolower($parts['host']);

        if (!empty($parts['port'])) {
            $clean .= ':' . $parts['port'];
        }

        $clean .= $parts['path'] ?? '';

        if ($query) {
            ksort($query);
            $clean .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $clean;
    }

    public static function hash_url(string $clean_url): string
    {
        return md5($clean_url);
    }

    public static function find_by_hash(string $hash): ?array
    {
        global $wpdb;

        if ($hash === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table_name() . ' WHERE source_url_hash = %s LIMIT 1',
                $hash
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function insert_pending(array $data)
    {
        global $wpdb;

        $source_url = isset($data['source_url']) ? trim((string) $data['source_url']) : '';
        $clean_url = isset($data['clean_url']) ? trim((string) $data['clean_url']) : self::clean_url_for_hash($source_url);
        $hash = isset($data['source_url_hash']) ? trim((string) $data['source_url_hash']) : self::hash_url($clean_url);

        if ($source_url === '' || $clean_url === '' || $hash === '') {
            return false;
        }

        $existing = self::find_by_hash($hash);
        if ($existing) {
            $updated = self::update_pending((int) $existing['id'], [
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'batch_id' => isset($data['batch_id']) ? sanitize_text_field((string) $data['batch_id']) : null,
                'story_id' => isset($data['story_id']) ? absint($data['story_id']) : 0,
                'chapter_number' => isset($data['chapter_number']) ? absint($data['chapter_number']) : null,
            ]);

            return $updated ? (int) $existing['id'] : false;
        }

        $now = current_time('mysql');
        $inserted = $wpdb->insert(
            self::get_table_name(),
            [
                'source_url_hash' => $hash,
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'batch_id' => isset($data['batch_id']) ? sanitize_text_field((string) $data['batch_id']) : null,
                'story_id' => isset($data['story_id']) ? absint($data['story_id']) : 0,
                'chapter_id' => isset($data['chapter_id']) ? absint($data['chapter_id']) : null,
                'chapter_number' => isset($data['chapter_number']) ? absint($data['chapter_number']) : null,
                'status' => self::STATUS_PENDING,
                'error_log' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    public static function mark_success(int $id, int $chapter_id): bool
    {
        return self::update_status($id, self::STATUS_SUCCESS, null, $chapter_id);
    }

    public static function mark_failed(int $id, string $error): bool
    {
        return self::update_status($id, self::STATUS_FAILED, $error);
    }

    public static function mark_skipped(int $id, string $reason): bool
    {
        return self::update_status($id, self::STATUS_SKIPPED, $reason);
    }

    public static function mark_duplicate(int $id, string $reason): bool
    {
        return self::update_status($id, self::STATUS_DUPLICATE, $reason);
    }

    private static function update_pending(int $id, array $data): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            self::get_table_name(),
            [
                'source_url' => $data['source_url'],
                'clean_url' => $data['clean_url'],
                'batch_id' => $data['batch_id'],
                'story_id' => $data['story_id'],
                'chapter_number' => $data['chapter_number'],
                'status' => self::STATUS_PENDING,
                'error_log' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    private static function update_status(int $id, string $status, ?string $message = null, ?int $chapter_id = null): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $data = [
            'status' => $status,
            'error_log' => $message,
            'updated_at' => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%s'];

        if ($chapter_id !== null) {
            $data['chapter_id'] = absint($chapter_id);
            $format[] = '%d';
        }

        $result = $wpdb->update(
            self::get_table_name(),
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }
}
