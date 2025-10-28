<?php

use ExtendSite\PostType\TemplateLoader;

get_header();
?>

<div class="es-single-warp es-pt-6 es-pb-6" data-plugin="extend-site">
    <div class="es-container">
        <?php
        TemplateLoader::part('common/breadcrumb');

        if (have_posts()) :
            while (have_posts()) : the_post();
                ?>
                <div class="es-post es-mt-6">
                    <div class="es-row es-row-gap-6">
                        <div class="es-col-12 es-col-sm-4 es-ratio-1-1">
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

                        <div class="es-col-12 es-col-sm-8">
                            <h1 class="title"><?php the_title(); ?></h1>

                            <div class="info es-grid-layout es-row-gap-3">
                                <div class="item item-story-updated">
                                    <strong class="item__label"><?php esc_html_e('Cập nhật', 'extend-site'); ?></strong>
                                    <div class="item__value">
                                        <?php echo esc_html( es_site_get_story_last_update( get_the_ID() ) ); ?>
                                    </div>
                                </div>

                                <div class="item item-story-author">
                                    <strong class="item__label"><?php esc_html_e('Tác giả', 'extend-site'); ?></strong>
                                    <div class="item__value">
                                        <strong><?php echo wp_kses_post( es_site_get_story_authors( get_the_ID() ) ); ?></strong>
                                    </div>
                                </div>

                                <div class="item item-story-genre">
                                    <strong class="item__label"><?php esc_html_e('Thể loại', 'extend-site'); ?></strong>
                                    <div class="item__value es-flex es-flex-wrap es-col-gap-2 es-row-gap-2">
                                        <?php
                                        $terms = get_the_terms(get_the_ID(), 'story_genre');
                                        if ($terms && !is_wp_error($terms)) :
                                            foreach ($terms as $term) : ?>
                                                <a class="es-btn es-btn-outline-primary" href="<?php echo esc_url( get_term_link($term) ); ?>"><?php echo esc_html($term->name); ?></a>
                                            <?php
                                            endforeach;
                                        else:
                                            echo esc_html__('Chưa phân loại', 'extend-site');
                                        endif;
                                        ?>
                                    </div>
                                </div>

                                <div class="item item-views">
                                    <strong class="item__label"><?php esc_html_e('Lượt xem', 'extend-site'); ?></strong>
                                    <div class="item__value">
                                        218
                                    </div>
                                </div>

                                <div class="item">
                                    <strong class="item__label"><?php esc_html_e('Trạng thái', 'extend-site'); ?></strong>
                                    <div class="item__value">
                                        <?php
                                        $status_terms = get_the_terms(get_the_ID(), 'story_status');
                                        if ($status_terms && !is_wp_error($status_terms)) {
                                            $status_names = wp_list_pluck($status_terms, 'name');
                                            echo esc_html(implode(', ', $status_names));
                                        } else {
                                            echo esc_html__('Chưa xác định', 'extend-site');
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="group-box es-mt-6 es-pt-6 es-row-gap-6">
                                <?php TemplateLoader::part('story/parts/single-actions'); ?>

                                <div class="content">
                                    <?php the_content(); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php TemplateLoader::part('story/partials/story-tabs', [
                        'story_id' => get_the_ID(),
                    ]); ?>
                </div>
            <?php
            endwhile;
            wp_reset_postdata();
        endif;
        ?>

        <?php TemplateLoader::part('story/parts/hot-month'); ?>
    </div>
</div>

<?php
get_footer();