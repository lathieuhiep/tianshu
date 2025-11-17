<?php
namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasQueryControls;
use ExtendSite\ElementorAddon\Traits\HasPostItemControls;
use ExtendSite\ElementorAddon\Traits\HasSliderControls;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\StoryRankingRepository;
use ExtendSite\Views\ViewTracker;

defined( 'ABSPATH' ) || exit;

class StoryCarousel extends Widget_Base {
    use HasImageSizeControl;
    use HasQueryControls;
    use HasPostItemControls;
    use HasSliderControls;

	// widget name
	public function get_name(): string {
		return 'es-story-carousel';
	}

	// widget title
	public function get_title(): string {
		return esc_html__( 'Danh sách truyện slider', 'extend-site' );
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
        return ['story', 'carousel', 'slider', 'extend site'];
	}

	// widget controls
	protected function register_controls(): void {

        // Query controls
        $this->start_controls_section(
            'content_query',
            [
                'label' => esc_html__('Thiết lập truy vấn', 'extend-site'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'query_source',
            [
                'label' => esc_html__('Nguồn dữ liệu', 'extend-site'),
                'type'  => Controls_Manager::SELECT,
                'default' => 'custom',
                'label_block' => true,
                'options' => [
                    'custom'  => esc_html__('Truy vấn bình thường', 'extend-site'),
                    'top_month' => esc_html__('Top truyện xem nhiều 30 ngày qua', 'extend-site'),
                ],
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
                'condition' => [
                    'query_source' => 'custom',
                ],
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
                'condition' => [
                    'query_source' => 'custom',
                ],
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
                'condition' => [
                    'query_source' => 'custom',
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
                'condition' => [
                    'query_source' => 'custom',
                ],
            ]
        );

        $this->end_controls_section();

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
	}

	// widget output on the frontend
	protected function render(): void {
		$settings = $this->get_settings_for_display();

        // Add classes for the slider wrapper
		$classes = ['es-addon-story es-addon-story-carousel swiper es-custom-swiper-slider'];

		if ( $settings['equal_height'] === 'yes' ) {
			$classes[] = 'es-equal-height';
		}

		$this->add_render_attribute( 'classes', 'class', $classes );

		// set settings for swiper
		$swiperOptions = $this->generateSlideConfig( $settings );

        // get query
        $query_source = $settings['query_source'] ?? 'custom';
        $limit   = absint($settings['limit'] ?? 18);

        if ( $query_source === 'top_month' ) {
            $ranked = StoryRankingRepository::top_30_days($limit);

            // Chuẩn hóa mảng ID
            $story_ids = wp_list_pluck($ranked, 'story_id');

            if (empty($story_ids)) {
                echo '<div class="es-empty">'. esc_html__('Không có dữ liệu tháng.', 'extend-site') .'</div>';

                return;
            }

            // Query theo danh sách ID (giữ đúng thứ tự ranking)
            $args = [
                'post_type'      => StoryPostType::SLUG,
                'post__in'       => $story_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count($story_ids),
                'no_found_rows'  => true,
            ];

        } else {
            $taxonomies = $settings['taxonomy'] ?? [];
            $tag = $settings['tag'] ?? [];
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
        }

        $query = new \WP_Query($args);

        if ( !$query->have_posts() ) {
            echo '<p>' . esc_html__('Không có truyện phù hợp với điều kiện.', 'extend-site') . '</p>';
            return;
        }
    ?>
        <div <?php echo $this->get_render_attribute_string( 'classes' ); ?> data-settings-swiper='<?php echo esc_attr( $swiperOptions ); ?>'>
            <div class="swiper-wrapper">
                <?php
                while ( $query->have_posts() ): $query->the_post();

                    $story_views = ViewTracker::format_full( ViewTracker::get_story_views( get_the_ID() ) );
                ?>
                    <div class="item swiper-slide">
                        <div class="thumbnail es-ratio-4-5">
                            <a class="es-thumb es-ratio-thumb" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                                <?php
                                if ( has_post_thumbnail() ) :
                                    the_post_thumbnail( $settings['image_size'] );
                                else:
                                ?>
                                    <img src="<?php echo esc_url( EXTEND_SITE_URL . 'assets/images/no-image.png' ); ?>" alt="<?php the_title(); ?>"/>
                                <?php endif; ?>
                            </a>

                            <div class="meta-data">
                                <div class="meta-item es-flex es-flex-align-center es-gap-2">
                                    <i class="es-ic-mask es-ic-mask-eye" aria-hidden="true"></i>
                                    <span itemprop="interactionCount"><?php echo esc_html( $story_views ); ?></span>
                                </div>
                            </div>
                        </div>

                        <h4 class="title">
                            <a href="<?php the_permalink(); ?>" title="<?php the_title() ?>"><?php echo the_title() ?></a>
                        </h4>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <?php if ( $settings['navigation'] == 'both' || $settings['navigation'] == 'dots' ) : ?>
                <div class="swiper-pagination"></div>
            <?php endif; ?>

            <?php if ( $settings['navigation'] == 'both' || $settings['navigation'] == 'arrows' ) : ?>
                <div class="swiper-button-prev">
                    <i class="es-ic-mask es-ic-mask-angle-left"></i>
                </div>

                <div class="swiper-button-next">
                    <i class="es-ic-mask es-ic-mask-angle-right"></i>
                </div>
            <?php endif; ?>
        </div>
    <?php
	}
}