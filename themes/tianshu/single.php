<?php
get_header();

// check elementor
$tianshu_is_elementor = tianshu_is_elementor();

// Lớp cho wrapper chính
$tianshu_wrapper_classes = $tianshu_is_elementor
    ? ['single-post-elementor']
    : ['site-container', 'single-post-warp'];

if ( empty( $tianshu_is_elementor ) ) :
    get_template_part('components/inc', 'breadcrumbs');
endif;
?>

<div class="<?php echo esc_attr( implode( ' ', $tianshu_wrapper_classes ) ); ?>">
    <?php
    if ( $tianshu_is_elementor ) :
        while ( have_posts() ) : the_post() ;
            the_content();
            tianshu_link_page();

            if ( comments_open() || get_comments_number() ) :
        ?>
            <div class="entry-comments container">
                <?php comments_template( '', true ); ?>
            </div>
        <?php endif; ?>
    <?php
        endwhile;
    else:
        get_template_part( 'template-parts/post/content', 'single' );
    endif;
    ?>
</div>

<?php
get_footer();

