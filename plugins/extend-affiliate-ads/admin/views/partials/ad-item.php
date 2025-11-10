<?php
/**
 * Partial: ad-item
 *
 * @var string $index
 * @var bool   $is_template
 */
?>
<div class="ad-item" aria-label="<?php esc_attr_e('Mục quảng cáo', 'extend-affiliate-ads'); ?>">
    <input type="text"
           name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][label]"
           placeholder="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-affiliate-ads'); ?>"
           class="regular-text"
           aria-label="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-affiliate-ads'); ?>" />

    <input type="url"
           name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][link]"
           placeholder="<?php esc_attr_e('Liên kết affiliate', 'extend-affiliate-ads'); ?>"
           class="regular-text"
           aria-label="<?php esc_attr_e('Liên kết affiliate', 'extend-affiliate-ads'); ?>" />

    <input type="text"
           name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][image]"
           placeholder="<?php esc_attr_e('Đường dẫn ảnh', 'extend-affiliate-ads'); ?>"
           class="regular-text"
           aria-label="<?php esc_attr_e('Đường dẫn ảnh', 'extend-affiliate-ads'); ?>" />

    <label class="ad-toggle">
        <input type="checkbox"
               name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][active]"
               value="1"
               checked
               aria-label="<?php esc_attr_e('Bật quảng cáo', 'extend-affiliate-ads'); ?>" />
        <?php esc_html_e('Kích hoạt', 'extend-affiliate-ads'); ?>
    </label>

    <button type="button"
            class="button remove-ad"
            aria-label="<?php esc_attr_e('Xóa quảng cáo này', 'extend-affiliate-ads'); ?>">
        <?php esc_html_e('Xóa', 'extend-affiliate-ads'); ?>
    </button>
</div>