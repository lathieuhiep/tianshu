<?php

namespace ExtendSite\ElementorAddon\Traits;

use Elementor\Controls_Manager;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * Trait: thêm nhóm control "Thiết lập bài viết" và build WP_Query cho widget Elementor.
 * Cách dùng (Cách 2): $this->addQueryControls($this, ...);
 */
trait HasQueryControls
{
    /**
     * Các tuỳ chọn control mặc định.
     */
    protected function defaultQueryOptions(): array
    {
        return [
            'taxonomy' => true,
            'limit' => true,
            'order_by' => true,
            'order' => true,
        ];
    }

    /**
     * Tắt/bật từng option qua danh sách exclude.
     */
    protected function filterQueryOptions(array $exclude_keys = []): array
    {
        $options = $this->defaultQueryOptions();
        foreach ($exclude_keys as $key) {
            if (isset($options[$key])) {
                $options[$key] = false;
            }
        }
        return $options;
    }

    /**
     * Thêm controls lựa chọn query cho widget.
     * - $widget: instance Widget_Base (truyền $this)
     * - $section_id: id section control (mặc định content_section)
     * - $limit: số bài mặc định
     * - $taxonomy: taxonomy để lọc (vd: category)
     * - $exclude_options: tắt bớt option ['excerpt','order_by',...]
     * - $after_controls: callback($widget) để chèn control bổ sung
     */
    protected function addQueryControls(
        $widget,
        ?string $section_id = null,
        int $limit = 6,
        string $taxonomy = 'category',
        array $exclude_options = [],
        ?callable $after_controls = null
    ): void
    {
        $options = $this->filterQueryOptions($exclude_options);
        $section_id = $section_id ?: 'content_section';

        $widget->start_controls_section(
            $section_id,
            [
                'label' => esc_html__('Thiết lập truy vấn', 'extend-site'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        // Taxonomy select (nếu tồn tại)
        if ($options['taxonomy'] && taxonomy_exists($taxonomy)) {
            $widget->add_control(
                'taxonomy',
                [
                    'label' => esc_html__('Chọn danh mục', 'extend-site'),
                    'type' => Controls_Manager::SELECT2,
                    'options' => es_get_tax_list($taxonomy),
                    'multiple' => true,
                    'label_block' => true,
                ]
            );
        }

        // Limit
        if ($options['limit']) {
            $widget->add_control(
                'limit',
                [
                    'label' => esc_html__('Số bài lấy ra', 'extend-site'),
                    'type' => Controls_Manager::NUMBER,
                    'default' => $limit,
                    'min' => 1,
                    'max' => 100,
                    'step' => 1,
                ]
            );
        }

        // Order by
        if ($options['order_by']) {
            $widget->add_control(
                'order_by',
                [
                    'label' => esc_html__('Sắp xếp theo', 'extend-site'),
                    'type' => Controls_Manager::SELECT,
                    'default' => 'ID',
                    'options' => [
                        'ID' => esc_html__('ID', 'extend-site'),
                        'title' => esc_html__('Tiêu đề', 'extend-site'),
                        'date' => esc_html__('Ngày đăng', 'extend-site'),
                    ],
                ]
            );
        }

        // Order
        if ($options['order']) {
            $widget->add_control(
                'order',
                [
                    'label' => esc_html__('Sắp xếp', 'extend-site'),
                    'type' => Controls_Manager::SELECT,
                    'default' => 'DESC',
                    'options' => [
                        'ASC' => esc_html__('Tăng dần', 'extend-site'),
                        'DESC' => esc_html__('Giảm dần', 'extend-site'),
                    ],
                ]
            );
        }

        if (is_callable($after_controls)) {
            call_user_func($after_controls, $widget);
        }

        $widget->end_controls_section();
    }

    /**
     * Build WP_Query từ $settings của widget.
     * - $post_type: post, page, hoặc CPT
     * - $taxonomy: taxonomy để lọc
     * - $custom_args: merge thêm args tuỳ biến
     * - $exclude_options: đồng bộ với addQueryControls()
     */
    protected function buildPostQuery(
        array  $settings,
        string $post_type = 'post',
        string $taxonomy = 'category',
        array  $custom_args = [],
        array  $exclude_options = []
    ): WP_Query
    {
        $options = $this->filterQueryOptions($exclude_options);

        $paged = 1;
        if (get_query_var('paged')) {
            $paged = (int) get_query_var('paged');
        } elseif (get_query_var('page')) {
            $paged = (int) get_query_var('page');
        }

        $args = [
            'post_type' => $post_type,
            'ignore_sticky_posts' => true,
            'paged' => $paged,
            'no_found_rows' => true, // tối ưu query khi không cần phân trang
            'update_post_meta_cache' => false, // tối ưu meta query
            'update_post_term_cache' => false, // tối ưu term query
        ];

        if ($options['limit'] && isset($settings['limit'])) {
            $args['posts_per_page'] = (int)$settings['limit'];
        }

        if ($options['order_by'] && !empty($settings['order_by'])) {
            $args['orderby'] = $settings['order_by'];
        }

        if ($options['order'] && !empty($settings['order'])) {
            $args['order'] = $settings['order'];
        }

        // Tax query
        $selected_terms = $settings['taxonomy'] ?? [];
        if ($options['taxonomy'] && !empty($selected_terms) && taxonomy_exists($taxonomy)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $selected_terms,
                ],
            ];
        }

        // Merge custom
        if (!empty($custom_args)) {
            $args = array_merge($args, $custom_args);
        }

        return new WP_Query($args);
    }
}