<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BasicTheme_Recent_Post_Widget extends WP_Widget {
    /* Widget setup */
    public function __construct() {
        $widget_ops = array(
            'classname'   => 'recent-post-widget',
            'description' => esc_html__( 'Hiển thị bài viết mới nhất', 'tianshu' ),
        );

        parent::__construct(
            'recent-post-widget',
            'My Theme: Bài viết mới nhất',
            $widget_ops
        );
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    function widget( $args, $instance ): void {
        // Sử dụng wp_parse_args để thiết lập giá trị mặc định, giúp code gọn hơn
        $instance = wp_parse_args( (array) $instance, array(
            'title'    => esc_html__( 'Bài viết mới nhất', 'tianshu' ),
            'number'   => 5,
            'order'    => 'DESC',
            'order_by' => 'ID',
            'select_cat' => array( '0' ),
        ) );

        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }

        $limit   = absint( $instance['number'] );
        $cat_ids = ! empty( $instance['select_cat'] ) ? array_map('absint', $instance['select_cat']) : array( 0 );

        $post_arg = array(
            'post_type'           => 'post',
            'posts_per_page'      => $limit,
            'orderby'             => $instance['order_by'],
            'order'               => $instance['order'],
            'ignore_sticky_posts' => 1,
            'suppress_filters' => true,
        );

        if ( is_singular('post') && get_the_ID() ) {
            $post_arg['post__not_in'] = array( get_the_ID() );
        }

        if ( ! in_array( 0, $cat_ids ) ) {
            $post_arg['cat'] = implode(',', $cat_ids);
        }

        $post_query = new WP_Query( $post_arg );

        if ( $post_query->have_posts() ) :
            ?>
            <div class="post-list theme-row-cols-1 gap-2">
                <?php while ( $post_query->have_posts() ) : $post_query->the_post(); ?>
                    <div class="item">
                        <div class="image">
                            <?php
                            if ( has_post_thumbnail() ):
                                the_post_thumbnail( 'thumbnail' );
                            else:
                                ?>
                                <img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/no-image.png' ) ); ?>"
                                     width="100"
                                     height="70"
                                     alt="<?php the_title(); ?>">
                            <?php endif; ?>
                        </div>

                        <div class="content">
                            <h4 class="title">
                                <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute() ?>"> <?php the_title(); ?>
                                </a>
                            </h4>

                            <p class="meta d-flex gap-1">
                                <i class="ic-mask ic-mask-calendar-days"></i>
                                <span><?php echo get_the_date(); ?></span>
                            </p>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php
        endif;

        echo $args['after_widget'];
    }

    /**
     * Outputs the options form on admin
     *
     * @param array $instance The widget options
     */
    function form( $instance ): void {
        $defaults = array(
            'title'      => esc_html__( 'Bài viết mới nhất', 'tianshu' ),
            'number'     => 5,
            'order'      => 'DESC',
            'order_by'   => 'ID',
            'select_cat' => array( '0' ),
        );

        $instance = wp_parse_args( (array) $instance, $defaults );

        $number     = absint( $instance['number'] );
        $select_cat = array_map( 'absint', (array) $instance['select_cat'] );
        $order      = $instance['order'];
        $order_by   = $instance['order_by'];

        $terms = get_terms( array(
            'taxonomy' => 'category',
            'orderby'  => 'id'
        ) );

        ?>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Tiêu đề:', 'tianshu' ); ?>
            </label>

            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr($instance['title']); ?>"/> </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'select_cat' ) ); ?>">
                <?php esc_attr_e( 'Chọn danh mục:', 'tianshu' ); ?>
            </label>

            <select id="<?php echo esc_attr( $this->get_field_id( 'select_cat' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'select_cat' ) ) . '[]'; ?>"
                    class="widefat"
                    size="10" multiple
            >
                <option value="0" <?php echo( in_array( 0, $select_cat ) ? 'selected="selected"' : '' ); ?>>
                    <?php esc_html_e( 'Tất cả', 'tianshu' ); ?>
                </option>

                <?php
                if ( ! empty( $terms ) ) :
                    foreach ( $terms as $term_item ) :
                ?>
                    <option value="<?php echo absint( $term_item->term_id ); ?>"
                        <?php echo( in_array( $term_item->term_id, $select_cat ) ? 'selected="selected"' : '' ); ?>
                    >
                        <?php echo esc_html( $term_item->name ) . ' (' . absint( $term_item->count ) . ')'; ?>
                    </option>
                <?php
                    endforeach;
                endif;
                ?>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>">
                <?php esc_html_e( 'Sắp xếp:', 'tianshu' ); ?>
            </label>

            <select id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"
                    name="<?php echo $this->get_field_name( 'order' ) ?>"
                    class="widefat"
            >
                <option value="ASC" <?php selected( $order, 'ASC' ); ?>>
                    <?php esc_html_e( 'Tăng dần', 'tianshu' ); ?>
                </option>

                <option value="DESC" <?php selected( $order, 'DESC' ); ?>>
                    <?php esc_html_e( 'Giảm dần', 'tianshu' ); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'order_by' ) ); ?>">
                <?php esc_html_e( 'Sắp xếp theo:', 'tianshu' ); ?>
            </label>

            <select id="<?php echo esc_attr( $this->get_field_id( 'order_by' ) ); ?>"
                    name="<?php echo $this->get_field_name( 'order_by' ) ?>" class="widefat">
                <option value="ID" <?php selected( $order_by, 'ID' ); ?>>
                    <?php esc_html_e( 'ID', 'tianshu' ); ?>
                </option>

                <option value="date" <?php selected( $order_by, 'date' ); ?>>
                    <?php esc_html_e( 'Ngày', 'tianshu' ); ?>
                </option>

                <option value="title" <?php selected( $order_by, 'title' ); ?>>
                    <?php esc_html_e( 'Tiêu đề', 'tianshu' ); ?>
                </option>

                <option value="rand" <?php selected( $order_by, 'rand' ); ?>>
                    <?php esc_html_e( 'Ngẫu nhiên', 'tianshu' ); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>">
                <?php esc_html_e( 'Số lượng bài viết hiển thị:', 'tianshu' ); ?>
            </label>

            <input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" class="tiny-text"
                   name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1"
                   value="<?php echo esc_attr( $number ); ?>" size="3"/>
        </p>

        <?php

    }

    /**
     * Processing widget options on save
     *
     * @param array $new_instance The new options
     * @param array $old_instance The previous options
     *
     * @return array
     */
    function update( $new_instance, $old_instance ): array {
        $instance = $old_instance;

        $instance['title']      = sanitize_text_field( $new_instance['title'] );
        $instance['number']     = (int) $new_instance['number'];
        $instance['order']      = sanitize_key( $new_instance['order'] );
        $instance['order_by']   = sanitize_key( $new_instance['order_by'] );
        $instance['select_cat'] = array_map( 'absint', (array) $new_instance['select_cat'] );

        return $instance;
    }
}

// Register widget
add_action( 'widgets_init', function () {
    register_widget( 'BasicTheme_Recent_Post_Widget' );
} );