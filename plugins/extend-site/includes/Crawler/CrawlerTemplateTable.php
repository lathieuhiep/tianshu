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
            template_key VARCHAR(64) NOT NULL DEFAULT '',
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
            cleanup_selectors LONGTEXT DEFAULT NULL,
            find_replace_rules LONGTEXT DEFAULT NULL,
            delay_between INT UNSIGNED NOT NULL DEFAULT 1,
            deleted_at DATETIME DEFAULT NULL,
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
        $template_key = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'template_key'));
        if (!$template_key) {
            $wpdb->query('ALTER TABLE ' . $table . ' ADD COLUMN template_key VARCHAR(64) NOT NULL DEFAULT \'\' AFTER id');
        }

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

        $deleted_at = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'deleted_at'));
        if (!$deleted_at) {
            $wpdb->query('ALTER TABLE ' . $table . ' ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER delay_between');
        }

        if (!self::has_cleanup_selectors_column()) {
            $wpdb->query('ALTER TABLE ' . $table . ' ADD COLUMN cleanup_selectors LONGTEXT DEFAULT NULL AFTER chapter_content_selector');
        }

        self::backfill_template_keys();
        self::repair_duplicate_template_keys();
        self::ensure_template_key_index();
    }

    public static function all(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT * FROM ' . self::get_table_name() . ' WHERE deleted_at IS NULL ORDER BY name ASC, domain ASC',
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'normalize_row'], $rows);
    }

    public static function query(array $args = []): array
    {
        global $wpdb;

        $per_page = isset($args['per_page']) ? absint($args['per_page']) : 20;
        $per_page = max(1, min(100, $per_page));
        $paged = isset($args['paged']) ? absint($args['paged']) : 1;
        $paged = max(1, $paged);
        $offset = ($paged - 1) * $per_page;
        $search = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';
        $status = isset($args['status']) ? sanitize_key((string) $args['status']) : 'active';
        $where = self::list_where_sql($search, $status);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table_name() . $where['sql'] . ' ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d',
                array_merge($where['params'], [$per_page, $offset])
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'normalize_row'], $rows);
    }

    public static function count(array $args = []): int
    {
        global $wpdb;

        $search = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';
        $status = isset($args['status']) ? sanitize_key((string) $args['status']) : 'active';
        $where = self::list_where_sql($search, $status);

        $sql = 'SELECT COUNT(*) FROM ' . self::get_table_name() . $where['sql'];
        if ($where['params']) {
            $sql = $wpdb->prepare($sql, $where['params']);
        }

        return (int) $wpdb->get_var($sql);
    }

    public static function find(int $id, bool $include_trashed = false): ?array
    {
        global $wpdb;

        if ($id <= 0) {
            return null;
        }

        $where = $include_trashed ? 'WHERE id = %d' : 'WHERE id = %d AND deleted_at IS NULL';
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_table_name() . ' ' . $where . ' LIMIT 1', $id), ARRAY_A);

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
                'SELECT * FROM ' . self::get_table_name() . ' WHERE domain = %s AND deleted_at IS NULL ORDER BY id DESC LIMIT 1',
                $domain
            ),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_row($row) : null;
    }

    public static function find_by_template_key(string $template_key, bool $include_trashed = false): ?array
    {
        global $wpdb;

        $template_key = self::sanitize_template_key($template_key);
        if ($template_key === '') {
            return null;
        }

        $where = $include_trashed ? 'WHERE template_key = %s' : 'WHERE template_key = %s AND deleted_at IS NULL';
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::get_table_name() . ' ' . $where . ' ORDER BY id DESC LIMIT 1', $template_key),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_row($row) : null;
    }

    public static function find_by_name_domain(string $name, string $domain): ?array
    {
        global $wpdb;

        $name = sanitize_text_field($name);
        $domain = self::normalize_domain($domain);
        if ($name === '' || $domain === '') {
            return null;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table_name() . ' WHERE name = %s AND domain = %s AND deleted_at IS NULL ORDER BY id DESC LIMIT 2',
                $name,
                $domain
            ),
            ARRAY_A
        );

        return is_array($rows) && count($rows) === 1 ? self::normalize_row($rows[0]) : null;
    }

    public static function save(array $data): ?array
    {
        global $wpdb;

        $id = isset($data['id']) ? absint($data['id']) : 0;
        $now = current_time('mysql');
        $existing = $id > 0 ? self::find($id, true) : null;
        $template_key = is_array($existing) ? self::sanitize_template_key((string) ($existing['template_key'] ?? '')) : '';
        $template_key = $template_key !== '' ? $template_key : self::sanitize_template_key((string) ($data['template_key'] ?? ''));
        if ($template_key === '') {
            $template_key = self::generate_template_key();
        }

        $row = [
            'template_key' => $template_key,
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
            'cleanup_selectors' => wp_json_encode(self::normalize_cleanup_selectors($data['cleanup_selectors'] ?? [])),
            'find_replace_rules' => wp_json_encode(self::normalize_replace_rules((array) ($data['find_replace_rules'] ?? []))),
            'delay_between' => max(1, absint($data['delay_between'] ?? 1)),
            'updated_at' => $now,
        ];

        if ($row['name'] === '' || $row['domain'] === '') {
            return null;
        }

        $formats = [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
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
        return self::trash($id);
    }

    public static function trash(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        return $wpdb->update(
            self::get_table_name(),
            [
                'deleted_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    public static function restore(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        return $wpdb->update(
            self::get_table_name(),
            [
                'deleted_at' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    public static function force_delete(int $id): bool
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

    public static function sanitize_template_key(string $template_key): string
    {
        $template_key = trim($template_key);
        if ($template_key === '' || strlen($template_key) > 64) {
            return '';
        }

        return preg_match('/^[a-zA-Z0-9_:-]+$/', $template_key) ? $template_key : '';
    }

    public static function generate_template_key(): string
    {
        do {
            try {
                $key = 'esct_' . bin2hex(random_bytes(12));
            } catch (\Throwable $e) {
                $key = str_replace('.', '', uniqid('esct_', true));
            }
        } while (self::find_by_template_key($key, true));

        return $key;
    }

    public static function has_template_key_index(): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare('SHOW INDEX FROM ' . self::get_table_name() . ' WHERE Key_name = %s', 'template_key')
        );
    }

    public static function has_cleanup_selectors_column(): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare('SHOW COLUMNS FROM ' . self::get_table_name() . ' LIKE %s', 'cleanup_selectors')
        );
    }

    private static function list_where_sql(string $search, string $status): array
    {
        global $wpdb;

        $clauses = [];
        $params = [];

        if ($status === 'trash') {
            $clauses[] = 'deleted_at IS NOT NULL';
        } else {
            $clauses[] = 'deleted_at IS NULL';
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $clauses[] = '(name LIKE %s OR domain LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        return [
            'sql' => ' WHERE ' . implode(' AND ', $clauses),
            'params' => $params,
        ];
    }

    private static function normalize_row(array $row): array
    {
        $row['id'] = isset($row['id']) ? (int) $row['id'] : 0;
        $row['template_key'] = isset($row['template_key']) ? self::sanitize_template_key((string) $row['template_key']) : '';
        $row['name'] = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        $row['domain'] = isset($row['domain']) ? self::normalize_domain((string) $row['domain']) : '';
        $row['toc_type'] = isset($row['toc_type']) ? sanitize_key((string) $row['toc_type']) : 'selector';
        $row['sample_story_url'] = isset($row['sample_story_url']) ? esc_url_raw((string) $row['sample_story_url']) : '';
        $row['sample_chapter_url'] = isset($row['sample_chapter_url']) ? esc_url_raw((string) $row['sample_chapter_url']) : '';
        $row['chapter_content_scope_selector'] = isset($row['chapter_content_scope_selector']) ? sanitize_text_field((string) $row['chapter_content_scope_selector']) : '';
        $row['delay_between'] = isset($row['delay_between']) ? max(1, absint($row['delay_between'])) : 1;
        $row['deleted_at'] = isset($row['deleted_at']) ? sanitize_text_field((string) $row['deleted_at']) : '';

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

        $cleanup_selectors = [];
        if (!empty($row['cleanup_selectors'])) {
            $decoded = json_decode((string) $row['cleanup_selectors'], true);
            $cleanup_selectors = is_array($decoded) ? $decoded : [];
        }
        $row['cleanup_selectors'] = self::normalize_cleanup_selectors($cleanup_selectors);

        return $row;
    }

    private static function backfill_template_keys(): void
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            'SELECT id FROM ' . self::get_table_name() . ' WHERE template_key = \'\' OR template_key IS NULL',
            ARRAY_A
        );
        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $id = isset($row['id']) ? absint($row['id']) : 0;
            if ($id <= 0) {
                continue;
            }

            $wpdb->update(
                self::get_table_name(),
                ['template_key' => self::generate_template_key()],
                ['id' => $id],
                ['%s'],
                ['%d']
            );
        }
    }

    private static function repair_duplicate_template_keys(): void
    {
        global $wpdb;

        $keys = $wpdb->get_col(
            'SELECT template_key FROM ' . self::get_table_name() . ' WHERE template_key <> \'\' GROUP BY template_key HAVING COUNT(*) > 1'
        );
        if (!is_array($keys)) {
            return;
        }

        foreach ($keys as $key) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id FROM ' . self::get_table_name() . ' WHERE template_key = %s ORDER BY id ASC',
                    (string) $key
                ),
                ARRAY_A
            );
            if (!is_array($rows) || count($rows) < 2) {
                continue;
            }

            array_shift($rows);
            foreach ($rows as $row) {
                $id = isset($row['id']) ? absint($row['id']) : 0;
                if ($id <= 0) {
                    continue;
                }

                $wpdb->update(
                    self::get_table_name(),
                    ['template_key' => self::generate_template_key()],
                    ['id' => $id],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    private static function ensure_template_key_index(): void
    {
        global $wpdb;

        if (self::has_template_key_index()) {
            return;
        }

        $wpdb->query('ALTER TABLE ' . self::get_table_name() . ' ADD UNIQUE KEY template_key (template_key)');
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

    public static function normalize_cleanup_selectors($selectors): array
    {
        if (is_string($selectors)) {
            $selectors = preg_split('/\r\n|\r|\n/', $selectors) ?: [];
        }

        if (!is_array($selectors)) {
            return [];
        }

        $normalized = [];
        foreach ($selectors as $selector) {
            $selector = trim(sanitize_text_field((string) $selector));
            if ($selector === '' || CssSelector::to_xpath($selector) === '') {
                continue;
            }

            $normalized[] = $selector;
        }

        return array_values(array_unique($normalized));
    }
}
