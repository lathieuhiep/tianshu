<?php

use ExtendSite\Repositories\StoryRepository;
use ExtendSite\Views\ViewTracker;

get_header();
?>

<div class="es-archive-warp es-pt-10 es-pb-10" data-plugin="extend-site" itemscope itemtype="https://schema.org/CollectionPage">
    <div class="es-container">
        <h1 class="page-archive-title es-fs-lg">
            <?php esc_html_e('Tác giả', 'extend-site'); ?>
        </h1>

        <div class="es-row">
            <div class="es-col-12<?php echo esc_attr( is_active_sidebar( 'es-sidebar' ) ? ' es-col-sm-9' : '' ); ?>">
                <?php if ( have_posts() ) : ?>
                    <div class="es-archive-list es-author-list">
                        <div class="es-row es-row-gap-6">
                            <?php
                            while ( have_posts() ) : the_post();
                                $total_stories = StoryRepository::count_by_author( get_the_ID() );
                                $total_views = ViewTracker::format_short( ViewTracker::get_author_views( get_the_ID() ) );
                            ?>

                            <article id="story-author-<?php the_ID(); ?>"
                                <?php post_class( 'es-col-12 es-col-sm-6 es-col-md-6 es-col-lg-4 es-col-xl-3' ); ?>
                                     itemscope
                                     itemtype="https://schema.org/Person"
                            >
                                <div class="item es-flex es-flex-column es-p-2">
                                    <div class="item__thumbnail es-ratio-1-1 es-text-center">
                                        <a class="es-ratio-thumb img" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                                            <?php if ( has_post_thumbnail() ) : ?>
                                                <?php the_post_thumbnail( 'medium', [ 'alt' => get_the_title() ] ); ?>
                                            <?php else : ?>
                                                <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                                     alt="<?php the_title_attribute(); ?>">
                                            <?php endif; ?>
                                        </a>
                                    </div>

                                    <h2 class="title es-mt-2 es-mb-2 es-fs-sm es-two-line-clamp es-text-center" itemprop="name">
                                        <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" rel="bookmark">
                                            <?php the_title(); ?>
                                        </a>
                                    </h2>

                                    <div class="info es-flex es-flex-wrap es-gap-2 es-flex-justify-space-between es-flex-align-center">
                                        <div class="info__item es-flex es-flex-align-center es-flex-justify-center es-gap-2">
                                            <i class="es-ic-mask es-ic-mask-eye"></i>
                                            <span class="txt-val"><?php echo esc_html( $total_views ); ?></span>
                                        </div>

                                        <div class="info__item es-flex es-flex-align-center es-flex-justify-center es-gap-2">
                                            <i class="es-ic-mask es-ic-mask-book"></i>
                                            <span class="txt-val"><?php echo esc_html( $total_stories ); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    </div>
                <?php
                    es_pagination();
                else :
                ?>
                    <p class="es-text-center">
                        <?php esc_html_e( 'Chưa có tác giả nào', 'extend-site' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if( is_active_sidebar( 'es-sidebar' ) ): ?>
                <aside class="es-col-12 es-col-sm-3">
                    <div class="sidebar-warp es-sticky-top">
                        <?php dynamic_sidebar( 'es-sidebar' ); ?>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
get_footer();