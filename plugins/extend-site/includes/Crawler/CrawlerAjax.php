<?php

namespace ExtendSite\Crawler;

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
        $existing = CrawlerLinkTable::find_by_hash($hash);

        if ($existing && $existing['status'] === CrawlerLinkTable::STATUS_SUCCESS) {
            wp_send_json_success(self::result_payload(CrawlerLinkTable::STATUS_DUPLICATE, __('URL nguồn này đã crawl thành công trước đó.', 'extend-site'), [
                'source_url' => $source_url,
                'clean_url' => $clean_url,
                'story_id' => $story_id,
                'chapter_id' => isset($existing['chapter_id']) ? (int) $existing['chapter_id'] : 0,
                'chapter_number' => $chapter_number,
            ]));
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

        $existing_source_chapter_id = self::find_existing_chapter_by_source_hash($hash);
        if ($existing_source_chapter_id) {
            $message = __('Source URL already has a linked chapter.', 'extend-site');
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
                'message' => __('Missing expected crawler URL count.', 'extend-site'),
            ], 400);
        }

        $max = (int) apply_filters('es_crawler_max_batch_size', self::MAX_BATCH_SIZE);
        if ($total > $max) {
            wp_send_json_error([
                'message' => sprintf(__('Crawler batch exceeds the safe limit: %d URLs.', 'extend-site'), $max),
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
        if ($path !== '' && preg_match('/(?:chuong|chapter|chap|tap)[\-_\/=]?([0-9]+)/i', $path, $matches)) {
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
                    __('Nguon chi thay link chuong toi da %1$d, khong co chuong dang crawl %2$d. Co the URL khong ton tai va site dang fallback ve trang khac.', 'extend-site'),
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
        $chapter_label = sprintf(__('Chương %d', 'extend-site'), $chapter_number);

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
        if (preg_match('/(?:chuong|chương)\s*[:.#-]?\s*0*' . $number . '\b/iu', $source_title)) {
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

    private static function find_existing_chapter_by_source_hash(string $hash): int
    {
        if ($hash === '') {
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
