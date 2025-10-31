<?php
/**
 * Single Story – Actions
 */
defined('ABSPATH') || exit;

use ExtendSite\Repositories\ChapterRepository;

$story_id = (int)get_the_ID();
$first_chapter = ChapterRepository::get_first_chapter($story_id);
$latest_chapter = ChapterRepository::get_latest_chapter( $story_id );
?>
<div class="es-story-actions es-row-gap-3 es-col-gap-3" role="navigation" aria-label="<?php esc_attr_e('Story quick actions', 'extend-site'); ?>">
    <?php if ( !empty( $first_chapter ) ): ?>
        <a class="es-btn es-btn-primary"
           href="<?php echo esc_url( $first_chapter['url'] ); ?>"
           title="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $first_chapter['number'], get_the_title() ) ); ?>"
           aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $first_chapter['number'], get_the_title() ) ); ?>"
           rel="bookmark"
        >
            <i class="es-ic-mask es-ic-mask-book-open"></i>
            <span class="btn-txt"><?php esc_html_e('Đọc từ đầu', 'extend-site'); ?></span>
        </a>
    <?php endif; ?>

    <?php if ( !empty( $latest_chapter ) ): ?>
        <a class="es-btn es-btn-success"
           href="<?php echo esc_url( $latest_chapter['url'] ); ?>"
           title="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?>"
           aria-label="<?php echo esc_attr( sprintf( esc_html__( 'Đọc chương %s truyện %s', 'extend-site' ), $latest_chapter['number'], get_the_title() ) ); ?>"
           rel="bookmark"
        >
            <i class="es-ic-mask es-ic-mask-star"></i>
            <span class="btn-txt"><?php esc_html_e('Đọc tập mới', 'extend-site'); ?></span>
        </a>
    <?php endif; ?>
</div>
