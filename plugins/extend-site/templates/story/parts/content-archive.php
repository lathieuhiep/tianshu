<?php
//use ExtendSite\Repositories\ChapterRepository;
//use ExtendSite\Views\ViewTracker;
//
//$latest_chapter = ChapterRepository::get_latest_chapter( get_the_ID() );
//$story_views   = ViewTracker::format_short( ViewTracker::get_story_views( get_the_ID() ) );
?>

<article id="story-<?php the_ID(); ?>"
    <?php post_class( 'es-col-12 es-col-md-4' ); ?>
         itemscope
         itemtype="https://schema.org/Book"
>
    <div class="item es-flex es-flex-column">
        <div class="item__thumbnail es-ratio-16-9">
            <a class="es-ratio-thumb" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                <?php if ( has_post_thumbnail() ) : ?>
                    <?php the_post_thumbnail( 'medium', [ 'alt' => get_the_title() ] ); ?>
                <?php else : ?>
                    <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                         alt="<?php the_title_attribute(); ?>">
                <?php endif; ?>
            </a>

            <div class="meta-data">
                <div class="meta-item es-flex es-flex-align-center es-gap-2">
                    <i class="es-ic-mask es-ic-mask-eye" aria-hidden="true"></i>
                    <span itemprop="interactionCount">
<!--                        --><?php //echo esc_html( $story_views ); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="item__content es-p-3">
            <h2 class="title es-fs-sm es-mb-2 es-two-line-clamp" itemprop="name">
                <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" rel="bookmark">
                    <?php the_title(); ?>
                </a>
            </h2>

<!--            <div class="item__meta es-text-sm es-text-gray-600 es-flex es-items-center es-flex-justify-space-between es-row-gap-1 es-col-gap-2 es-fs-sm">-->
<!--                --><?php //if ( !empty( $latest_chapter ) ): ?>
<!--                    <div class="es-story-latest"-->
<!--                         itemprop="hasPart"-->
<!--                         itemscope-->
<!--                         itemtype="https://schema.org/Chapter"-->
<!--                    >-->
<!--                        <a class="es-story-link"-->
<!--                           href="--><?php //echo esc_url( $latest_chapter['url'] ); ?><!--"-->
<!--                           title="--><?php //echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?><!--"-->
<!--                           aria-label="--><?php //echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?><!--"-->
<!--                           itemprop="url"-->
<!--                           rel="bookmark"-->
<!--                        >-->
<!--                                                        <span itemprop="name">-->
<!--                                                            --><?php
//                                                            printf(
//                                                                esc_html__( 'Chương %d: %s', 'extend-site' ),
//                                                                intval( $latest_chapter['number'] ),
//                                                                esc_html( $latest_chapter['title'] )
//                                                            );
//                                                            ?>
<!--                                                        </span>-->
<!--                        </a>-->
<!--                        <meta itemprop="position" content="--><?php //echo intval( $latest_chapter['number'] ); ?><!--">-->
<!--                    </div>-->
<!--                --><?php //else: ?>
<!--                    <p class="es-text-primary">-->
<!--                        --><?php //esc_html_e('Sắp ra...', 'extend-site'); ?>
<!--                    </p>-->
<!--                --><?php //endif; ?>
<!---->
<!--                <time datetime="--><?php //echo esc_attr( get_the_modified_date( 'c' ) ); ?><!--"-->
<!--                      itemprop="dateModified">-->
<!--                    --><?php //echo esc_html( es_display_time_ago() ); ?>
<!--                </time>-->
<!--            </div>-->
        </div>
    </div>
</article>