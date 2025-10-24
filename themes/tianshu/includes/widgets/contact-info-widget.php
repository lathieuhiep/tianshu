<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BasicTheme_Contact_Info_Widget extends WP_Widget {
    public function __construct() {
        $widget_ops = array(
            'classname'   => 'contact-info-widget',
            'description' => esc_html__( 'Hiển thị thông tin liên hệ', 'tianshu' ),
        );

        parent::__construct( 'contact-info-widget', 'My Theme: Thông tin liên hệ', $widget_ops );
    }

    public function widget( $args, $instance ): void {
        $instance = wp_parse_args( (array) $instance, array(
            'title'   => esc_html__( 'Thông tin liên hệ', 'tianshu' ),
            'address' => '',
            'email'   => '',
            'phone'   => ''
        ) );

        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }
        ?>
        <div class="list">
            <?php if ( ! empty( $instance['address'] ) ) : ?>
                <div class="item">
                    <i class="ic-mask ic-mask-location-dot"></i>
                    <span class="text"><?php echo wp_kses_post( $instance['address'] ); ?></span> </div>
            <?php endif; ?>

            <?php if ( ! empty( $instance['email'] ) ) : ?>
                <div class="item">
                    <i class="ic-mask ic-mask-envelope"></i>
                    <a class="text"
                       href="mailto:<?php echo esc_attr( $instance['email'] ); ?>"><?php echo esc_html( $instance['email'] ); ?></a>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $instance['phone'] ) ) : ?>
                <div class="item">
                    <i class="ic-mask ic-mask-phone"></i>
                    <a class="text"
                       href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $instance['phone'] ) ); ?>"><?php echo esc_html( $instance['phone'] ); ?></a> </div>
            <?php endif; ?>
        </div>
        <?php
        echo $args['after_widget'];
    }

    public function form( $instance ): void {
        $defaults = [
            'title'   => esc_html__( 'Thông tin liên hệ', 'tianshu' ),
            'address' => '',
            'email'   => '',
            'phone'   => ''
        ];

        $instance = wp_parse_args( (array) $instance, $defaults );

        // Vòng lặp gọn hơn
        $fields = [
            'title'   => esc_html__( 'Tiêu đề:', 'tianshu' ),
            'address' => esc_html__( 'Địa chỉ:', 'tianshu' ),
            'email'   => esc_html__( 'Email:', 'tianshu' ),
            'phone'   => esc_html__( 'Số điện thoại:', 'tianshu' )
        ];

        foreach ( $fields as $key => $label ) {
            ?>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
                <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"
                       name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="text"
                       value="<?php echo esc_attr( $instance[ $key ] ); ?>">
            </p>
            <?php
        }
    }

    public function update( $new_instance, $old_instance ): array {
        $instance = $old_instance;

        $instance['title']   = sanitize_text_field( $new_instance['title'] );
        $instance['address'] = wp_kses_post( $new_instance['address'] );
        $instance['email']   = sanitize_email( $new_instance['email'] );
        $instance['phone']   = sanitize_text_field( $new_instance['phone'] );

        return $instance;
    }
}

add_action( 'widgets_init', function () {
    register_widget( 'BasicTheme_Contact_Info_Widget' );
} );