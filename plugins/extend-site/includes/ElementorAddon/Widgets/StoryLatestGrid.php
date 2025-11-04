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

defined('ABSPATH') || exit;

class StoryLatestGrid extends Widget_Base
{
    use HasImageSizeControl;
    use HasQueryControls;
    use HasPostItemControls;

    // widget name
    public function get_name(): string
    {
        return 'es-story-latest-grid';
    }

    // widget title
    public function get_title(): string
    {
        return esc_html__('Truyện mới nhất', 'extend-site');
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
        return ['story', 'grid', 'latest', 'extend site'];
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
            'limit',
            [
                'label' => esc_html__('Số bài lấy ra', 'extend-site'),
                'type' => Controls_Manager::NUMBER,
                'default' => 12,
                'min' => 1,
                'max' => 36,
                'step' => 1,
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
    }

    // widget output on the frontend
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        global $wpdb;

        $wpdb->timer_start();

        $story_ids = ChapterRepository::get_latest_story_ids($settings['limit']);

        $elapsed = $wpdb->timer_stop(); // trả về số giây (float)
        error_log(sprintf('[Perf] SQL query took %.2f ms', $elapsed * 1000));

        $query = new \WP_Query([
            'post_type'      => 'story',
            'post__in'       => $story_ids,
            'orderby'        => 'post__in', // giữ thứ tự theo cập nhật
            'posts_per_page' => count($story_ids),
            'no_found_rows'  => true,
        ]);

        if ( !$query->have_posts() ) {
            echo '<p>' . esc_html__('Không có truyện phù hợp với điều kiện.', 'extend-site') . '</p>';
            return;
        }

        if ($query->have_posts()) :
        ?>
            <div class="es-addon-story-grid es-grid-layout story-latest">
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