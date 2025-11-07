<?php
namespace ExtendSite\Search;

use ExtendSite\PostType\StoryPostType;

defined('ABSPATH') || exit;

class SearchHelper {
    /**
     * Get chapter ranges list.
     *
     * @return array<string,string>
     */
    public static function get_chapter_ranges(): array {
        return [
            '1-10'   => esc_html__('Dưới 10', 'extend-site'),
            '11-20'  => esc_html__('11 – 20', 'extend-site'),
            '21-40'  => esc_html__('21 – 40', 'extend-site'),
            '41-100' => esc_html__('41 – 100', 'extend-site'),
            '100+'   => esc_html__('100+', 'extend-site'),
        ];
    }

    /**
     * Get numeric range for a given chapter key.
     *
     * @param string $key
     * @return array{min:int, max:?int}
     */
    public static function parse_chapter_range(string $key): array {
        return match ($key) {
            '1-10'   => ['min' => 1, 'max' => 10],
            '11-20'  => ['min' => 11, 'max' => 20],
            '21-40'  => ['min' => 21, 'max' => 40],
            '41-100' => ['min' => 41, 'max' => 100],
            '100+'   => ['min' => 100, 'max' => null],
            default  => ['min' => 0, 'max' => null],
        };
    }

    /**
     * Get sort options list.
     *
     * @return array<string,string>
     */
    public static function get_sort_options(): array {
        return [
            'latest'   => esc_html__('Mới nhất', 'extend-site'),
            'views'    => esc_html__('Nhiều lượt xem', 'extend-site'),
            'chapters' => esc_html__('Nhiều chương', 'extend-site'),
        ];
    }

    /**
     * Map sort key to query arguments.
     *
     * @param string $sort_key
     * @return array<string,mixed>
     */
    public static function parse_sort_option(string $sort_key): array {
        return match ($sort_key) {
            'views' => [
                'meta_key' => StoryPostType::META_STORY_VIEWS,
                'orderby'  => 'meta_value_num',
                'order'    => 'DESC',
            ],
            'chapters' => [
                'meta_key' => StoryPostType::META_CHAPTER_COUNT,
                'orderby'  => 'meta_value_num',
                'order'    => 'DESC',
            ],
            default => [
                'orderby' => 'date',
                'order'   => 'DESC',
            ],
        };
    }
}