<?php
/**
 * Theme Functions
 *
 * @package tianshu
 */

defined( 'ABSPATH' ) || exit;

// get version theme
function tianshu_get_version_theme(): string {
	return wp_get_theme()->get( 'Version' );
}

// check is blog
function tianshu_is_blog(): bool {
    return ( is_home() || ( is_archive() && get_post_type() === 'post' ) || is_search() );
}

// Callback Comment List
function tianshu_comment_item( $c, $args, $depth ): void {
    $GLOBALS['comment'] = $c;
?>
    <li <?php comment_class( empty($args['has_children']) ? '' : 'parent' ); ?> id="comment-<?php comment_ID(); ?>">
        <div id="div-comment-<?php comment_ID(); ?>" class="comment__body">
            <div class="d-flex gap-4">
                <div class="avatar">
                    <?php
                    if ( ! empty( $args['avatar_size'] ) ) :
                        echo get_avatar( $c, (int) $args['avatar_size'] );
                    endif;
                    ?>
                </div>

                <div class="content flex-fill d-flex gap-1 flex-column">
                    <div class="author-info">
                        <span class="name"><?php comment_author_link(); ?></span>

                        <a class="date" href="<?php echo esc_url( get_comment_link( $c ) ); ?>">
                            <?php echo esc_html( get_comment_date() . ' ' . get_comment_time() ); ?>
                        </a>
                    </div>

                    <?php if ( '0' === $c->comment_approved ) : ?>
                        <div class="awaiting">
                            <?php esc_html_e( 'Bình luận của bạn đang chờ kiểm duyệt.', 'tianshu' ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="comment">
                        <?php comment_text(); ?>
                    </div>

                    <div class="action">
                        <?php
                        edit_comment_link( esc_html__( 'Sửa', 'tianshu' ), '<span class="edit-link">', '</span>' );

                        comment_reply_link( array_merge( $args, [
                            'add_below' => 'div-comment',
                            'depth'     => $depth,
                            'max_depth' => $args['max_depth'] ?? 3,
                            'reply_text'=> esc_html__( 'Trả lời', 'tianshu' ),
                        ] ) );
                        ?>
                    </div>
                </div>
            </div>
        </div>
<?php
}

// Content Nav
function tianshu_comment_nav(): void {
	if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
		?>
        <nav class="navigation comment-navigation">
            <h2 class="screen-reader-text">
				<?php esc_html_e( 'Điều hướng bình luận', 'tianshu' ); ?>
            </h2>

            <div class="nav-links">
				<?php
				if ( $prev_link = get_previous_comments_link( esc_html__( 'Bình luận cũ hơn', 'tianshu' ) ) ) :
					printf( '<div class="nav-previous">%s</div>', $prev_link );
				endif;

				if ( $next_link = get_next_comments_link( esc_html__( 'Bình luận mới hơn', 'tianshu' ) ) ) :
					printf( '<div class="nav-next">%s</div>', $next_link );
				endif;
				?>
            </div>
        </nav>
	<?php
	endif;
}

// Pagination
function tianshu_pagination(): void {
	the_posts_pagination( array(
		'type'               => 'list',
		'mid_size'           => 2,
		'prev_text'          => esc_html__( 'Trước', 'tianshu' ),
		'next_text'          => esc_html__( 'Sau', 'tianshu' ),
		'screen_reader_text' => '&nbsp;',
	) );
}

// Pagination Nav Query
function tianshu_paging_nav_query( $query ): void {
	$args = array(
		'prev_text' => esc_html__( ' Trước', 'tianshu' ),
		'next_text' => esc_html__( 'Sau', 'tianshu' ),
		'current'   => max( 1, get_query_var( 'paged' ) ),
		'total'     => $query->max_num_pages,
		'type'      => 'list',
	);

	$paginate_links = paginate_links( $args );

	if ( $paginate_links ) :
		?>
        <nav class="pagination">
			<?php echo $paginate_links; ?>
        </nav>
	<?php
	endif;
}

// Get col global
function tianshu_col_use_sidebar( $option_sidebar, $active_sidebar ): string {
	if ( $option_sidebar != 'hide' && is_active_sidebar( $active_sidebar ) ):

		if ( $option_sidebar == 'left' ) :
			$class_position_sidebar = ' order-1 order-md-2';
		else:
			$class_position_sidebar = ' order-1';
		endif;

		$class_col_content = 'col-12 col-md-8 col-lg-9' . $class_position_sidebar;
	else:
		$class_col_content = 'col-md-12';
	endif;

	return $class_col_content;
}

function tianshu_col_sidebar(): string {
	return 'col-12 col-md-4 col-lg-3';
}

// Post Meta
function tianshu_post_meta(): void {
?>
    <div class="post-meta d-flex flex-wrap gap-1">
        <div class="post-meta__item post-meta__author">
            <strong class="theme-fw-medium"><?php esc_html_e( 'Tác giả:', 'tianshu' ); ?></strong>

            <a href="<?php echo get_author_posts_url( get_the_author_meta( 'ID' ) ); ?>">
                <?php the_author(); ?>
            </a>
        </div>

        <div class="post-meta__item post-meta__date">
            <strong class="theme-fw-medium"><?php esc_html_e( 'Ngày đăng: ', 'tianshu' ); ?></strong>
            <span><?php echo get_the_date(); ?></span>
        </div>

        <div class="post-meta__item post-meta__comments">
            <?php
            comments_popup_link( '0 ' . esc_html__( 'Bình luận', 'tianshu' ), '1 ' . esc_html__( 'Bình luận', 'tianshu' ), '% ' . esc_html__( 'Bình luận', 'tianshu' ) );
            ?>
        </div>
    </div>
<?php
}

// Link Pages
function tianshu_link_page(): void {
	wp_link_pages( array(
		'before'      => '<div class="page-links">' . esc_html__( 'Trang:', 'tianshu' ),
		'after'       => '</div>',
		'link_before' => '<span class="page-number">',
		'link_after'  => '</span>',
	) );
}

// Get Contact Form 7
function tianshu_get_form_cf7(): array {
	$options = array();

	if ( function_exists( 'wpcf7' ) ) {

		$wpcf7_form_list = get_posts( array(
			'post_type'   => 'wpcf7_contact_form',
			'numberposts' => - 1,
		) );

		$options[0] = esc_html__( 'Chọn một mẫu liên hệ', 'tianshu' );

		if ( ! empty( $wpcf7_form_list ) && ! is_wp_error( $wpcf7_form_list ) ) :
			foreach ( $wpcf7_form_list as $item ) :
				$options[ $item->ID ] = $item->post_title;
			endforeach;
		else :
			$options[0] = esc_html__( 'Tạo biểu mẫu trước tiên', 'tianshu' );
		endif;

	}

	return $options;
}

// list social network
function tianshu_list_social_network(): array {
	return array(
		'facebook-f'  => 'Facebook',
		'twitter'     => 'Twitter',
		'linkedin-in' => 'Linkedin',
		'youtube'     => 'Youtube',
		'instagram'   => 'Instagram'
	);
}

function tianshu_get_social_url(): void {
	$opt_social_networks = tianshu_get_option( 'opt_social_networks' );

	if ( ! empty( $opt_social_networks ) ) :
		foreach ( $opt_social_networks as $item ) :
			if ( empty( $item['item'] ) ) {
				continue;
			}
			?>
            <div class="social-network-item">
                <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank">
                    <i class="ic-mask ic-mask-<?php echo esc_attr( $item['item'] ); ?>"></i>
                </a>
            </div>
		<?php

		endforeach;
	endif;
}

// replace number
function tianshu_preg_replace_ony_number( $string ): string|null {
	$number = '';

	if ( ! empty( $string ) ) {
		$number = preg_replace( '/[^0-9]/', '', strip_tags( $string ) );
	}

	return $number;
}

// Create a function to fetch all post categories and return them as an associative array for use in a select dropdown
function tianshu_get_all_categories(): array {
	$categories = get_categories( array(
		'hide_empty' => 0,
	) );

	$categories_list = array();
	foreach ( $categories as $category ) {
		$categories_list[ $category->term_id ] = $category->name;
	}

	return $categories_list;
}

// Check is Elementor
if ( ! function_exists( 'tianshu_is_elementor' ) ) {
    function tianshu_is_elementor( ?int $post_id = null ): bool {
        $post_id = $post_id ?: get_queried_object_id();

        if ( ! $post_id ) {
            return false;
        }

        // Kiểm tra meta
        $check_meta = get_post_meta( $post_id, '_elementor_edit_mode', true );
        if ( $check_meta ) {
            return true;
        }

        // Kiểm tra trực tiếp qua Elementor API (nếu có)
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $doc = \Elementor\Plugin::$instance->documents->get( $post_id );
            if ( $doc && $doc->is_built_with_elementor() ) {
                return true;
            }
        }

        return false;
    }
}