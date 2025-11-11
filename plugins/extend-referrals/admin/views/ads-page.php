<?php
/**
 * Trang cài đặt quảng cáo Affiliate
 *
 * @package ExtendReferrals\Admin
 */

// Lấy dữ liệu quảng cáo đã lưu
use ExtendReferrals\Admin\Pages\AdsSettingsPage;

$settings = AdsSettingsPage::get_options();
$ads = $settings['ads'] ?? [];

// Giá trị TTL global
$ttl_value = isset($settings['ttl']) ? (int) $settings['ttl'] : 10;
?>

<div class="wrap extend-referrals-settings">
    <h1><?php echo esc_html__('Cài đặt quảng cáo', 'extend-referrals'); ?></h1>

    <p class="description">
        <?php echo esc_html__('Thêm, chỉnh sửa hoặc tạm tắt các quảng cáo hiển thị trên website. Mỗi quảng cáo gồm tiêu đề, liên kết, ảnh và trạng thái kích hoạt.', 'extend-referrals'); ?>
    </p>

    <form method="post" action="options.php">
        <?php settings_fields(AdsSettingsPage::OPTION_GROUP); ?>

        <div class="action-top">
            <!-- Thanh công cụ quảng cáo -->
            <div class="ads-toolbar">
                <button type="button" class="button toggle-all-ads">
                    <?php esc_html_e('Tắt tất cả quảng cáo', 'extend-referrals'); ?>
                </button>

                <input type="hidden" id="text-enable-all" value="<?php esc_attr_e('Bật tất cả quảng cáo', 'extend-referrals'); ?>">
                <input type="hidden" id="text-disable-all" value="<?php esc_attr_e('Tắt tất cả quảng cáo', 'extend-referrals'); ?>">
            </div>

            <!-- TTL global -->
            <div class="ttl-setting">
                <label for="<?php echo esc_attr( AdsSettingsPage::OPTION_KEY ) . '_ttl'; ?>" class="ttl-label">
                    <?php esc_html_e('TTL (Time To Live):', 'extend-referrals'); ?>
                </label>

                <input type="number"
                       id="<?php echo esc_attr( AdsSettingsPage::OPTION_KEY ) . '_ttl'; ?>"
                       name="<?php echo esc_attr( AdsSettingsPage::OPTION_KEY ) . '[ttl]'; ?>"
                       value="<?php echo esc_attr($ttl_value); ?>"
                       min="1"
                       step="1"
                       class="small-text" />

                <span class="ttl-unit"><?php esc_html_e('phút', 'extend-referrals'); ?></span>

                <p class="note">
                    <?php esc_html_e('Thời gian cache quảng cáo. Mặc định 10 phút. Giảm giá trị này nếu bạn thường xuyên cập nhật nội dung quảng cáo.', 'extend-referrals'); ?>
                </p>
            </div>
        </div>

        <!-- Danh sách quảng cáo -->
        <div id="affiliate-ads-list" class="affiliate-ads-list" aria-label="<?php esc_attr_e('Danh sách quảng cáo', 'extend-referrals'); ?>">
            <?php if (!empty($ads)) : ?>
                <?php foreach ($ads as $index => $ad) : ?>
                    <?php
                    $is_template = false;
                    include EXTEND_REFERRALS_PATH . 'admin/views/partials/ad-item.php';
                    ?>
                <?php endforeach; ?>
            <?php else : ?>
                <?php
                // Nếu chưa có quảng cáo nào → hiển thị 1 ô trống đầu tiên
                $index = 0;
                $is_template = false;
                include EXTEND_REFERRALS_PATH . 'admin/views/partials/ad-item.php';
                ?>
            <?php endif; ?>
        </div>

        <!-- Nút thêm mới -->
        <p class="add-ad-wrapper">
            <button type="button" class="button add-ad">
                <?php esc_html_e('Thêm quảng cáo mới', 'extend-referrals'); ?>
            </button>
        </p>

        <?php submit_button(esc_html__('Lưu thay đổi', 'extend-referrals')); ?>
    </form>
</div>

<!-- Template ẩn -->
<template id="affiliate-ad-template">
    <?php
    $index = '__INDEX__';
    $is_template = true;
    $ad = [];

    include EXTEND_REFERRALS_PATH . 'admin/views/partials/ad-item.php';
    ?>
</template>
