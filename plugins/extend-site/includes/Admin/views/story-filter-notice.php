<?php
/**
 * @var string $story_title
 * @var string $story_edit_url
 * @var string $add_url
 * @var string $all_url
 * @var bool $has_search
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <div class="story-filter-notice">
        <div class="notice notice-info inline">
            <div class="notice-info__data">
                <p>
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php
                    echo wp_kses_post(sprintf(
                        __('Danh sách chương của truyện: <a href="%s"><strong>%s</strong></a>', 'extend-site'),
                        esc_url((string) $story_edit_url),
                        esc_html((string) $story_title)
                    ));
                    ?>
                </p>

                <p>
                    <a href="<?php echo esc_url((string) $add_url); ?>" class="button button-primary">
                        <?php esc_html_e('Thêm chương mới', 'extend-site'); ?>
                    </a>
                    <a href="<?php echo esc_url((string) $all_url); ?>" class="button">
                        <?php esc_html_e('Xem tất cả chương', 'extend-site'); ?>
                    </a>
                </p>
            </div>

            <?php if (!empty($has_search)) : ?>
                <p class="notice-search">
                    <?php
                    printf(
                        esc_html__('Kết quả tìm kiếm trong truyện: "%s"', 'extend-site'),
                        esc_html((string) $story_title)
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
