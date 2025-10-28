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
                <h1 class="title"><?php the_title(); ?></h1>

                <div class="badge">
                    <div class="item item-date">
                        <span class="item__label"><?php esc_html_e('Cập nhật lúc:', 'extend-site'); ?></span>
                        <span class="item__value"><?php echo esc_html( get_the_date() ); ?></span>
                    </div>

                    <div class="item item-view">
                        <span class="item__label"><?php esc_html_e('Lượt xem:', 'extend-site'); ?></span>
                        <span class="item__value">2412</span>
                    </div>
                </div>

                <div class="content es-fs-md es-text-justify">
                    <?php the_content(); ?>
                </div>
            </div>
        <?php
            endwhile;
        endif;
        ?>
    </div>
</div>

<?php
get_footer();