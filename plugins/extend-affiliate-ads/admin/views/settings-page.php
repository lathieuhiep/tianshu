<?php
/**
 * Trang cài đặt quảng cáo Affiliate
 *
 * @package ExtendAffiliateAds\Admin
 */
?>

<div class="wrap extend-affiliate-ads-settings">
    <h1><?php echo esc_html__('Cài đặt quảng cáo Affiliate', 'extend-affiliate-ads'); ?></h1>

    <p class="description">
        <?php echo esc_html__('Thêm, chỉnh sửa hoặc tạm tắt các quảng cáo hiển thị trên website. Mỗi quảng cáo gồm tiêu đề, liên kết, ảnh và trạng thái kích hoạt.', 'extend-affiliate-ads'); ?>
    </p>

    <form method="post" action="options.php">
        <?php settings_fields('extend_affiliate_ads_settings'); ?>

        <div id="affiliate-ads-list" class="affiliate-ads-list" aria-label="<?php esc_attr_e('Danh sách quảng cáo', 'extend-affiliate-ads'); ?>">
            <?php
            // Hiển thị mục quảng cáo đầu tiên (index = 0)
            $index = 0;
            $is_template = false;
            include EXTEND_AFFILIATE_ADS_PATH . 'admin/views/partials/ad-item.php';
            ?>
        </div>

        <!-- Nút thêm mới -->
        <p class="add-ad-wrapper">
            <button type="button" class="button add-ad">
                <?php esc_html_e('Thêm quảng cáo mới', 'extend-affiliate-ads'); ?>
            </button>
        </p>

        <?php submit_button(esc_html__('Lưu thay đổi', 'extend-affiliate-ads')); ?>
    </form>
</div>

<!-- Template ẩn -->
<template id="affiliate-ad-template">
    <?php
    $index = '__INDEX__';
    $is_template = true;
    include EXTEND_AFFILIATE_ADS_PATH . 'admin/views/partials/ad-item.php';
    ?>
</template>
