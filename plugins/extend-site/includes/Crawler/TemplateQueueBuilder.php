<?php

namespace ExtendSite\Crawler;

use DOMDocument;
use DOMElement;
use DOMXPath;
use WP_Error;

defined('ABSPATH') || exit;

class TemplateQueueBuilder
{
    public const SOURCE_DETECTED_LINKS = 'detected_links';
    public const SOURCE_PATTERN_FALLBACK = 'pattern_fallback';
    public const MAX_TOC_PAGES = 50;

    public static function build(DOMXPath $xpath, array $template, string $story_url, int $from, int $to, int $padding)
    {
        if ($from < 1 || $to < 1 || $to < $from) {
            return new WP_Error(
                'invalid_template_range',
                __('Hãy nhập khoảng chương Từ/Đến hợp lệ để tạo queue từ Template.', 'extend-site')
            );
        }

        $links = self::template_chapter_links($xpath, $template, $story_url);
        $detected_total = count($links);
        if ($detected_total > 0) {
            $queue = self::chapter_queue_from_links($links);
            if (!($from === 1 && $to === 1)) {
                $queue = self::filter_queue_by_range($queue, $from, $to);
            }
            if (!$queue) {
                return new WP_Error(
                    'empty_detected_link_range',
                    __('Đã quét được link chương thật, nhưng không có chương nào nằm trong khoảng Từ/Đến đã chọn.', 'extend-site')
                );
            }

            return [
                'queue' => self::limit_queue($queue),
                'source' => self::SOURCE_DETECTED_LINKS,
                'detected_total' => $detected_total,
                'warnings' => [],
            ];
        }

        $queue = self::chapter_queue_from_template_pattern($template, $story_url, $from, $to, $padding);
        if (is_wp_error($queue)) {
            return $queue;
        }

        return [
            'queue' => self::limit_queue($queue),
            'source' => self::SOURCE_PATTERN_FALLBACK,
            'detected_total' => 0,
            'warnings' => [
                __('Không quét được link chương thật từ HTML. Crawler sẽ fallback sang Mẫu URL chương.', 'extend-site'),
            ],
        ];
    }

    public static function template_chapter_links(DOMXPath $xpath, array $template, string $story_url): array
    {
        $chapter_selector = (string) ($template['chapter_link_selector'] ?? '');
        $links = self::selector_links($xpath, $chapter_selector, $story_url);
        $toc_selector = (string) ($template['toc_page_link_selector'] ?? '');
        $toc_pages = $toc_selector !== '' ? self::selector_links($xpath, $toc_selector, $story_url) : [];

        $page_urls = array_keys($toc_pages);
        $page_urls = array_slice(array_values(array_unique($page_urls)), 0, self::MAX_TOC_PAGES);
        foreach ($page_urls as $page_url) {
            $body = self::fetch_html($page_url, 20);
            if (is_wp_error($body)) {
                continue;
            }

            $dom = self::load_dom($body);
            if (is_wp_error($dom)) {
                continue;
            }

            $page_xpath = new DOMXPath($dom);
            $links = array_replace($links, self::selector_links($page_xpath, $chapter_selector, $page_url));
        }

        return $links;
    }

    private static function chapter_queue_from_template_pattern(array $template, string $story_url, int $from, int $to, int $padding)
    {
        $pattern = trim((string) ($template['chapter_url_pattern'] ?? ''));
        if ($pattern === '') {
            return new WP_Error(
                'missing_chapter_url_pattern',
                __('Không quét được link chương thật và Template chưa có Mẫu URL chương để fallback.', 'extend-site')
            );
        }

        $max = (int) apply_filters('es_crawler_max_batch_size', CrawlerAjax::MAX_BATCH_SIZE);
        if (($to - $from + 1) > $max) {
            return new WP_Error(
                'template_range_too_large',
                sprintf(__('Khoảng chương vượt giới hạn an toàn: %d URL.', 'extend-site'), $max)
            );
        }

        $story_url_base = untrailingslashit($story_url);
        $story_slug = trim(basename(untrailingslashit((string) wp_parse_url($story_url, PHP_URL_PATH))), '/');
        $queue = [];
        for ($chapter = $from; $chapter <= $to; $chapter++) {
            $chapter_number = $padding > 0 ? str_pad((string) $chapter, $padding, '0', STR_PAD_LEFT) : (string) $chapter;
            $chapter_index = max(0, $chapter - 1);
            $chapter_index = $padding > 0 ? str_pad((string) $chapter_index, $padding, '0', STR_PAD_LEFT) : (string) $chapter_index;
            $url = str_replace(
                ['{story_url}', '{story_slug}', '{chapter_number}', '{chapter_index}', '{n}'],
                [$story_url_base, $story_slug, $chapter_number, $chapter_index, $chapter_number],
                $pattern
            );
            $url = esc_url_raw($url);
            if ($url === '' || !wp_http_validate_url($url)) {
                return new WP_Error(
                    'invalid_template_pattern_url',
                    __('Mẫu URL chương tạo ra URL không hợp lệ. Hãy kiểm tra {story_url}, {story_slug} và {chapter_number}.', 'extend-site')
                );
            }

            $queue[] = [
                'chapterNumber' => $chapter,
                'url' => $url,
                'retries' => 0,
                'completed' => false,
            ];
        }

        return $queue;
    }

    private static function chapter_queue_from_links(array $links): array
    {
        $items = [];
        $fallback_number = 1;
        foreach ($links as $url => $text) {
            $number = self::extract_chapter_number_from_url((string) $url) ?: self::extract_chapter_number_from_text((string) $text);
            if (!$number) {
                $number = $fallback_number;
            }

            $items[] = [
                'chapterNumber' => (int) $number,
                'url' => (string) $url,
                'retries' => 0,
                'completed' => false,
            ];
            $fallback_number++;
        }

        usort($items, static function (array $a, array $b): int {
            return ((int) $a['chapterNumber']) <=> ((int) $b['chapterNumber']);
        });

        return $items;
    }

    private static function filter_queue_by_range(array $queue, int $from, int $to): array
    {
        return array_values(array_filter($queue, static function (array $item) use ($from, $to): bool {
            $number = (int) ($item['chapterNumber'] ?? 0);

            return $number >= $from && $number <= $to;
        }));
    }

    private static function limit_queue(array $queue): array
    {
        $max = (int) apply_filters('es_crawler_max_batch_size', CrawlerAjax::MAX_BATCH_SIZE);

        return count($queue) > $max ? array_slice($queue, 0, $max) : $queue;
    }

    private static function extract_chapter_number_from_text(string $text): ?int
    {
        if (preg_match('/(?:chuong|chapter|chap|tap)?\s*0*([0-9]+)/iu', remove_accents($text), $matches)) {
            $number = (int) $matches[1];

            return $number > 0 ? $number : null;
        }

        return null;
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

    private static function query_selector_all(DOMXPath $xpath, string $selector)
    {
        $expression = CssSelector::to_xpath($selector);
        if ($expression === '') {
            return null;
        }

        return $xpath->query($expression);
    }

    private static function resolve_url(string $url, string $base_url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || strpos($url, '#') === 0 || preg_match('/^(javascript|mailto|tel):/i', $url)) {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return esc_url_raw($url);
        }

        $base = wp_parse_url($base_url);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return '';
        }

        if (strpos($url, '//') === 0) {
            return esc_url_raw($base['scheme'] . ':' . $url);
        }

        $root = $base['scheme'] . '://' . $base['host'] . (!empty($base['port']) ? ':' . $base['port'] : '');
        if (strpos($url, '/') === 0) {
            return esc_url_raw($root . $url);
        }

        $path = isset($base['path']) ? rtrim(dirname($base['path']), '/\\') : '';

        return esc_url_raw($root . ($path ? '/' . ltrim($path, '/') : '') . '/' . $url);
    }

    private static function node_text($node): string
    {
        if (!$node) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
    }

    private static function fetch_html(string $url, int $timeout = 20)
    {
        $url = esc_url_raw(trim($url));
        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_Error('invalid_url', __('URL nguồn không hợp lệ.', 'extend-site'));
        }

        $response = wp_remote_get($url, [
            'timeout' => $timeout,
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
            return new WP_Error('http_error', sprintf(__('Nguồn trả về HTTP %d.', 'extend-site'), $code));
        }

        $body = (string) wp_remote_retrieve_body($response);
        if (trim($body) === '') {
            return new WP_Error('empty_body', __('Nguồn trả về nội dung rỗng.', 'extend-site'));
        }

        return $body;
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
}
