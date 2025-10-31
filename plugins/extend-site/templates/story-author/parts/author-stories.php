<?php
defined('ABSPATH') || exit;

if ( ! isset( $args ) || ! isset( $args['query'] ) ) {
    return;
}

$story_query = $args['query'];
?>

<div class="es-author-stories es-post es-mt-6 es-pb-5" itemscope itemtype="https://schema.org/ItemList">
    <h2 class="es-author-stories__title es-mb-4 es-pb-2 es-fs-lg es-border-bottom-1">
        <?php esc_html_e( 'Các truyện đã viết', 'extend-site' ); ?>
    </h2>

    <?php if ( $story_query->have_posts() ) : ?>
        <div class="es-row es-row-gap-6">
            <?php while ( $story_query->have_posts() ) : $story_query->the_post(); ?>
                <article id="story-<?php the_ID(); ?>"
                    <?php post_class( 'es-col-12 es-col-sm-6 es-col-md-4 es-col-lg-3' ); ?>
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
                        </div>

                        <div class="item__content es-mt-3">
                            <h2 class="title es-fs-sm es-mb-2" itemprop="name">
                                <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" rel="bookmark">
                                    <?php the_title(); ?>
                                </a>
                            </h2>
                        </div>
                    </div>
                </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    <?php
        es_paging_nav_query( $story_query );
    else :
    ?>
        <p><?php esc_html_e( 'Chưa có truyện nào được viết bởi tác giả này.', 'extend-site' ); ?></p>
    <?php endif; ?>
</div>