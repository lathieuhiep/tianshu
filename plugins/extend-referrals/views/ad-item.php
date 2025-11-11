<?php
/**
 * Template: Hiển thị thông báo
 *
 * @var array $ad
 */
?>
<div class="es-ad">
    <p class="es-ad__desc">
        Mời Quý độc giả <strong>CLICK</strong> vào <strong>LIÊN KẾT HOẶC ẢNH</strong> bên dưới<br>
        <span class="es-ad__highlight">MỞ ỨNG DỤNG SHOPEE</span> để tiếp tục đọc toàn bộ chương truyện!
    </p>

    <p class="es-ad__link">
        <a href="<?php echo esc_url($ad['link']); ?>"
           data-affiliate-click="1"
           target="_blank">
            <?php echo esc_html($ad['link']); ?>
        </a>
    </p>

    <a href="<?php echo esc_url($ad['link']); ?>"
       data-affiliate-click="1"
       target="_blank"
       class="es-ad__banner">
        <img src="<?php echo esc_url($ad['image']); ?>"
             alt="<?php echo esc_attr($ad['label']); ?>" />
    </a>

    <p class="es-ad__footer">
        Đội ngũ chúng tôi xin chân thành cảm ơn!
    </p>
</div>

