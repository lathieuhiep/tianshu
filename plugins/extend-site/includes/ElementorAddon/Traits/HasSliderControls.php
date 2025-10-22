<?php
namespace ExtendSite\ElementorAddon\Traits;

use Elementor\Controls_Manager;

defined('ABSPATH') || exit;

/**
 * Trait cung cấp các control & cấu hình chung cho Slider/Carousel widget.
 */
trait HasSliderControls
{
    /**
     * Thêm section "Tùy chọn bổ sung" cho widget.
     */
    protected function addAdditionalOptionsSection($widget, bool $include_equal_height = false): void
    {
        $widget->start_controls_section(
            'content_additional_options',
            [
                'label' => esc_html__('Tùy chọn bổ sung', 'extend-site'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        if ($include_equal_height) {
            $widget->add_control(
                'equal_height',
                [
                    'label'        => esc_html__('Đồng bộ chiều cao slide', 'extend-site'),
                    'type'         => Controls_Manager::SWITCHER,
                    'label_on'     => esc_html__('Có', 'extend-site'),
                    'label_off'    => esc_html__('Không', 'extend-site'),
                    'return_value' => 'yes',
                    'default'      => '',
                ]
            );
        }

        $widget->add_control(
            'loop',
            [
                'type'         => Controls_Manager::SWITCHER,
                'label'        => esc_html__('Vòng lặp', 'extend-site'),
                'label_on'     => esc_html__('Có', 'extend-site'),
                'label_off'    => esc_html__('Không', 'extend-site'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $widget->add_control(
            'autoplay',
            [
                'label'        => esc_html__('Tự động chạy', 'extend-site'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Có', 'extend-site'),
                'label_off'    => esc_html__('Không', 'extend-site'),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $widget->add_control(
            'speed',
            [
                'label'   => esc_html__('Tốc độ trượt (ms)', 'extend-site'),
                'type'    => Controls_Manager::NUMBER,
                'default' => 800,
                'min'     => 100,
                'max'     => 5000,
                'step'    => 50,
            ]
        );

        $widget->add_control(
            'navigation',
            [
                'label'   => esc_html__('Thanh điều hướng', 'extend-site'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'arrows',
                'options' => [
                    'both'   => esc_html__('Mũi tên và Dấu chấm', 'extend-site'),
                    'arrows' => esc_html__('Mũi tên', 'extend-site'),
                    'dots'   => esc_html__('Dấu chấm', 'extend-site'),
                    'none'   => esc_html__('Không', 'extend-site'),
                ],
            ]
        );

        $widget->end_controls_section();
    }

    /**
     * Breakpoints mặc định.
     */
    protected function getBreakpoints(): array
    {
        return [
            ['prefix'=>'desktop_large','label'=>esc_html__('Từ 1200px','extend-site'),'width'=>1200,'items'=>4,'space'=>24],
            ['prefix'=>'desktop_small','label'=>esc_html__('Từ 992px','extend-site'),'width'=>992,'items'=>3,'space'=>20],
            ['prefix'=>'tablet_large','label'=>esc_html__('Từ 768px','extend-site'),'width'=>768,'items'=>3,'space'=>16],
            ['prefix'=>'tablet_small','label'=>esc_html__('Từ 576px','extend-site'),'width'=>576,'items'=>2,'space'=>12],
            ['prefix'=>'mobile_large','label'=>esc_html__('Từ 480px','extend-site'),'width'=>480,'items'=>2,'space'=>8],
            ['prefix'=>'mobile','label'=>esc_html__('Dưới 480px','extend-site'),'width'=>0,'items'=>1,'space'=>4],
        ];
    }

    /**
     * Thêm group control responsive breakpoints.
     */
    protected function addBreakpointsControlsGrouped($widget): void
    {
        $widget->start_controls_section(
            'slider_breakpoints',
            [
                'label' => esc_html__('Tùy chọn Responsive', 'extend-site'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        foreach ($this->getBreakpoints() as $bp) {
            $prefix = $bp['prefix'];

            $widget->add_control(
                "{$prefix}_options",
                [
                    'label'     => $bp['label'],
                    'type'      => Controls_Manager::HEADING,
                    'separator' => 'before',
                ]
            );

            $widget->add_control(
                "{$prefix}_items",
                [
                    'label'   => esc_html__('Hiển thị', 'extend-site'),
                    'type'    => Controls_Manager::NUMBER,
                    'default' => $bp['items'],
                    'min'     => 1,
                    'max'     => 100,
                    'step'    => 1,
                ]
            );

            $widget->add_control(
                "{$prefix}_spaces_between",
                [
                    'label'   => esc_html__('Khoảng cách', 'extend-site'),
                    'type'    => Controls_Manager::NUMBER,
                    'default' => $bp['space'],
                    'min'     => 0,
                    'max'     => 100,
                    'step'    => 1,
                ]
            );
        }

        $widget->end_controls_section();
    }

    /**
     * Sinh mảng breakpoints config.
     */
    protected function generateSlideBreakpoints(array $settings, ?array $breakpoints = null): array
    {
        $bps = $breakpoints ?: $this->getBreakpoints();
        usort($bps, fn($a, $b) => $a['width'] <=> $b['width']);

        $result = [];
        foreach ($bps as $bp) {
            $prefix = $bp['prefix'];
            $width  = $bp['width'];

            $result[$width] = [
                'slidesPerView' => intval($settings["{$prefix}_items"] ?? $bp['items']),
                'spaceBetween'  => intval($settings["{$prefix}_spaces_between"] ?? $bp['space']),
            ];
        }
        return $result;
    }

    /**
     * Sinh JSON config cho Swiper/Slider.
     */
    protected function generateSlideConfig(array $settings, bool $use_breakpoints = true, array $custom_breakpoints = [], array $extra = []): string
    {
        $config = [
            'loop'       => ('yes' === ($settings['loop'] ?? '')),
            'autoplay'   => ('yes' === ($settings['autoplay'] ?? '')),
            'speed'      => intval($settings['speed'] ?? 800),
            'navigation' => (in_array($settings['navigation'] ?? '', ['both','arrows'], true)),
            'pagination' => (in_array($settings['navigation'] ?? '', ['both','dots'], true)),
        ];

        if (isset($settings['equal_height'])) {
            $config['equalHeight'] = ('yes' === $settings['equal_height']);
        }

        if ($use_breakpoints) {
            $config['breakpoints'] = $this->generateSlideBreakpoints($settings, $custom_breakpoints);
        }

        if (!empty($extra)) {
            $config = array_merge($config, $extra);
        }

        return wp_json_encode($config);
    }
}
