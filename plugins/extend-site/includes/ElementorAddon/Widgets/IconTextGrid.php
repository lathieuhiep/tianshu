<?php
namespace ExtendSite\ElementorAddon\Widgets;

use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use ExtendSite\ElementorAddon\Base\ControlOptions;

defined( 'ABSPATH' ) || exit;

class IconTextGrid extends Widget_Base {

	// widget name
	public function get_name(): string {
		return 'es-icon-text-grid';
	}

	// widget title
	public function get_title(): string {
		return esc_html__( 'Lưới Icon và Nội dung', 'extend-site' );
	}

	// widget icon
	public function get_icon(): string {
		return 'eicon-icon-box';
	}

	// widget categories
	public function get_categories(): array {
		return array( 'es-addons' );
	}

	// widget keywords
	public function get_keywords(): array
	{
		return ['icon', 'list', 'text', 'extend site'];
	}

	// widget controls
	protected function register_controls(): void {
		// layout settings
		$this->start_controls_section(
			'content_layout_section',
			[
				'label' => esc_html__( 'Bố cục', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_responsive_control(
			'columns_grid',
			[
				'label' => esc_html__( 'Cột', 'clinic' ),
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'fr' => [
						'min' => 1,
						'max' => 12,
						'step' => 1,
					],
				],
				'size_units' => [ 'fr', 'custom' ],
				'unit_selectors_dictionary' => [
					'custom' => 'grid-template-columns: {{SIZE}}',
				],
				'default' => [
					'unit' => 'fr',
					'size' => 3,
				],
				'mobile_default' => [
					'unit' => 'fr',
					'size' => 1,
				],
				'selectors' => [
					'{{WRAPPER}} .es-addon-ic-txt-grid' => 'grid-template-columns: repeat({{SIZE}}, 1fr)',
				],
				'responsive' => true,
				'editor_available' => true,
			]
		);

		$this->add_responsive_control(
			'row_gap',
			[
				'label' => esc_html__( 'Khoảng cách hàng', 'extend-site' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'default' => [
					'size' => 2.4,
					'unit' => 'rem',
				],
				'selectors' => [
					'{{WRAPPER}} .es-addon-ic-txt-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'column_gap',
			[
				'label' => esc_html__( 'Khoảng cách cột', 'extend-site' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'default' => [
					'size' => 2.4,
					'unit' => 'rem',
				],
				'selectors' => [
					'{{WRAPPER}} .es-addon-ic-txt-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// Content
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Nội dung', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'html_tag',
			[
				'label'   => esc_html__( 'HTML Tag', 'extend-site' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => ControlOptions::heading_tags(),
                'description' => esc_html__('Chọn thẻ "H" cho tiêu đề', 'extend-site')
			]
		);

		$this->add_control(
			'list',
			[
				'label' => esc_html__( 'Danh sách', 'extend-site' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => [
					[
						'name' => 'title',
						'label' => esc_html__( 'Tiêu đề', 'extend-site' ),
						'type' => Controls_Manager::TEXT,
						'default' => esc_html__( 'List Title' , 'extend-site' ),
						'label_block' => true,
					],
					[
						'name' => 'icon',
						'label' => esc_html__( 'Icon', 'extend-site' ),
						'type' => Controls_Manager::ICONS,
						'default' => [
							'value' => 'fas fa-star',
							'library' => 'fa-solid',
						],
					],
					[
						'name' => 'content',
						'label' => esc_html__( 'Văn bản', 'extend-site' ),
						'type' => Controls_Manager::WYSIWYG,
						'default' => esc_html__( 'List Content' , 'extend-site' ),
						'show_label' => false,
					],
				],
				'default' => [
					[
						'title' => esc_html__( 'Title #1', 'extend-site' ),
						'content' => esc_html__( 'Nội dung mục. Nhấn nút chỉnh sửa để thay đổi nội dung này.', 'extend-site' ),
					],
					[
						'title' => esc_html__( 'Title #2', 'extend-site' ),
						'content' => esc_html__( 'Nội dung mục. Nhấn nút chỉnh sửa để thay đổi nội dung này.', 'extend-site' ),
					],
					[
						'title' => esc_html__( 'Title #3', 'extend-site' ),
						'content' => esc_html__( 'Nội dung mục. Nhấn nút chỉnh sửa để thay đổi nội dung này.', 'extend-site' ),
					],
				],
				'title_field' => '{{{ title }}}',
			]
		);

		$this->end_controls_section();

        // style icon
		$this->start_controls_section(
			'style_icon',
			[
				'label' => esc_html__( 'Icon', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_STYLE
			]
		);

		$this->add_responsive_control(
			'icon_width',
			[
				'label' => esc_html__( 'Độ rộng', 'textdomain' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 1000,
						'step' => 5,
					],
					'%' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => 'rem',
					'size' => 3,
				],
				'selectors' => [
					'{{WRAPPER}} .icon-box' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'icon_color',
			[
				'label'     => esc_html__( 'Màu', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .icon-box' => 'color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_section();

		// Style heading
		$this->start_controls_section(
			'style_heading',
			[
				'label' => esc_html__( 'Tiêu đề', 'extend-site' ),
				'tab'   => Controls_Manager::TAB_STYLE
			]
		);

		$this->add_responsive_control(
			'heading_spacer',
			[
				'label' => esc_html__( 'Khoảng cách', 'textdomain' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 1000,
						'step' => 5,
					],
					'%' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'default' => [
					'unit' => 'rem',
					'size' => '',
				],
				'selectors' => [
					'{{WRAPPER}} .text-box .heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'heading_color',
			[
				'label'     => esc_html__( 'Màu', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .text-box .heading' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'heading_typography',
				'label'    => esc_html__( 'Kiểu chữ', 'extend-site' ),
				'selector' => '{{WRAPPER}} .text-box .heading',
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

		$this->add_control(
			'desc_color',
			[
				'label'     => esc_html__( 'Color', 'extend-site' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .text-box .content' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'desc_typography',
				'label'    => esc_html__( 'Kiểu chữ', 'extend-site' ),
				'selector' => '{{WRAPPER}} .text-box .content',
			]
		);

		$this->end_controls_section();
	}

	// widget output on the frontend
	protected function render(): void {
		$settings = $this->get_settings_for_display();

        // controls
		$tag = $settings['html_tag'];
		$list = $settings['list'];

		if ( empty( $list ) ) return;
	?>
		<div class="es-addon-ic-txt-grid es-grid-layout">
			<?php
			$classList = ['list es-flex'];

            foreach ($list as $item) :
	            $classList[] = 'elementor-repeater-item-' . esc_attr($item['_id']);

                if ( $item['animation'] ) {
	                $classList[] = 'elementor-animated ' . esc_attr($item['animation']);
                }

	            $this->add_render_attribute( 'classList', 'class', $classList );
            ?>
				<div <?php echo $this->get_render_attribute_string( 'classList' ); ?>>
					<div class="icon-box">
						<?php Icons_Manager::render_icon( $item['icon'], [ 'aria-hidden' => 'true' ] ); ?>
                    </div>

					<div class="text-box">
                        <?php
                        printf(
	                        '<%1$s class="heading">%2$s</%1$s>',
	                        esc_attr( $tag ),
	                        esc_html( $item['title'] )
                        );
                        ?>

                        <div class="content">
	                        <?php echo wpautop( $item['content'] ); ?>
                        </div>
                    </div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php
	}

	protected function content_template() {
		?>
        <#
        var titleHTMLTag = elementor.helpers.validateHTMLTag( settings.html_tag );
        #>
        <div class="es-addon-ic-txt-grid es-grid-layout">
            <# if ( settings.list.length ) { #>
                <# _.each( settings.list, function( item ) { #>
                <div class="list es-flex elementor-repeater-item-{{ item._id }}">
                    <div class="icon-box">
                        <#
                        const iconHTML = elementor.helpers.renderIcon( view, item.icon, { 'aria-hidden': true }, 'i' , 'object' );
                        #>
                        {{{ iconHTML.value }}}
                    </div>

                    <div class="text-box">
                        <{{{ titleHTMLTag }}} class="heading">{{{ item.title }}}</{{{ titleHTMLTag }}}>

                        <div class="content">
                            {{{ item.content }}}
                        </div>
                    </div>
                </div>
                <# }); #>
            <# } #>
        </div>
		<?php
	}
}