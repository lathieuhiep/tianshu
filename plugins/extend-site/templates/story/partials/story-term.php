<?php
use ExtendSite\PostType\TemplateLoader;
?>

<div class="es-row">
    <div class="es-col-12<?php echo esc_attr( is_active_sidebar( 'es-sidebar' ) ? ' es-col-sm-9' : '' ); ?>">
        <?php
        if ( have_posts() ) : ?>
            <div class="es-archive-list es-story-list">
                <div class="es-row es-row-gap-6">
                    <?php
                    while ( have_posts() ) : the_post();
                        TemplateLoader::part('story/parts/content-archive');
                    endwhile; wp_reset_postdata();
                    ?>
                </div>
            </div>
        <?php
            es_pagination();
        else:
        ?>
            <p class="es-text-center">
                <?php esc_html_e('Chưa có truyện.' , 'extend-site'); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if( is_active_sidebar( 'es-sidebar' ) ): ?>
        <div class="es-col-12 es-col-sm-3">
            <?php dynamic_sidebar( 'es-sidebar' ); ?>
        </div>
    <?php endif; ?>
</div>