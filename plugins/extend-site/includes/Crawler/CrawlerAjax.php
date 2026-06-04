<?php

namespace ExtendSite\Crawler;

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
        $lock_result = CrawlerLock::acquire($story_id, get_current_user_id());

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

        $result = Scraper::scrape($source_url, $replace_rules, $allow_short);
        if (is_wp_error($result)) {
            wp_send_json_error(self::error_payload($result, [
                'source_url' => $source_url,
                'story_id' => $story_id,
                'chapter_number' => $chapter_number,
            ]));
        }

        wp_send_json_success([
            'status' => 'success',
            'message' => __('Đã phân tích bản xem thử thành công.', 'extend-site'),
            'source_url' => $source_url,
            'clean_url' => $result['clean_url'],
            'domain' => $result['domain'],
            'rule_label' => $result['rule_label'],
            'title' => $result['title'],
            'content_preview_html' => $result['content_html'],
            'content_length' => $result['content_length'],
            'story_id' => $story_id,
            'chapter_number' => $chapter_number,
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

        $chapter_id = wp_insert_post([
            'post_type' => ChapterPostType::SLUG,
            'post_title' => $scrape['title'],
            'post_content' => $scrape['content_html'],
            'post_status' => $post_status,
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
            'content_length' => $scrape['content_length'],
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
