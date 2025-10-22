<?php
get_header();

// check elementor
$tianshu_is_elementor = tianshu_is_elementor();

// class wrapper main
$tianshu_wrapper_classes = $tianshu_is_elementor
    ? ['site-container-elementor']
    : ['site-container', 'site-page-default'];

// class content
$tianshu_content_classes = ['entry-content'];
if ( ! $tianshu_is_elementor ) {
    $tianshu_content_classes[] = 'container';
}
?>
    <div class="<?php echo esc_attr( implode( ' ', $tianshu_wrapper_classes ) ); ?>">
        <div class="<?php echo esc_attr( implode( ' ', $tianshu_content_classes ) ); ?>">
            <?php
            while ( have_posts() ) : the_post();
                the_content();

                tianshu_link_page();
            endwhile;
            ?>
        </div>

        <?php if ( comments_open() || get_comments_number() ) : ?>
            <div class="entry-comments container">
                <?php comments_template( '', true ); ?>
            </div>
        <?php endif; ?>
    </div>
<?php
get_footer();