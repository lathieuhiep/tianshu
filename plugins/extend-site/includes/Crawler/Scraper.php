<?php

namespace ExtendSite\Crawler;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use WP_Error;

defined('ABSPATH') || exit;

class Scraper
{
    public const DEFAULT_TIMEOUT = 30;
    public const DEFAULT_CONNECT_TIMEOUT = 10;
    public const DEFAULT_MIN_CONTENT_LENGTH = 300;

    public static function get_user_agent(): string
    {
        return apply_filters(
            'es_crawler_user_agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36'
        );
    }

    public static function scrape(string $source_url, array $replace_rules = [], bool $allow_short_content = false)
    {
        $clean_url = CrawlerLinkTable::clean_url_for_hash($source_url);
        if ($clean_url === '' || !wp_http_validate_url($clean_url)) {
            return new WP_Error('invalid_url', __('URL nguồn không hợp lệ.', 'extend-site'));
        }

        $domain = self::normalize_domain((string) wp_parse_url($clean_url, PHP_URL_HOST));
        $use_domain_rules = (bool) apply_filters('es_crawler_use_domain_rules', false, $domain, $clean_url);
        $rule = $use_domain_rules ? self::get_rule_for_domain($domain) : null;
        $rule_warnings = [];
        if (!$rule) {
            $rule = self::get_generic_rule($domain);
        }

        $body = self::fetch($clean_url);
        if (is_wp_error($body)) {
            return $body;
        }

        $parsed = self::parse_html($body, $rule, $replace_rules, $clean_url);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        $content_length = self::content_length($parsed['content_html']);
        $warnings = array_merge($rule_warnings, $parsed['warnings']);
        $min_length = (int) apply_filters('es_crawler_min_content_length', self::DEFAULT_MIN_CONTENT_LENGTH, $clean_url, $domain);

        if ($content_length < $min_length) {
            $warnings[] = sprintf(__('Nội dung quá ngắn: %d ký tự.', 'extend-site'), $content_length);
            if (!$allow_short_content) {
                return new WP_Error('content_too_short', __('Nội dung phân tích được ngắn hơn giới hạn tối thiểu.', 'extend-site'), [
                    'content_length' => $content_length,
                    'warnings' => $warnings,
                ]);
            }
        }

        return [
            'source_url' => $source_url,
            'clean_url' => $clean_url,
            'source_url_hash' => CrawlerLinkTable::hash_url($clean_url),
            'domain' => $domain,
            'rule_label' => $rule['label'] ?? $domain,
            'title' => $parsed['title'],
            'content_html' => $parsed['content_html'],
            'content_length' => $content_length,
            'source_chapter_number' => $parsed['source_chapter_number'],
            'source_max_chapter_number' => $parsed['source_max_chapter_number'],
            'warnings' => $warnings,
        ];
    }

    public static function scrape_with_template(string $source_url, array $template, array $replace_rules = [], bool $allow_short_content = false)
    {
        $scope_selector = trim((string) ($template['chapter_content_scope_selector'] ?? ''));
        $content_selector = trim((string) ($template['chapter_content_selector'] ?? ''));
        if ($scope_selector === '') {
            return new WP_Error('content_scope_selector_required', __('Template chua cau hinh selector khoi boc noi dung chuong.', 'extend-site'));
        }

        $clean_url = CrawlerLinkTable::clean_url_for_hash($source_url);
        if ($clean_url === '' || !wp_http_validate_url($clean_url)) {
            return new WP_Error('invalid_url', __('URL nguá»“n khÃ´ng há»£p lá»‡.', 'extend-site'));
        }

        $domain = self::normalize_domain((string) wp_parse_url($clean_url, PHP_URL_HOST));
        $body = self::fetch($clean_url);
        if (is_wp_error($body)) {
            return $body;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return new WP_Error('html_parse_failed', __('KhÃ´ng thá»ƒ phÃ¢n tÃ­ch HTML nguá»“n.', 'extend-site'));
        }

        $xpath = new DOMXPath($dom);
        self::remove_unwanted_nodes($xpath, [
            './/script',
            './/style',
            './/noscript',
            './/iframe',
        ]);

        $scope_node = self::first_selector_node($xpath, $scope_selector);
        if (!$scope_node) {
            return new WP_Error('content_scope_selector_missing', __('Selector khoi boc noi dung chuong khong khop.', 'extend-site'));
        }

        $title = self::first_selector_text($xpath, (string) ($template['chapter_title_selector'] ?? ''), $scope_node);
        if ($title === '') {
            $title = self::first_text($xpath, './/h1', $scope_node);
        }

        $content_node = $content_selector === '' || trim($content_selector) === trim($scope_selector)
            ? $scope_node
            : self::first_selector_node($xpath, $content_selector, $scope_node);
        if (!$content_node) {
            return new WP_Error('content_selector_missing', __('Selector noi dung chuong khong khop trong khoi boc.', 'extend-site'));
        }

        $content_html = self::inner_html($content_node);
        $content_html = self::apply_replacements($content_html, $replace_rules);
        $content_html = wp_kses_post($content_html);
        $content_html = self::apply_text_node_replacements($content_html, $replace_rules);
        $content_html = self::cleanup_fragment_html($content_html);
        $content_length = self::content_length($content_html);
        $warnings = [];
        $min_length = (int) apply_filters('es_crawler_min_content_length', self::DEFAULT_MIN_CONTENT_LENGTH, $clean_url, $domain);

        if ($content_length < $min_length) {
            $warnings[] = sprintf(__('Ná»™i dung quÃ¡ ngáº¯n: %d kÃ½ tá»±.', 'extend-site'), $content_length);
            if (!$allow_short_content) {
                return new WP_Error('content_too_short', __('Ná»™i dung phÃ¢n tÃ­ch Ä‘Æ°á»£c ngáº¯n hÆ¡n giá»›i háº¡n tá»‘i thiá»ƒu.', 'extend-site'), [
                    'content_length' => $content_length,
                    'warnings' => $warnings,
                ]);
            }
        }

        return [
            'source_url' => $source_url,
            'clean_url' => $clean_url,
            'source_url_hash' => CrawlerLinkTable::hash_url($clean_url),
            'domain' => $domain,
            'rule_label' => (string) ($template['name'] ?? $domain),
            'title' => sanitize_text_field($title),
            'content_html' => $content_html,
            'content_length' => $content_length,
            'source_chapter_number' => self::detect_source_chapter_number($xpath),
            'source_max_chapter_number' => self::detect_source_max_chapter_number($xpath, $source_url),
            'warnings' => $warnings,
        ];
    }

    public static function get_rules(): array
    {
        return apply_filters('es_crawler_domain_rules', []);
    }

    private static function fetch(string $url)
    {
        $response = wp_remote_get($url, [
            'timeout' => (int) apply_filters('es_crawler_http_timeout', self::DEFAULT_TIMEOUT, $url),
            'connecttimeout' => (int) apply_filters('es_crawler_http_connect_timeout', self::DEFAULT_CONNECT_TIMEOUT, $url),
            'redirection' => 5,
            'headers' => [
                'User-Agent' => self::get_user_agent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('http_error', sprintf(__('Nguồn trả về HTTP %d.', 'extend-site'), $code));
        }

        $body = (string) wp_remote_retrieve_body($response);
        if (trim($body) === '') {
            return new WP_Error('empty_body', __('Nguồn trả về nội dung rỗng.', 'extend-site'));
        }

        if (stripos($body, '<html') === false && stripos($body, '<body') === false) {
            return new WP_Error('non_html_body', __('Phản hồi nguồn không giống HTML.', 'extend-site'));
        }


        return $body;
    }

    private static function parse_html(string $html, array $rule, array $replace_rules, string $source_url = '')
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

        $xpath = new DOMXPath($dom);
        self::remove_unwanted_nodes($xpath, $rule['cleanup_xpath'] ?? []);

        $warnings = [];
        $title = self::first_text($xpath, (string) ($rule['title_xpath'] ?? ''));
        if ($title === '') {
            $title = '';
        }

        $content_node = self::best_content_node($xpath, (string) ($rule['content_xpath'] ?? ''));
        if (!$content_node) {
            return new WP_Error('content_xpath_missing', __('XPath nội dung không khớp.', 'extend-site'), [
                'warnings' => $warnings,
            ]);
        }

        $content_html = self::inner_html($content_node);
        $content_html = self::apply_replacements($content_html, $replace_rules);
        $content_html = wp_kses_post($content_html);
        $content_html = self::apply_text_node_replacements($content_html, $replace_rules);
        $content_html = self::cleanup_fragment_html($content_html);

        if (trim(wp_strip_all_tags($content_html)) === '') {
            return new WP_Error('empty_content', __('Nội dung phân tích được đang rỗng.', 'extend-site'), [
                'warnings' => $warnings,
            ]);
        }

        if (self::looks_like_error_page($title . "\n" . wp_strip_all_tags($content_html), self::content_length($content_html))) {
            return new WP_Error('blocked_or_error_page', __('Nội dung lấy được giống trang lỗi, captcha hoặc trang chặn truy cập.', 'extend-site'), [
                'warnings' => $warnings,
            ]);
        }

        return [
            'title' => sanitize_text_field($title),
            'content_html' => $content_html,
            'source_chapter_number' => self::detect_source_chapter_number($xpath),
            'source_max_chapter_number' => self::detect_source_max_chapter_number($xpath, $source_url),
            'warnings' => $warnings,
        ];
    }

    private static function get_rule_for_domain(string $domain): ?array
    {
        foreach (self::get_rules() as $rule_domain => $rule) {
            $normalized = self::normalize_domain((string) $rule_domain);
            if ($domain === $normalized || substr($domain, -strlen('.' . $normalized)) === '.' . $normalized) {
                return is_array($rule) ? $rule : null;
            }
        }

        return null;
    }

    private static function get_generic_rule(string $domain): array
    {
        return [
            'label' => sprintf(__('Luật tự động (%s)', 'extend-site'), $domain),
            'title_xpath' => "//h1[string-length(normalize-space(.)) > 0] | //h2[string-length(normalize-space(.)) > 0] | //h3[string-length(normalize-space(.)) > 0]",
            'content_xpath' => "//*[@id='chapter-c'] | //*[@id='chapter-content'] | //div[contains(@class,'chapter-c') or contains(@class,'chapter-content') or contains(@class,'entry-content') or contains(@class,'reading-content')] | //article[string-length(normalize-space(.)) > 120] | //main[string-length(normalize-space(.)) > 120] | //section[string-length(normalize-space(.)) > 200] | //div[string-length(normalize-space(.)) > 200 and (count(.//p) >= 2 or count(.//br) >= 4 or string-length(normalize-space(.)) > 800)]",
            'cleanup_xpath' => [
                ".//script",
                ".//style",
                ".//noscript",
                ".//iframe",
                ".//header",
                ".//nav",
                ".//footer",
                ".//*[contains(@class,'ads') or contains(@class,'advert') or contains(@class,'chapter-nav') or contains(@class,'breadcrumb') or contains(@class,'social') or contains(@class,'popup') or contains(@class,'modal') or contains(@class,'overlay') or contains(@class,'menu') or contains(@class,'navbar') or contains(@class,'sidebar') or contains(@class,'widget')]",
            ],
        ];
    }

    private static function normalize_domain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        return preg_replace('/^www\./', '', $domain) ?: $domain;
    }

    private static function looks_like_error_page(string $content, int $content_length = 0): bool
    {
        $content = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $content) ?: $content;
        $text = strtolower(remove_accents(wp_strip_all_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        $text = preg_replace('/\s+/', ' ', $text) ?: $text;

        if ($content_length > 2000) {
            return false;
        }

        $strong_patterns = [
            '404 not found',
            '404 page',
            'error 404',
            'page not found',
            'trang khong ton tai',
            'captcha',
            'access denied',
            'forbidden',
            'just a moment',
            'checking your browser',
            'verify you are human',
        ];

        foreach ($strong_patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return true;
            }
        }

        $weak_patterns = [
            'not found',
            'khong tim thay',
        ];
        foreach ($weak_patterns as $pattern) {
            if (strpos($text, $pattern) !== false && mb_strlen($text) < 2000) {
                return true;
            }
        }

        return false;
    }

    private static function remove_unwanted_nodes(DOMXPath $xpath, array $expressions): void
    {
        $defaults = [
            './/script',
            './/style',
            './/noscript',
            './/iframe',
            './/header',
            './/nav',
            './/footer',
            ".//*[contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'ads') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'advert') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'popup') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'modal') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'overlay') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'menu') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'navbar') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'sidebar') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'widget') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'ads') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'advert') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'popup') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'modal') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'overlay') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'mask') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'menu') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'navbar') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'sidebar') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'widget')]",
        ];
        foreach (array_merge($defaults, $expressions) as $expression) {
            foreach ($xpath->query($expression) ?: [] as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    private static function first_text(DOMXPath $xpath, string $expression, ?DOMNode $context = null): string
    {
        $node = self::first_node($xpath, $expression, $context);

        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent) ?: '') : '';
    }

    private static function first_node(DOMXPath $xpath, string $expression, ?DOMNode $context = null): ?DOMNode
    {
        if ($expression === '') {
            return null;
        }

        $nodes = $context ? $xpath->query($expression, $context) : $xpath->query($expression);
        if (!$nodes || $nodes->length < 1) {
            return null;
        }

        return $nodes->item(0);
    }

    private static function first_selector_text(DOMXPath $xpath, string $selector, ?DOMNode $context = null): string
    {
        $node = self::first_selector_node($xpath, $selector, $context);

        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent) ?: '') : '';
    }

    private static function first_selector_node(DOMXPath $xpath, string $selector, ?DOMNode $context = null): ?DOMNode
    {
        $expression = self::css_selector_to_xpath($selector);
        if ($expression === '') {
            return null;
        }

        if ($context && strpos($expression, '//') === 0) {
            $expression = '.' . $expression;
        }

        return self::first_node($xpath, $expression, $context);
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
                    $predicates[] = '@id = ' . self::xpath_literal($token[2]);
                    continue;
                }

                $predicates[] = 'contains(concat(" ", normalize-space(@class), " "), ' . self::xpath_literal(' ' . $token[2] . ' ') . ')';
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

    private static function best_content_node(DOMXPath $xpath, string $expression): ?DOMNode
    {
        if ($expression === '') {
            return null;
        }

        $nodes = $xpath->query($expression);
        if (!$nodes || $nodes->length < 1) {
            return null;
        }

        $best = null;
        $best_score = PHP_INT_MIN;
        $body_text = self::normalized_node_text(self::first_node($xpath, '//body') ?: $nodes->item(0));
        $body_length = max(1, mb_strlen($body_text));

        foreach ($nodes as $node) {
            $text = self::normalized_node_text($node);
            $length = mb_strlen($text);
            if ($length < 80) {
                continue;
            }

            $score = self::score_content_candidate($xpath, $node, $text, $length, $body_length);

            if ($score > $best_score) {
                $best = $node;
                $best_score = $score;
            }
        }

        return $best ?: $nodes->item(0);
    }

    private static function detect_source_chapter_number(DOMXPath $xpath): ?int
    {
        $number = self::detect_chapter_number_from_inputs($xpath);
        if ($number !== null) {
            return $number;
        }

        return self::detect_chapter_number_from_current_label($xpath);
    }

    private static function detect_chapter_number_from_inputs(DOMXPath $xpath): ?int
    {
        foreach ($xpath->query('//input[@value]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $field = strtolower(trim($node->getAttribute('id') . ' ' . $node->getAttribute('name')));
            if ($field === '' || !preg_match('/chapter|chuong|chương|tap|tập/u', $field)) {
                continue;
            }

            $value = trim($node->getAttribute('value'));
            if (preg_match('/^\d+$/', $value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    private static function detect_chapter_number_from_current_label(DOMXPath $xpath): ?int
    {
        $expressions = [
            "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'active') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'current') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'current')]",
            "//*[@aria-current='page']",
        ];

        foreach ($expressions as $expression) {
            foreach ($xpath->query($expression) ?: [] as $node) {
                $text = self::normalized_node_text($node);
                if (preg_match('/(?:chương|chuong|chapter|chap|tập|tap)\s*([0-9]+)/iu', $text, $matches)) {
                    $number = (int) $matches[1];
                    if ($number > 0) {
                        return $number;
                    }
                }
            }
        }

        return null;
    }

    private static function detect_source_max_chapter_number(DOMXPath $xpath, string $source_url): ?int
    {
        $max = null;
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $number = self::extract_chapter_number_from_href($node->getAttribute('href'), $source_url);
            if ($number === null) {
                continue;
            }

            $max = $max === null ? $number : max($max, $number);
        }

        return $max;
    }

    private static function extract_chapter_number_from_href(string $href, string $source_url): ?int
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || strpos($href, '#') === 0 || preg_match('/^(javascript|mailto|tel):/i', $href)) {
            return null;
        }

        $source_parts = parse_url($source_url) ?: [];
        $href_parts = parse_url($href) ?: [];
        if (!empty($href_parts['host']) && !empty($source_parts['host']) && strtolower($href_parts['host']) !== strtolower($source_parts['host'])) {
            return null;
        }

        $source_path = self::normalize_url_path((string) ($source_parts['path'] ?? ''));
        $href_path = self::normalize_url_path((string) ($href_parts['path'] ?? ''));
        if ($href_path !== '' && $source_path !== '' && rtrim($href_path, '/') !== rtrim($source_path, '/')) {
            return null;
        }

        $query = (string) ($href_parts['query'] ?? '');
        if ($query !== '') {
            parse_str($query, $params);
            foreach (['chuong', 'chapter', 'chap', 'tap'] as $key) {
                if (isset($params[$key]) && preg_match('/^\d+$/', (string) $params[$key])) {
                    $number = (int) $params[$key];
                    return $number > 0 ? $number : null;
                }
            }
        }

        $path = $href_path !== '' ? $href_path : $href;
        if (preg_match('/(?:chuong|chapter|chap|tap)[\-_\/=]?([0-9]+)/i', $path, $matches)) {
            $number = (int) $matches[1];
            return $number > 0 ? $number : null;
        }

        return null;
    }

    private static function normalize_url_path(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $path = preg_replace('#/+#', '/', $path) ?: $path;

        return '/' . ltrim($path, '/');
    }

    private static function score_content_candidate(DOMXPath $xpath, DOMNode $node, string $text, int $length, int $body_length): int
    {
        $paragraph_count = self::query_count($xpath, './/p', $node);
        $long_paragraph_count = self::long_paragraph_count($xpath, $node);
        $br_count = self::query_count($xpath, './/br', $node);
        $link_count = self::query_count($xpath, './/a', $node);
        $list_count = self::query_count($xpath, './/li', $node);
        $button_count = self::query_count($xpath, './/button', $node);
        $form_count = self::query_count($xpath, './/form | .//input | .//select | .//textarea', $node);
        $media_count = self::query_count($xpath, './/img | .//video | .//iframe', $node);
        $heading_count = self::query_count($xpath, './/h1 | .//h2 | .//h3 | .//h4', $node);
        $sentence_count = self::sentence_count($text);
        $bad_keyword_count = self::bad_content_keyword_count($text);
        $bad_attr_count = self::bad_content_attribute_count($node);
        $link_density = $length > 0 ? $link_count / max(1, $length / 250) : 0;
        $body_ratio = $length / $body_length;

        $score = $length;
        $score += $paragraph_count * 140;
        $score += $long_paragraph_count * 220;
        $score += min($br_count, 20) * 35;
        $score += min($sentence_count, 80) * 20;
        $score += $heading_count * 20;

        $score -= $link_count * 120;
        $score -= $list_count * 70;
        $score -= $button_count * 160;
        $score -= $form_count * 220;
        $score -= $media_count * 25;
        $score -= $bad_keyword_count * 260;
        $score -= $bad_attr_count * 420;
        $score -= (int) round($link_density * 180);

        if ($paragraph_count === 0 && $br_count < 2 && $sentence_count < 4) {
            $score -= 700;
        }

        if ($body_ratio > 0.85 && ($link_count > 10 || $list_count > 10 || $bad_attr_count > 0)) {
            $score -= 1200;
        }

        return (int) $score;
    }

    private static function normalized_node_text(?DOMNode $node): string
    {
        if (!$node) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: '');
    }

    private static function query_count(DOMXPath $xpath, string $expression, DOMNode $context): int
    {
        $nodes = $xpath->query($expression, $context);

        return $nodes ? $nodes->length : 0;
    }

    private static function long_paragraph_count(DOMXPath $xpath, DOMNode $context): int
    {
        $count = 0;
        foreach ($xpath->query('.//p', $context) ?: [] as $paragraph) {
            if (mb_strlen(self::normalized_node_text($paragraph)) >= 80) {
                $count++;
            }
        }

        return $count;
    }

    private static function sentence_count(string $text): int
    {
        $matches = [];

        return preg_match_all('/[.!?;:。！？…]+/u', $text, $matches) ?: 0;
    }

    private static function bad_content_keyword_count(string $text): int
    {
        $text = mb_strtolower($text);
        $keywords = [
            'dang luc',
            'dang lúc',
            'đăng lúc',
            'luot xem',
            'lượt xem',
            'binh luan',
            'bình luận',
            'chuong truoc',
            'chương trước',
            'chuong sau',
            'chương sau',
            'danh sach chuong',
            'danh sách chương',
            'chia se',
            'chia sẻ',
            'theo doi',
            'theo dõi',
            'dang nhap',
            'đăng nhập',
            'dang ky',
            'đăng ký',
        ];

        $count = 0;
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_strpos($text, $keyword) !== false) {
                $count++;
            }
        }

        return $count;
    }

    private static function bad_content_attribute_count(DOMNode $node): int
    {
        if (!$node instanceof DOMElement) {
            return 0;
        }

        $value = mb_strtolower(trim($node->getAttribute('id') . ' ' . $node->getAttribute('class')));
        if ($value === '') {
            return 0;
        }

        $patterns = [
            'breadcrumb',
            'comment',
            'binh-luan',
            'binh_luan',
            'nav',
            'menu',
            'sidebar',
            'widget',
            'footer',
            'header',
            'modal',
            'popup',
            'overlay',
            'social',
            'share',
            'related',
            'recommend',
            'pagination',
            'chapter-list',
            'ds-chuong',
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            if (strpos($value, $pattern) !== false) {
                $count++;
            }
        }

        return $count;
    }

    private static function inner_html(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument ? $node->ownerDocument->saveHTML($child) : '';
        }

        return $html;
    }

    private static function apply_replacements(string $content, array $replace_rules): string
    {
        foreach ($replace_rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $find = isset($rule['find']) ? (string) $rule['find'] : '';
            if ($find === '') {
                continue;
            }

            $replace = isset($rule['replace']) ? (string) $rule['replace'] : '';
            $is_regex = !empty($rule['regex']);
            if (!empty($rule['remove_container']) && !$is_regex) {
                continue;
            }

            if ($is_regex) {
                $result = @preg_replace($find, $replace, $content);
                if (is_string($result)) {
                    $content = $result;
                }
            } else {
                $content = self::replace_plain_text($content, $find, $replace);
            }
        }

        return $content;
    }

    private static function apply_text_node_replacements(string $html, array $replace_rules): string
    {
        if (!$replace_rules || trim($html) === '') {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div id="es-crawler-fragment">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        self::remove_matching_containers($xpath, $replace_rules);
        foreach ($xpath->query('//text()') ?: [] as $text_node) {
            $value = $text_node->nodeValue;
            $removed_text = false;
            foreach ($replace_rules as $rule) {
                if (!is_array($rule) || !empty($rule['regex']) || !empty($rule['remove_container'])) {
                    continue;
                }

                $find = isset($rule['find']) ? (string) $rule['find'] : '';
                if ($find === '') {
                    continue;
                }

                $replace = isset($rule['replace']) ? (string) $rule['replace'] : '';
                $value = self::replace_plain_text($value, $find, $replace);
                if ($replace === '') {
                    $removed_text = true;
                }
            }
            if ($removed_text) {
                $value = self::cleanup_text_after_empty_replacement($value);
            }
            $text_node->nodeValue = $value;
        }

        $wrapper = $dom->getElementById('es-crawler-fragment');
        if (!$wrapper) {
            return $html;
        }

        return self::inner_html($wrapper);
    }

    private static function cleanup_fragment_html(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div id="es-crawler-fragment">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        self::remove_unwanted_nodes($xpath, []);
        self::remove_empty_nodes($xpath);

        $wrapper = $dom->getElementById('es-crawler-fragment');
        if (!$wrapper) {
            return $html;
        }

        return self::inner_html($wrapper);
    }

    private static function remove_empty_nodes(DOMXPath $xpath): void
    {
        $tags = [
            'span',
            'i',
            'b',
            'strong',
            'em',
            'p',
            'div',
        ];

        do {
            $removed = false;
            foreach ($tags as $tag) {
                foreach ($xpath->query('//' . $tag) ?: [] as $node) {
                    if ($node instanceof DOMElement && self::is_removable_empty_element($node) && $node->parentNode) {
                        $node->parentNode->removeChild($node);
                        $removed = true;
                    }
                }
            }
        } while ($removed);
    }

    private static function is_removable_empty_element(DOMElement $node): bool
    {
        if ($node->getAttribute('id') === 'es-crawler-fragment') {
            return false;
        }

        if (self::normalize_empty_check_text($node->textContent) !== '') {
            return false;
        }

        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                continue;
            }

            if (!in_array($tag, ['span', 'i', 'b', 'strong', 'em'], true)) {
                return false;
            }

            if (!self::is_removable_empty_element($child)) {
                return false;
            }
        }

        return true;
    }

    private static function normalize_empty_check_text(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        return trim($text);
    }

    private static function cleanup_text_after_empty_replacement(string $text): string
    {
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?: $text;
        if (trim($text) === '') {
            return '';
        }

        return $text;
    }

    private static function remove_matching_containers(DOMXPath $xpath, array $replace_rules): void
    {
        $rules = array_values(array_filter($replace_rules, static function ($rule): bool {
            return is_array($rule) && !empty($rule['remove_container']) && empty($rule['regex']) && !empty($rule['find']);
        }));

        if (!$rules) {
            return;
        }

        $nodes_to_remove = [];
        foreach ($xpath->query('//text()') ?: [] as $text_node) {
            $text = (string) $text_node->nodeValue;
            foreach ($rules as $rule) {
                if (self::plain_text_contains($text, (string) $rule['find'])) {
                    $container = self::find_removable_container($text_node);
                    if ($container && $container->parentNode) {
                        $nodes_to_remove[spl_object_hash($container)] = $container;
                    }
                    break;
                }
            }
        }

        foreach ($nodes_to_remove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private static function find_removable_container(DOMNode $node): ?DOMNode
    {
        $block_tags = ['a', 'button', 'p', 'div', 'section', 'article', 'blockquote', 'li', 'nav', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $current = $node->parentNode;
        $fallback = $current instanceof DOMElement ? $current : $node;

        while ($current && $current instanceof DOMElement) {
            if ($current->getAttribute('id') === 'es-crawler-fragment') {
                return $fallback;
            }

            if (in_array(strtolower($current->tagName), $block_tags, true)) {
                return $current;
            }

            $fallback = $current;
            $current = $current->parentNode;
        }

        return $fallback;
    }

    private static function plain_text_contains(string $content, string $find): bool
    {
        $content = self::normalize_for_plain_match($content);
        $find = self::normalize_for_plain_match($find);

        return $find !== '' && strpos($content, $find) !== false;
    }

    private static function normalize_for_plain_match(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        $value = function_exists('remove_accents') ? remove_accents($value) : $value;
        $value = mb_strtolower($value, 'UTF-8');

        return trim($value);
    }

    private static function replace_plain_text(string $content, string $find, string $replace): string
    {
        $variants = array_unique(array_filter([
            $find,
            html_entity_decode($find, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            function_exists('wp_specialchars_decode') ? wp_specialchars_decode($find, ENT_QUOTES) : $find,
            htmlentities($find, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ]));

        foreach ($variants as $variant) {
            $content = str_replace($variant, $replace, $content);
        }

        $content = self::replace_plain_text_case_insensitive($content, $find, $replace);

        $normalized_find = trim(str_replace("\xc2\xa0", ' ', html_entity_decode($find, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($normalized_find === '') {
            return $content;
        }

        $parts = preg_split('/\s+/u', $normalized_find);
        if (!$parts || count($parts) < 2) {
            return $content;
        }

        $pattern = '/' . implode('\s+', array_map(static fn($part) => preg_quote($part, '/'), $parts)) . '/u';
        $result = preg_replace($pattern, $replace, str_replace("\xc2\xa0", ' ', $content));

        return is_string($result) ? $result : $content;
    }

    private static function replace_plain_text_case_insensitive(string $content, string $find, string $replace): string
    {
        $find = trim(html_entity_decode($find, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($find === '') {
            return $content;
        }

        $pattern = '/' . preg_quote($find, '/') . '/iu';
        $result = preg_replace($pattern, $replace, $content);

        return is_string($result) ? $result : $content;
    }

    private static function content_length(string $html): int
    {
        return strlen(trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
    }
}
