<?php
/**
 * Partial: partner-info
 *
 * @var array $ad
 * @var string $content
 */

defined('ABSPATH') || exit;
?>

<div class="er-partner-info" data-ttl="<?php echo esc_attr($ad['ttl']); ?>" hidden>
    <p class="er-partner-info__desc">
        Mời Quý độc giả <strong>CLICK</strong> vào <strong>LIÊN KẾT HOẶC ẢNH</strong> bên dưới<br>
        <span class="highlight"><?php echo esc_html($ad['sub_title']); ?></span> để tiếp tục đọc toàn bộ chương truyện!
    </p>

    <p class="er-partner-info__link">
        <a href="<?php echo esc_url($ad['link']); ?>" target="_blank" data-affiliate-click="1">
            <?php echo esc_html($ad['link']); ?>
        </a>
    </p>

    <a href="<?php echo esc_url($ad['link']); ?>" target="_blank" data-affiliate-click="1" class="er-partner-info__image">
        <img src="<?php echo esc_url($ad['image']); ?>" alt="<?php echo esc_attr($ad['label']); ?>" />
    </a>

    <p class="er-partner-info__footer">
        <?php esc_html_e('Đội ngũ chúng tôi xin chân thành cảm ơn!', 'extend-referrals'); ?>
    </p>
</div>

<div id="er-partner-content-wrapper" hidden>
    <?php echo $content; ?>
</div>
