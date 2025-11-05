<?php
namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\PostType\StoryPostType;

if ( ! defined( 'ABSPATH' ) ) exit;

class StoryTaxonomyList extends Widget_Base {

    public function get_name(): string
    {
        return 'es-story-taxonomy-list';
    }

    public function get_title(): string
    {
        return esc_html__( 'Danh sách thể loại', 'extend-site' );
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
    }

    public function get_categories(): array
    {
        return [ 'es-addons' ];
    }

    public function get_keywords(): array
    {
        return [ 'story', 'taxonomy', 'list', 'extend site' ];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [ 'label' => esc_html__( 'Content', 'extend-site' ) ]
        );

        $this->add_control(
            'title',
            [
                'label'       => esc_html__( 'Addon Title', 'extend-site' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => esc_html__( 'Danh sách thể loại', 'extend-site' ),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'taxonomy',
            [
                'label'   => esc_html__( 'Select Taxonomy', 'extend-site' ),
                'type'    => Controls_Manager::SELECT,
                'default' => StoryPostType::TAX_SLUG,
                'options' => [
                    StoryPostType::TAX_SLUG => esc_html__('Danh mục truyện', 'extend-site'),
                    StoryPostType::STATUS_TAX => esc_html__('Trạng thái truyện', 'extend-site'),
                    StoryPostType::TAG_SLUG => esc_html__('Thẻ truyện (Tag)', 'extend-site'),
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

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $taxonomy = $settings['taxonomy'];
        $title    = $settings['title'];

        // get terms
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ]);

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return;
        }
    ?>
        <div class="es-addon-story-tax-list">
            <?php if ( $title ) : ?>
                <div class="heading es-flex es-flex-justify-space-between es-flex-align-center es-gap-3">
                    <h2 class="title"><?php echo esc_html( $title ); ?></h2>

                    <button type="button" class="es-btn es-btn-primary es-btn-tax-toggle">
                        <i class="es-ic-mask es-ic-mask-angle-down"></i>
                    </button>
                </div>
            <?php endif; ?>

            <div class="es-grid-layout tax-list">
                <?php foreach ( $terms as $term ) : ?>
                    <div class="item">
                        <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="es-flex es-gap-3 es-flex-align-center">
                            <i class="es-ic-mask es-ic-mask-angle-right"></i>
                            <span><?php echo esc_html( $term->name ); ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }
}