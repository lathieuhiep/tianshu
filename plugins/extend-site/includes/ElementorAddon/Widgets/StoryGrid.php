<?php

namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasQueryControls;
use ExtendSite\ElementorAddon\Traits\HasPostItemControls;
use ExtendSite\PostType\StoryPostType;

defined('ABSPATH') || exit;

class StoryGrid extends Widget_Base
{
    use HasImageSizeControl;
    use HasQueryControls;
    use HasPostItemControls;

    // widget name
    public function get_name(): string
    {
        return 'es-story-grid';
    }

    // widget title
    public function get_title(): string
    {
        return esc_html__('Truyện dạng lưới', 'extend-site');
    }

    // widget icon
    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    // widget categories
    public function get_categories(): array
    {
        return array('es-addons');
    }

    // widget keywords
    public function get_keywords(): array
    {
        return ['story', 'grid', 'extend site'];
    }

    // widget controls
    protected function register_controls(): void
    {
        // Query controls
        $this->start_controls_section(
            'content_query',
            [
                'label' => esc_html__('Thiết lập truy vấn', 'extend-site'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'taxonomy',
            [
                'label' => esc_html__('Chọn danh mục', 'extend-site'),
                'type' => Controls_Manager::SELECT2,
                'options' => es_get_tax_list(StoryPostType::TAX_SLUG),
                'multiple' => true,
                'label_block' => true,
            ]
        );

        $this->add_control(
            'tag',
            [
                'label' => esc_html__('Chọn tag truyện', 'extend-site'),
                'type' => Controls_Manager::SELECT2,
                'options' => es_get_story_tags(),
                'multiple' => false,
                'label_block' => true,
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => esc_html__('Số bài lấy ra', 'extend-site'),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 36,
                'step' => 1,
            ]
        );

        $this->add_control(
            'order_by',
            [
                'label' => esc_html__('Sắp xếp theo', 'extend-site'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ID',
                'options' => [
                    'ID' => esc_html__('ID', 'extend-site'),
                    'title' => esc_html__('Tiêu đề', 'extend-site'),
                    'date' => esc_html__('Ngày đăng', 'extend-site'),
                    'views' => esc_html__('Lượt xem', 'extend-site'),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => esc_html__('Sắp xếp', 'extend-site'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'DESC' => esc_html__('Tăng dần', 'extend-site'),
                    'ASC' => esc_html__('Giảm dần', 'extend-site'),
                ],
            ]
        );

        $this->end_controls_section();

        // Content layout
        $this->start_controls_section(
            'content_layout',
            [
                'label' => esc_html__('Thiết lập giao diện', 'extend-site'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'column_number',
            [
                'label' => esc_html__('Số cột', 'extend-site'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 100,
                'step' => 1,
                'default' => 3,
                'selectors' => [
                    '{{WRAPPER}} .es-grid-layout' => 'grid-template-columns: repeat({{VALUE}}, 1fr)',
                ],
            ]
        );

        $this->add_responsive_control(
            'column_gap',
            [
                'label' => esc_html__('Khoảng cách cột', 'extend-site'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'default' => [
                    'size' => 2.4,
                    'unit' => 'rem',
                ],
                'selectors' => [
                    '{{WRAPPER}} .es-grid-layout' => 'column-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'row_gap',
            [
                'label' => esc_html__('Khoảng cách hàng', 'extend-site'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'default' => [
                    'size' => 2.4,
                    'unit' => 'rem',
                ],
                'selectors' => [
                    '{{WRAPPER}} .es-grid-layout' => 'row-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->addImageSizeControl($this);

        $this->addImageRatioControl($this);

        $this->end_controls_section();

        // Style title
        $this->start_controls_section(
            'style_title',
            [
                'label' => esc_html__('Tiêu đề', 'extend-site'),
                'tab' => Controls_Manager::TAB_STYLE
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => esc_html__('Màu', 'extend-site'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .item .title a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_color_hover',
            [
                'label' => esc_html__('Màu thay đổi', 'extend-site'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .item .title a:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .item .title',
            ]
        );

        $this->add_responsive_control(
            'title_align',
            [
                'label' => esc_html__('Căn chỉnh', 'extend-site'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Trái', 'extend-site'),
                        'icon' => 'eicon-text-align-left',
                    ],

                    'center' => [
                        'title' => esc_html__('Giữa', 'extend-site'),
                        'icon' => 'eicon-text-align-center',
                    ],

                    'right' => [
                        'title' => esc_html__('Phải', 'extend-site'),
                        'icon' => 'eicon-text-align-right',
                    ],

                    'justify' => [
                        'title' => esc_html__('Căn đều hai lề', 'extend-site'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .item .title' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style excerpt
        $this->start_controls_section(
            'style_excerpt',
            [
                'label' => esc_html__('Nôi dung tóm tắt', 'extend-site'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_excerpt' => 'show',
                ],
            ]
        );

        $this->add_control(
            'excerpt_color',
            [
                'label' => esc_html__('Màu', 'extend-site'),
                'type' => Controls_Manager::COLOR,
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .item .content' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'excerpt_typography',
                'selector' => '{{WRAPPER}} .item .content',
            ]
        );

        $this->add_responsive_control(
            'excerpt_align',
            [
                'label' => esc_html__('Căn chỉnh', 'extend-site'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Trái', 'extend-site'),
                        'icon' => 'eicon-text-align-left',
                    ],

                    'center' => [
                        'title' => esc_html__('Giữa', 'extend-site'),
                        'icon' => 'eicon-text-align-center',
                    ],

                    'right' => [
                        'title' => esc_html__('Phải', 'extend-site'),
                        'icon' => 'eicon-text-align-right',
                    ],

                    'justify' => [
                        'title' => esc_html__('Căn đều hai lề', 'extend-site'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .item .content' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

    }

    // widget output on the frontend
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // get query
        $taxonomies = $settings['taxonomy'] ?? [];
        $tag = $settings['tag'] ?? [];
        $limit   = absint($settings['limit'] ?? 18);
        $order_by = $settings['order_by'] ?? 'ID';
        $order   = $settings['order'] ?? 'DESC';

        // Lọc theo danh mục truyện (story_genre)
        $tax_query = [];

        if (!empty($taxonomies)) {
            $tax_query[] = [
                'taxonomy' => StoryPostType::TAX_SLUG,
                'field'    => 'term_id',
                'terms'    => (array) $taxonomies,
                'operator' => 'IN',
            ];
        }

        // Lọc theo tag (story_tag)
        if (!empty($tag)) {
            $tax_query[] = [
                'taxonomy' => StoryPostType::TAG_SLUG,
                'field'    => 'term_id',
                'terms'    => (array) $tag,
                'operator' => 'IN',
            ];
        }

        // Query stories
        $args = [
            'post_type'      => StoryPostType::SLUG,
            'posts_per_page' => $limit,
            'order'          => $order,
            'no_found_rows'  => true,
        ];

        // Nếu sắp xếp theo view
        if ($order_by === 'views') {
            $args['meta_key']   = StoryPostType::META_STORY_VIEWS;
            $args['meta_type']  = 'NUMERIC';
            $args['orderby']    = 'meta_value_num';
        } else {
            $args['orderby'] = $order_by;
        }

        // Chỉ thêm tax_query khi có ít nhất 1 điều kiện
        if (!empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query = array_merge(['relation' => 'AND'], $tax_query);
            }

            $args['tax_query'] = $tax_query;
        }

        $query = new \WP_Query($args);

        if ( !$query->have_posts() ) {
            echo '<p>' . esc_html__('Không có truyện phù hợp với điều kiện.', 'extend-site') . '</p>';
            return;
        }

        if ($query->have_posts()) :
            ?>
            <div class="es-addon-story-grid es-grid-layout">
                <?php while ($query->have_posts()): $query->the_post(); ?>
                    <div class="item">
                        <div class="thumbnail es-ratio-4-5">
                            <a class="es-ratio-thumb" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                                <?php
                                if (has_post_thumbnail()) :
                                    the_post_thumbnail($settings['image_size']);
                                else:
                                    ?>
                                    <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                         alt="<?php the_title(); ?>"/>
                                <?php endif; ?>
                            </a>
                        </div>

                        <h4 class="title">
                            <a href="<?php the_permalink(); ?>" title="<?php the_title() ?>"><?php echo the_title() ?></a>
                        </h4>
                    </div>
                <?php
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php
        endif;
    }
}