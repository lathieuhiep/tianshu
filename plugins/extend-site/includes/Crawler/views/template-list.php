<?php

defined('ABSPATH') || exit;

$search = isset($view_data['search']) ? (string)$view_data['search'] : '';
$status = isset($view_data['status']) ? (string)$view_data['status'] : 'active';
$paged = isset($view_data['paged']) ? (int)$view_data['paged'] : 1;
$total_pages = isset($view_data['total_pages']) ? (int)$view_data['total_pages'] : 1;
$total_items = isset($view_data['total_items']) ? (int)$view_data['total_items'] : 0;
$all_items = isset($view_data['all_items']) ? (int)$view_data['all_items'] : 0;
$trash_items = isset($view_data['trash_items']) ? (int)$view_data['trash_items'] : 0;
$templates = isset($view_data['templates']) && is_array($view_data['templates']) ? $view_data['templates'] : [];
$page_slug = isset($view_data['page_slug']) ? (string)$view_data['page_slug'] : '';
$notice = isset($view_data['notice']) && is_array($view_data['notice']) ? $view_data['notice'] : [];
$import_export_url = isset($view_data['import_export_url']) ? (string)$view_data['import_export_url'] : admin_url('admin.php');
$page_url_callback = $view_data['page_url_callback'] ?? null;
$template_action_url_callback = $view_data['template_action_url_callback'] ?? null;
$export_url_callback = $view_data['export_url_callback'] ?? null;
$format_datetime_callback = $view_data['format_datetime_callback'] ?? null;
$page_url = static function (array $args = []) use ($page_url_callback): string {
    return is_callable($page_url_callback) ? (string)call_user_func($page_url_callback, $args) : admin_url('admin.php');
};
$template_action_url = static function (string $action, int $template_id) use ($template_action_url_callback): string {
    return is_callable($template_action_url_callback) ? (string)call_user_func($template_action_url_callback, $action, $template_id) : '#';
};
$export_url = static function (int $template_id) use ($export_url_callback): string {
    return is_callable($export_url_callback) ? (string)call_user_func($export_url_callback, $template_id) : '#';
};
$format_datetime = static function (string $datetime) use ($format_datetime_callback): string {
    return is_callable($format_datetime_callback) ? (string)call_user_func($format_datetime_callback, $datetime) : $datetime;
};
?>
<div class="wrap es-crawler-template-page">
    <div class="es-template-list-heading">
        <div>
            <h1><?php esc_html_e('Mẫu crawler', 'extend-site'); ?></h1>
            <p><?php esc_html_e('Quản lý các mẫu bóc dữ liệu truyện, mục lục và nội dung chương.', 'extend-site'); ?></p>
        </div>
        <div class="es-template-list-actions">
            <a class="button" href="<?php echo esc_url($import_export_url); ?>">
                <?php esc_html_e('Import/Export', 'extend-site'); ?>
            </a>
            <a class="button button-primary" href="<?php echo esc_url($page_url(['action' => 'new'])); ?>">
                <?php esc_html_e('Thêm mẫu mới', 'extend-site'); ?>
            </a>
        </div>
    </div>
    <hr class="wp-header-end">
    <?php if (!empty($notice['message'])) : ?>
        <div class="notice <?php echo !empty($notice['is_error']) ? 'notice-error' : 'notice-success'; ?> is-dismissible">
            <p><?php echo esc_html((string)$notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <div class="es-template-list-card">
        <div class="es-template-list-toolbar">
            <ul class="subsubsub es-template-list-views">
                <li>
                    <a href="<?php echo esc_url($page_url($search !== '' ? ['s' => $search] : [])); ?>"
                       class="<?php echo $status === 'active' ? 'current' : ''; ?>" <?php echo $status === 'active' ? 'aria-current="page"' : ''; ?>>
                        <?php
                        printf(
                                '%s <span class="count">(%s)</span>',
                                esc_html__('Tất cả', 'extend-site'),
                                esc_html(number_format_i18n($all_items))
                        );
                        ?>
                    </a>
                </li>
                <li>
                    |
                    <a href="<?php echo esc_url($page_url(array_merge(['action' => 'trash'], $search !== '' ? ['s' => $search] : []))); ?>"
                       class="<?php echo $status === 'trash' ? 'current' : ''; ?>" <?php echo $status === 'trash' ? 'aria-current="page"' : ''; ?>>
                        <?php
                        printf(
                                '%s <span class="count">(%s)</span>',
                                esc_html__('Thùng rác', 'extend-site'),
                                esc_html(number_format_i18n($trash_items))
                        );
                        ?>
                    </a>
                </li>
                <?php if ($search !== '') : ?>
                    <li>
                        | <span class="current">
                            <?php
                            printf(
                                    '%s <span class="count">(%s)</span>',
                                    esc_html__('Kết quả tìm kiếm', 'extend-site'),
                                    esc_html(number_format_i18n($total_items))
                            );
                            ?>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="es-template-list-count">
                <?php
                printf(
                        esc_html__('%s mẫu crawler', 'extend-site'),
                        esc_html(number_format_i18n($total_items))
                );
                ?>
            </div>

            <form method="get" class="es-template-search-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>"/>
                <label class="screen-reader-text"
                       for="es-crawler-template-search-input"><?php esc_html_e('Tìm mẫu crawler', 'extend-site'); ?></label>
                <input type="search" id="es-crawler-template-search-input" name="s"
                       value="<?php echo esc_attr($search); ?>"
                       placeholder="<?php echo esc_attr__('Tìm theo tên hoặc domain', 'extend-site'); ?>"/>
                <input type="submit" class="button" value="<?php echo esc_attr__('Tìm', 'extend-site'); ?>"/>
                <?php if ($search !== '') : ?>
                    <a class="button"
                       href="<?php echo esc_url($page_url($status === 'trash' ? ['action' => 'trash'] : [])); ?>"><?php esc_html_e('Xóa lọc', 'extend-site'); ?></a>
                <?php endif; ?>
            </form>
        </div>

        <table class="widefat striped es-template-list-table">
            <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Tên mẫu', 'extend-site'); ?></th>
                <th scope="col"><?php esc_html_e('Domain', 'extend-site'); ?></th>
                <th scope="col"><?php esc_html_e('Cập nhật', 'extend-site'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($templates) : ?>
                <?php foreach ($templates as $template) : ?>
                    <?php $template_id = isset($template['id']) ? (int)$template['id'] : 0; ?>
                    <tr>
                        <td>
                            <strong>
                                <?php if ($status === 'trash') : ?>
                                    <?php echo esc_html((string)($template['name'] ?? '')); ?>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($page_url(['action' => 'edit', 'id' => $template_id])); ?>">
                                        <?php echo esc_html((string)($template['name'] ?? '')); ?>
                                    </a>
                                <?php endif; ?>
                            </strong>
                            <div class="row-actions es-template-row-actions <?php echo $status === 'trash' ? 'is-trash' : 'is-active'; ?>">
                                    <span class="edit">
                                        <a href="<?php echo esc_url($page_url(['action' => 'edit', 'id' => $template_id])); ?>">
                                            <?php esc_html_e('Sửa', 'extend-site'); ?>
                                        </a>
                                    </span>
                                <?php if ($status === 'trash') : ?>
                                    <span class="restore">
                                            <a href="<?php echo esc_url($template_action_url('restore_template', $template_id)); ?>">
                                                <?php esc_html_e('Khôi phục', 'extend-site'); ?>
                                            </a>
                                        </span>
                                    <span class="delete">
                                            | <a class="submitdelete"
                                                 href="<?php echo esc_url($template_action_url('force_delete_template', $template_id)); ?>"
                                                 onclick="return confirm('<?php echo esc_js(__('Xóa vĩnh viễn mẫu crawler này?', 'extend-site')); ?>');">
                                                <?php esc_html_e('Xóa vĩnh viễn', 'extend-site'); ?>
                                            </a>
                                        </span>
                                <?php else : ?>
                                    <span class="export">
                                            | <a href="<?php echo esc_url($export_url($template_id)); ?>">
                                                <?php esc_html_e('Export JSON', 'extend-site'); ?>
                                            </a>
                                        </span>
                                    <span class="trash">
                                            | <a class="submitdelete"
                                                 href="<?php echo esc_url($template_action_url('trash_template', $template_id)); ?>"
                                                 onclick="return confirm('<?php echo esc_js(__('Bỏ mẫu crawler này vào thùng rác?', 'extend-site')); ?>');">
                                                <?php esc_html_e('Bỏ vào thùng rác', 'extend-site'); ?>
                                            </a>
                                        </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><code><?php echo esc_html((string)($template['domain'] ?? '')); ?></code></td>
                        <td><?php echo esc_html($format_datetime((string)($template['updated_at'] ?? ''))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="3">
                        <?php echo $search !== '' ? esc_html__('Không tìm thấy mẫu crawler phù hợp.', 'extend-site') : esc_html($status === 'trash' ? __('Thùng rác đang trống.', 'extend-site') : __('Chưa có mẫu crawler nào.', 'extend-site')); ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(
                            paginate_links([
                                    'base' => add_query_arg('paged', '%#%', $page_url(array_merge($status === 'trash' ? ['action' => 'trash'] : [], $search !== '' ? ['s' => $search] : []))),
                                    'format' => '',
                                    'current' => $paged,
                                    'total' => $total_pages,
                                    'prev_text' => '&lsaquo;',
                                    'next_text' => '&rsaquo;',
                            ])
                    );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
