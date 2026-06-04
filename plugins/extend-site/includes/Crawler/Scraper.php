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
    public const DEFAULT_TIMEOUT = 20;
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
        $rule = self::get_rule_for_domain($domain);
        $rule_warnings = [];
        if (!$rule) {
            $rule = self::get_generic_rule($domain);
            $rule_warnings[] = __('Chưa có luật riêng cho tên miền này, crawler đang thử luật tự động.', 'extend-site');
        }

        $body = self::fetch($clean_url);
        if (is_wp_error($body)) {
            return $body;
        }

        $parsed = self::parse_html($body, $rule, $replace_rules);
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
            'warnings' => $warnings,
        ];
    }

    public static function get_rules(): array
    {
        $rules = [
            'tinhvan.site' => [
                'label' => 'Tinh Van',
                'title_xpath' => "//h1[contains(@class,'entry-title') or contains(@class,'chapter-title') or contains(@class,'title')]",
                'content_xpath' => "//*[contains(@class,'entry-content') or contains(@class,'chapter-content') or contains(@class,'reading-content') or contains(@class,'content')]",
                'cleanup_xpath' => [
                    ".//script",
                    ".//style",
                    ".//*[contains(@class,'ads') or contains(@class,'advert') or contains(@class,'sharedaddy')]",
                ],
            ],
            'doctruyenchill.net' => [
                'label' => 'Doc Truyen Chill',
                'title_xpath' => "//h1[contains(@class,'chapter-title') or contains(@class,'entry-title') or contains(@class,'title')]",
                'content_xpath' => "//*[contains(@class,'chapter-content') or contains(@class,'entry-content') or contains(@class,'reading-content') or @id='chapter-c']",
                'cleanup_xpath' => [
                    ".//script",
                    ".//style",
                    ".//*[contains(@class,'ads') or contains(@class,'advert') or contains(@class,'chapter-nav')]",
                ],
            ],
        ];

        return apply_filters('es_crawler_domain_rules', $rules);
    }

    private static function fetch(string $url)
    {
        $response = wp_remote_get($url, [
            'timeout' => (int) apply_filters('es_crawler_http_timeout', self::DEFAULT_TIMEOUT, $url),
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

    private static function parse_html(string $html, array $rule, array $replace_rules)
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
            $warnings[] = __('XPath tiêu đề không khớp.', 'extend-site');
            $title = __('Chương chưa có tiêu đề', 'extend-site');
        }

        $content_node = self::first_node($xpath, (string) ($rule['content_xpath'] ?? ''));
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

        return [
            'title' => sanitize_text_field($title),
            'content_html' => $content_html,
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
            'title_xpath' => "//h1 | //h2[contains(@class,'chapter-title') or contains(@class,'entry-title') or contains(@class,'title')] | //*[@class='chapter-title']",
            'content_xpath' => "//*[@id='chapter-c'] | //*[@id='chapter-content'] | //article | //div[contains(@class,'chapter-c') or contains(@class,'chapter-content') or contains(@class,'entry-content') or contains(@class,'reading-content') or contains(@class,'content')]",
            'cleanup_xpath' => [
                ".//script",
                ".//style",
                ".//noscript",
                ".//iframe",
                ".//*[contains(@class,'ads') or contains(@class,'advert') or contains(@class,'chapter-nav') or contains(@class,'breadcrumb') or contains(@class,'social')]",
            ],
        ];
    }

    private static function normalize_domain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        return preg_replace('/^www\./', '', $domain) ?: $domain;
    }

    private static function remove_unwanted_nodes(DOMXPath $xpath, array $expressions): void
    {
        $defaults = [
            './/script',
            './/style',
            './/noscript',
            './/iframe',
            ".//*[contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'ads') or contains(translate(@id, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'advert') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'ads') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'advert')]",
        ];
        foreach (array_merge($defaults, $expressions) as $expression) {
            foreach ($xpath->query($expression) ?: [] as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    private static function first_text(DOMXPath $xpath, string $expression): string
    {
        $node = self::first_node($xpath, $expression);

        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent) ?: '') : '';
    }

    private static function first_node(DOMXPath $xpath, string $expression): ?DOMNode
    {
        if ($expression === '') {
            return null;
        }

        $nodes = $xpath->query($expression);
        if (!$nodes || $nodes->length < 1) {
            return null;
        }

        return $nodes->item(0);
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
            foreach ($replace_rules as $rule) {
                if (!is_array($rule) || !empty($rule['regex'])) {
                    continue;
                }

                $find = isset($rule['find']) ? (string) $rule['find'] : '';
                if ($find === '') {
                    continue;
                }

                $value = self::replace_plain_text($value, $find, isset($rule['replace']) ? (string) $rule['replace'] : '');
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
        $expressions = [
            "//div[not(@id='es-crawler-fragment') and not(normalize-space()) and not(*)]",
            "//p[not(normalize-space()) and not(*)]",
            "//span[not(normalize-space()) and not(*)]",
            "//i[not(normalize-space()) and not(*)]",
            "//b[not(normalize-space()) and not(*)]",
            "//strong[not(normalize-space()) and not(*)]",
            "//em[not(normalize-space()) and not(*)]",
        ];

        foreach ($expressions as $expression) {
            foreach ($xpath->query($expression) ?: [] as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
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
        $block_tags = ['p', 'div', 'section', 'article', 'blockquote', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
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

    private static function content_length(string $html): int
    {
        return strlen(trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
    }
}
