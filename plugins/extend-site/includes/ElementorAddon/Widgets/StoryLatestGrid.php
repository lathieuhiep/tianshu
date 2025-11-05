<?php

namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\Ajax\LoadLatestStories;
use ExtendSite\DB\LatestChapterTable;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasQueryControls;
use ExtendSite\ElementorAddon\Traits\HasPostItemControls;

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

    // widget scripts dependencies
    public function get_script_depends(): array
    {
        return ['es-addons-elementor'];
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
        $limit = !empty($settings['limit']) ? intval($settings['limit']) : 12;
        $image_size = $settings['image_size'] ?? 'medium';

        // parse instance settings
        $config = [
            'limit' => $limit,
            'image_size' => $image_size
        ];

        // Get latest story
        $latestStories = LatestChapterTable::get_latest_stories($limit);

        if ( empty( $latestStories ) && empty( $story_ids ) ) {
            echo '<p>' . esc_html__('Không có truyện phù hợp với điều kiện.', 'extend-site') . '</p>';
            return;
        }

        $story_ids = wp_list_pluck($latestStories, 'story_id');
    ?>
        <div class="es-addon-story-grid" data-config='<?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE); ?>'>
            <div class="es-grid-layout story-latest-grid" data-story-grid>
                <?php echo LoadLatestStories::render_view($story_ids, $image_size); ?>
            </div>

            <div class="action-box es-text-center es-mt-6">
                <div class="es-loading es-flex es-flex-column es-flex-align-center es-row-gap-2 es-mb-2" hidden>
                    <span class="es-spinner"></span>
                    <span class="text-load"><?php esc_html_e('Đang tải...', 'extend-site'); ?></span>
                </div>

                <button class="es-btn es-btn-primary es-btn-load-more">
                    <?php esc_html_e('Xem thêm', 'extend-site'); ?>
                </button>
            </div>
        </div>
    <?php
    }
}