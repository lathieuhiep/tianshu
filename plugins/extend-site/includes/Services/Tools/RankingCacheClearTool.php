<?php
namespace ExtendSite\Services\Tools;

use ExtendSite\Services\RankingCache;

defined('ABSPATH') || exit;

/** Cong cu admin; controller xac minh nonce truoc khi goi run(). */
final class RankingCacheClearTool implements ToolInterface
{
    public static function get_title(): string
    {
        return __('Xóa cache xếp hạng', 'extend-site');
    }

    public static function get_description(): string
    {
        return __('Xóa cache truyện và tác giả của tất cả kỳ xếp hạng. Dữ liệu được dựng lại khi có lượt truy cập; không xóa lượt xem gốc.', 'extend-site');
    }

    public static function run(): array
    {
        if (!current_user_can('manage_options')) {
            return ['message' => __('Bạn không có quyền sử dụng công cụ này.', 'extend-site')];
        }
        return ['message' => RankingCache::clear()
            ? __('Đã xóa cache xếp hạng. Lượt truy cập tiếp theo sẽ dựng lại dữ liệu.', 'extend-site')
            : __('Không thể xóa cache xếp hạng. Vui lòng kiểm tra database.', 'extend-site')];
    }
}
