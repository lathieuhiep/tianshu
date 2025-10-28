<?php
/**
 * Single Story – Actions
 */
defined('ABSPATH') || exit;

use ExtendSite\Repositories\ChapterRepository;

$story_id = (int)get_the_ID();
$edges = ChapterRepository::get_edge_urls($story_id);
?>
<div class="es-story-actions es-row-gap-3 es-col-gap-3" role="navigation" aria-label="<?php esc_attr_e('Story quick actions', 'extend-site'); ?>">
    <?php if (!empty($edges['first'])): ?>
        <a class="es-btn es-btn-primary" href="<?php echo esc_url($edges['first']); ?>">
            <i class="es-ic-mask es-ic-mask-book-open"></i>
            <span class="btn-txt"><?php esc_html_e('Đọc từ đầu', 'extend-site'); ?></span>
        </a>
    <?php endif; ?>

    <?php if (!empty($edges['latest'])): ?>
        <a class="es-btn es-btn-success" href="<?php echo esc_url($edges['latest']); ?>">
            <i class="es-ic-mask es-ic-mask-star"></i>
            <span class="btn-txt"><?php esc_html_e('Đọc tập mới', 'extend-site'); ?></span>
        </a>
    <?php endif; ?>
</div>
