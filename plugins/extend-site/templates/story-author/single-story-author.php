<?php

use ExtendSite\PostType\TemplateLoader;
use ExtendSite\Repositories\StoryRepository;

get_header();

$author_id = get_the_ID();
$author_name = get_the_title( $author_id );
$paged = max( 1, get_query_var( 'paged' ) );

$query = StoryRepository::get_by_author( $author_id, $paged );
$total_stories = $query->found_posts;
?>

<div class="es-single-warp es-single-author-warp es-pt-6 es-pb-6" data-plugin="extend-site">
    <div class="es-container">
        <?php
        if (have_posts()) :
            while (have_posts()) : the_post();
        ?>
            <div class="es-post">
                <div class="es-row es-row-gap-6">
                    <div class="es-col-12 es-col-sm-3 es-col-md-2 es-ratio-1-1">
                        <div class="thumbnail-box es-ratio-thumb">
                            <?php
                            if (has_post_thumbnail()) :
                                the_post_thumbnail('large');
                            else :
                            ?>
                                <img src="<?php echo esc_url(EXTEND_SITE_URL . 'assets/images/no-image.png'); ?>"
                                     alt="<?php the_title_attribute(); ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="es-col-12 es-col-sm-9 es-col-md-10">
                        <h1 class="title"><?php the_title(); ?></h1>

                        <div class="content">
                            <?php the_content(); ?>
                        </div>

                        <div class="info es-mt-6">
                            <div class="item item-author-stories-count es-flex es-gap-3 es-items-center">
                                <strong class="item__label"><?php esc_html_e('Số truyện', 'extend-site'); ?>:</strong>
                                <div class="item__value">
                                    <?php echo esc_html( $total_stories ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            endwhile;
            wp_reset_postdata();
        endif;

        TemplateLoader::part('story-author/parts/author-stories', [
            'query' => $query,
        ]);
        ?>
    </div>
</div>

<?php
get_footer();