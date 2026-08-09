<?php

namespace ExtendSite\Crawler;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use ExtendSite\DB\LatestChapterTable;
use ExtendSite\PostType\AuthorPostType;
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
    public const ACTION_TEMPLATE_SAVE = 'es_crawler_template_save';
    public const ACTION_TEMPLATE_LOAD = 'es_crawler_template_load';
    public const ACTION_TEMPLATE_DELETE = 'es_crawler_template_delete';
    public const ACTION_TEMPLATE_SEARCH = 'es_crawler_template_search';
    public const ACTION_TEMPLATE_PREPARE_BATCH = 'es_crawler_prepare_template_batch';
    public const ACTION_TEMPLATE_ENSURE_STORY = 'es_crawler_template_ensure_story';
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
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_SAVE, [self::class, 'save_template']);
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_LOAD, [self::class, 'load_template']);
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_DELETE, [self::class, 'delete_template']);
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_SEARCH, [self::class, 'search_templates']);
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_PREPARE_BATCH, [self::class, 'prepare_template_batch']);
        add_action('wp_ajax_' . self::ACTION_TEMPLATE_ENSURE_STORY, [self::class, 'ensure_template_story']);
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
                'message' => __('Đang có một batch crawler khác chạy.', 'extend-site'),
                'lock' => $lock_result['lock'],
            ], 409);
        }

        wp_send_json_success([
            'message' => __('Batch crawler đã bắt đầu.', 'extend-site'),
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
                'message' => __('Lock crawler bị thiếu, đã hết hạn hoặc không khớp.', 'extend-site'),
            ], 409);
        }

        wp_send_json_success([
            'message' => __('Heartbeat crawler đã được chấp nhận.', 'extend-site'),
            'lock' => $lock,
        ]);
    }

    public static function stop_batch(): void
    {
        self::verify_request();

        $batch_id = self::get_batch_id();
        if (!CrawlerLock::release($batch_id)) {
            wp_send_json_error([
                'message' => __('Lock crawler bị thiếu, đã hết hạn hoặc không khớp.', 'extend-site'),
            ], 409);
        }

        wp_send_json_success([
            'message' => __('Batch crawler đã dừng.', 'extend-site'),
        ]);
    }

    public static function preview_url(): void
    {
        self::verify_request();

        $story_id = absint($_POST['story_id'] ?? 0);
        $preview_story_title = sanitize_text_field((string) wp_unslash($_POST['preview_story_title'] ?? ''));
        if ($story_id <= 0 && $preview_story_title === '') {
            $story_id = self::get_valid_story_id();
        } elseif ($story_id > 0 && get_post_type($story_id) !== StoryPostType::SLUG) {
            wp_send_json_error([
                'message' => __('ID truyện không hợp lệ.', 'extend-site'),
            ], 400);
        }
        $chapter_number = self::get_valid_chapter_number();
        $source_url = self::get_source_url();
        $replace_rules = self::get_replace_rules();
        $template_id = absint($_POST['template_id'] ?? 0);
        $template = $template_id > 0 ? CrawlerTemplateTable::find($template_id) : null;
        if ($template_id > 0 && !$template) {
            wp_send_json_error([
                'message' => __('Không tìm thấy template crawler.', 'extend-site'),
            ], 404);
        }
        if ($template && !$replace_rules) {
            $replace_rules = is_array($template['find_replace_rules'] ?? null) ? $template['find_replace_rules'] : [];
        }
        $title_mode = self::get_title_mode();
        $title_template = self::get_title_template();
        $expected_chapter_number = self::resolve_expected_chapter_number($source_url, $chapter_number);

        $result = $template
            ? Scraper::scrape_with_template($source_url, $template, $replace_rules)
            : Scraper::scrape($source_url, $replace_rules);
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

        $final_title = $story_id > 0
            ? self::build_chapter_title($story_id, $expected_chapter_number, (string) $result['title'], $title_mode, $title_template)
            : self::build_chapter_title_from_story_title($preview_story_title, $expected_chapter_number, (string) $result['title'], $title_mode, $title_template);

        wp_send_json_success([
            'status' => 'success',
            'message' => __('Đã phân tích bản xem thử thành công.', 'extend-site'),
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
            'target_url' => $target_url,
        ]);
    }

    public static function test_parse(): void
    {
        self::verify_request();

        $story_url = self::get_target_url_with_fallback('story_url', 'target_url');
        $chapter_url = self::get_target_url_with_fallback('chapter_url', 'target_url');
        $selectors = self::get_template_selectors();
        $warnings = [];

        $story_body = self::fetch_html($story_url, 20);
        if (is_wp_error($story_body)) {
            wp_send_json_error([
                'message' => $story_body->get_error_message(),
            ], 400);
        }

        $story_dom = self::load_dom($story_body);
        if (is_wp_error($story_dom)) {
            wp_send_json_error([
                'message' => $story_dom->get_error_message(),
            ], 400);
        }

        $chapter_body = self::fetch_html($chapter_url, 20);
        if (is_wp_error($chapter_body)) {
            wp_send_json_error([
                'message' => $chapter_body->get_error_message(),
            ], 400);
        }

        $chapter_dom = self::load_dom($chapter_body);
        if (is_wp_error($chapter_dom)) {
            wp_send_json_error([
                'message' => $chapter_dom->get_error_message(),
            ], 400);
        }

        $story_xpath = new DOMXPath($story_dom);
        $chapter_xpath = new DOMXPath($chapter_dom);
        $matched = [];
        $match_samples = [];
        $selector_counts = [];
        $selector_samples = [];
        $selector_labels = self::template_selector_labels();
        foreach ($selectors as $key => $selector) {
            $label = $selector_labels[$key] ?? $key;
            $selector_xpath = in_array($key, ['chapter_content_scope_selector', 'chapter_title_selector', 'chapter_content_selector'], true)
                ? $chapter_xpath
                : $story_xpath;
            $match_count = $selector !== '' ? self::query_selector_count($selector_xpath, $selector) : 0;
            $samples = $selector !== '' ? self::query_selector_samples($selector_xpath, $selector, 3) : [];
            $selector_counts[$key] = $match_count;
            $selector_samples[$key] = $samples;
            $matched[$label] = $match_count;
            $match_samples[$label] = $samples;
            if ($selector !== '' && $match_count === 0) {
                $warnings[] = sprintf(
                    __('Selector không có kết quả ở mục "%1$s": %2$s', 'extend-site'),
                    $label,
                    $selector
                );
            }
        }

        $rules = self::get_template_extract_rules($selectors);
        $story_title = self::extract_rule_value($story_xpath, $rules['story_title'], $story_url);
        $story_author = self::extract_rule_value($story_xpath, $rules['story_author'], $story_url);
        $story_desc = self::extract_rule_value($story_xpath, $rules['story_desc'], $story_url);
        $story_thumb = self::extract_rule_value($story_xpath, $rules['story_thumb'], $story_url);
        $story_cats_value = self::extract_rule_value($story_xpath, $rules['story_cats'], $story_url);
        $story_cats = is_array($story_cats_value) ? $story_cats_value : array_filter(array_map('trim', explode(',', (string) $story_cats_value)));
        $chapter_scope = null;
        $use_chapter_scope = $selectors['chapter_content_scope_selector'] !== '';
        if ($use_chapter_scope) {
            $scope_nodes = self::query_selector_all($chapter_xpath, $selectors['chapter_content_scope_selector']);
            $chapter_scope = $scope_nodes && $scope_nodes->length > 0 ? $scope_nodes->item(0) : null;
            if (!$chapter_scope) {
                $warnings[] = sprintf(
                __('Không tìm thấy khối bọc nội dung chương ở mục "%s".', 'extend-site'),
                    $selector_labels['chapter_content_scope_selector']
                );
            }
        }
        $chapter_title = !$use_chapter_scope || $chapter_scope
            ? self::first_selector_text($chapter_xpath, $selectors['chapter_title_selector'], $chapter_scope)
            : '';
        $chapter_content = '';
        $same_chapter_content_selector = trim($selectors['chapter_content_selector']) !== ''
            && trim($selectors['chapter_content_selector']) === trim($selectors['chapter_content_scope_selector']);
        if (!$use_chapter_scope || $chapter_scope) {
            $chapter_content = $selectors['chapter_content_selector'] !== '' && !$same_chapter_content_selector
                ? self::first_selector_text($chapter_xpath, $selectors['chapter_content_selector'], $chapter_scope)
                : self::node_text($chapter_scope);
        }
        if ($selectors['chapter_content_selector'] !== '' && !$same_chapter_content_selector && $use_chapter_scope && $chapter_scope && $chapter_content === '') {
            $warnings[] = sprintf(
                __('Không tìm thấy nội dung chương bên trong mục "%s".', 'extend-site'),
                $selector_labels['chapter_content_scope_selector']
            );
        }
        $chapter_content_length = mb_strlen($chapter_content);
        $chapter_parse = Scraper::scrape_with_template($chapter_url, [
            'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? 'Template preview')),
            'chapter_content_scope_selector' => $selectors['chapter_content_scope_selector'],
            'chapter_title_selector' => $selectors['chapter_title_selector'],
            'chapter_content_selector' => $selectors['chapter_content_selector'],
            'cleanup_selectors' => self::get_template_cleanup_selectors(),
        ], self::get_template_find_replace_rules());
        if (is_wp_error($chapter_parse)) {
            $warnings[] = $chapter_parse->get_error_message();
            $chapter_content_length = 0;
        } else {
            $chapter_title = (string) ($chapter_parse['title'] ?? '');
            $chapter_content_length = (int) ($chapter_parse['content_length'] ?? 0);
            $warnings = array_merge($warnings, (array) ($chapter_parse['warnings'] ?? []));
        }
        $chapter_links = self::chapter_link_summary(
            $story_xpath,
            $selectors['chapter_link_selector'],
            $selectors['toc_page_link_selector'],
            $story_url
        );
        $warnings = array_merge($warnings, $chapter_links['warnings']);

        if ($story_title === '') {
            $warnings[] = sprintf(
                __('Không bóc được tên truyện ở mục "%s".', 'extend-site'),
                $selector_labels['story_title_selector']
            );
        }

        if ($selectors['chapter_link_selector'] !== '' && $chapter_links['count'] === 0) {
            $warnings[] = sprintf(
                __('Không tìm thấy link chương ở mục "%s".', 'extend-site'),
                $selector_labels['chapter_link_selector']
            );
        }

        $field_results = self::template_test_field_results(
            $selectors,
            $selector_counts,
            $selector_samples,
            [
                'story_title' => $story_title,
                'story_author' => $story_author,
                'story_desc' => $story_desc,
                'story_desc_length' => mb_strlen($story_desc),
                'story_thumb' => $story_thumb,
                'story_cats' => $story_cats,
                'chapter_title' => $chapter_title,
                'chapter_content_length' => $chapter_content_length,
                'chapter_link_count' => $chapter_links['count'],
                'toc_page_count' => $chapter_links['toc_page_count'],
            ]
        );

        $result = [
            'story_title' => $story_title,
            'story_author' => $story_author,
            'story_desc' => $story_desc,
            'story_desc_length' => mb_strlen($story_desc),
            'story_thumb' => $story_thumb,
            'story_cats' => $story_cats,
            'chapter_title' => $chapter_title,
            'chapter_content_length' => $chapter_content_length,
            'chapter_link_count' => $chapter_links['count'],
            'chapter_link_estimated' => $chapter_links['chapter_link_estimated'],
            'toc_page_count' => $chapter_links['toc_page_count'],
            'toc_pages_scanned' => $chapter_links['toc_pages_scanned'],
            'chapter_link_samples' => $chapter_links['samples'],
            'target_url' => $story_url,
            'story_url' => $story_url,
            'chapter_url' => $chapter_url,
            'matched' => $matched,
            'match_samples' => $match_samples,
            'field_results' => $field_results,
            'warnings' => array_values(array_unique($warnings)),
        ];
        $result['html'] = self::render_ajax_view('template-test-result', $result);

        wp_send_json_success($result);
    }

    public static function save_template(): void
    {
        self::verify_request();

        $selectors = self::get_template_selectors();
        $data = [
            'id' => absint($_POST['template_id'] ?? 0),
            'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
            'domain' => CrawlerTemplateTable::normalize_domain((string) wp_unslash($_POST['domain'] ?? '')),
            'toc_type' => sanitize_key((string) wp_unslash($_POST['toc_type'] ?? 'selector')),
            'chapter_link_selector' => $selectors['chapter_link_selector'],
            'toc_page_link_selector' => $selectors['toc_page_link_selector'],
            'chapter_url_pattern' => self::normalize_chapter_url_pattern((string) wp_unslash($_POST['chapter_url_pattern'] ?? '')),
            'sample_story_url' => esc_url_raw(trim((string) wp_unslash($_POST['sample_story_url'] ?? ''))),
            'sample_chapter_url' => esc_url_raw(trim((string) wp_unslash($_POST['sample_chapter_url'] ?? ''))),
            'story_extract_rules' => self::get_template_extract_rules($selectors),
            'chapter_content_scope_selector' => $selectors['chapter_content_scope_selector'],
            'chapter_title_selector' => $selectors['chapter_title_selector'],
            'chapter_content_selector' => $selectors['chapter_content_selector'],
            'cleanup_selectors' => self::get_template_cleanup_selectors(),
            'find_replace_rules' => self::get_template_find_replace_rules(),
            'delay_between' => max(1, absint($_POST['delay_between'] ?? 1)),
        ];

        if ($data['name'] === '') {
            wp_send_json_error(['message' => __('Thiếu tên nguồn/template.', 'extend-site')], 400);
        }

        if ($data['domain'] === '') {
            wp_send_json_error(['message' => __('Thiếu domain template.', 'extend-site')], 400);
        }

        if ($data['chapter_content_scope_selector'] === '') {
            wp_send_json_error(['message' => __('Thiếu selector khối bọc nội dung chương.', 'extend-site')], 400);
        }

        if ($data['chapter_link_selector'] === '' && $data['chapter_url_pattern'] === '') {
            wp_send_json_error(['message' => __('Hãy cấu hình selector danh sách chương hoặc URL fallback từ mẫu.', 'extend-site')], 400);
        }

        if ($data['chapter_url_pattern'] !== '' && strpos($data['chapter_url_pattern'], '{chapter_number}') === false) {
            wp_send_json_error(['message' => __('Mẫu URL chương phải có biến số chương {chapter_number} hoặc {n}, ví dụ: {story_url}/chuong-{chapter_number}/', 'extend-site')], 400);
        }

        $template = CrawlerTemplateTable::save($data);
        if (!$template) {
            wp_send_json_error(['message' => __('Không thể lưu template crawler.', 'extend-site')], 500);
        }

        wp_send_json_success([
            'message' => __('Đã lưu template crawler.', 'extend-site'),
            'template' => $template,
        ]);
    }

    public static function load_template(): void
    {
        self::verify_request();

        $id = absint($_POST['template_id'] ?? 0);
        $template = CrawlerTemplateTable::find($id);
        if (!$template) {
            wp_send_json_error(['message' => __('Không tìm thấy template crawler.', 'extend-site')], 404);
        }

        wp_send_json_success([
            'template' => $template,
        ]);
    }

    public static function search_templates(): void
    {
        self::verify_request();

        $search = sanitize_text_field((string) wp_unslash($_POST['q'] ?? ''));
        $templates = CrawlerTemplateTable::query([
            'search' => $search,
            'status' => 'active',
            'paged' => 1,
            'per_page' => 20,
        ]);

        $results = array_map(static function (array $template): array {
            return [
                'id' => (string) $template['id'],
                'text' => trim((string) $template['name'] . ' - ' . (string) $template['domain']),
                'domain' => (string) $template['domain'],
                'chapter_url_pattern' => (string) ($template['chapter_url_pattern'] ?? ''),
            ];
        }, $templates);

        wp_send_json([
            'results' => $results,
        ]);
    }

    public static function delete_template(): void
    {
        self::verify_request();

        $id = absint($_POST['template_id'] ?? 0);
        if (!CrawlerTemplateTable::delete($id)) {
            wp_send_json_error(['message' => __('Không thể xóa template crawler.', 'extend-site')], 400);
        }

        wp_send_json_success([
            'message' => __('Đã xóa template crawler.', 'extend-site'),
        ]);
    }

    public static function prepare_template_batch(): void
    {
        self::verify_request();

        $template_id = absint($_POST['template_id'] ?? 0);
        $template = CrawlerTemplateTable::find($template_id);
        if (!$template) {
            wp_send_json_error(['message' => __('Không tìm thấy template crawler.', 'extend-site')], 404);
        }
        if (trim((string) ($template['chapter_content_scope_selector'] ?? '')) === '') {
            wp_send_json_error(['message' => __('Template chưa cấu hình selector khối bọc nội dung chương.', 'extend-site')], 400);
        }

        $story_url = self::get_target_url('story_url');
        $body = self::fetch_html($story_url, 20);
        if (is_wp_error($body)) {
            wp_send_json_error(['message' => $body->get_error_message()], 400);
        }

        $dom = self::load_dom($body);
        if (is_wp_error($dom)) {
            wp_send_json_error(['message' => $dom->get_error_message()], 400);
        }

        $xpath = new DOMXPath($dom);
        $extract_rules = is_array($template['story_extract_rules'] ?? null) ? $template['story_extract_rules'] : [];
        $story_title = self::filled_title(
            (string) self::extract_rule_value($xpath, $extract_rules['story_title'] ?? [], $story_url),
            self::title_from_url($story_url)
        );
        $story_author = (string) self::extract_rule_value($xpath, $extract_rules['story_author'] ?? [], $story_url);
        $story_desc = (string) self::extract_rule_value($xpath, $extract_rules['story_desc'] ?? [], $story_url);
        $story_thumb = (string) self::extract_rule_value($xpath, $extract_rules['story_thumb'] ?? [], $story_url);
        $story_cats_value = self::extract_rule_value($xpath, $extract_rules['story_cats'] ?? [], $story_url);
        $story_cats = is_array($story_cats_value)
            ? $story_cats_value
            : array_filter(array_map('trim', explode(',', (string) $story_cats_value)));

        $existing_story_id = self::find_story_by_title($story_title);

        $from = absint($_POST['range_from'] ?? 0);
        $to = absint($_POST['range_to'] ?? 0);
        $padding = absint($_POST['padding'] ?? 0);
        $queue_result = TemplateQueueBuilder::build($xpath, $template, $story_url, $from, $to, $padding);
        if (is_wp_error($queue_result)) {
            wp_send_json_error([
                'message' => $queue_result->get_error_message(),
            ], 400);
        }

        $queue = $queue_result['queue'];
        $detected_total = (int) $queue_result['detected_total'];
        $queue_source = (string) $queue_result['source'];
        $warnings = (array) ($queue_result['warnings'] ?? []);
        $range_to = $to;
        if ($detected_total > 0) {
            $warnings[] = sprintf(
                __('Tổng chương phát hiện chỉ là ước lượng: %d chương.', 'extend-site'),
                $detected_total
            );
            if ($range_to > $detected_total) {
                $warnings[] = sprintf(
                    __('Khoảng chương đang chọn vượt tổng ước lượng (%d). Crawler vẫn sẽ chạy theo khoảng đã nhập và tự dừng nếu gặp nhiều lỗi liên tiếp.', 'extend-site'),
                    $detected_total
                );
            }
        } else {
            $warnings[] = __('Không phát hiện được link chương thật từ template. Crawler sẽ dùng URL fallback từ mẫu theo khoảng Từ/Đến đã nhập.', 'extend-site');
        }

        wp_send_json_success([
            'message' => sprintf(__('Đã kiểm tra queue: %d URL chương.', 'extend-site'), count($queue)),
            'story_id' => $existing_story_id,
            'story_title' => $existing_story_id > 0 ? get_the_title($existing_story_id) : $story_title,
            'story_created' => false,
            'story_exists' => $existing_story_id > 0,
            'prepared_story' => [
                'title' => $story_title,
                'author' => $story_author,
                'desc' => $story_desc,
                'thumb' => $story_thumb,
                'cats' => array_values($story_cats),
                'source_url' => $story_url,
            ],
            'template_id' => (int) $template['id'],
            'template_name' => (string) $template['name'],
            'template_domain' => (string) $template['domain'],
            'story_url' => $story_url,
            'total_chapters' => count($queue),
            'detected_total_chapters' => $detected_total,
            'queue_source' => $queue_source,
            'queue' => $queue,
            'delay_between' => (int) $template['delay_between'],
            'find_replace_rules' => $template['find_replace_rules'],
            'chapter_url_pattern' => (string) ($template['chapter_url_pattern'] ?? ''),
            'warnings' => $warnings,
        ]);
    }

    public static function ensure_template_story(): void
    {
        self::verify_request();

        $title = sanitize_text_field((string) wp_unslash($_POST['story_title'] ?? ''));
        $desc = wp_kses_post((string) wp_unslash($_POST['story_desc'] ?? ''));
        $author = sanitize_text_field((string) wp_unslash($_POST['story_author'] ?? ''));
        $thumb = esc_url_raw(trim((string) wp_unslash($_POST['story_thumb'] ?? '')));
        $source_url = self::get_target_url('story_url');
        $cats_raw = wp_unslash($_POST['story_cats'] ?? []);
        if (is_string($cats_raw)) {
            $decoded = json_decode($cats_raw, true);
            $cats_raw = is_array($decoded) ? $decoded : explode(',', $cats_raw);
        }
        $cats = is_array($cats_raw)
            ? array_values(array_filter(array_map(static fn($cat) => sanitize_text_field((string) $cat), $cats_raw)))
            : [];

        $story_result = self::find_or_create_story($title, $desc, $author, $cats, $thumb, $source_url);
        if (is_wp_error($story_result)) {
            wp_send_json_error(['message' => $story_result->get_error_message()], 500);
        }

        $story_id = (int) $story_result['story_id'];
        wp_send_json_success([
            'message' => !empty($story_result['created'])
                ? __('Đã tạo truyện mới.', 'extend-site')
                : __('Đã tìm thấy truyện có sẵn.', 'extend-site'),
            'story_id' => $story_id,
            'story_title' => get_the_title($story_id),
            'story_created' => (bool) $story_result['created'],
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
        $template_id = absint($_POST['template_id'] ?? 0);
        $template = $template_id > 0 ? CrawlerTemplateTable::find($template_id) : null;
        if ($template_id > 0 && !$template) {
            wp_send_json_error(self::result_payload(CrawlerLinkTable::STATUS_FAILED, __('Không tìm thấy template crawler.', 'extend-site'), [
                'source_url' => $source_url,
                'story_id' => $story_id,
                'chapter_number' => $chapter_number,
            ]), 404);
        }
        if ($template && !$replace_rules) {
            $replace_rules = is_array($template['find_replace_rules'] ?? null) ? $template['find_replace_rules'] : [];
        }
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
                wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_DUPLICATE, __('URL nguồn này đã crawl thành công trước đó.', 'extend-site'), [
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
            wp_send_json_error(self::result_payload(CrawlerLinkTable::STATUS_FAILED, __('Không thể tạo dòng tracking crawler.', 'extend-site'), [
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
            $message = __('Số chương này đã tồn tại trong truyện.', 'extend-site');
            CrawlerLinkTable::mark_duplicate((int) $tracking_id, $message);
            wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_DUPLICATE, $message, [
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'story_id' => $story_id,
                'chapter_id' => $existing_chapter_id,
                'chapter_number' => $chapter_number,
            ]));
        }

        $scrape = $template
            ? Scraper::scrape_with_template($source_url, $template, $replace_rules)
            : Scraper::scrape($source_url, $replace_rules);
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

        wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_SUCCESS, __('Đã thêm chương thành công.', 'extend-site'), [
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
                    'message' => __('Lock crawler đang hoạt động và không khớp batch này.', 'extend-site'),
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
            'message' => __('Đã hoàn tất crawler.', 'extend-site'),
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

    private static function get_target_url_with_fallback(string $key, string $fallback_key): string
    {
        $raw = trim((string) wp_unslash($_POST[$key] ?? ''));
        if ($raw === '') {
            $raw = trim((string) wp_unslash($_POST[$fallback_key] ?? ''));
        }

        $url = esc_url_raw($raw);
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
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
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
        $preview_style = '<style id="es-crawler-preview-style">a[href]{pointer-events:none;cursor:default;}a[href]::after{content:"";}</style>';
        if (preg_match('/<head\b[^>]*>/i', $html)) {
            return preg_replace('/(<head\b[^>]*>)/i', '$1' . $base_tag . $preview_style, $html, 1) ?: $html;
        }

        return $base_tag . $preview_style . $html;
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
            'toc_page_link_selector',
            'chapter_content_scope_selector',
            'chapter_title_selector',
            'chapter_content_selector',
        ];

        $selectors = [];
        foreach ($keys as $key) {
            $selectors[$key] = trim(sanitize_text_field((string) wp_unslash($_POST[$key] ?? '')));
        }

        return $selectors;
    }

    private static function normalize_chapter_url_pattern(string $pattern): string
    {
        $pattern = trim(sanitize_text_field($pattern));
        if ($pattern === '') {
            return '';
        }

        return str_replace('{n}', '{chapter_number}', $pattern);
    }

    private static function template_selector_labels(): array
    {
        return [
            'story_title_selector' => __('Thông tin truyện > Tên truyện', 'extend-site'),
            'story_author_selector' => __('Thông tin truyện > Tác giả', 'extend-site'),
            'story_desc_selector' => __('Thông tin truyện > Mô tả', 'extend-site'),
            'story_thumb_selector' => __('Thông tin truyện > Ảnh bìa', 'extend-site'),
            'story_cats_selector' => __('Thông tin truyện > Thể loại', 'extend-site'),
            'chapter_link_selector' => __('Danh sách chương trên trang truyện > Khối/link danh sách chương', 'extend-site'),
            'toc_page_link_selector' => __('Danh sách chương trên trang truyện > Link phân trang mục lục', 'extend-site'),
            'chapter_content_scope_selector' => __('Trang chi tiết chương > Vùng chi tiết chương', 'extend-site'),
            'chapter_title_selector' => __('Trang chi tiết chương > Tên chương', 'extend-site'),
            'chapter_content_selector' => __('Trang chi tiết chương > Nội dung truyện', 'extend-site'),
        ];
    }

    private static function template_test_field_results(array $selectors, array $counts, array $samples, array $values): array
    {
        $fields = [
            [
                'key' => 'story_title_selector',
                'group' => __('Thông tin truyện', 'extend-site'),
                'label' => __('Tên truyện', 'extend-site'),
                'value' => (string) ($values['story_title'] ?? ''),
                'result' => (string) ($values['story_title'] ?? ''),
            ],
            [
                'key' => 'story_author_selector',
                'group' => __('Thông tin truyện', 'extend-site'),
                'label' => __('Tác giả', 'extend-site'),
                'value' => (string) ($values['story_author'] ?? ''),
                'result' => (string) ($values['story_author'] ?? ''),
            ],
            [
                'key' => 'story_cats_selector',
                'group' => __('Thông tin truyện', 'extend-site'),
                'label' => __('Thể loại', 'extend-site'),
                'value' => implode(', ', (array) ($values['story_cats'] ?? [])),
                'result' => implode(', ', (array) ($values['story_cats'] ?? [])),
            ],
            [
                'key' => 'story_desc_selector',
                'group' => __('Thông tin truyện', 'extend-site'),
                'label' => __('Mô tả', 'extend-site'),
                'value' => (string) ($values['story_desc'] ?? ''),
                'result' => sprintf(__('%d ky tu', 'extend-site'), (int) ($values['story_desc_length'] ?? 0)),
            ],
            [
                'key' => 'story_thumb_selector',
                'group' => __('Thông tin truyện', 'extend-site'),
                'label' => __('Ảnh bìa', 'extend-site'),
                'value' => (string) ($values['story_thumb'] ?? ''),
                'result' => (string) ($values['story_thumb'] ?? ''),
            ],
            [
                'key' => 'chapter_link_selector',
                'group' => __('Danh sách chương', 'extend-site'),
                'label' => __('Link chương', 'extend-site'),
                'value' => (string) ((int) ($values['chapter_link_count'] ?? 0)),
                'result' => sprintf(__('%d link', 'extend-site'), (int) ($values['chapter_link_count'] ?? 0)),
                'missing_hint' => __('Không tìm thấy link chương trong HTML gốc. Nếu danh sách chương vẫn hiện trên trình duyệt nhưng không hiện ở preview, nguồn có thể tải bằng JavaScript/AJAX.', 'extend-site'),
            ],
            [
                'key' => 'toc_page_link_selector',
                'group' => __('Danh sách chương', 'extend-site'),
                'label' => __('Link phan trang muc luc', 'extend-site'),
                'value' => (string) ((int) ($values['toc_page_count'] ?? 0)),
                'result' => sprintf(__('%d link', 'extend-site'), (int) ($values['toc_page_count'] ?? 0)),
            ],
            [
                'key' => 'chapter_content_scope_selector',
                'group' => __('Trang chi tiết chương', 'extend-site'),
                'label' => __('Vùng chi tiết chương', 'extend-site'),
                'value' => (string) ((int) ($counts['chapter_content_scope_selector'] ?? 0)),
                'result' => sprintf(__('%d phan tu khop', 'extend-site'), (int) ($counts['chapter_content_scope_selector'] ?? 0)),
            ],
            [
                'key' => 'chapter_title_selector',
                'group' => __('Trang chi tiết chương', 'extend-site'),
                'label' => __('Tên chương', 'extend-site'),
                'value' => (string) ($values['chapter_title'] ?? ''),
                'result' => (string) ($values['chapter_title'] ?? ''),
            ],
            [
                'key' => 'chapter_content_selector',
                'group' => __('Trang chi tiết chương', 'extend-site'),
                'label' => __('Nội dung truyện', 'extend-site'),
                'value' => (string) ((int) ($values['chapter_content_length'] ?? 0)),
                'result' => sprintf(__('%d ky tu', 'extend-site'), (int) ($values['chapter_content_length'] ?? 0)),
            ],
        ];

        $results = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $selector = trim((string) ($selectors[$key] ?? ''));
            if ($selector === '') {
                continue;
            }

            $count = (int) ($counts[$key] ?? 0);
            $has_value = trim((string) ($field['value'] ?? '')) !== '' && trim((string) ($field['value'] ?? '')) !== '0';
            $status = $count > 0 && $has_value ? 'ok' : 'missing';
            $hint = (string) ($field['missing_hint'] ?? __('Không tìm thấy phần tử cho field này. Nguyên nhân thường gặp: selector sai, class nằm ở thẻ khác, hoặc nội dung được tải bằng JavaScript nên crawler không thấy trong HTML gốc.', 'extend-site'));

            $results[] = [
                'group' => (string) $field['group'],
                'label' => (string) $field['label'],
                'selector' => $selector,
                'status' => $status,
                'result' => $status === 'ok' ? (string) ($field['result'] ?? '') : __('Không tìm thấy', 'extend-site'),
                'hint' => $status === 'missing' ? $hint : '',
                'match_count' => $count,
                'samples' => $samples[$key] ?? [],
            ];
        }

        return $results;
    }

    private static function get_template_extract_rules(array $selectors): array
    {
        $fields = [
            'story_title' => [
                'selector_key' => 'story_title_selector',
                'value_mode' => 'node_text',
            ],
            'story_author' => [
                'selector_key' => 'story_author_selector',
                'value_mode' => 'first_link_text',
            ],
            'story_desc' => [
                'selector_key' => 'story_desc_selector',
                'value_mode' => 'node_text',
            ],
            'story_thumb' => [
                'selector_key' => 'story_thumb_selector',
                'value_mode' => 'first_image_src',
            ],
            'story_cats' => [
                'selector_key' => 'story_cats_selector',
                'value_mode' => 'all_link_texts',
            ],
        ];
        $rules = [];

        foreach ($fields as $field => $config) {
            $selector_key = (string) $config['selector_key'];
            $default_value_mode = (string) $config['value_mode'];
            $value_mode = sanitize_key((string) wp_unslash($_POST[$field . '_value_mode'] ?? $default_value_mode));

            $rules[$field] = [
                'selector' => $selectors[$selector_key] ?? '',
                'label' => trim(sanitize_text_field((string) wp_unslash($_POST[$field . '_label'] ?? ''))),
                'value_mode' => self::normalize_value_mode($value_mode),
            ];
        }

        return $rules;
    }

    private static function get_template_find_replace_rules(): array
    {
        $raw = wp_unslash($_POST['find_replace_rules'] ?? []);
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

    private static function get_template_cleanup_selectors(): array
    {
        return CrawlerTemplateTable::normalize_cleanup_selectors((string) wp_unslash($_POST['cleanup_selectors'] ?? ''));
    }

    private static function find_or_create_story(string $title, string $desc, string $author, array $cats, string $thumb_url, string $source_url)
    {
        $title = sanitize_text_field(trim($title));
        if ($title === '') {
            return new WP_Error('missing_story_title', __('Không bóc được tên truyện.', 'extend-site'));
        }

        $existing = self::find_story_by_title($title);
        if ($existing > 0) {
            return [
                'story_id' => $existing,
                'created' => false,
            ];
        }

        $story_id = wp_insert_post([
            'post_type' => StoryPostType::SLUG,
            'post_title' => $title,
            'post_content' => wp_kses_post($desc),
            'post_excerpt' => wp_strip_all_tags($desc),
            'post_status' => 'publish',
            'meta_input' => [
                StoryPostType::META_STORY_VIEWS => 0,
                StoryPostType::META_CHAPTER_COUNT => 0,
                '_crawler_source_url' => esc_url_raw($source_url),
            ],
        ], true);

        if (is_wp_error($story_id)) {
            return $story_id;
        }

        $story_id = (int) $story_id;
        self::assign_story_categories($story_id, $cats);
        self::assign_story_author($story_id, $author);
        self::assign_story_thumbnail($story_id, $thumb_url);

        return [
            'story_id' => $story_id,
            'created' => true,
        ];
    }

    private static function find_story_by_title(string $title): int
    {
        $query = new WP_Query([
            'post_type' => StoryPostType::SLUG,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'title' => $title,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);

        return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
    }

    private static function assign_story_categories(int $story_id, array $cats): void
    {
        $term_ids = [];
        foreach ($cats as $cat) {
            $name = sanitize_text_field((string) $cat);
            if ($name === '') {
                continue;
            }

            $term = term_exists($name, StoryPostType::TAX_SLUG);
            if (!$term) {
                $term = wp_insert_term($name, StoryPostType::TAX_SLUG);
            }

            if (!is_wp_error($term)) {
                $term_ids[] = (int) (is_array($term) ? ($term['term_id'] ?? 0) : $term);
            }
        }

        $term_ids = array_values(array_filter(array_unique($term_ids)));
        if ($term_ids) {
            wp_set_object_terms($story_id, $term_ids, StoryPostType::TAX_SLUG);
        }
    }

    private static function assign_story_author(int $story_id, string $author): void
    {
        $author = sanitize_text_field(trim($author));
        if ($author === '') {
            return;
        }

        $query = new WP_Query([
            'post_type' => AuthorPostType::SLUG,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'title' => $author,
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);
        $author_id = !empty($query->posts[0]) ? (int) $query->posts[0] : 0;

        if (!$author_id) {
            $inserted = wp_insert_post([
                'post_type' => AuthorPostType::SLUG,
                'post_title' => $author,
                'post_status' => 'publish',
            ], true);
            $author_id = is_wp_error($inserted) ? 0 : (int) $inserted;
        }

        if ($author_id > 0) {
            update_post_meta($story_id, StoryPostType::META_AUTHOR_IDS, [$author_id]);
        }
    }

    private static function assign_story_thumbnail(int $story_id, string $thumb_url): void
    {
        $thumb_url = esc_url_raw(trim($thumb_url));
        if ($thumb_url === '' || has_post_thumbnail($story_id)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $media_id = media_sideload_image($thumb_url, $story_id, null, 'id');
        if (!is_wp_error($media_id)) {
            set_post_thumbnail($story_id, (int) $media_id);
        }
    }

    private static function title_from_url(string $url): string
    {
        $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?: '');
        $slug = trim(basename(untrailingslashit($path)));
        $slug = $slug !== '' ? $slug : 'truyen-moi';

        return ucwords(str_replace(['-', '_'], ' ', $slug));
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

    private static function extract_rule_value(DOMXPath $xpath, array $rule, string $base_url)
    {
        $selector = trim((string) ($rule['selector'] ?? ''));
        $label = trim((string) ($rule['label'] ?? ''));
        $value_mode = self::normalize_value_mode((string) ($rule['value_mode'] ?? 'node_text'));

        if ($selector === '') {
            return $value_mode === 'all_link_texts' ? [] : '';
        }

        if ($label !== '') {
            return self::extract_by_label(
                $xpath,
                $selector,
                $label,
                $value_mode,
                $base_url
            );
        }

        return self::extract_by_selector($xpath, $selector, $value_mode, $base_url);
    }

    private static function extract_by_selector(DOMXPath $xpath, string $selector, string $value_mode, string $base_url)
    {
        if ($value_mode === 'first_image_src') {
            return self::first_selector_url($xpath, $selector, $base_url);
        }

        if ($value_mode === 'first_link_href') {
            return self::first_selector_href($xpath, $selector, $base_url);
        }

        if ($value_mode === 'first_link_text') {
            return self::first_selector_link_text($xpath, $selector);
        }

        if ($value_mode === 'all_link_texts') {
            return self::selector_link_texts($xpath, $selector, 20);
        }

        if ($value_mode === 'node_html') {
            return self::first_selector_html($xpath, $selector);
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

        $label = self::normalize_label($label);
        if ($label === '') {
            return $text;
        }

        $normalized_text = self::normalize_label($text);
        if (strpos($normalized_text, $label) === 0) {
            $value = mb_substr($text, mb_strlen($label, 'UTF-8'), null, 'UTF-8');

            return trim((string) $value, " \t\n\r\0\x0B:");
        }

        $pattern = '/^\s*' . preg_quote($label, '/') . '\s*:?\s*/iu';
        $value = preg_replace($pattern, '', self::normalize_label($text));

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
            return new WP_Error('html_parse_failed', __('Không thể phân tích HTML nguồn.', 'extend-site'));
        }

        return $dom;
    }

    private static function query_selector_count(DOMXPath $xpath, string $selector): int
    {
        $nodes = self::query_selector_all($xpath, $selector);

        return $nodes ? $nodes->length : 0;
    }

    private static function query_selector_samples(DOMXPath $xpath, string $selector, int $limit): array
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return [];
        }

        $samples = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            $id = trim((string) $node->getAttribute('id'));
            $class = trim((string) $node->getAttribute('class'));
            $label = $tag;
            if ($id !== '') {
                $label .= '#' . $id;
            }
            if ($class !== '') {
                $label .= '.' . preg_replace('/\s+/', '.', $class);
            }

            $samples[] = [
                'node' => $label,
                'text' => self::limit_text(self::node_text($node), 100),
            ];

            if (count($samples) >= $limit) {
                break;
            }
        }

        return $samples;
    }

    private static function query_selector_all(DOMXPath $xpath, string $selector, ?DOMNode $context = null)
    {
        $expression = CssSelector::to_xpath($selector);
        if ($expression === '') {
            return null;
        }

        if ($context && strpos($expression, '//') === 0) {
            $expression = '.' . $expression;
        }

        return $context ? $xpath->query($expression, $context) : $xpath->query($expression);
    }


    private static function first_selector_text(DOMXPath $xpath, string $selector, ?DOMNode $context = null): string
    {
        $nodes = self::query_selector_all($xpath, $selector, $context);
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

    private static function first_selector_link_text(DOMXPath $xpath, string $selector): string
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return '';
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            if (strtolower($node->tagName) === 'a') {
                return self::node_text($node);
            }

            $link = self::first_descendant_element($xpath, $node, './/a');
            if ($link) {
                return self::node_text($link);
            }
        }

        return '';
    }

    private static function selector_link_texts(DOMXPath $xpath, string $selector, int $limit): array
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return [];
        }

        $texts = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $links = strtolower($node->tagName) === 'a' ? [$node] : ($xpath->query('.//a', $node) ?: []);
            foreach ($links as $link) {
                $text = self::node_text($link);
                if ($text === '') {
                    continue;
                }

                $texts[] = trim($text, " \t\n\r\0\x0B,");
                if (count($texts) >= $limit) {
                    break 2;
                }
            }
        }

        return array_values(array_unique(array_filter($texts)));
    }

    private static function first_selector_html(DOMXPath $xpath, string $selector): string
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return '';
        }

        return self::inner_html($nodes->item(0));
    }

    private static function first_selector_href(DOMXPath $xpath, string $selector, string $base_url): string
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return '';
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            if (strtolower($node->tagName) === 'a' && $node->hasAttribute('href')) {
                return self::resolve_url($node->getAttribute('href'), $base_url);
            }

            $link = self::first_descendant_element($xpath, $node, './/a[@href]');
            if ($link instanceof DOMElement) {
                return self::resolve_url($link->getAttribute('href'), $base_url);
            }
        }

        return '';
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
        if ($url === '') {
            $media = self::first_descendant_element($xpath, $node, './/img|.//a[@href]');
            if ($media instanceof DOMElement) {
                $url = $media->getAttribute('src') ?: $media->getAttribute('data-src') ?: $media->getAttribute('href');
            }
        }

        return self::resolve_url($url, $base_url);
    }

    private static function chapter_link_summary(DOMXPath $xpath, string $chapter_selector, string $toc_page_selector, string $base_url): array
    {
        $links = self::selector_links($xpath, $chapter_selector, $base_url);
        $toc_pages = $toc_page_selector !== '' ? self::selector_links($xpath, $toc_page_selector, $base_url) : [];
        $warnings = [];
        $scanned = 1;
        $first_page_count = count($links);
        $estimated_count = $first_page_count;
        $estimated = false;
        $last_page_number = 1;
        $last_page_links = [];
        $last_page_url = '';

        foreach ($toc_pages as $toc_url => $toc_text) {
            $page_number = self::toc_page_number((string) $toc_text, (string) $toc_url);
            if ($page_number > $last_page_number) {
                $last_page_number = $page_number;
                $last_page_url = (string) $toc_url;
            }
        }

        $base_clean = strtok($base_url, '#') ?: $base_url;
        $last_clean = $last_page_url !== '' ? (strtok($last_page_url, '#') ?: $last_page_url) : '';

        if ($last_page_number > 1 && $last_clean !== '' && $last_clean !== $base_clean) {
            $body = self::fetch_html($last_page_url, 20);
            if (is_wp_error($body)) {
                $warnings[] = sprintf(__('Không tải được trang mục lục cuối: %s.', 'extend-site'), $last_page_url);
            } else {
                $dom = self::load_dom($body);
                if (is_wp_error($dom)) {
                    $warnings[] = sprintf(__('Không phân tích được trang mục lục cuối: %s.', 'extend-site'), $last_page_url);
                } else {
                    $scanned++;
                    $page_xpath = new DOMXPath($dom);
                    $last_page_links = self::selector_links($page_xpath, $chapter_selector, $last_page_url);
                    $estimated_count = max(0, ($last_page_number - 1) * $first_page_count + count($last_page_links));
                    $estimated = true;
                }
            }
        }

        return [
            'count' => $estimated_count,
            'toc_page_count' => $last_page_number,
            'toc_pages_scanned' => $scanned,
            'chapter_link_estimated' => $estimated,
            'samples' => self::link_samples(array_replace($links, $last_page_links)),
            'warnings' => $warnings,
        ];
    }

    private static function toc_page_number(string $text, string $url): int
    {
        $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/\d+/', $text, $matches)) {
            return max(1, (int) $matches[0]);
        }

        $parts = wp_parse_url($url);
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (['page', 'paged', 'pagenum', 'pg'] as $key) {
                if (isset($query[$key]) && is_numeric($query[$key])) {
                    return max(1, (int) $query[$key]);
                }
            }
        }

        $path = (string) ($parts['path'] ?? '');
        if (preg_match('~/(?:page|paged)/(\d+)/?~i', $path, $matches)) {
            return max(1, (int) $matches[1]);
        }

        if (preg_match('~(?:^|[-_/])(\d+)/?$~', $path, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }

    private static function selector_links(DOMXPath $xpath, string $selector, string $base_url): array
    {
        $nodes = self::query_selector_all($xpath, $selector);
        if (!$nodes || $nodes->length < 1) {
            return [];
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

        return $links;
    }

    private static function link_samples(array $links): array
    {
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

        return $samples;
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
                'message' => __('Bạn không có quyền chạy crawler.', 'extend-site'),
            ], 403);
        }
    }

    private static function get_valid_story_id(): int
    {
        $story_id = absint($_POST['story_id'] ?? 0);
        if ($story_id <= 0 || get_post_type($story_id) !== StoryPostType::SLUG) {
            wp_send_json_error([
                'message' => __('ID truyện không hợp lệ.', 'extend-site'),
            ], 400);
        }

        return $story_id;
    }

    private static function get_expected_total(): int
    {
        $total = absint($_POST['expected_total'] ?? 0);
        if ($total <= 0) {
            wp_send_json_error([
                'message' => __('Thiếu tổng số URL crawler dự kiến.', 'extend-site'),
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
                'message' => __('Số chương không hợp lệ.', 'extend-site'),
            ], 400);
        }

        return $chapter_number;
    }

    private static function get_batch_id(bool $required = true): string
    {
        $batch_id = sanitize_text_field((string) ($_POST['batch_id'] ?? ''));
        if ($required && $batch_id === '') {
            wp_send_json_error([
                'message' => __('Thiếu batch ID của crawler.', 'extend-site'),
            ], 400);
        }

        return $batch_id;
    }

    private static function get_source_url(): string
    {
        $source_url = esc_url_raw(trim((string) ($_POST['source_url'] ?? '')));
        if ($source_url === '') {
            wp_send_json_error([
                'message' => __('Thiếu URL nguồn.', 'extend-site'),
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
                    __('Không tìm thấy chương %2$d trong nguồn, chương cuối phát hiện được là %1$d.', 'extend-site'),
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
                __('Nguồn trả về chương %1$d, không khớp chương đang crawl %2$d. Có thể URL không tồn tại và site đang fallback về chương khác.', 'extend-site'),
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

        return self::build_chapter_title_from_story_title($story_title, $chapter_number, $source_title, $mode, $template);
    }

    private static function build_chapter_title_from_story_title(string $story_title, int $chapter_number, string $source_title, string $mode, string $template = ''): string
    {
        $story_title = sanitize_text_field($story_title);
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
                'message' => __('Lock crawler bị thiếu, đã hết hạn hoặc không khớp.', 'extend-site'),
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

    private static function render_ajax_view(string $view, array $data = []): string
    {
        $file = __DIR__ . '/views/ajax/' . sanitize_file_name($view) . '.php';
        if (!is_readable($file)) {
            return '';
        }

        ob_start();
        include $file;

        return (string) ob_get_clean();
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
