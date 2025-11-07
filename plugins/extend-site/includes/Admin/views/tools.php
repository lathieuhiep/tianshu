<?php
/**
 * @var array $tools  Danh sách các class implements ToolInterface
 * @var string|null $message  Thông báo sau khi chạy tool
 */

?>
<div class="wrap">
    <h1><?php esc_html_e('Công cụ hệ thống', 'extend-site'); ?></h1>
    <p><?php esc_html_e('Chọn một công cụ bên dưới để thực thi.', 'extend-site'); ?></p>
    <hr/>

    <?php if (!empty($message)) : ?>
        <div class="updated notice"><p><strong><?php echo esc_html($message); ?></strong></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('run_tool_action', 'run_tool_nonce'); ?>
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e('Tên công cụ', 'extend-site'); ?></th>
                <th><?php esc_html_e('Mô tả', 'extend-site'); ?></th>
                <th><?php esc_html_e('Hành động', 'extend-site'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tools as $key => $tool_class): ?>
                <tr>
                    <td><strong><?php echo esc_html($tool_class::get_title()); ?></strong></td>
                    <td><?php echo esc_html($tool_class::get_description()); ?></td>
                    <td>
                        <button type="submit" name="run_tool" value="<?php echo esc_attr($key); ?>" class="button button-primary">
                            <?php esc_html_e('Chạy ngay', 'extend-site'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form>

</div>