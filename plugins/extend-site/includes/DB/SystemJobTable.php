<?php

namespace ExtendSite\DB;

defined('ABSPATH') || exit;

class SystemJobTable
{
    public static function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'es_system_jobs';
    }

    public static function create(): void
    {
        global $wpdb;

        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_key VARCHAR(80) NOT NULL,
            job_type VARCHAR(80) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payload LONGTEXT NULL,
            last_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            processed BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total BIGINT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY job_key (job_key),
            KEY status (status),
            KEY job_type_status (job_type, status),
            KEY updated_at (updated_at),
            KEY finished_at (finished_at)
        ) ENGINE=InnoDB {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function insert(array $job): void
    {
        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            [
                'job_key' => (string) ($job['id'] ?? ''),
                'job_type' => sanitize_key((string) ($job['type'] ?? '')),
                'status' => sanitize_key((string) ($job['status'] ?? 'pending')),
                'payload' => wp_json_encode($job['payload'] ?? []),
                'last_item_id' => absint($job['last_item_id'] ?? 0),
                'processed' => absint($job['processed'] ?? 0),
                'total' => absint($job['total'] ?? 0),
                'message' => (string) ($job['message'] ?? ''),
                'created_at' => (string) ($job['created_at'] ?? current_time('mysql')),
                'updated_at' => (string) ($job['updated_at'] ?? current_time('mysql')),
                'finished_at' => self::finished_at_for_status((string) ($job['status'] ?? 'pending'), $job['finished_at'] ?? null),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
    }

    public static function get_by_key(string $job_key): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::get_table_name() . ' WHERE job_key = %s LIMIT 1', $job_key),
            ARRAY_A
        );

        return $row ? self::normalize_row($row) : null;
    }

    public static function get_all(int $limit = 100): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table_name() . ' ORDER BY id DESC LIMIT %d',
                max(1, $limit)
            ),
            ARRAY_A
        );

        $jobs = [];
        foreach ($rows ?: [] as $row) {
            $job = self::normalize_row($row);
            $jobs[$job['id']] = $job;
        }

        return $jobs;
    }

    public static function get_pending(int $limit = 3): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE status = 'pending' ORDER BY id ASC LIMIT %d",
                max(1, $limit)
            ),
            ARRAY_A
        );

        $jobs = [];
        foreach ($rows ?: [] as $row) {
            $job = self::normalize_row($row);
            $jobs[$job['id']] = $job;
        }

        return $jobs;
    }

    public static function update_by_key(string $job_key, array $updates): void
    {
        global $wpdb;

        $data = [];
        $formats = [];

        foreach ($updates as $key => $value) {
            switch ($key) {
                case 'type':
                    $data['job_type'] = sanitize_key((string) $value);
                    $formats[] = '%s';
                    break;
                case 'status':
                    $status = sanitize_key((string) $value);
                    $data['status'] = $status;
                    $formats[] = '%s';
                    $data['finished_at'] = self::finished_at_for_status($status, $updates['finished_at'] ?? null);
                    $formats[] = '%s';
                    break;
                case 'payload':
                    $data['payload'] = wp_json_encode(is_array($value) ? $value : []);
                    $formats[] = '%s';
                    break;
                case 'last_item_id':
                    $data['last_item_id'] = absint($value);
                    $formats[] = '%d';
                    break;
                case 'processed':
                    $data['processed'] = absint($value);
                    $formats[] = '%d';
                    break;
                case 'total':
                    $data['total'] = absint($value);
                    $formats[] = '%d';
                    break;
                case 'message':
                    $data['message'] = (string) $value;
                    $formats[] = '%s';
                    break;
                case 'updated_at':
                    $data['updated_at'] = (string) $value;
                    $formats[] = '%s';
                    break;
                case 'finished_at':
                    if (!array_key_exists('finished_at', $data)) {
                        $data['finished_at'] = $value ? (string) $value : null;
                        $formats[] = '%s';
                    }
                    break;
            }
        }

        if (!array_key_exists('updated_at', $data)) {
            $data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        }

        if (!$data) {
            return;
        }

        $wpdb->update(
            self::get_table_name(),
            $data,
            ['job_key' => $job_key],
            $formats,
            ['%s']
        );
    }

    public static function has_active_jobs(): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::get_table_name() . " WHERE status IN ('pending', 'running')"
        ) > 0;
    }

    public static function cleanup_finished(?int $days = null): int
    {
        global $wpdb;

        if ($days !== null) {
            $threshold = date('Y-m-d H:i:s', current_time('timestamp') - (max(1, $days) * DAY_IN_SECONDS));

            return (int) $wpdb->query($wpdb->prepare(
                "
                DELETE FROM " . self::get_table_name() . "
                WHERE status IN ('done', 'failed', 'cancelled')
                  AND finished_at IS NOT NULL
                  AND finished_at < %s
                ",
                $threshold
            ));
        }

        return (int) $wpdb->query(
            "
            DELETE FROM " . self::get_table_name() . "
            WHERE status IN ('done', 'failed', 'cancelled')
            "
        );
    }

    private static function normalize_row(array $row): array
    {
        $payload = [];
        if (!empty($row['payload'])) {
            $decoded = json_decode((string) $row['payload'], true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => (string) ($row['job_key'] ?? ''),
            'type' => (string) ($row['job_type'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'payload' => $payload,
            'last_item_id' => absint($row['last_item_id'] ?? 0),
            'processed' => absint($row['processed'] ?? 0),
            'total' => absint($row['total'] ?? 0),
            'message' => (string) ($row['message'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'finished_at' => (string) ($row['finished_at'] ?? ''),
        ];
    }

    private static function finished_at_for_status(string $status, $fallback): ?string
    {
        if (!in_array($status, ['done', 'failed', 'cancelled'], true)) {
            return null;
        }

        return $fallback ? (string) $fallback : current_time('mysql');
    }
}
