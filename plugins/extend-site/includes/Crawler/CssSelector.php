<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

final class CssSelector
{
    public static function to_xpath(string $selector): string
    {
        $selector = trim($selector);
        if ($selector === '' || strpos($selector, ',') !== false) {
            return '';
        }

        $selector = preg_replace('/\s*>\s*/', ' > ', $selector);
        $parts = preg_split('/\s+/', (string) $selector, -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return '';
        }

        $xpath = '';
        $axis = '//';
        $needs_selector = true;

        foreach ($parts as $part) {
            if ($part === '>') {
                if ($needs_selector) {
                    return '';
                }

                $axis = '/';
                $needs_selector = true;
                continue;
            }

            $segment = self::part_to_xpath($part);
            if ($segment === '') {
                return '';
            }

            $xpath .= $axis . $segment;
            $axis = '//';
            $needs_selector = false;
        }

        return $needs_selector ? '' : $xpath;
    }

    private static function part_to_xpath(string $part): string
    {
        if (!preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)?((?:[#.][a-zA-Z0-9_-]+)*)(?::([a-z-]+)(?:\((\d+)\))?)?$/', $part, $matches)) {
            return '';
        }

        $tag = $matches[1] !== '' ? strtolower($matches[1]) : '*';
        $suffix = $matches[2] ?? '';
        $pseudo = $matches[3] ?? '';
        $pseudo_value = isset($matches[4]) ? max(0, (int) $matches[4]) : 0;
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

        if ($pseudo !== '') {
            $predicate = self::pseudo_to_xpath_predicate($pseudo, $pseudo_value, $tag);
            if ($predicate === '') {
                return '';
            }

            $predicates[] = $predicate;
        }

        return $tag . ($predicates ? '[' . implode(' and ', $predicates) . ']' : '');
    }

    private static function pseudo_to_xpath_predicate(string $pseudo, int $value, string $tag): string
    {
        switch ($pseudo) {
            case 'first-child':
                return 'count(preceding-sibling::*) = 0';
            case 'last-child':
                return 'count(following-sibling::*) = 0';
            case 'nth-child':
                return $value > 0 ? 'count(preceding-sibling::*) = ' . ($value - 1) : '';
            case 'first-of-type':
                return $tag !== '*' ? 'count(preceding-sibling::' . $tag . ') = 0' : '';
            case 'last-of-type':
                return $tag !== '*' ? 'count(following-sibling::' . $tag . ') = 0' : '';
            case 'nth-of-type':
                return $value > 0 && $tag !== '*' ? 'count(preceding-sibling::' . $tag . ') = ' . ($value - 1) : '';
        }

        return '';
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
}
