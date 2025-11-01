<?php
namespace ExtendSite\DB;

class DBInstaller {
    public static function install(): void {
        ViewsStoryDailyTable::create();
    }
}