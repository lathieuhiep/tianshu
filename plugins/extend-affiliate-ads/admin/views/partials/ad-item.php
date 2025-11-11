<?php
/**
 * Partial: ad-item
 *
 * @var string $index
 * @var bool   $is_template
 */

use ExtendAffiliateAds\Repository\SettingsRepository;

defined('ABSPATH') || exit;

// đảm bảo biến $ad luôn tồn tại
$ad = $ad ?? [];

// name attribute based on index
$name_control = SettingsRepository::field_name("[ads][$index]");

// trạng thái kích hoạt
$is_active = isset($ad['active'])
    ? (int) $ad['active'] === 1
    : (empty($ad) || $is_template);

// readonly nếu không kích hoạt
$readonly = ! $is_active ? 'readonly' : '';
$ad_item_class = $is_active ? 'ad-item' : 'ad-item ad-item--inactive';
?>
<div id="ad-item-<?php echo esc_attr($index); ?>"
     class="<?php echo esc_attr($ad_item_class); ?>"
     aria-label="<?php esc_attr_e('Mục quảng cáo', 'extend-affiliate-ads'); ?>">
    <div class="ad-fields">
        <input type="text"
               name="<?php echo esc_attr( $name_control . '[label]' ); ?>"
               value="<?php echo esc_attr($ad['label'] ?? ''); ?>"
               placeholder="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-affiliate-ads'); ?>"
               class="regular-text" aria-label="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-affiliate-ads'); ?>"
                <?php echo esc_attr($readonly); ?> />

        <input type="url"
               name="<?php echo esc_attr( $name_control . '[link]' ); ?>"
               value="<?php echo esc_attr($ad['link'] ?? ''); ?>"
               placeholder="<?php esc_attr_e('Liên kết affiliate', 'extend-affiliate-ads'); ?>"
               class="regular-text" aria-label="<?php esc_attr_e('Liên kết affiliate', 'extend-affiliate-ads'); ?>"
                <?php echo esc_attr( $readonly ); ?> />

        <div class="ad-image-field">
            <div class="group-option">
                <input type="hidden"
                       name="<?php echo esc_attr( $name_control . '[image_id]' ); ?>"
                       class="ad-image-id"
                       value="<?php echo esc_attr($ad['image_id'] ?? 0); ?>"
                        <?php echo esc_attr( $readonly ); ?> />

                <input type="text"
                       name="<?php echo esc_attr( $name_control . '[image]' ); ?>"
                       value="<?php echo esc_attr($ad['image'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('Đường dẫn ảnh', 'extend-affiliate-ads'); ?>"
                       class="regular-text ad-image-url"
                       aria-label="<?php esc_attr_e('Đường dẫn ảnh', 'extend-affiliate-ads'); ?>"
                        <?php echo esc_attr( $readonly ); ?> />

                <button type="button"
                        class="button select-ad-image"

                >
                    <?php esc_html_e('Chọn ảnh', 'extend-affiliate-ads'); ?>
                </button>
            </div>

            <div class="ad-image-preview">
                <?php if (!empty($ad['image'])) : ?>
                    <img src="<?php echo esc_url($ad['image']); ?>" alt="<?php echo esc_attr($ad['label'] ?? ''); ?>">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="ad-actions">
        <label class="ad-toggle">
            <input type="checkbox"
                   name="<?php echo esc_attr( $name_control . '[active]' ); ?>"
                   value="1" <?php checked(1, $is_active); ?> />

            <?php esc_html_e('Kích hoạt', 'extend-affiliate-ads'); ?>
        </label>

        <button type="button" class="button remove-ad">
            <?php esc_html_e('Xóa', 'extend-affiliate-ads'); ?>
        </button>
    </div>
</div>