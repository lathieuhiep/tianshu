<?php
namespace ExtendSite\DB;

use ExtendSite\Crawler\CrawlerLinkTable;

class DBInstaller {
    public static function install(): void {
        LatestChapterTable::create();
        ViewsStoryDailyTable::create();
        CrawlerLinkTable::create();
    }
}
