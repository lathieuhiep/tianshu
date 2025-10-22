<?php
namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Base\ControlOptions;

defined( 'ABSPATH' ) || exit;

class HeadingEditor extends Widget_Base {

	// widget name
	public function get_name(): string {
		return 'es-heading-editor';
	}

	// widget title
	public function get_title(): string {
		return esc_html__( 'Tiêu đề và văn bản', 'extend-site' );
	}

	// widget icon
	public function get_icon(): string {
		return 'eicon-pencil';
	}

	// widget categories
	public function get_categories(): array {
		return array( 'es-addons' );
	}

	// widget keywords
	public function get_keywords(): array
	{
		return ['heading', 'editor', 'text', 'extend site'];
	}

	// widget controls
	protected function register_controls(): void {
		// Content
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Nội dung', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'heading',
			[
				'label'       => esc_html__( 'Tiêu đề', 'extend-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Tiêu đề', 'extend-site' ),
				'label_block' => true
			]
		);

		$this->add_control(
			'html_tag',
			[
				'label'   => esc_html__( 'HTML Tag', 'extend-site' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => ControlOptions::heading_tags(),
			]
		);

		$this->add_control(
			'description',
			[
				'label'   => esc_html__( 'Văn bản', 'extend-site' ),
				'type'    => Controls_Manager::WYSIWYG,
				'default' => esc_html__( 'Nội dung văn bản', 'extend-site' ),
			]
		);

		$this->end_controls_section();

		// Style Heading
		$this->start_controls_section(
			'style_heading',
			[
				'label' => esc_html__( 'Tiêu đề', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_STYLE
			]
		);

		$this->add_responsive_control(
			'heading_margin',
			[
				'label' => esc_html__('Lề Ngoài', 'extend-site'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%', 'em'],
				'selectors' => [
					'{{WRAPPER}} .heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'heading_align',
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
					'{{WRAPPER}} .heading' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'heading_color',
			[
				'label'     => esc_html__( 'Màu', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .heading' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'heading_typography',
				'label'    => esc_html__( 'Kiểu chữ', 'extend-site' ),
				'selector' => '{{WRAPPER}} .heading',
			]
		);

		$this->end_controls_section();

		// Style desc
		$this->start_controls_section(
			'style_description',
			[
				'label' => esc_html__( 'Văn bản', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_STYLE
			]
		);

		$this->add_responsive_control(
			'desc_margin',
			[
				'label' => esc_html__('Lề Ngoài', 'extend-site'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%', 'em'],
				'selectors' => [
					'{{WRAPPER}} .desc' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'desc_align',
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
					'{{WRAPPER}} .desc' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'desc_color',
			[
				'label'     => esc_html__( 'Color', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .desc' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'desc_typography',
				'label'    => esc_html__( 'Kiểu chữ', 'extend-site' ),
				'selector' => '{{WRAPPER}} .desc',
			]
		);

		$this->end_controls_section();
	}

	// widget output on the frontend
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$tag = $settings['html_tag'];
    ?>
        <div class="es-addon-widget es-addon-heading-with-editor">
            <?php
            if ( $settings['heading'] ) :
                printf(
                    '<%1$s class="heading">%2$s</%1$s>',
                    esc_attr( $tag ),
                    esc_html( $settings['heading'] )
                );
            endif;
            ?>

			<?php if ( ! empty( $settings['description'] ) ) : ?>
                <div class="desc">
					<?php echo wpautop( $settings['description'] ); ?>
                </div>
			<?php endif; ?>
        </div>
    <?php
	}

	protected function content_template() {
		?>
        <#
        var tag = settings.html_tag;
        #>

        <div class="es-addon-widget es-addon-heading-with-editor">
            <{{{ tag }}} class="heading">
                {{{ settings.heading }}}
            </{{{ tag }}}>

            <# if ( '' !== settings.description ) {#>
                <div class="desc">
                    {{{ settings.description }}}
                </div>
            <# } #>
        </div>
		<?php
	}

}