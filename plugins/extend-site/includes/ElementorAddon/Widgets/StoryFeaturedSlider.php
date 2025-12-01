<?php

namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use ExtendSite\ElementorAddon\Traits\HasImageSizeControl;
use ExtendSite\ElementorAddon\Traits\HasSliderControls;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\StoryRankingRepository;
use ExtendSite\Repositories\StoryRepository;

defined('ABSPATH') || exit;

class StoryFeaturedSlider extends Widget_Base
{
    use HasImageSizeControl;
    use HasSliderControls;

    // widget name
    public function get_name(): string
    {
        return 'es-story-featured-slider';
    }

    // widget title
    public function get_title(): string
    {
        return esc_html__('Truyện Đề Cử Hôm Nay', 'extend-site');
    }

    // widget icon
    public function get_icon(): string
    {
        return 'eicon-post-slider';
    }

    // widget categories
    public function get_categories(): array
    {
        return ['es-addons'];
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
        return ['story', 'carousel', 'slider', 'extend site'];
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
            'query_source',
            [
                'label' => esc_html__('Nguồn dữ liệu', 'extend-site'),
                'type'  => Controls_Manager::SELECT,
                'default' => 'custom',
                'label_block' => true,
                'options' => [
                    'custom'  => esc_html__('Truy vấn bình thường', 'extend-site'),
                    'top_week' => esc_html__('Top truyện xem nhiều 7 ngày qua', 'extend-site'),
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
                'default' => 18,
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

        $this->add_control(
            'excerpt_words',
            [
                'label' => esc_html__( 'Số từ mô tả', 'extend-site' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 25,
                'min' => 5,
                'max' => 100,
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

        $this->addImageSizeControl($this);

        $this->end_controls_section();

        // additional options
        $this->addAdditionalOptionsSection( $this, true );
    }

    // render widget output on the frontend
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Add classes for the slider wrapper
        $classes = ['es-addon-story es-addon-story-featured swiper es-custom-swiper-slider'];

        $this->add_render_attribute( 'classes', 'class', $classes );

        // set settings for swiper
        $swiperOptions = $this->generateSlideConfig( $settings, false , [], [
            'spaceBetween' => 20,
            'breakpoints' => [
                0 => ['slidesPerView' => 1],
                768 => ['slidesPerView' => 2]
            ]
        ] );

        // get query
        $query_source = $settings['query_source'] ?? 'custom';
        $limit   = absint($settings['limit'] ?? 18);

        if ( $query_source === 'top_week' ) {

            // Lấy danh sách story ID theo ranking tuần
            $ranked = StoryRankingRepository::top_7_days($limit);

            // Chuẩn hóa mảng ID
            $story_ids = wp_list_pluck($ranked, 'story_id');

            if (empty($story_ids)) {
                echo '<div class="es-empty">'. esc_html__('Không có dữ liệu tuần.', 'extend-site') .'</div>';

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

        // Execute query
        $query = new \WP_Query($args);

        if ( !$query->have_posts() ) {
            echo '<p>' . esc_html__('Không có truyện phù hợp với điều kiện.', 'extend-site') . '</p>';
            return;
        }
        ?>
        <div <?php echo $this->get_render_attribute_string( 'classes' ); ?> data-settings-swiper='<?php echo esc_attr( $swiperOptions ); ?>'>
            <div class="swiper-wrapper">
                <?php
                $i = 0;
                while ( $query->have_posts() ) :
                    $query->the_post();
                    $authors = StoryRepository::get_authors(get_the_ID());

                    // Lấy excerpt với giới hạn từ
                    $word_limit = (int) $settings['excerpt_words'] ?? 25;

                    $excerpt_raw = get_post_field( 'post_excerpt', get_the_ID() );
                    $excerpt     = $excerpt_raw ?: get_post_field( 'post_content', get_the_ID() );

                    $excerpt     = wp_strip_all_tags( $excerpt );
                    $excerpt     = wp_trim_words( $excerpt, $word_limit, '…' );

                    // Mở slide mới
                    if ( $i % 3 === 0 ) {
                        if ( $i > 0 ) {
                            // Nếu không phải slide đầu tiên thì đóng slide cũ trước
                            echo '</div></div>';
                        }
                        echo '<div class="item swiper-slide"><div class="es-grid-layout es-gap-3">';
                    }

                    // Item truyện
                    ?>
                    <article class="story-item es-flex es-gap-3">
                        <div class="thumbnail es-ratio-4-5">
                            <a href="<?php the_permalink(); ?>" class="es-thumb es-ratio-thumb">
                                <?php
                                if ( has_post_thumbnail() ) :
                                    the_post_thumbnail( $settings['image_size'] );
                                else :
                                ?>
                                    <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                         alt="<?php the_title(); ?>"/>
                                <?php endif; ?>
                            </a>
                        </div>

                        <div class="detail">
                            <h4 class="title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h4>

                            <div class="desc">
                                <?php echo esc_html( $excerpt ); ?>
                            </div>

                            <?php if ( $authors ) : ?>
                                <div class="meta-data-author">
                                    <?php
                                    foreach ($authors as $a) {
                                        printf(
                                            '<a href="%s" class="es-author es-flex es-flex-align-center es-gap-2"><i class="es-ic-mask es-ic-mask-user"></i><span>%s</span></a>',
                                            esc_url($a['url']),
                                            esc_html($a['name'])
                                        );
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php

                    $i++;
                endwhile;

                // Đóng slide cuối cùng
                if ( $i > 0 ) {
                    echo '</div></div>';
                }

                wp_reset_postdata();
                ?>
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