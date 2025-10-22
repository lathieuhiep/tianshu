<?php get_header(); ?>

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