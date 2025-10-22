<?php
$sidebar = tianshu_get_option('opt_post_single_sidebar_position', 'right');
$show_related = tianshu_get_option('opt_post_single_related', '1');
$class_col_content = tianshu_col_use_sidebar( $sidebar, 'sidebar-main' );
?>

<div class="container">
    <div class="row">
        <div class="<?php echo esc_attr( $class_col_content ); ?>">
            <?php if ( have_posts() ) :
                while (have_posts()) :
                    the_post();
                ?>
                    <div id="post-<?php the_ID() ?>" <?php post_class('single-post-content'); ?>>
                        <?php if ( has_post_thumbnail() ) :?>
                            <div class="single-post-content__image">
                                <?php the_post_thumbnail('full'); ?>
                            </div>
                        <?php endif; ?>

                        <h2 class="single-post-content__title">
                            <?php the_title(); ?>
                        </h2>

                        <?php tianshu_post_meta(); ?>

                        <div class="single-post-content__detail">
                            <?php
                            the_content();

                            tianshu_link_page();
                            ?>
                        </div>

                        <div class="single-post-content__tax">
                            <?php if( get_the_category() ): ?>
                                <p class="post-category">
                                    <?php
                                    esc_html_e('Danh mục: ','tianshu');
                                    the_category( ', ' );
                                    ?>
                                </p>
                            <?php
                            endif;

                            if( get_the_tags() ):
                                ?>
                                <p class="post-tag">
                                    <?php
                                    esc_html_e( 'Tag: ','tianshu' );
                                    the_tags('',', ');
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php
                    if ( comments_open() || get_comments_number() ) :
                        comments_template( '', true );
                    endif;

                    if ( $show_related == '1' ) :
                        get_template_part( 'template-parts/post/inc','related-post' );
                    endif;
                endwhile;
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