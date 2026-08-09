<?php

defined('ABSPATH') || exit;

$page_url = isset($view_data['page_url']) ? (string)$view_data['page_url'] : '';
$page_slug = isset($view_data['page_slug']) ? (string)$view_data['page_slug'] : '';
$notice = isset($view_data['notice']) && is_array($view_data['notice']) ? $view_data['notice'] : [];
$import_modes = isset($view_data['import_modes']) && is_array($view_data['import_modes']) ? $view_data['import_modes'] : [];
$default_import_mode = isset($view_data['default_import_mode']) ? (string)$view_data['default_import_mode'] : 'update';
?>
<div class="wrap es-crawler-template-page">
    <h1><?php esc_html_e('Import/Export mẫu crawler', 'extend-site'); ?></h1>
    <p><?php esc_html_e('Xuất file JSON để sao lưu hoặc chuyển mẫu crawler sang website khác. Import dùng template key để cập nhật đúng mẫu giữa các môi trường và tránh tạo trùng ngoài ý muốn.', 'extend-site'); ?></p>
    <hr class="wp-header-end">

    <?php if (!empty($notice['message'])) : ?>
        <div class="notice <?php echo !empty($notice['is_error']) ? 'notice-error' : 'notice-success'; ?> is-dismissible">
            <p><?php echo esc_html((string)$notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <div class="es-template-list-card">
        <h2><?php esc_html_e('Export', 'extend-site'); ?></h2>
        <form method="post" action="<?php echo esc_url($page_url); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>"/>
            <?php wp_nonce_field('es_crawler_template_export_bulk', 'es_crawler_template_export_nonce'); ?>

            <p>
                <label for="es-crawler-template-export-select"><?php esc_html_e('Chọn mẫu crawler để export', 'extend-site'); ?></label>
                <select id="es-crawler-template-export-select"
                        class="regular-text"
                        name="template_ids[]"
                        multiple="multiple"
                        data-placeholder="<?php echo esc_attr__('Gõ để tìm mẫu crawler...', 'extend-site'); ?>"></select>
            </p>
            <p class="description"><?php esc_html_e('Gõ tên hoặc domain để chọn nhiều mẫu. Nếu muốn xuất toàn bộ mẫu đang hoạt động, dùng nút export tất cả.', 'extend-site'); ?></p>

            <p>
                <button type="submit" class="button button-primary" name="es_crawler_template_export_selected"
                        value="1">
                    <?php esc_html_e('Export mẫu đã chọn', 'extend-site'); ?>
                </button>
                <button type="submit" class="button" name="es_crawler_template_export_all" value="1">
                    <?php esc_html_e('Export tất cả mẫu đang hoạt động', 'extend-site'); ?>
                </button>
            </p>
        </form>
    </div>

    <div class="es-template-list-card">
        <h2><?php esc_html_e('Import', 'extend-site'); ?></h2>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($page_url); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>"/>
            <?php wp_nonce_field('es_crawler_template_import', 'es_crawler_template_import_nonce'); ?>
            <p>
                <label for="es-crawler-template-import-file"><?php esc_html_e('File JSON', 'extend-site'); ?></label><br>
                <input type="file" id="es-crawler-template-import-file" name="es_crawler_template_import_file"
                       accept="application/json,.json"/>
            </p>
            <?php if ($import_modes) : ?>
                <fieldset>
                    <legend><?php esc_html_e('Chế độ import', 'extend-site'); ?></legend>
                    <?php foreach ($import_modes as $mode => $label) : ?>
                        <label>
                            <input type="radio" name="import_mode" value="<?php echo esc_attr((string)$mode); ?>" <?php checked($default_import_mode, (string)$mode); ?>/>
                            <?php echo esc_html((string)$label); ?>
                        </label><br>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>
            <p class="description"><?php esc_html_e('Hỗ trợ file export một mẫu hoặc file export nhiều mẫu. Mặc định sẽ cập nhật mẫu đang hoạt động có cùng template key; file cũ chưa có key sẽ thử khớp chính xác theo tên và domain.', 'extend-site'); ?></p>
            <p>
                <button type="submit" class="button button-primary" name="es_crawler_template_import" value="1">
                    <?php esc_html_e('Import JSON', 'extend-site'); ?>
                </button>
            </p>
        </form>
    </div>
</div>
