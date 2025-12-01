<?php

namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasQueryControls;
use ExtendSite\ElementorAddon\Traits\HasPostItemControls;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\ChapterRepository;
use ExtendSite\Views\ViewTracker;

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
            'taxonomy_status',
            [
                'label' => esc_html__('Trạng thái truyện', 'extend-site'),
                'type' => Controls_Manager::SELECT2,
                'options' => es_get_tax_list(StoryPostType::STATUS_TAX),
                'multiple' => false,
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
                    'DESC' => esc_html__('Giảm dần', 'extend-site'),
                    'ASC' => esc_html__('Tăng dần', 'extend-site'),
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

        $this->add_responsive_control(
            'thumb_height',
            [
                'label' => esc_html__( 'Chiều cao khối ảnh', 'extend-site' ),
                'type'  => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'vh', 'custom' ],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 600,
                    ],
                    '%' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                    'vh' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 280,
                ],
                'selectors' => [
                    '{{WRAPPER}} .es-addon-story-grid .image-link' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Status label
        $this->start_controls_section(
            'content_status_label',
            [
                'label' => esc_html__('Nhãn trạng thái truyện', 'extend-site'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_status_label',
            [
                'label' => esc_html__('Hiển thị nhãn trạng thái', 'extend-site'),
                'type'  => Controls_Manager::SWITCHER,
                'label_on'  => esc_html__('Bật', 'extend-site'),
                'label_off' => esc_html__('Tắt', 'extend-site'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'status_label_text',
            [
                'label' => esc_html__('Văn bản nhãn (tùy chọn)', 'extend-site'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => esc_html__('Ví dụ: Đang phát hành...', 'extend-site'),
                'label_block' => true,
                'condition' => [
                    'show_status_label' => 'yes',
                ],
            ]
        );

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
    }

    // widget output on the frontend
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $image_size = $settings['image_size'] ?? 'medium';
        $show_status_label = $settings['show_status_label'] ?? 'yes';
        $text_status_label = $settings['status_label_text'] ?? '';

        // get query
        $taxonomies = $settings['taxonomy'] ?? [];
        $taxonomy_status = $settings['taxonomy_status'] ?? [];
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

        // Lọc theo trạng thái truyện (story_status)
        if ( !empty($taxonomy_status) ) {
            $tax_query[] = [
                'taxonomy' => StoryPostType::STATUS_TAX,
                'field'    => 'term_id',
                'terms'    => (array) $taxonomy_status,
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
                <?php
                while ($query->have_posts()): $query->the_post();
                    $latest_chapter = ChapterRepository::get_latest_chapter( get_the_ID() );
                    $story_views = ViewTracker::format_short( ViewTracker::get_story_views( get_the_ID() ) );
                ?>
                    <div class="item">
                        <div class="thumbnail">
                            <?php if ( $show_status_label === 'yes' && $text_status_label ) : ?>
                                <div class="status-label">
                                    <?php echo esc_html( $text_status_label ); ?>
                                </div>
                            <?php endif; ?>

                            <a class="image-link" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                                <?php
                                if (has_post_thumbnail()) :
                                    the_post_thumbnail( $image_size );
                                else:
                                    ?>
                                    <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                         alt="<?php the_title(); ?>"/>
                                <?php endif; ?>
                            </a>

                            <div class="meta-data">
                                <div class="meta-item es-flex es-flex-align-center es-gap-2">
                                    <i class="es-ic-mask es-ic-mask-eye" aria-hidden="true"></i>
                                    <span itemprop="interactionCount"><?php echo esc_html( $story_views ); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="detail es-p-3">
                            <h4 class="title  es-fs-sm es-mb-2 es-two-line-clamp">
                                <a href="<?php the_permalink(); ?>" title="<?php the_title() ?>"><?php echo the_title() ?></a>
                            </h4>

                            <div class="detail__info es-text-sm es-text-gray-600 es-flex es-items-center es-flex-justify-space-between es-row-gap-1 es-col-gap-2 es-fs-sm">
                                <?php if ( !empty( $latest_chapter ) ): ?>
                                    <div class="story-latest-box"
                                         itemprop="hasPart"
                                         itemscope
                                         itemtype="https://schema.org/Chapter"
                                    >
                                        <a class="es-story-link"
                                           href="<?php echo esc_url( $latest_chapter['url'] ); ?>"
                                           title="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?>"
                                           aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?>"
                                           itemprop="url"
                                           rel="bookmark"
                                        >
                                    <span itemprop="name">
                                        <?php
                                        printf(
                                            esc_html__( 'Chương %d: %s', 'extend-site' ),
                                            intval( $latest_chapter['number'] ),
                                            esc_html( $latest_chapter['title'] )
                                        );
                                        ?>
                                    </span>
                                        </a>
                                        <meta itemprop="position" content="<?php echo intval( $latest_chapter['number'] ); ?>">
                                    </div>
                                <?php endif; ?>

                                <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"
                                      itemprop="dateModified">
                                    <?php echo esc_html( es_display_time_ago() ); ?>
                                </time>
                            </div>
                        </div>
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