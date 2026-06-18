<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class CrawlerTemplateTable
{
    public static function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'es_crawler_templates';
    }

    public static function create(): void
    {
        global $wpdb;

        $sql = self::get_creation_sql();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function get_creation_sql(): string
    {
        global $wpdb;

        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        return "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            domain VARCHAR(191) NOT NULL,
            toc_type VARCHAR(50) NOT NULL DEFAULT 'selector',
            chapter_link_selector TEXT DEFAULT NULL,
            chapter_url_pattern TEXT DEFAULT NULL,
            story_title_selector TEXT DEFAULT NULL,
            story_author_selector TEXT DEFAULT NULL,
            story_desc_selector TEXT DEFAULT NULL,
            story_thumb_selector TEXT DEFAULT NULL,
            story_cats_selector TEXT DEFAULT NULL,
            chapter_title_selector TEXT DEFAULT NULL,
            chapter_content_selector TEXT DEFAULT NULL,
            find_replace_rules LONGTEXT DEFAULT NULL,
            delay_between INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY domain (domain)
        ) ENGINE=InnoDB {$charset};";
    }

    public static function all(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT * FROM ' . self::get_table_name() . ' ORDER BY name ASC, domain ASC',
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'normalize_row'], $rows);
    }

    public static function find(int $id): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::get_table_name() . ' WHERE id = %d LIMIT 1', $id),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_row($row) : null;
    }

    public static function find_by_domain(string $domain): ?array
    {
        global $wpdb;

        $domain = self::normalize_domain($domain);
        if ($domain === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table_name() . ' WHERE domain = %s ORDER BY id DESC LIMIT 1',
                $domain
            ),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_row($row) : null;
    }

    public static function normalize_domain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain) ?: $domain;
        $domain = preg_replace('/^www\./', '', $domain) ?: $domain;
        $domain = preg_replace('/[\/?#].*$/', '', $domain) ?: $domain;

        return sanitize_text_field($domain);
    }

    private static function normalize_row(array $row): array
    {
        $row['id'] = isset($row['id']) ? (int) $row['id'] : 0;
        $row['name'] = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        $row['domain'] = isset($row['domain']) ? self::normalize_domain((string) $row['domain']) : '';
        $row['toc_type'] = isset($row['toc_type']) ? sanitize_key((string) $row['toc_type']) : 'selector';
        $row['delay_between'] = isset($row['delay_between']) ? max(1, absint($row['delay_between'])) : 1;

        $rules = [];
        if (!empty($row['find_replace_rules'])) {
            $decoded = json_decode((string) $row['find_replace_rules'], true);
            $rules = is_array($decoded) ? $decoded : [];
        }
        $row['find_replace_rules'] = self::normalize_replace_rules($rules);

        return $row;
    }

    private static function normalize_replace_rules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $find = isset($rule['find']) ? (string) $rule['find'] : '';
            if ($find === '') {
                continue;
            }

            $normalized[] = [
                'find' => $find,
                'replace' => isset($rule['replace']) ? (string) $rule['replace'] : '',
                'regex' => !empty($rule['regex']),
                'remove_container' => !empty($rule['remove_container']),
            ];
        }

        return $normalized;
    }
}
