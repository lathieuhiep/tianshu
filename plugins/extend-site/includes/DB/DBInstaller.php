<?php
namespace ExtendSite\DB;

class DBInstaller {
    public static function install(): void {
        LatestChapterTable::create();
        ViewsStoryDailyTable::create();
    }
}