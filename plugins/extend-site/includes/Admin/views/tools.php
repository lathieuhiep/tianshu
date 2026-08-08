<?php
/**
 * @var array $tool_rows
 * @var string|null $message
 * @var array $formatted_jobs
 */
?>
<div class="wrap">
    <h1><?php esc_html_e('Công cụ hệ thống', 'extend-site'); ?></h1>
    <p><?php esc_html_e('Chọn một công cụ bên dưới để thực thi.', 'extend-site'); ?></p>
    <hr/>

    <?php if (!empty($message)) : ?>
        <div class="updated notice"><p><strong><?php echo esc_html($message); ?></strong></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e('Đồng bộ trạng thái chương', 'extend-site'); ?></h2>
    <form method="post" id="es-status-sync-form" style="margin-bottom: 24px;">
        <?php wp_nonce_field('create_status_sync_job_action', 'create_status_sync_job_nonce'); ?>
        <input type="hidden" name="create_status_sync_job" value="1" />
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="sync_story_id"><?php esc_html_e('Truyện cần đồng bộ', 'extend-site'); ?></label>
                </th>
                <td>
                    <select
                        id="sync_story_id"
                        name="sync_story_id"
                        class="regular-text"
                        data-es-ajax-select
                        data-es-type="story"
                        data-placeholder="<?php echo esc_attr__('Nhập tên truyện...', 'extend-site'); ?>"
                    ></select>
                    <p class="description"><?php esc_html_e('Chọn một truyện, hệ thống sẽ tạo job nền để cập nhật trạng thái toàn bộ chương của truyện đó.', 'extend-site'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Trạng thái áp dụng', 'extend-site'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="sync_status_mode" value="story" checked />
                            <?php esc_html_e('Theo trạng thái hiện tại của truyện', 'extend-site'); ?>
                        </label><br/>
                        <label>
                            <input type="radio" name="sync_status_mode" value="publish" />
                            <?php esc_html_e('Xuất bản toàn bộ chương', 'extend-site'); ?>
                        </label><br/>
                        <label>
                            <input type="radio" name="sync_status_mode" value="draft" />
                            <?php esc_html_e('Đưa toàn bộ chương về bản nháp', 'extend-site'); ?>
                        </label>
                    </fieldset>
                    <p class="description"><?php esc_html_e('Nếu chọn theo trạng thái truyện: truyện đang xuất bản thì chương sẽ xuất bản, các trạng thái khác sẽ đưa chương về bản nháp.', 'extend-site'); ?></p>
                </td>
            </tr>
            </tbody>
        </table>
        <p>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Tạo job đồng bộ', 'extend-site'); ?>
            </button>
            <span id="es-status-sync-message" style="margin-left: 8px;"></span>
        </p>
    </form>

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
            <?php foreach ($tool_rows as $tool): ?>
                <tr>
                    <td><strong><?php echo esc_html((string) ($tool['title'] ?? '')); ?></strong></td>
                    <td><?php echo esc_html((string) ($tool['description'] ?? '')); ?></td>
                    <td>
                        <button type="submit" name="run_tool" value="<?php echo esc_attr((string) ($tool['key'] ?? '')); ?>" class="button button-primary">
                            <?php esc_html_e('Chạy ngay', 'extend-site'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <style>
        .es-job-progress {
            background: #dcdcde;
            border-radius: 4px;
            height: 8px;
            margin-top: 6px;
            max-width: 180px;
            overflow: hidden;
        }
        .es-job-progress__bar {
            background: #2271b1;
            height: 100%;
            min-width: 6px;
            transition: width 280ms ease;
        }
        .es-job-progress__bar.is-active {
            animation: esJobProgress 900ms linear infinite;
            background-image: linear-gradient(45deg, rgba(255,255,255,.26) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.26) 50%, rgba(255,255,255,.26) 75%, transparent 75%, transparent);
            background-size: 16px 16px;
        }
        @keyframes esJobProgress {
            from { background-position: 0 0; }
            to { background-position: 16px 0; }
        }
    </style>

    <h2 id="es-system-jobs"><?php esc_html_e('Tiến trình job nền', 'extend-site'); ?></h2>
    <table class="widefat striped">
        <thead>
        <tr>
            <th><?php esc_html_e('Mã job', 'extend-site'); ?></th>
            <th><?php esc_html_e('Loại job', 'extend-site'); ?></th>
            <th><?php esc_html_e('Đối tượng', 'extend-site'); ?></th>
            <th><?php esc_html_e('Trạng thái', 'extend-site'); ?></th>
            <th><?php esc_html_e('Tiến trình', 'extend-site'); ?></th>
            <th><?php esc_html_e('Cập nhật lúc', 'extend-site'); ?></th>
            <th><?php esc_html_e('Thông báo', 'extend-site'); ?></th>
        </tr>
        </thead>
        <tbody id="es-system-jobs-body">
        <?php if (empty($formatted_jobs)) : ?>
            <tr>
                <td colspan="7"><?php esc_html_e('Chưa có job nền nào.', 'extend-site'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($formatted_jobs as $job) : ?>
                <tr>
                    <td><code><?php echo esc_html($job['id']); ?></code></td>
                    <td><?php echo esc_html($job['type_label']); ?></td>
                    <td><?php echo esc_html($job['subject_label']); ?></td>
                    <td><?php echo esc_html($job['status_label']); ?></td>
                    <td>
                        <?php echo esc_html($job['progress_label']); ?>
                        <div class="es-job-progress" aria-hidden="true">
                            <div class="es-job-progress__bar <?php echo $job['is_active'] ? 'is-active' : ''; ?>" style="width: <?php echo esc_attr((string) $job['percent']); ?>%"></div>
                        </div>
                    </td>
                    <td><?php echo esc_html($job['updated_at']); ?></td>
                    <td><?php echo esc_html($job['message']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
