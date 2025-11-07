<?php
namespace ExtendSite\Services\Tools;

defined('ABSPATH') || exit;

interface ToolInterface {
    /** Tên hiển thị trong admin */
    public static function get_title(): string;

    /** Mô tả ngắn về chức năng */
    public static function get_description(): string;

    /** Hành động thực tế khi bấm nút */
    public static function run(): array;
}
