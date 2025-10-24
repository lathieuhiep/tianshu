<?php
get_header();

//$author_ids = get_post_meta(get_the_ID(), \ExtendSite\PostType\StoryPostType::META_AUTHOR_IDS, true);
//$author_ids = is_array($author_ids) ? $author_ids : [];
//if ($author_ids) {
//    foreach ($author_ids as $aid) {
//        if (get_post_type($aid) !== 'story_author') continue;
//        echo '<a href="' . esc_url(get_permalink($aid)) . '">' . esc_html(get_the_title($aid)) . '</a> ';
//    }
//}
?>

<div class="es-single-portfolio-warp">
    <div class="es-container">
        <?php
        if ( have_posts() ) :
            while (have_posts()) : the_post();
        ?>
            <div class="es-post">
                <h1 class="es-post__title"><?php the_title(); ?></h1>

                <div class="es-post__content">
                    <?php the_content(); ?>
                </div>
            </div>
        <?php
            endwhile;
            wp_reset_postdata();
        endif;
        ?>
    </div>
</div>

<?php
get_footer();