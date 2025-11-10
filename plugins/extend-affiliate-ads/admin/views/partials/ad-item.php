<?php
/**
 * Partial: ad-item
 *
 * @var string $index
 * @var bool   $is_template
 */

$ad = $ad ?? [];
?>
<div id="ad-item-<?php echo esc_attr($index); ?>"
    class="ad-item"
     aria-label="<?php esc_attr_e('Mục quảng cáo', 'extend-affiliate-ads'); ?>">
    <div class="ad-fields">
        <input type="text"
               name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][label]"
               value="<?php echo esc_attr($ad['label'] ?? ''); ?>"
               placeholder="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-affiliate-ads'); ?>"
               class="regular-text" aria-label="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-affiliate-ads'); ?>" />

        <input type="url"
               name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][link]"
               value="<?php echo esc_attr($ad['link'] ?? ''); ?>"
               placeholder="<?php esc_attr_e('Liên kết affiliate', 'extend-affiliate-ads'); ?>"
               class="regular-text" aria-label="<?php esc_attr_e('Liên kết affiliate', 'extend-affiliate-ads'); ?>" />

        <div class="ad-image-field">
            <div class="group-option">
                <input type="hidden"
                       name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][image_id]"
                       class="ad-image-id"
                       value="<?php echo esc_attr($ad['image_id'] ?? 0); ?>" />

                <input type="text"
                       name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][image]"
                       value="<?php echo esc_attr($ad['image'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('Đường dẫn ảnh', 'extend-affiliate-ads'); ?>"
                       class="regular-text ad-image-url" aria-label="" />

                <button type="button" class="button select-ad-image">
                    <?php esc_html_e('Chọn ảnh', 'extend-affiliate-ads'); ?>
                </button>
            </div>

            <div class="ad-image-preview">
                <?php if (!empty($ad['image'])) : ?>
                    <img src="<?php echo esc_url($ad['image']); ?>" alt="">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="ad-actions">
        <label class="ad-toggle">
            <input type="checkbox"
                   name="extend_affiliate_ads_settings[ads][<?php echo esc_attr($index); ?>][active]"
                   value="1"
                <?php checked(isset($ad['active']) && (int)$ad['active'] === 1); ?> />

            <?php esc_html_e('Kích hoạt', 'extend-affiliate-ads'); ?>
        </label>

        <button type="button" class="button remove-ad">
            <?php esc_html_e('Xóa', 'extend-affiliate-ads'); ?>
        </button>
    </div>
</div>