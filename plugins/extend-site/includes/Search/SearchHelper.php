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

    /**
     * Get all active filters for current search.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function get_active_filters(): array {
        $filters = [];

        // Helper nội bộ: tạo URL khi bỏ 1 filter
        $remove_arg = function ($key, $value = null): string {
            $args = $_GET;
            if (!isset($args[$key])) {
                return add_query_arg([], home_url('/' . SearchController::SLUG . '/'));
            }
            if ($value === null) {
                unset($args[$key]);
            } else {
                if (is_array($args[$key])) {
                    $args[$key] = array_diff($args[$key], [$value]);
                    if (empty($args[$key])) unset($args[$key]);
                } elseif ((string)$args[$key] === (string)$value) {
                    unset($args[$key]);
                }
            }
            return add_query_arg($args, home_url('/' . SearchController::SLUG . '/'));
        };

        // --- Keyword ---
        if (!empty($_GET['q'])) {
            $filters[] = [
                'label' => sprintf(
                    '%s <span class="es-filter-value">“%s”</span>',
                    esc_html__('Từ khóa:', 'extend-site'),
                    esc_html($_GET['q'])
                ),
                'url' => $remove_arg('q'),
            ];
        }

        // --- Genres ---
        if (!empty($_GET['genre']) && is_array($_GET['genre'])) {
            $genres = get_terms([
                'taxonomy'   => StoryPostType::TAX_SLUG,
                'include'    => array_map('intval', $_GET['genre']),
                'hide_empty' => false,
            ]);
            if ($genres && !is_wp_error($genres)) {
                foreach ($genres as $term) {
                    $filters[] = [
                        'label' => sprintf(
                            '%s <span class="es-filter-value">%s</span>',
                            esc_html__('Thể loại:', 'extend-site'),
                            esc_html($term->name)
                        ),
                        'url' => $remove_arg('genre', (string)$term->term_id),
                    ];
                }
            }
        }

        // --- Status ---
        if (!empty($_GET['status'])) {
            $term = get_term((int) $_GET['status'], StoryPostType::STATUS_TAX);
            if ($term && !is_wp_error($term)) {
                $filters[] = [
                    'label' => sprintf(
                        '%s <span class="es-filter-value">%s</span>',
                        esc_html__('Tình trạng:', 'extend-site'),
                        esc_html($term->name)
                    ),
                    'url' => $remove_arg('status'),
                ];
            }
        }

        // --- Chapters ---
        if (!empty($_GET['chapters'])) {
            $ranges = self::get_chapter_ranges();
            $label  = $ranges[$_GET['chapters']] ?? '';
            if ($label) {
                $filters[] = [
                    'label' => sprintf(
                        '%s <span class="es-filter-value">%s</span>',
                        esc_html__('Số chương:', 'extend-site'),
                        esc_html($label)
                    ),
                    'url' => $remove_arg('chapters'),
                ];
            }
        }

        // --- Sort ---
        $sort_key = $_GET['sort'] ?? 'latest';
        $sorts    = self::get_sort_options();
        if (!empty($_GET['sort']) && $sort_key !== 'latest') {
            $label = $sorts[$sort_key] ?? '';
            if ($label) {
                $filters[] = [
                    'label' => sprintf(
                        '%s <span class="es-filter-value">%s</span>',
                        esc_html__('Sắp xếp:', 'extend-site'),
                        esc_html($label)
                    ),
                    'url' => $remove_arg('sort'),
                ];
            }
        }

        return $filters;
    }
}