<?php
$sidebar = tianshu_get_option('opt_post_cat_sidebar_position', 'right');
$class_col_content = tianshu_col_use_sidebar($sidebar, 'sidebar-main');
$per_row_classes = tianshu_get_responsive_row_class('opt_post_cat_per_row');
?>

<div class="site-container archive-post-warp">
    <div class="container">
        <div class="row">
            <div class="<?php echo esc_attr( $class_col_content ); ?>">
                <?php if ( have_posts() ) : ?>
                    <div class="content-archive-post gap-6 <?php echo esc_attr( $per_row_classes ); ?>">
		                <?php while ( have_posts() ) : the_post(); ?>
                            <div class="item d-flex flex-column gap-2">
                                <h2 class="post-title">
                                    <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <?php if ( has_post_thumbnail() ) : ?>
                                    <div class="post-thumbnail">
                                        <?php the_post_thumbnail('large'); ?>
                                    </div>
                                <?php endif; ?>

                                <?php tianshu_post_meta(); ?>

                                <div class="post-desc">
                                    <p>
                                        <?php
                                        if (has_excerpt()) :
                                            echo esc_html(get_the_excerpt());
                                        else:
                                            echo wp_trim_words(get_the_content(), 30, '...');
                                        endif;
                                        ?>
                                    </p>

                                    <?php tianshu_link_page(); ?>
                                </div>

                                <div class="read-more flex-fill d-flex align-items-end">
                                    <a href="<?php the_permalink(); ?>" class="text-read-more">
                                        <?php esc_html_e('Đọc tiếp', 'tianshu'); ?>
                                    </a>
                                </div>
                            </div>
		                <?php endwhile; wp_reset_postdata();
		                ?>
                    </div>
                <?php
	                tianshu_pagination();
                else:
	                if ( is_search() ) :
		                get_template_part('template-parts/post/content', 'no-data');
	                endif;
                endif;
                ?>
            </div>

            <?php
            if ( $sidebar !== 'hide' ) :
                get_sidebar();
            endif;
            ?>
        </div>
    </div>
</div>