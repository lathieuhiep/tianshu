<?php
namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasQueryControls;
use ExtendSite\ElementorAddon\Traits\HasPostItemControls;
use ExtendSite\ElementorAddon\Traits\HasSliderControls;

defined( 'ABSPATH' ) || exit;

class PostCarousel extends Widget_Base {
    use HasImageSizeControl;
    use HasQueryControls;
    use HasPostItemControls;
    use HasSliderControls;

	// widget name
	public function get_name(): string {
		return 'es-post-carousel';
	}

	// widget title
	public function get_title(): string {
		return esc_html__( 'Slider bài viết', 'extend-site' );
	}

	// widget icon
	public function get_icon(): string {
		return 'eicon-slider-push';
	}

	// widget categories
	public function get_categories(): array {
		return array( 'es-addons' );
	}

	// widget style dependencies
	public function get_style_depends(): array {
		return [ 'swiper' ];
	}

	// widget scripts dependencies
	public function get_script_depends(): array {
		return [ 'swiper', 'es-addons-elementor' ];
	}

	// widget keywords
	public function get_keywords(): array
	{
        return ['post', 'carousel', 'slider', 'extend site'];
	}

	// widget controls
	protected function register_controls(): void {

		// Content section
        $this->addQueryControls($this);

        // Post item
        $this->addPostItemControls($this);

        // Content layout
        $this->start_controls_section(
            'content_layout',
            [
                'label' => esc_html__( 'Thiết lập giao diện', 'extend-site' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->addImageSizeControl($this);

        $this->end_controls_section();

		// additional options
        $this->addAdditionalOptionsSection( $this, true );

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
				'label'     => esc_html__( 'Màu', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .item .title a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'title_color_hover',
			[
				'label'     => esc_html__( 'Màu khi di chuột', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .item .title a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .item .title',
			]
		);

		$this->add_responsive_control(
			'title_align',
			[
				'label'     => esc_html__( 'Căn chỉnh', 'extend-site' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left' => [
						'title' => esc_html__( 'Trái', 'extend-site' ),
						'icon'  => 'eicon-text-align-left',
					],

					'center' => [
						'title' => esc_html__( 'Giữa', 'extend-site' ),
						'icon'  => 'eicon-text-align-center',
					],

					'right' => [
						'title' => esc_html__( 'Phải', 'extend-site' ),
						'icon'  => 'eicon-text-align-right',
					],

					'justify' => [
						'title' => esc_html__( 'Căn đều hai lề', 'extend-site' ),
						'icon'  => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .item .title' => 'text-align: {{VALUE}};',
				]
			]
		);

		$this->end_controls_section();

		// Style excerpt
		$this->start_controls_section(
			'style_excerpt',
			[
				'label'     => esc_html__( 'Nôi dung tóm tắt', 'extend-site' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_excerpt' => 'show',
				],
			]
		);

		$this->add_control(
			'excerpt_color',
			[
				'label'     => esc_html__( 'Màu', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .item .desc' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'excerpt_typography',
				'selector' => '{{WRAPPER}} .item .desc',
			]
		);

		$this->add_responsive_control(
			'excerpt_align',
			[
				'label'     => esc_html__( 'Căn chỉnh', 'extend-site' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left' => [
						'title' => esc_html__( 'Trái', 'extend-site' ),
						'icon'  => 'eicon-text-align-left',
					],

					'center' => [
						'title' => esc_html__( 'Giữa', 'extend-site' ),
						'icon'  => 'eicon-text-align-center',
					],

					'right' => [
						'title' => esc_html__( 'Phải', 'extend-site' ),
						'icon'  => 'eicon-text-align-right',
					],

					'justify' => [
						'title' => esc_html__( 'Căn đều hai lề', 'extend-site' ),
						'icon'  => 'eicon-text-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}} .item .desc' => 'text-align: {{VALUE}};',
				]
			]
		);

		$this->end_controls_section();

	}

	// widget output on the frontend
	protected function render(): void {
		$settings = $this->get_settings_for_display();

        // Add classes for the slider wrapper
		$classes = ['es-addon-post-carousel swiper es-custom-swiper-slider'];

		if ( $settings['equal_height'] === 'yes' ) {
			$classes[] = 'es-equal-height';
		}

		$this->add_render_attribute( 'classes', 'class', $classes );

		// set settings for swiper
		$swiperOptions = $this->generateSlideConfig( $settings );

        // query settings
		$query = $this->buildPostQuery( $settings );

		if ( $query->have_posts() ) :
        ?>
            <div <?php echo $this->get_render_attribute_string( 'classes' ); ?> data-settings-swiper='<?php echo esc_attr( $swiperOptions ); ?>'>
                <div class="swiper-wrapper">
					<?php while ( $query->have_posts() ): $query->the_post(); ?>
                        <div class="item swiper-slide">
                            <div class="thumbnail">
                                <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
									<?php
									if ( has_post_thumbnail() ) :
										the_post_thumbnail( $settings['image_size'] );
									else:
                                    ?>
                                        <img src="<?php echo esc_url( EXTEND_SITE_URL . 'assets/images/no-image.png' ); ?>" alt="<?php the_title(); ?>"/>
									<?php endif; ?>
                                </a>
                            </div>

                            <div class="body">
                                <?php
                                printf(
                                    '<%1$s class="title"><a href="%2$s" rel="bookmark">%3$s</a></%1$s>',
                                    esc_attr( $settings['post_heading_tag'] ?? 'h3' ),
                                    esc_url( get_permalink() ),
                                    esc_html( get_the_title() )
                                );
                                ?>

                                <?php if ( $settings['post_show_category'] === 'yes' ) : ?>
                                    <div class="cats">
                                        <?php echo get_the_category_list( ', ' ); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $settings['post_show_excerpt'] === 'yes' ) : ?>
                                    <div class="desc">
                                        <p>
                                            <?php
                                            $excerpt_length = absint( $settings['post_excerpt_length'] ?? 18 );

                                            $excerpt_source = has_excerpt()
                                                ? get_the_excerpt()
                                                : get_the_content();

                                            echo esc_html(
                                                wp_trim_words( $excerpt_source, $excerpt_length, '...' )
                                            );
                                            ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php
                                if ( $settings['post_show_read_more'] === 'yes' ) :
                                    $post_label = !empty($settings['post_read_more_label'])
                                        ? $settings['post_read_more_label']
                                        : esc_html__('Read more', 'extend-site');
                                    ?>
                                    <div class="read-more">
                                        <a href="<?php echo esc_url(get_permalink()); ?>" class="read-more" rel="bookmark">
                                            <?php echo esc_html($post_label); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
					<?php endwhile; wp_reset_postdata(); ?>
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
		endif;
	}
}