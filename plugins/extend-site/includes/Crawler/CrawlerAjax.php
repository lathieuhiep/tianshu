<?php

namespace ExtendSite\Crawler;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use ExtendSite\DB\LatestChapterTable;
use ExtendSite\PostType\ChapterPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;
use WP_Error;
use WP_Query;

defined('ABSPATH') || exit;

class CrawlerAjax
{
    public const NONCE_ACTION = 'es_crawler_nonce';
    public const ACTION_START = 'es_crawler_start_batch';
    public const ACTION_HEARTBEAT = 'es_crawler_heartbeat';
    public const ACTION_STOP = 'es_crawler_stop_batch';
    public const ACTION_PREVIEW = 'es_crawler_preview_url';
    public const ACTION_TEMPLATE_PREVIEW_PROXY = 'es_crawler_preview_proxy';
    public const ACTION_TEMPLATE_TEST_PARSE = 'es_crawler_test_parse';
    public const ACTION_PROCESS = 'es_crawler_process_url';
    public const ACTION_FINALIZE = 'es_crawler_finalize_story';
    public const MAX_BATCH_SIZE = 200;
    public const TITLE_MODE_AUTO = 'auto';
    public const TITLE_MODE_NUMBER = 'number';
    public const TITLE_MODE_STORY_NUMBER = 'story_number';
    public const TITLE_MODE_SOURCE_PREFIXED = 'source_prefixed';
    public const TITLE_MODE_CUSTOM = 'custom';

    public static function init(): void
    {
        add_action('wp_ajax_' . self::ACTION_START, [self::class, 'start_batch']);
        add_action('wp_ajax_' . self::ACTION_HEARTBEAT, [self::class, 'heartbeat']);
        add_action('wp_ajax_' . self::ACTION_STOP, [self::class, 'stop_batch']);
        add_action('wp_ajax_' . self::ACTION_PREVIEW, [self::class, 'preview_url']);
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_PREVIEW_PROXY, [self::class, 'preview_proxy']);
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_TEST_PARSE, [self::class, 'test_parse']);
        add_action('wp_ajax_' . self::ACTION_PROCESS, [self::class, 'process_url']);
        add_action('wp_ajax_' . self::ACTION_FINALIZE, [self::class, 'finalize_story']);
    }

    public static function start_batch(): void
    {
        self::verify_request();

        $story_id = self::get_valid_story_id();
        $expected_total = self::get_expected_total();
        $lock_result = CrawlerLock::acquire($story_id, get_current_user_id(), CrawlerLock::DEFAULT_TTL, $expected_total);

        if (!$lock_result['acquired']) {
            wp_send_json_error([
                'message' => __('Dang co mot batch crawler khac chay.', 'extend-site'),
                'lock' => $lock_result['lock'],
            ], 409);
        }

        wp_send_json_success([
            'message' => __('Batch crawler da bat dau.', 'extend-site'),
            'batch_id' => $lock_result['lock']['batch_id'],
            'lock' => $lock_result['lock'],
        ]);
    }

    public static function heartbeat(): void
    {
        self::verify_request();

        $batch_id = self::get_batch_id();
        $lock = CrawlerLock::heartbeat($batch_id);
        if (!$lock) {
            wp_send_json_error([
                'message' => __('Lock crawler bi thieu, da het han hoac khong khop.', 'extend-site'),
            ], 409);
        }

        wp_send_json_success([
            'message' => __('Heartbeat crawler da duoc chap nhan.', 'extend-site'),
            'lock' => $lock,
        ]);
    }

    public static function stop_batch(): void
    {
        self::verify_request();

        $batch_id = self::get_batch_id();
        if (!CrawlerLock::release($batch_id)) {
            wp_send_json_error([
                'message' => __('Lock crawler bi thieu, da het han hoac khong khop.', 'extend-site'),
            ], 409);
        }

        wp_send_json_success([
            'message' => __('Batch crawler da dung.', 'extend-site'),
        ]);
    }

    public static function preview_url(): void
    {
        self::verify_request();

        $story_id = self::get_valid_story_id();
        $chapter_number = self::get_valid_chapter_number();
        $source_url = self::get_source_url();
        $replace_rules = self::get_replace_rules();
        $allow_short = self::get_bool('allow_short_content');
        $title_mode = self::get_title_mode();
        $title_template = self::get_title_template();
        $expected_chapter_number = self::resolve_expected_chapter_number($source_url, $chapter_number);

        $result = Scraper::scrape($source_url, $replace_rules, $allow_short);
        if (is_wp_error($result)) {
            wp_send_json_error(self::error_payload($result, [
                'source_url' => $source_url,
                'story_id' => $story_id,
                'chapter_number' => $expected_chapter_number,
            ]));
        }

        $chapter_mismatch = self::validate_scraped_chapter_number($result, $expected_chapter_number);
        if (is_wp_error($chapter_mismatch)) {
            wp_send_json_error(self::error_payload($chapter_mismatch, [
                'source_url' => $source_url,
                'clean_url' => $result['clean_url'],
                'story_id' => $story_id,
                'chapter_number' => $expected_chapter_number,
                'content_length' => $result['content_length'],
                'warnings' => $result['warnings'],
            ]));
        }

        $final_title = self::build_chapter_title($story_id, $expected_chapter_number, (string) $result['title'], $title_mode, $title_template);

        wp_send_json_success([
            'status' => 'success',
            'message' => __('Da phan tich ban xem thu thanh cong.', 'extend-site'),
            'source_url' => $source_url,
            'clean_url' => $result['clean_url'],
            'domain' => $result['domain'],
            'rule_label' => $result['rule_label'],
            'title' => $final_title,
            'source_title' => $result['title'],
            'final_title' => $final_title,
            'content_preview_html' => $result['content_html'],
            'content_length' => $result['content_length'],
            'source_chapter_number' => $result['source_chapter_number'] ?? 0,
            'source_max_chapter_number' => $result['source_max_chapter_number'] ?? 0,
            'story_id' => $story_id,
            'chapter_number' => $expected_chapter_number,
            'warnings' => $result['warnings'],
        ]);
    }

    public static function preview_proxy(): void
    {
        self::verify_request();

        $target_url = self::get_target_url('target_url');
        $body = self::fetch_html($target_url, 20);
        if (is_wp_error($body)) {
            wp_send_json_error([
                'message' => $body->get_error_message(),
            ], 400);
        }

        $base_url = self::get_base_url($target_url);
        $html = self::sanitize_preview_html($body, $base_url);

        wp_send_json_success([
            'html' => $html,
            'base_url' => $base_url,
        ]);
    }

    public static function test_parse(): void
    {
        self::verify_request();

        $target_url = self::get_target_url('target_url');
        $selectors = self::get_template_selectors();
        $warnings = [];

        $body = self::fetch_html($target_url, 20);
        if (is_wp_error($body)) {
            wp_send_json_error([
                'message' => $body->get_error_message(),
            ], 400);
        }

        $dom = self::load_dom($body);
        if (is_wp_error($dom)) {
            wp_send_json_error([
                'message' => $dom->get_error_message(),
            ], 400);
        }

        $xpath = new DOMXPath($dom);
        $matched = [];
        foreach ($selectors as $key => $selector) {
            $matched[$key] = $selector !== '' ? self::query_selector_count($xpath, $selector) : 0;
            if ($selector !== '' && $matched[$key] === 0) {
                $warnings[] = sprintf(__('Selector khong co ket qua: %s.', 'extend-site'), $key);
            }
        }

        $extractors = self::get_template_extractors();
        $story_title = self::extract_template_value($xpath, 'story_title', $selectors['story_title_selector'], $extractors, $target_url);
        $story_author = self::extract_template_value($xpath, 'story_author', $selectors['story_author_selector'], $extractors, $target_url);
        $story_desc = self::limit_text(self::extract_template_value($xpath, 'story_desc', $selectors['story_desc_selector'], $extractors, $target_url), 500);
        $story_thumb = self::extract_template_value($xpath, 'story_thumb', $selectors['story_thumb_selector'], $extractors, $target_url, 'first_image_src');
        $story_cats_value = self::extract_template_value($xpath, 'story_cats', $selectors['story_cats_selector'], $extractors, $target_url, 'all_link_texts');
        $story_cats = is_array($story_cats_value) ? $story_cats_value : array_filter(array_map('trim', explode(',', (string) $story_cats_value)));
        $chapter_title = self::first_selector_text($xpath, $selectors['chapter_title_selector']);
        $chapter_content = self::first_selector_text($xpath, $selectors['chapter_content_selector']);
        $chapter_links = self::chapter_link_samples($xpath, $selectors['chapter_link_selector'], $target_url);

        if ($story_title === '') {
            $warnings[] = __('Khong boc duoc tieu de truyen.', 'extend-site');
        }

        if ($selectors['chapter_link_selector'] !== '' && $chapter_links['count'] === 0) {
            $warnings[] = __('Khong tim thay link chuong tu selector muc luc.', 'extend-site');
        }

        wp_send_json_success([
            'story_title' => $story_title,
            'story_author' => $story_author,
            'story_desc' => $story_desc,
            'story_thumb' => $story_thumb,
            'story_cats' => $story_cats,
            'chapter_title' => $chapter_title,
            'chapter_content_length' => mb_strlen($chapter_content),
            'chapter_link_count' => $chapter_links['count'],
            'chapter_link_samples' => $chapter_links['samples'],
            'matched' => $matched,
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    public static function process_url(): void
    {
        self::verify_request();

        $story_id = self::get_valid_story_id();
        self::require_matching_lock($story_id);
        $chapter_number = self::get_valid_chapter_number();
        $batch_id = self::get_batch_id();
        $source_url = self::get_source_url();
        $post_status = self::get_post_status();
        $replace_rules = self::get_replace_rules();
        $allow_short = self::get_bool('allow_short_content');
        $title_mode = self::get_title_mode();
        $title_template = self::get_title_template();
        $expected_chapter_number = self::resolve_expected_chapter_number($source_url, $chapter_number);

        $clean_url = CrawlerLinkTable::clean_url_for_hash($source_url);
        $hash = CrawlerLinkTable::hash_url($clean_url);
        $existing = CrawlerLinkTable::find_by_story_and_hash($story_id, $hash);

        if ($existing && $existing['status'] === CrawlerLinkTable::STATUS_SUCCESS) {
            $existing_chapter_id = isset($existing['chapter_id']) ? (int) $existing['chapter_id'] : 0;
            if (!$existing_chapter_id || !self::is_existing_story_chapter($existing_chapter_id, $story_id)) {
                $existing_chapter_id = self::find_existing_chapter_by_source_hash($story_id, $hash);
            }

            if (!$existing_chapter_id) {
                $existing = null;
            } else {
                wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_DUPLICATE, __('URL nguon nay da crawl thanh cong truoc do.', 'extend-site'), [
                    'source_url' => $source_url,
                    'clean_url' => $clean_url,
                    'story_id' => $story_id,
                    'chapter_id' => $existing_chapter_id,
                    'chapter_number' => $chapter_number,
                ]));
            }
        }

        if (!$existing) {
            self::enforce_batch_capacity($batch_id);
        }

        $tracking_id = CrawlerLinkTable::insert_pending([
            'source_url' => $source_url,
            'clean_url' => $clean_url,
            'source_url_hash' => $hash,
            'batch_id' => $batch_id,
            'story_id' => $story_id,
            'chapter_number' => $chapter_number,
        ]);

        if (!$tracking_id) {
            wp_send_json_error(self::result_payload(CrawlerLinkTable::STATUS_FAILED, __('Khong the tao dong tracking crawler.', 'extend-site'), [
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'story_id' => $story_id,
                'chapter_number' => $chapter_number,
            ]));
        }

        $existing_source_chapter_id = self::find_existing_chapter_by_source_hash($story_id, $hash);
        if ($existing_source_chapter_id) {
            $message = __('Source URL da co chapter lien ket.', 'extend-site');
            CrawlerLinkTable::mark_duplicate((int) $tracking_id, $message);
            wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_DUPLICATE, $message, [
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'story_id' => $story_id,
                'chapter_id' => $existing_source_chapter_id,
                'chapter_number' => $chapter_number,
            ]));
        }

        $existing_chapter_id = self::find_existing_chapter($story_id, $chapter_number);
        if ($existing_chapter_id) {
            $message = __('So chuong nay da ton tai trong truyen.', 'extend-site');
            CrawlerLinkTable::mark_duplicate((int) $tracking_id, $message);
            wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_DUPLICATE, $message, [
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'story_id' => $story_id,
                'chapter_id' => $existing_chapter_id,
                'chapter_number' => $chapter_number,
            ]));
        }

        $scrape = Scraper::scrape($source_url, $replace_rules, $allow_short);
        if (is_wp_error($scrape)) {
            $message = $scrape->get_error_message();
            CrawlerLinkTable::mark_failed((int) $tracking_id, $message);
            wp_send_json_error(self::error_payload($scrape, [
                'status' => CrawlerLinkTable::STATUS_FAILED,
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'story_id' => $story_id,
                'chapter_number' => $chapter_number,
            ]));
        }

        $chapter_mismatch = self::validate_scraped_chapter_number($scrape, $expected_chapter_number);
        if (is_wp_error($chapter_mismatch)) {
            $message = $chapter_mismatch->get_error_message();
            CrawlerLinkTable::mark_failed((int) $tracking_id, $message);
            wp_send_json_error(self::error_payload($chapter_mismatch, [
                'status' => CrawlerLinkTable::STATUS_FAILED,
                'source_url' => $source_url,
                'clean_url' => $scrape['clean_url'],
                'story_id' => $story_id,
                'chapter_number' => $expected_chapter_number,
                'content_length' => $scrape['content_length'],
                'warnings' => $scrape['warnings'],
            ]));
        }

        // Build the final title server-side so preview and inserted chapters use the same strategy.
        $final_title = self::build_chapter_title($story_id, $chapter_number, (string) $scrape['title'], $title_mode, $title_template);

        $chapter_id = wp_insert_post([
            'post_type' => ChapterPostType::SLUG,
            'post_title' => $final_title,
            'post_content' => $scrape['content_html'],
            'post_status' => $post_status,
            'meta_input' => [
                ChapterPostType::META_STORY_ID => $story_id,
                ChapterPostType::META_NUMBER => $chapter_number,
                ChapterPostType::META_CHAPTER_VIEWS => 0,
                '_crawler_source_url' => esc_url_raw($source_url),
                '_crawler_clean_url' => esc_url_raw($scrape['clean_url']),
                '_crawler_source_url_hash' => $scrape['source_url_hash'],
            ],
        ], true);

        if (is_wp_error($chapter_id)) {
            $message = $chapter_id->get_error_message();
            CrawlerLinkTable::mark_failed((int) $tracking_id, $message);
            wp_send_json_error(self::result_payload(CrawlerLinkTable::STATUS_FAILED, $message, [
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'story_id' => $story_id,
                'chapter_number' => $chapter_number,
                'content_length' => $scrape['content_length'],
                'warnings' => $scrape['warnings'],
            ]));
        }

        $chapter_id = (int) $chapter_id;
        update_post_meta($chapter_id, ChapterPostType::META_STORY_ID, $story_id);
        update_post_meta($chapter_id, ChapterPostType::META_NUMBER, $chapter_number);

        if (!metadata_exists('post', $chapter_id, ChapterPostType::META_CHAPTER_VIEWS)) {
            update_post_meta($chapter_id, ChapterPostType::META_CHAPTER_VIEWS, 0);
        }

        update_post_meta($chapter_id, '_crawler_source_url', esc_url_raw($source_url));
        update_post_meta($chapter_id, '_crawler_clean_url', esc_url_raw($scrape['clean_url']));
        update_post_meta($chapter_id, '_crawler_source_url_hash', $scrape['source_url_hash']);

        CrawlerLinkTable::mark_success((int) $tracking_id, $chapter_id);

        wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_SUCCESS, __('Da them chuong thanh cong.', 'extend-site'), [
            'source_url' => $source_url,
            'clean_url' => $scrape['clean_url'],
            'story_id' => $story_id,
            'chapter_id' => $chapter_id,
            'chapter_number' => $chapter_number,
            'source_title' => $scrape['title'],
            'final_title' => $final_title,
            'content_length' => $scrape['content_length'],
            'source_chapter_number' => $scrape['source_chapter_number'] ?? 0,
            'source_max_chapter_number' => $scrape['source_max_chapter_number'] ?? 0,
            'warnings' => $scrape['warnings'],
        ]));
    }

    public static function finalize_story(): void
    {
        self::verify_request();

        $story_id = self::get_valid_story_id();
        $batch_id = self::get_batch_id(false);
        $lock = CrawlerLock::get();

        if ($lock && !CrawlerLock::is_expired($lock)) {
            if ($batch_id === '' || !CrawlerLock::matches($batch_id, $lock) || (int) ($lock['story_id'] ?? 0) !== $story_id) {
                wp_send_json_error([
                    'message' => __('Lock crawler dang hoat dong va khong khop batch nay.', 'extend-site'),
                    'lock' => $lock,
                ], 409);
            }
        }

        $count = ChapterRepository::sync_count_for_story($story_id);
        $latest = LatestChapterTable::resync_story($story_id);
        self::clear_story_cache($story_id);

        $released = false;
        if ($batch_id !== '') {
            $released = CrawlerLock::release($batch_id);
        }

        wp_send_json_success([
            'status' => 'success',
            'message' => __('Da hoan tat crawler.', 'extend-site'),
            'story_id' => $story_id,
            'chapter_count' => $count,
            'chapter_status_counts' => self::get_story_chapter_status_counts($story_id),
            'latest_chapter' => $latest,
            'lock_released' => $released,
        ]);
    }

    private static function get_target_url(string $key): string
    {
        $url = esc_url_raw(trim((string) wp_unslash($_POST[$key] ?? '')));
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));

        if ($url === '' || !in_array($scheme, ['http', 'https'], true) || !wp_http_validate_url($url)) {
            wp_send_json_error([
                'message' => __('URL khong hop le.', 'extend-site'),
            ], 400);
        }

        return $url;
    }

    private static function fetch_html(string $url, int $timeout = 20)
    {
        $response = wp_remote_get($url, [
            'timeout' => $timeout,
            'connecttimeout' => 10,
            'redirection' => 5,
            'headers' => [
                'User-Agent' => Scraper::get_user_agent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('http_error', sprintf(__('Nguon tra ve HTTP %d.', 'extend-site'), $code));
        }

        $body = (string) wp_remote_retrieve_body($response);
        if (trim($body) === '') {
            return new WP_Error('empty_body', __('Nguon tra ve noi dung rong.', 'extend-site'));
        }

        return $body;
    }

    private static function get_base_url(string $url): string
    {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $scheme = !empty($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
        $base = $scheme . '://' . $parts['host'];

        if (!empty($parts['port'])) {
            $base .= ':' . (int) $parts['port'];
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '' || substr($path, -1) === '/') {
            return $base . ($path ?: '/');
        }

        $directory = trailingslashit(dirname($path));

        return $base . $directory;
    }

    private static function sanitize_preview_html(string $html, string $base_url): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?: $html;
        $html = preg_replace('/\s+on[a-z]+\s*=\s*"[^"]*"/i', '', $html) ?: $html;
        $html = preg_replace("/\s+on[a-z]+\s*=\s*'[^']*'/i", '', $html) ?: $html;
        $html = preg_replace('/\s+on[a-z]+\s*=\s*[^\s>]+/i', '', $html) ?: $html;

        $base_tag = '<base href="' . esc_url($base_url) . '">';
        if (preg_match('/<head\b[^>]*>/i', $html)) {
            return preg_replace('/(<head\b[^>]*>)/i', '$1' . $base_tag, $html, 1) ?: $html;
        }

        return $base_tag . $html;
    }

    private static function get_template_selectors(): array
    {
        $keys = [
            'story_title_selector',
            'story_author_selector',
            'story_desc_selector',
            'story_thumb_selector',
            'story_cats_selector',
            'chapter_link_selector',
            'chapter_title_selector',
            'chapter_content_selector',
        ];

        $selectors = [];
        foreach ($keys as $key) {
            $selectors[$key] = trim(sanitize_text_field((string) wp_unslash($_POST[$key] ?? '')));
        }

        return $selectors;
    }

    private static function get_template_extractors(): array
    {
        $fields = [
            'story_title',
            'story_author',
            'story_desc',
            'story_thumb',
            'story_cats',
        ];
        $extractors = [];

        foreach ($fields as $field) {
            $mode = sanitize_key((string) wp_unslash($_POST[$field . '_extract_mode'] ?? 'selector'));
            $value_mode = sanitize_key((string) wp_unslash($_POST[$field . '_value_mode'] ?? 'node_text'));

            $extractors[$field] = [
                'extract_mode' => in_array($mode, ['selector', 'label'], true) ? $mode : 'selector',
                'area_selector' => trim(sanitize_text_field((string) wp_unslash($_POST[$field . '_area_selector'] ?? ''))),
                'label' => trim(sanitize_text_field((string) wp_unslash($_POST[$field . '_label'] ?? ''))),
                'value_mode' => self::normalize_value_mode($value_mode),
            ];
        }

        return $extractors;
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

    private static function extract_template_value(DOMXPath $xpath, string $field, string $selector, array $extractors, string $base_url, string $fallback_value_mode = 'node_text')
    {
        $extractor = $extractors[$field] ?? [];
        $extract_mode = (string) ($extractor['extract_mode'] ?? 'selector');

        if ($extract_mode === 'label') {
            $value = self::extract_by_label(
                $xpath,
                (string) ($extractor['area_selector'] ?? ''),
                (string) ($extractor['label'] ?? ''),
                (string) ($extractor['value_mode'] ?? $fallback_value_mode),
                $base_url
            );

            if ($value !== '' && $value !== []) {
                return $value;
            }
        }

        if ($selector === '') {
            return $fallback_value_mode === 'all_link_texts' ? [] : '';
        }

        if ($fallback_value_mode === 'first_image_src') {
            return self::first_selector_url($xpath, $selector, $base_url);
        }

        if ($fallback_value_mode === 'all_link_texts') {
            return self::selector_texts($xpath, $selector, 20);
        }

        return self::first_selector_text($xpath, $selector);
    }

    private static function extract_by_label(DOMXPath $xpath, string $area_selector, string $label, string $value_mode, string $base_url)
    {
        if ($area_selector === '' || $label === '') {
            return $value_mode === 'all_link_texts' ? [] : '';
        }

        $areas = self::query_selector_all($xpath, $area_selector);
        if (!$areas || $areas->length < 1) {
            return $value_mode === 'all_link_texts' ? [] : '';
        }

        foreach ($areas as $area) {
            $label_node = self::find_label_node($xpath, $area, $label);
            if (!$label_node) {
                continue;
            }

            $container = self::find_label_value_container($label_node, $area);
            if (!$container) {
                continue;
            }

            $value = self::extract_value_from_container($xpath, $container, $label, $value_mode, $base_url);
            if ($value !== '' && $value !== []) {
                return $value;
            }
        }

        return $value_mode === 'all_link_texts' ? [] : '';
    }

    private static function find_label_node(DOMXPath $xpath, DOMNode $area, string $label): ?DOMNode
    {
        $label = self::normalize_label($label);
        if ($label === '') {
            return null;
        }

        foreach ($xpath->query('.//*', $area) ?: [] as $node) {
            $text = self::normalize_label(self::node_text($node));
            if ($text !== '' && strpos($text, $label) !== false) {
                return $node;
            }
        }

        $area_text = self::normalize_label(self::node_text($area));

        return strpos($area_text, $label) !== false ? $area : null;
    }

    private static function find_label_value_container(DOMNode $label_node, DOMNode $area): ?DOMNode
    {
        $current = $label_node;
        while ($current && $current !== $area) {
            if ($current instanceof DOMElement && in_array(strtolower($current->tagName), ['p', 'div', 'li', 'tr', 'section'], true)) {
                return $current;
            }

            $current = $current->parentNode;
        }

        return $label_node;
    }

    private static function extract_value_from_container(DOMXPath $xpath, DOMNode $container, string $label, string $value_mode, string $base_url)
    {
        if ($value_mode === 'first_link_text') {
            $link = self::first_descendant_element($xpath, $container, './/a');

            return $link ? self::node_text($link) : self::text_after_label($container, $label);
        }

        if ($value_mode === 'all_link_texts') {
            $texts = [];
            foreach ($xpath->query('.//a', $container) ?: [] as $link) {
                $text = self::node_text($link);
                if ($text !== '') {
                    $texts[] = trim($text, " \t\n\r\0\x0B,");
                }
            }

            return array_values(array_unique(array_filter($texts)));
        }

        if ($value_mode === 'first_link_href') {
            $link = self::first_descendant_element($xpath, $container, './/a[@href]');
            if ($link instanceof DOMElement) {
                return self::resolve_url($link->getAttribute('href'), $base_url);
            }

            return '';
        }

        if ($value_mode === 'first_image_src') {
            $image = self::first_descendant_element($xpath, $container, './/img');
            if ($image instanceof DOMElement) {
                return self::resolve_url($image->getAttribute('src') ?: $image->getAttribute('data-src'), $base_url);
            }

            return '';
        }

        if ($value_mode === 'node_html') {
            return self::inner_html($container);
        }

        if ($value_mode === 'next_text') {
            return self::text_after_label($container, $label);
        }

        return self::text_after_label($container, $label) ?: self::node_text($container);
    }

    private static function first_descendant_element(DOMXPath $xpath, DOMNode $context, string $expression): ?DOMElement
    {
        $nodes = $xpath->query($expression, $context);
        if (!$nodes || $nodes->length < 1) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private static function text_after_label(DOMNode $container, string $label): string
    {
        $text = self::node_text($container);
        if ($text === '') {
            return '';
        }

        $pattern = '/^\s*' . preg_quote($label, '/') . '\s*:?\s*/iu';
        $value = preg_replace($pattern, '', $text);

        return trim((string) $value, " \t\n\r\0\x0B:");
    }

    private static function normalize_label(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = function_exists('remove_accents') ? remove_accents($value) : $value;
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

        return trim($value, " \t\n\r\0\x0B:");
    }

    private static function load_dom(string $html)
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return new WP_Error('html_parse_failed', __('Khong the phan tich HTML nguon.', 'extend-site'));
        }

        return $dom;
    }

    private static function query_selector_count(DOMXPath $xpath, string $selector): int
    {
        $nodes = self::query_selector_all($xpath, $selector);

        return $nodes ? $nodes->length : 0;
    }

    private static function query_selector_all(DOMXPath $xpath, string $selector, ?DOMNode $context = null)
    {
        $expression = self::css_selector_to_xpath($selector);
        if ($expression === '') {
            return null;
        }

        return $context ? $xpath->query($expression, $context) : $xpath->query($expression);
    }

    private static function css_selector_to_xpath(string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '' || strpos($selector, ',') !== false) {
            return '';
        }

        $parts = preg_split('/\s+/', $selector);
        if (!$parts) {
            return '';
        }

        $xpath = '';
        foreach ($parts as $part) {
            $segment = self::css_selector_part_to_xpath($part);
            if ($segment === '') {
                return '';
            }

            $xpath .= '//' . $segment;
        }

        return $xpath;
    }

    private static function css_selector_part_to_xpath(string $part): string
    {
        if (!preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)?((?:[#.][a-zA-Z0-9_-]+)*)$/', $part, $matches)) {
            return '';
        }

        $tag = $matches[1] !== '' ? strtolower($matches[1]) : '*';
        $suffix = $matches[2] ?? '';
        $predicates = [];

        if ($suffix !== '') {
            preg_match_all('/([#.])([a-zA-Z0-9_-]+)/', $suffix, $tokens, PREG_SET_ORDER);
            foreach ($tokens as $token) {
                if ($token[1] === '#') {
                    $predicates[] = '@id=' . self::xpath_literal($token[2]);
                } else {
                    $predicates[] = "contains(concat(' ', normalize-space(@class), ' '), " . self::xpath_literal(' ' . $token[2] . ' ') . ')';
                }
            }
        }

        return $tag . ($predicates ? '[' . implode(' and ', $predicates) . ']' : '');
    }

    private static function xpath_literal(string $value): string
    {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }

        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }

        $parts = explode("'", $value);

        return "concat('" . implode("', \"'\", '", $parts) . "')";
    }

    private static function first_selector_text(DOMXPath $xpath, string $selector): string
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return '';
        }

        return self::node_text($nodes->item(0));
    }

    private static function selector_texts(DOMXPath $xpath, string $selector, int $limit): array
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return [];
        }

        $texts = [];
        foreach ($nodes as $node) {
            $text = self::node_text($node);
            if ($text === '') {
                continue;
            }

            $texts[] = $text;
            if (count($texts) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($texts));
    }

    private static function first_selector_url(DOMXPath $xpath, string $selector, string $base_url): string
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return '';
        }

        $node = $nodes->item(0);
        if (!$node instanceof DOMElement) {
            return '';
        }

        $url = $node->getAttribute('src') ?: $node->getAttribute('data-src') ?: $node->getAttribute('href');

        return self::resolve_url($url, $base_url);
    }

    private static function chapter_link_samples(DOMXPath $xpath, string $selector, string $base_url): array
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return [
                'count' => 0,
                'samples' => [],
            ];
        }

        $links = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement && strtolower($node->tagName) === 'a' && $node->hasAttribute('href')) {
                $href = self::resolve_url($node->getAttribute('href'), $base_url);
                if ($href !== '') {
                    $links[$href] = self::node_text($node);
                }
                continue;
            }

            foreach ($xpath->query('.//a[@href]', $node) ?: [] as $link_node) {
                if (!$link_node instanceof DOMElement) {
                    continue;
                }

                $href = self::resolve_url($link_node->getAttribute('href'), $base_url);
                if ($href !== '') {
                    $links[$href] = self::node_text($link_node);
                }
            }
        }

        $samples = [];
        foreach ($links as $href => $text) {
            $samples[] = [
                'text' => self::limit_text($text, 120),
                'href' => $href,
            ];

            if (count($samples) >= 5) {
                break;
            }
        }

        return [
            'count' => count($links),
            'samples' => $samples,
        ];
    }

    private static function node_text(?DOMNode $node): string
    {
        if (!$node) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
    }

    private static function inner_html(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument ? $node->ownerDocument->saveHTML($child) : '';
        }

        return $html;
    }

    private static function limit_text(string $text, int $limit): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 3) . '...';
    }

    private static function resolve_url(string $url, string $base_url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || strpos($url, '#') === 0 || preg_match('/^(javascript|mailto|tel):/i', $url)) {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $scheme = (string) wp_parse_url($base_url, PHP_URL_SCHEME);
            $url = ($scheme ?: 'https') . ':' . $url;
        }

        if (wp_http_validate_url($url)) {
            return esc_url_raw($url);
        }

        $base = wp_parse_url($base_url);
        if (!is_array($base) || empty($base['host'])) {
            return '';
        }

        $scheme = !empty($base['scheme']) ? strtolower((string) $base['scheme']) : 'https';
        $host = (string) $base['host'];
        $port = !empty($base['port']) ? ':' . (int) $base['port'] : '';

        if (strpos($url, '/') === 0) {
            return esc_url_raw($scheme . '://' . $host . $port . $url);
        }

        $path = (string) ($base['path'] ?? '/');
        $directory = substr($path, -1) === '/' ? $path : trailingslashit(dirname($path));

        return esc_url_raw($scheme . '://' . $host . $port . $directory . $url);
    }

    private static function verify_request(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Ban khong co quyen chay crawler.', 'extend-site'),
            ], 403);
        }
    }

    private static function get_valid_story_id(): int
    {
        $story_id = absint($_POST['story_id'] ?? 0);
        if ($story_id <= 0 || get_post_type($story_id) !== StoryPostType::SLUG) {
            wp_send_json_error([
                'message' => __('ID truyen khong hop le.', 'extend-site'),
            ], 400);
        }

        return $story_id;
    }

    private static function get_expected_total(): int
    {
        $total = absint($_POST['expected_total'] ?? 0);
        if ($total <= 0) {
            wp_send_json_error([
                'message' => __('Thieu tong so URL crawler du kien.', 'extend-site'),
            ], 400);
        }

        $max = (int) apply_filters('es_crawler_max_batch_size', self::MAX_BATCH_SIZE);
        if ($total > $max) {
            wp_send_json_error([
                'message' => sprintf(__('Batch crawler vuot gioi han an toan: %d URL.', 'extend-site'), $max),
                'max_batch_size' => $max,
            ], 400);
        }

        return $total;
    }

    private static function get_valid_chapter_number(): int
    {
        $chapter_number = absint($_POST['chapter_number'] ?? 0);
        if ($chapter_number <= 0) {
            wp_send_json_error([
                'message' => __('So chuong khong hop le.', 'extend-site'),
            ], 400);
        }

        return $chapter_number;
    }

    private static function get_batch_id(bool $required = true): string
    {
        $batch_id = sanitize_text_field((string) ($_POST['batch_id'] ?? ''));
        if ($required && $batch_id === '') {
            wp_send_json_error([
                'message' => __('Thieu batch ID cua crawler.', 'extend-site'),
            ], 400);
        }

        return $batch_id;
    }

    private static function get_source_url(): string
    {
        $source_url = esc_url_raw(trim((string) ($_POST['source_url'] ?? '')));
        if ($source_url === '') {
            wp_send_json_error([
                'message' => __('Thieu URL nguon.', 'extend-site'),
            ], 400);
        }

        return $source_url;
    }

    private static function get_post_status(): string
    {
        $status = sanitize_key((string) ($_POST['post_status'] ?? 'publish'));

        return in_array($status, ['publish', 'draft'], true) ? $status : 'publish';
    }

    private static function get_title_mode(): string
    {
        $mode = sanitize_key((string) ($_POST['title_mode'] ?? self::TITLE_MODE_AUTO));
        $allowed = [
            self::TITLE_MODE_AUTO,
            self::TITLE_MODE_NUMBER,
            self::TITLE_MODE_STORY_NUMBER,
            self::TITLE_MODE_SOURCE_PREFIXED,
            self::TITLE_MODE_CUSTOM,
        ];

        return in_array($mode, $allowed, true) ? $mode : self::TITLE_MODE_AUTO;
    }

    private static function get_title_template(): string
    {
        return sanitize_text_field((string) wp_unslash($_POST['title_template'] ?? ''));
    }

    private static function get_replace_rules(): array
    {
        $raw = wp_unslash($_POST['replace_rules'] ?? []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $rules = [];
        foreach ($raw as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $find = isset($rule['find']) ? (string) $rule['find'] : '';
            if ($find === '') {
                continue;
            }

            $rules[] = [
                'find' => $find,
                'replace' => isset($rule['replace']) ? (string) $rule['replace'] : '',
                'regex' => !empty($rule['regex']),
                'remove_container' => !empty($rule['remove_container']),
            ];
        }

        return $rules;
    }

    private static function get_bool(string $key): bool
    {
        return filter_var($_POST[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private static function resolve_expected_chapter_number(string $source_url, int $fallback_chapter_number): int
    {
        $url_chapter_number = self::extract_chapter_number_from_url($source_url);

        return $url_chapter_number ?: $fallback_chapter_number;
    }

    private static function extract_chapter_number_from_url(string $source_url): ?int
    {
        $parts = wp_parse_url($source_url);
        if (!is_array($parts)) {
            return null;
        }

        $query = (string) ($parts['query'] ?? '');
        if ($query !== '') {
            parse_str($query, $params);
            foreach (['chuong', 'chapter', 'chap', 'tap'] as $key) {
                if (isset($params[$key]) && preg_match('/^\d+$/', (string) $params[$key])) {
                    $number = (int) $params[$key];
                    return $number > 0 ? $number : null;
                }
            }
        }

        $path = (string) ($parts['path'] ?? '');
        if (preg_match('/(?:chuong|ch\\x{01B0}\\x{01A1}ng|chapter|chap|tap)[\\s\\/_-]*0*([0-9]+)/iu', $path, $matches)) {
            $number = (int) $matches[1];
            return $number > 0 ? $number : null;
        }

        return null;
    }

    private static function validate_scraped_chapter_number(array $scrape, int $expected_chapter_number)
    {
        $source_chapter_number = isset($scrape['source_chapter_number']) ? (int) $scrape['source_chapter_number'] : 0;
        $source_max_chapter_number = isset($scrape['source_max_chapter_number']) ? (int) $scrape['source_max_chapter_number'] : 0;

        if ($source_max_chapter_number > 0 && $expected_chapter_number > $source_max_chapter_number) {
            return new WP_Error(
                'source_chapter_out_of_range',
                sprintf(
                    __('Khong tim thay chuong %2$d trong nguon, chuong cuoi phat hien duoc la %1$d.', 'extend-site'),
                    $source_max_chapter_number,
                    $expected_chapter_number
                ),
                [
                    'source_max_chapter_number' => $source_max_chapter_number,
                    'expected_chapter_number' => $expected_chapter_number,
                ]
            );
        }

        if ($source_chapter_number <= 0 || $source_chapter_number === $expected_chapter_number) {
            return true;
        }

        return new WP_Error(
            'source_chapter_mismatch',
            sprintf(
                __('Nguon tra ve chuong %1$d, khong khop chuong dang crawl %2$d. Co the URL khong ton tai va site dang fallback ve chuong khac.', 'extend-site'),
                $source_chapter_number,
                $expected_chapter_number
            ),
            [
                'source_chapter_number' => $source_chapter_number,
                'expected_chapter_number' => $expected_chapter_number,
            ]
        );
    }

    private static function build_chapter_title(int $story_id, int $chapter_number, string $source_title, string $mode, string $template = ''): string
    {
        $story_title = sanitize_text_field((string) get_the_title($story_id));
        $source_title = sanitize_text_field(trim(wp_strip_all_tags($source_title)));
        $chapter_label = sprintf("Ch\xC6\xB0\xC6\xA1ng %d", $chapter_number);

        if ($mode === self::TITLE_MODE_NUMBER) {
            return $chapter_label;
        }

        if ($mode === self::TITLE_MODE_STORY_NUMBER) {
            return $story_title !== ''
                ? sprintf('%s - %s', $story_title, $chapter_label)
                : $chapter_label;
        }

        if ($mode === self::TITLE_MODE_SOURCE_PREFIXED) {
            return $source_title !== ''
                ? sprintf('%s: %s', $chapter_label, $source_title)
                : $chapter_label;
        }

        if ($mode === self::TITLE_MODE_CUSTOM && $template !== '') {
            $custom = str_replace(
                ['{story}', '{n}', '{source_title}'],
                [$story_title, (string) $chapter_number, $source_title],
                $template
            );

            return self::filled_title(
                sanitize_text_field($custom),
                self::auto_chapter_title($story_title, $chapter_label, $chapter_number, $source_title)
            );
        }

        return self::auto_chapter_title($story_title, $chapter_label, $chapter_number, $source_title);
    }

    private static function auto_chapter_title(string $story_title, string $chapter_label, int $chapter_number, string $source_title): string
    {
        if ($source_title === '') {
            return $chapter_label;
        }

        $normalized_source = self::normalize_title_for_compare($source_title);
        $normalized_story = self::normalize_title_for_compare($story_title);

        if ($normalized_source === $normalized_story || strpos($normalized_source, 'chua co tieu de') !== false) {
            return $chapter_label;
        }

        $number = preg_quote((string) $chapter_number, '/');
        if (preg_match('/(?:chuong|ch\\x{01B0}\\x{01A1}ng)\\s*[:.#-]?\\s*0*' . $number . '\\b/iu', $source_title)) {
            return $source_title;
        }

        return sprintf('%s: %s', $chapter_label, $source_title);
    }

    private static function normalize_title_for_compare(string $title): string
    {
        $title = strtolower(remove_accents(trim(wp_strip_all_tags($title))));

        return preg_replace('/\s+/', ' ', $title) ?: '';
    }

    private static function filled_title(string $title, string $fallback): string
    {
        $title = trim($title);

        return $title !== '' ? $title : $fallback;
    }

    private static function require_matching_lock(int $story_id): void
    {
        $batch_id = self::get_batch_id();
        $lock = CrawlerLock::get();
        if (!CrawlerLock::matches($batch_id, $lock) || (int) ($lock['story_id'] ?? 0) !== $story_id) {
            wp_send_json_error([
                'message' => __('Lock crawler bi thieu, da het han hoac khong khop.', 'extend-site'),
                'lock' => $lock,
            ], 409);
        }
    }

    private static function enforce_batch_capacity(string $batch_id): void
    {
        $lock = CrawlerLock::get();
        $expected_total = (int) ($lock['expected_total'] ?? 0);
        if ($expected_total <= 0) {
            return;
        }

        if (CrawlerLinkTable::count_by_batch($batch_id) >= $expected_total) {
            wp_send_json_error(self::result_payload(CrawlerLinkTable::STATUS_FAILED, __('Crawler batch URL limit reached.', 'extend-site'), [
                'batch_id' => $batch_id,
            ]), 400);
        }
    }

    private static function find_existing_chapter(int $story_id, int $chapter_number): int
    {
        $query = new WP_Query([
            'post_type' => ChapterPostType::SLUG,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => ChapterPostType::META_STORY_ID,
                    'value' => $story_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => ChapterPostType::META_NUMBER,
                    'value' => $chapter_number,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
    }

    private static function is_existing_story_chapter(int $chapter_id, int $story_id): bool
    {
        if ($chapter_id <= 0 || $story_id <= 0) {
            return false;
        }

        $post = get_post($chapter_id);
        if (!$post || $post->post_type !== ChapterPostType::SLUG) {
            return false;
        }

        if (!in_array($post->post_status, ['publish', 'draft', 'pending', 'private', 'future'], true)) {
            return false;
        }

        return (int) get_post_meta($chapter_id, ChapterPostType::META_STORY_ID, true) === $story_id;
    }

    private static function find_existing_chapter_by_source_hash(int $story_id, string $hash): int
    {
        if ($story_id <= 0 || $hash === '') {
            return 0;
        }

        $query = new WP_Query([
            'post_type' => ChapterPostType::SLUG,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => ChapterPostType::META_STORY_ID,
                    'value' => $story_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => '_crawler_source_url_hash',
                    'value' => $hash,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
    }

    private static function clear_story_cache(int $story_id): void
    {
        $key = "es:story_last_update:{$story_id}";
        wp_cache_delete($key, 'es_story');
        delete_transient($key);
    }

    private static function get_story_chapter_status_counts(int $story_id): array
    {
        global $wpdb;

        if ($story_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "
            SELECT p.post_status, COUNT(DISTINCT p.ID) AS total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND pm.meta_key = %s
              AND pm.meta_value = %s
            GROUP BY p.post_status
            ",
            ChapterPostType::SLUG,
            ChapterPostType::META_STORY_ID,
            (string) $story_id
        ), ARRAY_A);

        $counts = [];
        foreach ($rows ?: [] as $row) {
            $counts[(string) $row['post_status']] = (int) $row['total'];
        }

        return $counts;
    }

    private static function result_payload(string $status, string $message, array $data = []): array
    {
        return array_merge([
            'status' => $status,
            'message' => $message,
            'source_url' => '',
            'clean_url' => '',
            'story_id' => 0,
            'chapter_id' => 0,
            'chapter_number' => 0,
            'content_length' => 0,
            'source_chapter_number' => 0,
            'source_max_chapter_number' => 0,
            'warnings' => [],
        ], $data);
    }

    private static function error_payload(WP_Error $error, array $data = []): array
    {
        $error_data = $error->get_error_data();
        if (is_array($error_data)) {
            $data = array_merge($error_data, $data);
        }

        return self::result_payload(
            $data['status'] ?? CrawlerLinkTable::STATUS_FAILED,
            $error->get_error_message(),
            $data
        );
    }
}
