<?php
namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasSliderControls;

defined( 'ABSPATH' ) || exit;

class TestimonialSlider extends Widget_Base {
    use HasImageSizeControl;
    use HasSliderControls;

	// widget name
	public function get_name(): string {
		return 'es-testimonial-slider';
	}

	// widget title
	public function get_title(): string {
		return esc_html__( 'Slider lời chứng thực', 'extend-site' );
	}

	// widget icon
	public function get_icon(): string {
		return 'eicon-user-circle-o';
	}

	// widget categories
	public function get_categories(): array {
		return array( 'es-addons' );
	}

    // widget keywords
    public function get_keywords(): array
    {
        return ['testimonial', 'slider', 'review', 'extend site'];
    }

	// widget style dependencies
	public function get_style_depends(): array {
		return [ 'swiper' ];
	}

	// widget scripts dependencies
	public function get_script_depends(): array {
		return [ 'swiper', 'es-addons-elementor' ];
	}

	// widget controls
	protected function register_controls(): void {

		// Content testimonial
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Nội dung', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->addImageSizeControl($this);

		$repeater = new Repeater();

		$repeater->add_control(
			'list_title', [
				'label'       => esc_html__( 'Tên', 'extend-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'John Doe', 'extend-site' ),
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'list_position',
			[
				'label'       => esc_html__( 'Vị trí', 'extend-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Vị trí', 'extend-site' ),
				'label_block' => true
			]
		);

		$repeater->add_control(
			'list_image',
			[
				'label'   => esc_html__( 'Chọn ảnh', 'extend-site' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => [
					'url' => Utils::get_placeholder_image_src(),
				],
			]
		);

		$repeater->add_control(
			'list_description',
			[
				'label'       => esc_html__( 'Văn bản', 'extend-site' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 10,
				'default'     => esc_html__( 'GEMs are robotics algorithm for modules that built & optimized for NVIDIA AGX Data should underlie every business decision. Data should underlie every business Yet too often some very down the certain routes.', 'extend-site' ),
				'placeholder' => esc_html__( 'Nhập văn bản', 'extend-site' ),
			]
		);

		$this->add_control(
			'list',
			[
				'label'       => esc_html__( 'Danh sách', 'extend-site' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => [
					[
						'list_title' => esc_html__( 'Tiêu đề #1', 'extend-site' ),
					],
					[
						'list_title' => esc_html__( 'Tiêu đề #2', 'extend-site' ),
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

		// Style title
		$this->start_controls_section(
			'style_title',
			[
				'label' => esc_html__( 'Tiêu đề', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_STYLE
			]
		);

        $this->add_control(
            'title_color',
            [
                'label'     => esc_html__( 'Màu sắc', 'extend-site' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => [
                    '{{WRAPPER}} .item__content .name' => 'color: {{VALUE}};',
                ],
            ]
        );

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .item__content .name',
			]
		);

		$this->end_controls_section();

		// Style position
		$this->start_controls_section(
			'style_position',
			[
				'label' => esc_html__( 'Vị trí', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_STYLE
			]
		);

		$this->add_control(
			'position_color',
			[
				'label'     => esc_html__( 'Màu sắc', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .item__content .position' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'position_typography',
				'selector' => '{{WRAPPER}} .item__content .position',
			]
		);

		$this->end_controls_section();

		// Style desc
		$this->start_controls_section(
			'style_desc',
			[
				'label' => esc_html__( 'Văn bản', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_STYLE
			]
		);

		$this->add_control(
			'desc_color',
			[
				'label'     => esc_html__( 'Màu sắc', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .item__content .desc' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'desc_typography',
				'selector' => '{{WRAPPER}} .item__content .desc',
			]
		);

		$this->end_controls_section();
	}

	// widget output on the frontend
	protected function render(): void {
		$settings = $this->get_settings_for_display();

        // Add classes for the slider wrapper
        $classes = ['es-addon-carousel-images swiper es-custom-swiper-slider'];

        if ( $settings['equal_height'] === 'yes' ) {
            $classes[] = 'es-equal-height';
        }

        $this->add_render_attribute( 'classes', 'class', $classes );

		// set settings for swiper
		$swiperOptions = $this->generateSlideConfig( $settings );
    ?>
        <div <?php echo $this->get_render_attribute_string( 'classes' ); ?> data-settings-swiper='<?php echo esc_attr( $swiperOptions ); ?>'>
            <div class="swiper-wrapper">
                <?php
                foreach ( $settings['list'] as $item ) :
                    $imageId = $item['list_image']['id'];
                ?>
                    <div class="item swiper-slide elementor-repeater-item-<?php echo esc_attr( $item['_id'] ); ?>">
                        <div class="item__image">
                            <?php
                            if ( $imageId ) :
                                echo wp_get_attachment_image( $item['list_image']['id'], $settings['image_size'] );
                            else:
                                ?>
                                <img src="<?php echo esc_url( EXTEND_SITE_URL . 'assets/images/user-avatar.png' ); ?>"
                                     alt="<?php echo esc_attr( $item['list_title'] ); ?>"/>
                            <?php endif; ?>
                        </div>

                        <div class="item__content">
                            <div class="desc">
                                <?php echo wp_kses_post( $item['list_description'] ) ?>
                            </div>

                            <div class="name">
                                <?php echo esc_html( $item['list_title'] ); ?>
                            </div>

                            <div class="position">
                                <?php echo esc_html( $item['list_position'] ); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

	        <?php if ( $settings['navigation'] == 'both' || $settings['navigation'] == 'dots' ) : ?>
                <div class="swiper-pagination"></div>
	        <?php endif; ?>

	        <?php if ( $settings['navigation'] == 'both' || $settings['navigation'] == 'arrows' ) : ?>
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