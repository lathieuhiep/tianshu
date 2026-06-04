<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class Scraper
{
    public const DEFAULT_TIMEOUT = 20;

    public static function get_user_agent(): string
    {
        return apply_filters(
            'es_crawler_user_agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36'
        );
    }
}
