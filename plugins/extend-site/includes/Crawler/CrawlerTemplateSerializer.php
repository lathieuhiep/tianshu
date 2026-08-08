<?php

namespace ExtendSite\Crawler;

use WP_Error;

defined('ABSPATH') || exit;

class CrawlerTemplateSerializer
{
    private const TYPE = 'extend-site-crawler-template';
    private const COLLECTION_TYPE = 'extend-site-crawler-template-collection';
    private const VERSION = 1;

    public static function fields(): array
    {
        return [
            'name',
            'domain',
            'toc_type',
            'chapter_link_selector',
            'toc_page_link_selector',
            'chapter_url_pattern',
            'sample_story_url',
            'sample_chapter_url',
            'story_extract_rules',
            'chapter_content_scope_selector',
            'chapter_title_selector',
            'chapter_content_selector',
            'find_replace_rules',
            'delay_between',
        ];
    }

    public static function export_payload(array $template): array
    {
        $data = [];
        foreach (self::fields() as $field) {
            $data[$field] = $template[$field] ?? self::default_value($field);
        }

        return [
            'type' => self::TYPE,
            'version' => self::VERSION,
            'exported_at' => current_time('mysql'),
            'template' => $data,
        ];
    }

    public static function export_collection_payload(array $templates): array
    {
        $items = [];
        foreach ($templates as $template) {
            if (is_array($template)) {
                $items[] = self::export_payload($template)['template'];
            }
        }

        return [
            'type' => self::COLLECTION_TYPE,
            'version' => self::VERSION,
            'exported_at' => current_time('mysql'),
            'templates' => $items,
        ];
    }

    /**
     * @return array|WP_Error
     */
    public static function import_items(array $payload)
    {
        if (($payload['type'] ?? '') === self::COLLECTION_TYPE) {
            if (empty($payload['templates']) || !is_array($payload['templates'])) {
                return new WP_Error('invalid_collection', __('File JSON không có danh sách mẫu crawler hợp lệ.', 'extend-site'));
            }

            $items = [];
            foreach ($payload['templates'] as $template) {
                if (!is_array($template)) {
                    return new WP_Error('invalid_collection_item', __('Danh sách mẫu crawler trong file JSON không hợp lệ.', 'extend-site'));
                }

                $data = self::import_data($template);
                if (is_wp_error($data)) {
                    return $data;
                }

                $items[] = $data;
            }

            return $items;
        }

        $data = self::import_data($payload);
        if (is_wp_error($data)) {
            return $data;
        }

        return [$data];
    }

    /**
     * @return array|WP_Error
     */
    public static function import_data(array $payload)
    {
        $template = self::extract_template($payload);
        if (is_wp_error($template)) {
            return $template;
        }

        $data = ['id' => 0];
        foreach (self::fields() as $field) {
            $data[$field] = $template[$field] ?? self::default_value($field);
        }

        $data['domain'] = CrawlerTemplateTable::normalize_domain((string) $data['domain']);
        $data['toc_type'] = in_array($data['toc_type'], ['selector', 'pattern'], true) ? (string) $data['toc_type'] : 'selector';
        $data['sample_story_url'] = esc_url_raw((string) $data['sample_story_url']);
        $data['sample_chapter_url'] = esc_url_raw((string) $data['sample_chapter_url']);
        $data['chapter_url_pattern'] = str_replace('{n}', '{chapter_number}', (string) $data['chapter_url_pattern']);
        $data['delay_between'] = max(1, absint($data['delay_between']));
        $data['story_extract_rules'] = is_array($data['story_extract_rules']) ? $data['story_extract_rules'] : [];
        $data['find_replace_rules'] = is_array($data['find_replace_rules']) ? $data['find_replace_rules'] : [];

        if (trim((string) $data['name']) === '') {
            return new WP_Error('missing_name', __('File import thiếu tên mẫu crawler.', 'extend-site'));
        }

        if ($data['domain'] === '') {
            return new WP_Error('missing_domain', __('File import thiếu domain mẫu crawler.', 'extend-site'));
        }

        if (trim((string) $data['chapter_content_scope_selector']) === '') {
            return new WP_Error('missing_chapter_scope', __('File import thiếu selector khối bóc nội dung chương.', 'extend-site'));
        }

        if (
            trim((string) $data['chapter_link_selector']) === ''
            && trim((string) $data['chapter_url_pattern']) === ''
        ) {
            return new WP_Error('missing_chapter_source', __('File import cần selector danh sách chương hoặc mẫu URL chương.', 'extend-site'));
        }

        if (
            trim((string) $data['chapter_url_pattern']) !== ''
            && strpos((string) $data['chapter_url_pattern'], '{chapter_number}') === false
        ) {
            return new WP_Error('invalid_chapter_pattern', __('Mẫu URL chương trong file import phải có {chapter_number} hoặc {n}.', 'extend-site'));
        }

        return $data;
    }

    /**
     * @return array|WP_Error
     */
    private static function extract_template(array $payload)
    {
        if (isset($payload['type']) && $payload['type'] !== self::TYPE) {
            return new WP_Error('invalid_type', __('File JSON không phải mẫu crawler của Extend Site.', 'extend-site'));
        }

        if (isset($payload['template'])) {
            if (!is_array($payload['template'])) {
                return new WP_Error('invalid_template', __('Dữ liệu template trong file JSON không hợp lệ.', 'extend-site'));
            }

            return $payload['template'];
        }

        return $payload;
    }

    private static function default_value(string $field)
    {
        if (in_array($field, ['story_extract_rules', 'find_replace_rules'], true)) {
            return [];
        }

        if ($field === 'delay_between') {
            return 1;
        }

        if ($field === 'toc_type') {
            return 'selector';
        }

        return '';
    }
}
