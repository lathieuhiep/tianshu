<?php

defined('ABSPATH') || exit;

$templates = isset($view_data['templates']) && is_array($view_data['templates']) ? $view_data['templates'] : [];
$page_url = isset($view_data['page_url']) ? (string)$view_data['page_url'] : '';
$page_slug = isset($view_data['page_slug']) ? (string)$view_data['page_slug'] : '';
$notice = isset($view_data['notice']) && is_array($view_data['notice']) ? $view_data['notice'] : [];
$export_url_callback = $view_data['export_url_callback'] ?? null;
$format_datetime_callback = $view_data['format_datetime_callback'] ?? null;
?>
<div class="wrap es-crawler-template-page">
    <h1><?php esc_html_e('Import/Export mẫu crawler', 'extend-site'); ?></h1>
    <p><?php esc_html_e('Xuất file JSON để sao lưu hoặc chuyển mẫu crawler sang website khác. Import sẽ tạo mẫu mới và không ghi đè mẫu hiện có.', 'extend-site'); ?></p>
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
                <button type="submit" class="button button-primary" name="es_crawler_template_export_selected"
                        value="1">
                    <?php esc_html_e('Export mẫu đã chọn', 'extend-site'); ?>
                </button>
                <button type="submit" class="button" name="es_crawler_template_export_all" value="1">
                    <?php esc_html_e('Export tất cả mẫu đang hoạt động', 'extend-site'); ?>
                </button>
            </p>

            <table class="widefat striped">
                <thead>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" id="es-crawler-template-check-all"
                               onclick="document.querySelectorAll('.es-crawler-template-export-checkbox').forEach(function(item){ item.checked = this.checked; }, this);"/>
                    </td>
                    <th scope="col"><?php esc_html_e('Tên mẫu', 'extend-site'); ?></th>
                    <th scope="col"><?php esc_html_e('Domain', 'extend-site'); ?></th>
                    <th scope="col"><?php esc_html_e('Cập nhật', 'extend-site'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($templates) : ?>
                    <?php foreach ($templates as $template) : ?>
                        <?php
                        $template_id = isset($template['id']) ? (int)$template['id'] : 0;
                        $export_url = is_callable($export_url_callback) ? (string)call_user_func($export_url_callback, $template_id) : '#';
                        $updated_at = isset($template['updated_at']) ? (string)$template['updated_at'] : '';
                        $formatted_date = is_callable($format_datetime_callback) ? (string)call_user_func($format_datetime_callback, $updated_at) : $updated_at;
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input class="es-crawler-template-export-checkbox" type="checkbox" name="template_ids[]"
                                       value="<?php echo esc_attr((string)$template_id); ?>"/>
                            </th>
                            <td>
                                <strong><?php echo esc_html((string)($template['name'] ?? '')); ?></strong>
                                <div class="row-actions">
                                        <span class="export">
                                            <a href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export JSON', 'extend-site'); ?></a>
                                        </span>
                                </div>
                            </td>
                            <td><code><?php echo esc_html((string)($template['domain'] ?? '')); ?></code></td>
                            <td><?php echo esc_html($formatted_date); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('Chưa có mẫu crawler nào để export.', 'extend-site'); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
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
            <p class="description"><?php esc_html_e('Hỗ trợ file export một mẫu hoặc file export nhiều mẫu. Mỗi lần import sẽ tạo bản ghi mới.', 'extend-site'); ?></p>
            <p>
                <button type="submit" class="button button-primary" name="es_crawler_template_import" value="1">
                    <?php esc_html_e('Import JSON', 'extend-site'); ?>
                </button>
            </p>
        </form>
    </div>
</div>