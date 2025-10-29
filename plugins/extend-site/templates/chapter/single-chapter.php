<?php

use ExtendSite\PostType\TemplateLoader;
use ExtendSite\Repositories\ChapterRepository;

get_header();

$current_id = get_the_ID();

$story_id = ChapterRepository::get_story_id($current_id);

$prev_id = ChapterRepository::get_adjacent_chapter($current_id, 'prev');
$next_id = ChapterRepository::get_adjacent_chapter($current_id, 'next');
?>

<div class="es-single-warp es-pt-6 es-pb-6" data-plugin="extend-site">
    <div class="es-container">
        <?php
        TemplateLoader::part('common/breadcrumb');

        if (have_posts()) :
            while (have_posts()) : the_post();
        ?>
            <div class="es-post es-mt-6">
                <h1 class="title es-mb-6"><?php the_title(); ?></h1>

                <div class="es-badge es-badge-info es-mb-6">
                    <div class="item item-date">
                        <span class="item__label"><?php esc_html_e('Cập nhật lúc:', 'extend-site'); ?></span>
                        <span class="item__value"><?php echo esc_html( get_the_date('d-m-Y') ); ?></span>
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

<div class="chapter-footer">
    <div class="es-container es-flex es-flex-wrap es-row-gap-3 es-col-gap-3 es-flex-justify-center es-flex-align-center">
        <?php
        if ( $story_id ) :
            $story_url   = get_permalink($story_id);
            $story_title = get_the_title($story_id);
        ?>
            <a href="<?php echo esc_url($story_url); ?>"
               class="story-back-link es-btn es-btn-secondary"
               title="<?php echo esc_attr(sprintf(esc_html__('Về truyện: %s', 'extend-site'), $story_title)); ?>"
            >
                <i class="es-ic-mask es-ic-mask-book-open" aria-hidden="true"></i>
                <span class="chapter-back-text"><?php esc_html_e('Về truyện', 'extend-site'); ?></span>
            </a>
        <?php endif; ?>

        <nav class="chapter-navigation es-flex es-flex-wrap es-flex-align-center es-col-gap-3 es-row-gap-3">
            <?php if ($prev_id): ?>
                <a href="<?php echo esc_url(get_permalink($prev_id)); ?>" class="es-btn es-btn-primary prev">
                    <i class="es-ic-mask es-ic-mask-angle-left"></i>
                    <span><?php esc_html_e('Chương trước', 'extend-site'); ?></span>
                </a>
            <?php else: ?>
                <span class="es-btn es-btn-primary disabled prev">
                    <i class="es-ic-mask es-ic-mask-angle-left"></i>
                    <span><?php esc_html_e('Chương trước', 'extend-site'); ?></span>
                </span>
            <?php endif; ?>

            <?php if ( $story_id ) : ?>
                <div class="chapter-selector-box">
                    <select id="chapter-selector"
                            class="chapter-selector"
                            data-story-id="<?php echo esc_attr( $story_id ) ?>"
                            data-current="<?php echo esc_attr( ChapterRepository::get_chapter_number($current_id) ) ?>" aria-label=""></select>
                </div>
            <?php endif; ?>

            <?php if ($next_id): ?>
                <a href="<?php echo esc_url(get_permalink($next_id)); ?>" class="es-btn es-btn-primary next">
                    <span><?php esc_html_e('Chương sau', 'extend-site'); ?></span>
                    <i class="es-ic-mask es-ic-mask-angle-right"></i>
                </a>
            <?php else: ?>
                <span class="es-btn es-btn-primary disabled next">
                    <span><?php esc_html_e('Chương sau', 'extend-site'); ?></span>
                    <i class="es-ic-mask es-ic-mask-angle-right"></i>
                </span>
            <?php endif; ?>
        </nav>
    </div>
</div>

<?php
get_footer();