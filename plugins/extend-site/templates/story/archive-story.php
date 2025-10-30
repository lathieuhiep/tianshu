<?php get_header(); ?>

<div class="es-archive-warp es-pt-10 es-pb-10" data-plugin="extend-site">
    <div class="es-container">
        <div class="es-row">
            <div class="es-col-12 es-col-sm-9">
                <?php if ( have_posts() ) : ?>
                    <div class="es-story-list">
                        <div class="es-row">
                            <?php while ( have_posts() ) : the_post(); ?>
                            <div id="story-<?php the_ID(); ?>" class="es-col-12 es-col-md-4">
                                <div class="item">
                                    <div class="item__thumbnail es-mb-3 es-ratio-16-9">
                                        <a class="es-ratio-thumb" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                                            <?php if ( has_post_thumbnail() ) : ?>
                                                <?php the_post_thumbnail( 'medium', [ 'class' => 'es-w-full es-h-auto' ] ); ?>
                                            <?php else : ?>
                                                <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                                     alt="<?php the_title_attribute(); ?>">
                                            <?php endif; ?>
                                        </a>
                                    </div>

                                    <h2 class="title es-fs-sm es-mb-2">
                                        <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                                            <?php the_title(); ?>
                                        </a>
                                    </h2>

                                    <div class="item__meta es-text-center es-text-sm es-text-gray-600">
                                        <span class="item__date">
                                            <?php echo esc_html( get_the_date() ); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    </div>

                    <?php
                        es_pagination();
                    else:
                    ?>
                    <p><?php esc_html_e('Chưa có truyện' , 'extend-site'); ?></p>
                <?php endif; ?>
            </div>

            <div class="es-col-12 es-col-sm-3">Sidebar</div>
        </div>
    </div>
</div>

<?php
get_footer();