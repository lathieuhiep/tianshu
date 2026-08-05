<?php

defined('ABSPATH') || exit;

$data = is_array($data ?? null) ? $data : [];

$links = is_array($data['chapter_link_samples'] ?? null) ? $data['chapter_link_samples'] : [];
$field_results = is_array($data['field_results'] ?? null) ? $data['field_results'] : [];
$ok_count = count(array_filter($field_results, static function ($item): bool {
    return is_array($item) && ($item['status'] ?? '') === 'ok';
}));
$missing_count = count(array_filter($field_results, static function ($item): bool {
    return is_array($item) && ($item['status'] ?? '') === 'missing';
}));
$story_cats = is_array($data['story_cats'] ?? null) ? implode(', ', $data['story_cats']) : '';
$chapter_link_count = (string) ($data['chapter_link_count'] ?? 0);
if (!empty($data['chapter_link_estimated'])) {
    $chapter_link_count .= ' ' . __('(ước tính)', 'extend-site');
}

$summary_items = [
    __('Kết quả test', 'extend-site') => sprintf(
        __('%1$d mục có dữ liệu, %2$d mục chưa tìm thấy', 'extend-site'),
        $ok_count,
        $missing_count
    ),
    __('Truyện', 'extend-site') => $data['story_title'] ?? __('(trống)', 'extend-site'),
    __('Tác giả', 'extend-site') => $data['story_author'] ?? __('(trống)', 'extend-site'),
    __('Link chương', 'extend-site') => $chapter_link_count,
    __('Trang mục lục', 'extend-site') => sprintf(
        '%1$s/%2$s',
        (string) ($data['toc_pages_scanned'] ?? 1),
        (string) ($data['toc_page_count'] ?? 0)
    ),
];

$rows = [
    __('Story URL', 'extend-site') => $data['target_url'] ?? '',
    __('Mô tả', 'extend-site') => $data['story_desc'] ?? '',
    __('Độ dài mô tả', 'extend-site') => $data['story_desc_length'] ?? 0,
    __('Ảnh bìa', 'extend-site') => $data['story_thumb'] ?? '',
    __('Thể loại', 'extend-site') => $story_cats,
    __('Tên chương', 'extend-site') => $data['chapter_title'] ?? '',
    __('Độ dài nội dung chương', 'extend-site') => $data['chapter_content_length'] ?? 0,
];

$groups = [];
foreach ($field_results as $item) {
    if (!is_array($item)) {
        continue;
    }

    $group = (string) ($item['group'] ?? __('Khác', 'extend-site'));
    $groups[$group][] = $item;
}
?>

<table class="widefat striped es-template-result-table es-template-summary-table">
    <tbody>
        <?php foreach ($summary_items as $label => $value) : ?>
            <tr>
                <th scope="row"><?php echo esc_html((string) $label); ?></th>
                <td><?php echo esc_html((string) $value); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($field_results && (int) ($data['chapter_link_count'] ?? 0) < 1) : ?>
    <p class="es-template-result-note">
        <?php esc_html_e('Chưa tạo được danh sách chương nếu không tìm thấy link chương trong HTML gốc.', 'extend-site'); ?>
    </p>
<?php endif; ?>

<table class="widefat striped es-template-result-table">
    <tbody>
        <?php foreach ($rows as $label => $value) : ?>
            <tr>
                <th scope="row"><?php echo esc_html((string) $label); ?></th>
                <td><?php echo esc_html(is_array($value) ? implode(', ', $value) : (string) $value); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($groups) : ?>
    <div class="es-template-field-results">
        <?php foreach ($groups as $group => $items) : ?>
            <section class="es-template-result-group">
                <h3><?php echo esc_html($group); ?></h3>
                <table class="widefat striped es-template-result-table es-template-field-result-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Trường', 'extend-site'); ?></th>
                            <th><?php esc_html_e('Trạng thái', 'extend-site'); ?></th>
                            <th><?php esc_html_e('Selector', 'extend-site'); ?></th>
                            <th><?php esc_html_e('Kết quả', 'extend-site'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $status = ($item['status'] ?? '') === 'ok' ? 'ok' : 'missing';
                            $samples = is_array($item['samples'] ?? null) ? $item['samples'] : [];
                            ?>
                            <tr class="es-template-field-row is-<?php echo esc_attr($status); ?>">
                                <th scope="row"><?php echo esc_html((string) ($item['label'] ?? '')); ?></th>
                                <td>
                                    <span class="es-template-status-pill is-<?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($status === 'ok' ? __('Có dữ liệu', 'extend-site') : __('Không thấy', 'extend-site')); ?>
                                    </span>
                                </td>
                                <td><code><?php echo esc_html((string) ($item['selector'] ?? '')); ?></code></td>
                                <td>
                                    <?php echo esc_html((string) ($item['result'] ?? '')); ?>
                                    <?php if ($samples) : ?>
                                        <div class="es-template-node-samples">
                                            <?php foreach ($samples as $sample) : ?>
                                                <?php
                                                $node = is_array($sample) ? (string) ($sample['node'] ?? '') : '';
                                                $text = is_array($sample) ? (string) ($sample['text'] ?? '') : '';
                                                ?>
                                                <div>
                                                    <code><?php echo esc_html($node); ?></code><?php echo $text !== '' ? ' - ' . esc_html($text) : ''; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($status === 'missing' && !empty($item['hint'])) : ?>
                                        <p class="es-template-result-hint"><?php echo esc_html((string) $item['hint']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($links) : ?>
    <h3><?php esc_html_e('Mẫu link chương', 'extend-site'); ?></h3>
    <table class="widefat striped es-template-result-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Text', 'extend-site'); ?></th>
                <th><?php esc_html_e('URL', 'extend-site'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($links as $item) : ?>
                <tr>
                    <td><?php echo esc_html(is_array($item) ? (string) ($item['text'] ?? '') : ''); ?></td>
                    <td><code><?php echo esc_html(is_array($item) ? (string) ($item['href'] ?? '') : ''); ?></code></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
