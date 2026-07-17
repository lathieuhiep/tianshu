<?php

namespace ExtendSite\Services\Tools;

use ExtendSite\DB\SystemJobTable;

defined('ABSPATH') || exit;

class SystemJobCleanupTool implements ToolInterface
{
    public static function get_title(): string
    {
        return 'Dọn job đã kết thúc';
    }

    public static function get_description(): string
    {
        return 'Xóa các job đã hoàn tất, lỗi hoặc đã hủy. Không xóa job đang chờ hoặc đang chạy.';
    }

    public static function run(): array
    {
        $deleted = SystemJobTable::cleanup_finished();

        return [
            'message' => sprintf('Đã xóa %d job đã kết thúc.', $deleted),
        ];
    }
}
