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
        self::ensure_columns();
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
            toc_page_link_selector TEXT DEFAULT NULL,
            chapter_url_pattern TEXT DEFAULT NULL,
            sample_story_url TEXT DEFAULT NULL,
            sample_chapter_url TEXT DEFAULT NULL,
            story_extract_rules LONGTEXT DEFAULT NULL,
            chapter_content_scope_selector TEXT DEFAULT NULL,
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

    private static function ensure_columns(): void
    {
        global $wpdb;

        $table = self::get_table_name();
        $column = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'chapter_content_scope_selector'));
        if (!$column) {
            $wpdb->query('ALTER TABLE ' . $table . ' ADD COLUMN chapter_content_scope_selector TEXT DEFAULT NULL AFTER story_extract_rules');
        }

        $sample_story_url = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'sample_story_url'));
        if (!$sample_story_url) {
            $wpdb->query('ALTER TABLE ' . $table . ' ADD COLUMN sample_story_url TEXT DEFAULT NULL AFTER chapter_url_pattern');
        }

        $sample_chapter_url = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'sample_chapter_url'));
        if (!$sample_chapter_url) {
            $wpdb->query('ALTER TABLE ' . $table . ' ADD COLUMN sample_chapter_url TEXT DEFAULT NULL AFTER sample_story_url');
        }
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

    public static function save(array $data): ?array
    {
        global $wpdb;

        $id = isset($data['id']) ? absint($data['id']) : 0;
        $now = current_time('mysql');
        $row = [
            'name' => sanitize_text_field((string) ($data['name'] ?? '')),
            'domain' => self::normalize_domain((string) ($data['domain'] ?? '')),
            'toc_type' => in_array(($data['toc_type'] ?? 'selector'), ['selector', 'pattern'], true) ? (string) $data['toc_type'] : 'selector',
            'chapter_link_selector' => sanitize_text_field((string) ($data['chapter_link_selector'] ?? '')),
            'toc_page_link_selector' => sanitize_text_field((string) ($data['toc_page_link_selector'] ?? '')),
            'chapter_url_pattern' => sanitize_text_field((string) ($data['chapter_url_pattern'] ?? '')),
            'sample_story_url' => esc_url_raw((string) ($data['sample_story_url'] ?? '')),
            'sample_chapter_url' => esc_url_raw((string) ($data['sample_chapter_url'] ?? '')),
            'story_extract_rules' => wp_json_encode(self::normalize_story_extract_rules((array) ($data['story_extract_rules'] ?? []))),
            'chapter_content_scope_selector' => sanitize_text_field((string) ($data['chapter_content_scope_selector'] ?? '')),
            'chapter_title_selector' => sanitize_text_field((string) ($data['chapter_title_selector'] ?? '')),
            'chapter_content_selector' => sanitize_text_field((string) ($data['chapter_content_selector'] ?? '')),
            'find_replace_rules' => wp_json_encode(self::normalize_replace_rules((array) ($data['find_replace_rules'] ?? []))),
            'delay_between' => max(1, absint($data['delay_between'] ?? 1)),
            'updated_at' => $now,
        ];

        if ($row['name'] === '' || $row['domain'] === '') {
            return null;
        }

        $formats = [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
        ];

        if ($id > 0) {
            $updated = $wpdb->update(self::get_table_name(), $row, ['id' => $id], $formats, ['%d']);
            if ($updated === false) {
                return null;
            }

            return self::find($id);
        }

        $row['created_at'] = $now;
        $inserted = $wpdb->insert(
            self::get_table_name(),
            $row,
            array_merge($formats, ['%s'])
        );

        if (!$inserted) {
            return null;
        }

        return self::find((int) $wpdb->insert_id);
    }

    public static function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        return (bool) $wpdb->delete(self::get_table_name(), ['id' => $id], ['%d']);
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
        $row['sample_story_url'] = isset($row['sample_story_url']) ? esc_url_raw((string) $row['sample_story_url']) : '';
        $row['sample_chapter_url'] = isset($row['sample_chapter_url']) ? esc_url_raw((string) $row['sample_chapter_url']) : '';
        $row['chapter_content_scope_selector'] = isset($row['chapter_content_scope_selector']) ? sanitize_text_field((string) $row['chapter_content_scope_selector']) : '';
        $row['delay_between'] = isset($row['delay_between']) ? max(1, absint($row['delay_between'])) : 1;

        $extract_rules = [];
        if (!empty($row['story_extract_rules'])) {
            $decoded = json_decode((string) $row['story_extract_rules'], true);
            $extract_rules = is_array($decoded) ? $decoded : [];
        }
        $row['story_extract_rules'] = self::normalize_story_extract_rules($extract_rules);

        $rules = [];
        if (!empty($row['find_replace_rules'])) {
            $decoded = json_decode((string) $row['find_replace_rules'], true);
            $rules = is_array($decoded) ? $decoded : [];
        }
        $row['find_replace_rules'] = self::normalize_replace_rules($rules);

        return $row;
    }

    private static function normalize_story_extract_rules(array $rules): array
    {
        $defaults = [
            'story_title' => 'node_text',
            'story_author' => 'first_link_text',
            'story_desc' => 'node_text',
            'story_thumb' => 'first_image_src',
            'story_cats' => 'all_link_texts',
        ];

        $normalized = [];
        foreach ($defaults as $field => $default_value_mode) {
            $rule = isset($rules[$field]) && is_array($rules[$field]) ? $rules[$field] : [];

            $normalized[$field] = [
                'selector' => isset($rule['selector']) ? trim(sanitize_text_field((string) $rule['selector'])) : '',
                'label' => isset($rule['label']) ? trim(sanitize_text_field((string) $rule['label'])) : '',
                'value_mode' => self::normalize_value_mode((string) ($rule['value_mode'] ?? $default_value_mode)),
            ];
        }

        return $normalized;
    }

    private static function normalize_value_mode(string $mode): string
    {
        $allowed = [
            'next_text',
            'first_link_text',
            'all_link_texts',
            'first_link_href',
            'first_image_src',
            'node_text',
            'node_html',
        ];

        return in_array($mode, $allowed, true) ? $mode : 'node_text';
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
