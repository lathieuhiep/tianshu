<?php
/**
 * Partial: partner-item
 *
 * @var string $index
 * @var bool   $is_template
 */

use ExtendReferrals\Admin\Pages\AdsSettingsPage;

defined('ABSPATH') || exit;

// đảm bảo biến $ad luôn tồn tại
$ad = $ad ?? [];

// name attribute based on index
$name_control = AdsSettingsPage::field_name("[ads][$index]");

// trạng thái kích hoạt
$is_active = isset($ad['active'])
    ? (int) $ad['active'] === 1
    : (empty($ad) || $is_template);

// readonly nếu không kích hoạt
$readonly = ! $is_active ? 'readonly' : '';
$ad_item_class = $is_active ? 'partner-item' : 'partner-item partner-item--inactive';
?>
<div id="partner-item-<?php echo esc_attr($index); ?>"
     class="<?php echo esc_attr($ad_item_class); ?>"
     aria-label="<?php esc_attr_e('Mục quảng cáo', 'extend-referrals'); ?>">
    <div class="fields">
        <input type="text"
               name="<?php echo esc_attr( $name_control . '[label]' ); ?>"
               value="<?php echo esc_attr($ad['label'] ?? ''); ?>"
               placeholder="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-referrals'); ?>"
               class="regular-text" aria-label="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-referrals'); ?>"
                <?php echo esc_attr($readonly); ?> />

        <input type="text"
               name="<?php echo esc_attr( $name_control . '[sub_title]' ); ?>"
               value="<?php echo esc_attr($ad['sub_title'] ?? ''); ?>"
               placeholder="<?php esc_attr_e('Tiêu đề phụ', 'extend-referrals'); ?>"
               class="regular-text" aria-label="<?php esc_attr_e('Tiêu đề quảng cáo', 'extend-referrals'); ?>"
            <?php echo esc_attr($readonly); ?> />

        <input type="url"
               name="<?php echo esc_attr( $name_control . '[link]' ); ?>"
               value="<?php echo esc_attr($ad['link'] ?? ''); ?>"
               placeholder="<?php esc_attr_e('Liên kết affiliate', 'extend-referrals'); ?>"
               class="regular-text" aria-label="<?php esc_attr_e('Liên kết affiliate', 'extend-referrals'); ?>"
                <?php echo esc_attr( $readonly ); ?> />

        <div class="image-field">
            <div class="group-option">
                <input type="hidden"
                       name="<?php echo esc_attr( $name_control . '[image_id]' ); ?>"
                       class="image-id"
                       value="<?php echo esc_attr($ad['image_id'] ?? 0); ?>"
                        <?php echo esc_attr( $readonly ); ?> />

                <input type="text"
                       name="<?php echo esc_attr( $name_control . '[image]' ); ?>"
                       value="<?php echo esc_attr($ad['image'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('Đường dẫn ảnh', 'extend-referrals'); ?>"
                       class="regular-text image-url"
                       aria-label="<?php esc_attr_e('Đường dẫn ảnh', 'extend-referrals'); ?>"
                        <?php echo esc_attr( $readonly ); ?> />

                <button type="button" class="button select-image">
                    <?php esc_html_e('Chọn ảnh', 'extend-referrals'); ?>
                </button>
            </div>

            <div class="image-preview">
                <?php if (!empty($ad['image'])) : ?>
                    <img src="<?php echo esc_url($ad['image']); ?>" alt="<?php echo esc_attr($ad['label'] ?? ''); ?>">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="actions">
        <label class="toggle-check">
            <input type="checkbox"
                   name="<?php echo esc_attr( $name_control . '[active]' ); ?>"
                   value="1" <?php checked(1, $is_active); ?> />

            <?php esc_html_e('Kích hoạt', 'extend-referrals'); ?>
        </label>

        <button type="button" class="button remove-ad">
            <?php esc_html_e('Xóa', 'extend-referrals'); ?>
        </button>
    </div>
</div>