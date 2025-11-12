<?php
/**
 * Template: Hiển thị thông báo
 *
 * @var array $ad
 */
?>
<div class="er-partner-info">
    <p class="er-partner-info__desc">
        Mời Quý độc giả <strong>CLICK</strong> vào <strong>LIÊN KẾT HOẶC ẢNH</strong> bên dưới<br>
        <span class="highlight"><?php echo esc_html($ad['sub_title']); ?></span> để tiếp tục đọc toàn bộ chương truyện!
    </p>

    <p class="er-partner-info__link">
        <a href="<?php echo esc_url($ad['link']); ?>"
           data-affiliate-click="1"
           target="_blank">
            <?php echo esc_html($ad['link']); ?>
        </a>
    </p>

    <a href="<?php echo esc_url($ad['link']); ?>"
       data-affiliate-click="1"
       target="_blank"
       class="er-partner-info__image">
        <img src="<?php echo esc_url($ad['image']); ?>"
             alt="<?php echo esc_attr($ad['label']); ?>" />
    </a>

    <p class="er-partner-info__footer">
        Đội ngũ chúng tôi xin chân thành cảm ơn!
    </p>
</div>

