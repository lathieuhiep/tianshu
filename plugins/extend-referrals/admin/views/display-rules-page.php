<?php
/**
 * View: Thiết lập hiển thị quảng cáo đối tác
 *
 * @var array $post_types Danh sách post type (key => label)
 * @var array $selected   Danh sách post type đã chọn
 */

use ExtendReferrals\Admin\Pages\DisplayRulesPage;

defined('ABSPATH') || exit;
?>
<div class="wrap extend-referrals-display-rules">
    <h1><?php esc_html_e('Thiết lập hiển thị quảng cáo đối tác', 'extend-referrals'); ?></h1>

    <p class="description">
        <?php esc_html_e('Chọn loại nội dung mà quảng cáo đối tác được phép hiển thị. Ví dụ: Bài viết, Chương truyện…', 'extend-referrals'); ?>
    </p>

    <form method="post" action="options.php" class="er-display-rules-form">
        <?php settings_fields(DisplayRulesPage::OPTION_GROUP); ?>

        <div class="er-actions">
            <button type="button" class="button er-select-all">
                <?php esc_html_e('Chọn tất cả', 'extend-referrals'); ?>
            </button>
            <button type="button" class="button er-unselect-all">
                <?php esc_html_e('Bỏ chọn tất cả', 'extend-referrals'); ?>
            </button>
        </div>

        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><?php esc_html_e('Loại nội dung', 'extend-referrals'); ?></th>
                <td>
                    <?php if (!empty($post_types)) : ?>
                        <ul class="er-post-type-list">
                            <?php foreach ($post_types as $slug => $label) : ?>
                                <li class="er-post-type-item">
                                    <label>
                                        <input type="checkbox"
                                               name="<?php echo esc_attr(DisplayRulesPage::OPTION_KEY); ?>[]"
                                               value="<?php echo esc_attr($slug); ?>"
                                            <?php checked(in_array($slug, $selected, true)); ?>>
                                        <span class="er-label-text"><?php echo esc_html($label); ?></span>
                                        <span class="er-slug">(
                                                <?php echo esc_html($slug); ?>)</span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e('Không tìm thấy post type công khai nào.', 'extend-referrals'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(esc_html__('Lưu thay đổi', 'extend-referrals')); ?>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('.er-display-rules-form');
        if (!form) return;

        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        const selectAllBtn = form.querySelector('.er-select-all');
        const unselectAllBtn = form.querySelector('.er-unselect-all');

        if (selectAllBtn && unselectAllBtn) {
            selectAllBtn.addEventListener('click', () => {
                checkboxes.forEach(cb => cb.checked = true);
            });
            unselectAllBtn.addEventListener('click', () => {
                checkboxes.forEach(cb => cb.checked = false);
            });
        }
    });
</script>
