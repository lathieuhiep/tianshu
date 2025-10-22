<?php

namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasSliderControls;

defined('ABSPATH') || exit;

class CarouselImages extends Widget_Base
{
    use HasImageSizeControl;
    use HasSliderControls;

    // widget name
    public function get_name(): string
    {
        return 'es-carousel-images';
    }

    // widget title
    public function get_title(): string
    {
        return esc_html__('Ảnh trình chiếu', 'extend-site');
    }

    // widget icon
    public function get_icon(): string
    {
        return 'eicon-slider-full-screen';
    }

    // widget categories
    public function get_categories(): array
    {
        return array('es-addons');
    }

    // widget style dependencies
    public function get_style_depends(): array
    {
        return ['swiper'];
    }

    // widget scripts dependencies
    public function get_script_depends(): array
    {
        return ['swiper', 'es-addons-elementor'];
    }

    // widget keywords
    public function get_keywords(): array
    {
        return ['carousel', 'image', 'slider', 'extend site'];
    }

    // widget controls
    protected function register_controls(): void
    {

        // Section carousel images
        $this->start_controls_section(
            'section_carousel_images',
            [
                'label' => esc_html__('Trình chiếu ảnh', 'extend-site'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        // Add controls image size
        $this->addImageSizeControl($this);

        // add control repeater
        $repeater = new Repeater();

        $repeater->add_control(
            'list_title', [
                'label' => esc_html__('Tên', 'extend-site'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Tên #1', 'extend-site'),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'list_image',
            [
                'label' => esc_html__('Chọn ảnh', 'extend-site'),
                'type' => Controls_Manager::MEDIA,
                'default' => [
                    'url' => Utils::get_placeholder_image_src(),
                ],
            ]
        );

        $repeater->add_control(
            'list_link',
            [
                'label' => esc_html__('URL', 'extend-site'),
                'type' => Controls_Manager::URL,
                'placeholder' => esc_html__('https://your-link.com', 'extend-site'),
                'default' => [
                    'url' => '',
                    'is_external' => true,
                    'nofollow' => true,
                    'custom_attributes' => '',
                ],
            ]
        );

        $this->add_control(
            'list',
            [
                'label' => esc_html__('Danh sách', 'extend-site'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'list_title' => __('Tên #1', 'extend-site'),
                    ],
                    [
                        'list_title' => __('Tên #2', 'extend-site'),
                    ],
                    [
                        'list_title' => __('Tên #3', 'extend-site'),
                    ],
                    [
                        'list_title' => __('Tên #4', 'extend-site'),
                    ],
                ],
                'title_field' => '{{{ list_title }}}',
            ]
        );

        $this->end_controls_section();

        // additional options
        $this->addAdditionalOptionsSection($this, true);

        // Breakpoints options
        $this->addBreakpointsControlsGrouped($this);
    }

    // widget output on the frontend
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Add classes for the slider wrapper
        $classes = ['es-addon-carousel-images swiper es-custom-swiper-slider'];

        if ($settings['equal_height'] === 'yes') {
            $classes[] = 'es-equal-height';
        }

        $this->add_render_attribute('classes', 'class', $classes);

        // set settings for swiper
        $swiperOptions = $this->generateSlideConfig($settings);
        ?>

        <div <?php echo $this->get_render_attribute_string('classes'); ?>
                data-settings-swiper='<?php echo esc_attr($swiperOptions); ?>'>
            <div class="swiper-wrapper">
                <?php
                foreach ($settings['list'] as $index => $item) :
                    $image_id = $item['list_image']['id'];
                    $url = $item['list_link']['url'];
                    ?>

                    <div class="item swiper-slide elementor-repeater-item-<?php echo esc_attr($item['_id']); ?>">
                        <?php
                        if ($image_id) :
                            echo wp_get_attachment_image($image_id, $settings['image_size']);
                        else:
                            ?>
                            <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                 alt="<?php the_title(); ?>"/>
                        <?php
                        endif;

                        if ($url) :
                            $link_key = 'link_' . $index;
                            $this->add_link_attributes($link_key, $item['list_link']);
                            ?>

                            <a class="item__link" <?php echo $this->get_render_attribute_string($link_key); ?>></a>

                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            </div>

            <?php if ($settings['navigation'] == 'both' || $settings['navigation'] == 'dots') : ?>
                <div class="swiper-pagination"></div>
            <?php endif; ?>

            <?php if ($settings['navigation'] == 'both' || $settings['navigation'] == 'arrows') : ?>
                <div class="swiper-button-prev">
                    <i class="es-icon-mask es-icon-mask-angle-left"></i>
                </div>

                <div class="swiper-button-next">
                    <i class="es-icon-mask es-icon-mask-angle-right"></i>
                </div>
            <?php endif; ?>
        </div>

        <?php
    }
}