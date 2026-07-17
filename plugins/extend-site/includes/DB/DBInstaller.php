<?php
namespace ExtendSite\DB;

use ExtendSite\Crawler\CrawlerLinkTable;
use ExtendSite\Crawler\CrawlerTemplateTable;

class DBInstaller {
    public static function install(): void {
        LatestChapterTable::create();
        ViewsStoryDailyTable::create();
        SystemJobTable::create();
        CrawlerLinkTable::create();
        CrawlerTemplateTable::create();
    }
}
