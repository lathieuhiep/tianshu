<?php

use ExtendSite\Ajax\LoadChapters;
use ExtendSite\PostType\TemplateLoader;
use ExtendSite\Repositories\ChapterRepository;
use ExtendSite\Views\ViewTracker;

get_header();

$current_id = get_the_ID();
$paged    = isset($_GET['chap_page']) ? max(1, (int) $_GET['chap_page']) : 1;
$per_page = 20;

$story_id = ChapterRepository::get_story_id($current_id);
$prev_id = ChapterRepository::get_adjacent_chapter($current_id, 'prev');
$next_id = ChapterRepository::get_adjacent_chapter($current_id, 'next');
$chapter_number = ChapterRepository::get_chapter_number($current_id);
$chapter_views = ViewTracker::format_short( ViewTracker::get_chapter_views( $current_id ) );
?>
<!-- Single Chapter Content -->
<div class="es-single-warp es-pt-10 es-pb-10" data-plugin="extend-site">
    <div class="es-container">
        <?php
        TemplateLoader::part('common/breadcrumb');

        if (have_posts()) :
            while (have_posts()) : the_post();
        ?>
            <div class="es-post es-mt-6" data-chapter-id="<?php echo esc_attr( $current_id ); ?>">
                <h1 class="title es-mb-6"><?php the_title(); ?></h1>

                <div class="es-badge es-badge-info es-mb-6">
                    <div class="item item-date">
                        <span class="item__label"><?php esc_html_e('Cập nhật lúc:', 'extend-site'); ?></span>
                        <span class="item__value"><?php echo esc_html( get_the_date('d-m-Y') ); ?></span>
                    </div>

                    <div class="item item-view">
                        <span class="item__label"><?php esc_html_e('Lượt xem:', 'extend-site'); ?></span>
                        <span class="item__value"><?php echo esc_html( $chapter_views ) ?></span>
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

<!-- Chapter Footer Navigation -->
<div class="chapter-footer">
    <div class="es-container es-flex es-flex-wrap es-row-gap-3 es-col-gap-3 es-flex-justify-center es-flex-align-center">
        <?php
        if ( $story_id ) :
            $story_url   = get_permalink($story_id);
            $story_title = get_the_title($story_id);
        ?>
            <a href="<?php echo esc_url($story_url); ?>"
               class="story-back-link es-btn es-btn-secondary es-fs-sm"
               title="<?php echo esc_attr(sprintf(esc_html__('Về truyện: %s', 'extend-site'), $story_title)); ?>"
            >
                <i class="es-ic-mask es-ic-mask-book-open" aria-hidden="true"></i>
                <span class="chapter-back-text"><?php esc_html_e('Về truyện', 'extend-site'); ?></span>
            </a>
        <?php endif; ?>

        <nav class="chapter-navigation es-flex es-flex-wrap es-flex-align-center es-col-gap-3 es-row-gap-3">
            <?php if ($prev_id): ?>
                <a href="<?php echo esc_url(get_permalink($prev_id)); ?>" class="es-btn es-btn-primary prev es-fs-sm">
                    <i class="es-ic-mask es-ic-mask-angle-left"></i>
                    <span class="text-nav"><?php esc_html_e('Chương trước', 'extend-site'); ?></span>
                </a>
            <?php else: ?>
                <span class="es-btn es-btn-primary disabled prev es-fs-sm">
                    <i class="es-ic-mask es-ic-mask-angle-left"></i>
                    <span class="text-nav"><?php esc_html_e('Chương trước', 'extend-site'); ?></span>
                </span>
            <?php endif; ?>

            <?php if ( $story_id ) : ?>
                <button type="button"
                        data-story-id="<?php echo esc_attr( $story_id ) ?>"
                        class="es-btn es-btn-success es-btn-chapter-list es-fs-sm"
                        aria-label="<?php esc_html_e('Danh sách chương', 'extend-site'); ?>"
                >
                    <i class="es-ic-mask es-ic-mask-list"></i>
                    <span class="text-chapter-list"><?php esc_html_e('Danh sách chương', 'extend-site'); ?></span>
                </button>
            <?php endif; ?>

            <?php if ($next_id): ?>
                <a href="<?php echo esc_url(get_permalink($next_id)); ?>" class="es-btn es-btn-primary next es-fs-sm">
                    <span class="text-nav"><?php esc_html_e('Chương sau', 'extend-site'); ?></span>
                    <i class="es-ic-mask es-ic-mask-angle-right"></i>
                </a>
            <?php else: ?>
                <span class="es-btn es-btn-primary disabled next es-fs-sm">
                    <span class="text-nav"><?php esc_html_e('Chương sau', 'extend-site'); ?></span>
                    <i class="es-ic-mask es-ic-mask-angle-right"></i>
                </span>
            <?php endif; ?>
        </nav>
    </div>
</div>

<!--Modal for Chapter List-->
<div id="es-chapter-modal" class="es-chapter-modal es-modal" aria-hidden="true">
    <div class="es-modal__overlay" data-close="true"></div>
    <div class="es-modal__dialog" role="dialog" aria-modal="true">
        <header class="es-modal__header es-flex es-flex-justify-space-between es-flex-align-center">
            <h3 class="es-modal__title es-fs-md es-m-0">
                <?php esc_html_e('Danh sách chương', 'extend-site'); ?>
            </h3>

            <button type="button" class="es-modal__close" data-close="true" aria-label="<?php esc_attr_e('Đóng', 'extend-site'); ?>">
                <i class="es-ic-mask es-ic-mask-xmark"></i>
            </button>
        </header>

        <div class="es-modal__body" id="es-chapter-modal-body">
            <p class="txt-current-chapter es-mb-3 es-fw-bold">
                <?php esc_html_e('Bạn đang xem chương', 'extend-site'); echo ' ' . esc_html( $chapter_number ); ?>
            </p>

            <div class="es-chapters"
                 data-story-id="<?php echo esc_attr($story_id); ?>"
                 data-per-page="<?php echo esc_attr($per_page); ?>"
                 data-current-page="<?php echo esc_attr($paged); ?>"
                 data-show-title="false"
                 data-show-date="false">
                <?php echo LoadChapters::render($story_id, $paged, $per_page, false, false); ?>
            </div>

<!--            <div class="es-loading es-flex es-flex-column es-flex-align-center es-row-gap-2">-->
<!--                <span class="es-spinner"></span>-->
<!--                <span class="text-load">--><?php //esc_html_e('Đang tải danh sách chương...', 'extend-site'); ?><!--</span>-->
<!--            </div>-->
        </div>

        <footer class="es-modal__footer">
            <button type="button" class="es-btn es-btn-secondary" data-close="true">
                <?php esc_html_e('Đóng', 'extend-site'); ?>
            </button>
        </footer>
    </div>
</div>
<?php
get_footer();