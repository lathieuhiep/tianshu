<div class="er-partner-info">
    <p class="er-partner-info__desc">
        Mời Quý độc giả <strong>CLICK</strong> vào <strong>LIÊN KẾT HOẶC ẢNH</strong> bên dưới<br>
        <span class="highlight"><?php echo esc_html($ad['sub_title']); ?></span> để tiếp tục đọc toàn bộ chương truyện!
    </p>

    <p class="er-partner-info__link">
        <a href="<?php echo esc_url($ad['link']); ?>" data-affiliate-click="1">
            <?php echo esc_html($ad['link']); ?>
        </a>
    </p>

    <a href="<?php echo esc_url($ad['link']); ?>" data-affiliate-click="1" class="er-partner-info__image">
        <img src="<?php echo esc_url($ad['image']); ?>" alt="<?php echo esc_attr($ad['label']); ?>" />
    </a>

    <p class="er-partner-info__footer">
        <?php esc_html_e('Đội ngũ chúng tôi xin chân thành cảm ơn!', 'extend-referrals'); ?>
    </p>
</div>

<!-- POPUP AFFILIATE -->
<div id="er-aff-popup" class="er-aff-popup">
    <div class="er-aff-popup__inner">

        <!-- Nút đóng popup -->
        <button type="button" class="er-aff-popup__close" aria-label="<?php esc_attr_e('Đóng', 'extend-referrals') ?>">
            &times;
        </button>

        <h3 class="heading"><?php esc_html_e('Đang mở quảng cáo…', 'extend-referrals'); ?></h3>
        <p><?php esc_html_e('Vui lòng chờ 1 chút', 'extend-referrals'); ?> ❤️</p>
    </div>
</div>
