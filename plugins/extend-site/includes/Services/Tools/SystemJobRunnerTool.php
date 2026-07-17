<?php

namespace ExtendSite\Services\Tools;

use ExtendSite\Services\SystemJobQueue;

defined('ABSPATH') || exit;

class SystemJobRunnerTool implements ToolInterface
{
    public static function get_title(): string
    {
        return 'Chạy tiếp job nền đang chờ';
    }

    public static function get_description(): string
    {
        return 'Xử lý một vài job nền đang chờ, ví dụ clone chương của truyện vừa nhân bản.';
    }

    public static function run(): array
    {
        $processed = SystemJobQueue::process_pending(3);

        return [
            'message' => sprintf('Đã xử lý %d job nền đang chờ.', $processed),
        ];
    }
}
