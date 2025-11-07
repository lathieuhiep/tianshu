<?php
/**
 * Template: Search Story Results Page
 *
 * @var string   $keyword
 * @var WP_Query $query
 */

use ExtendSite\PostType\TemplateLoader;
use ExtendSite\Search\SearchRepository;
use ExtendSite\Views\ViewTracker;

get_header();

// If $query is not set, or any filter is applied, perform advanced search
if ( !isset($query)
    || isset($_GET['genre'])
    || isset($_GET['status'])
    || isset($_GET['chapters'])
    || isset($_GET['sort'])
) {
    $query = SearchRepository::search_stories_advanced();
}
?>

<div class="es-archive-warp es-search-results es-pt-10 es-pb-10" data-plugin="extend-site" itemscope itemtype="https://schema.org/CollectionPage">
    <div class="es-container">
        <div class="es-filter-active es-mb-6">
            <?php TemplateLoader::part('parts/filter-active-bar'); ?>
        </div>

        <div class="es-row">
            <div class="es-col-12 es-col-sm-3">
                <div class="es-filter-box es-sticky-top">
                    <?php TemplateLoader::part('parts/form-search-advanced'); ?>
                </div>
            </div>

            <div class="es-col-12 es-col-sm-9">
                <div class="es-badge es-badge-primary es-fs-md es-mb-6">
                    <?php
                    printf(esc_html__('Tìm thấy %s truyện', 'extend-site'), esc_html( ViewTracker::format_full( $query->found_posts ) ));
                    ?>
                </div>

                <?php if ( $query->have_posts() ) : ?>
                    <div class="es-archive-list es-story-list">
                        <div class="es-row es-row-gap-6">
                            <?php
                            while ( $query->have_posts() ) : $query->the_post();
                                TemplateLoader::part('story/parts/content-archive');
                            endwhile; wp_reset_postdata();
                            ?>
                        </div>
                    </div>
                    <?php
                    es_paging_nav_query($query);
                else:
                    ?>
                    <p>
                        <?php esc_html_e('Không tìm thấy truyện nào phù hợp.', 'extend-site'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
