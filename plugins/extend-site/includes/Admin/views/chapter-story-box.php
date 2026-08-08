<?php
/**
 * @var string $story_title
 * @var string $story_url
 * @var string $add_url
 * @var string $list_url
 * @var bool $is_draft
 */

defined('ABSPATH') || exit;
?>
<div class="misc-pub-section">
    <p>
        <span class="dashicons dashicons-book-alt"></span>
        <?php
        echo wp_kses_post(sprintf(
            __('Thuộc truyện: <a href="%s"><strong>%s</strong></a>', 'extend-site'),
            esc_url((string) $story_url),
            esc_html((string) $story_title)
        ));
        ?>
    </p>

    <p class="chapter-story-actions">
        <?php if (empty($is_draft)) : ?>
            <a href="<?php echo esc_url((string) $add_url); ?>" class="button button-small button-primary">
                <?php esc_html_e('Thêm chương mới', 'extend-site'); ?>
            </a>
        <?php endif; ?>
        <a href="<?php echo esc_url((string) $list_url); ?>" class="button button-small">
            <?php esc_html_e('Danh sách chương', 'extend-site'); ?>
        </a>
    </p>
</div>
