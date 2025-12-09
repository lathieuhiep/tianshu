<?php
/**
 * View: Thiết lập diều kiện hiển thị
 *
 * @var array $chapter Danh sách điều kiện hiển thị post type chapter
 * @var void $post   Danh sách điều kiện hiển thị post
 */

use ExtendReferrals\Admin\Pages\AdvancedRulesPage;

?>
<div class="wrap extend-referrals-advanced-rules">
    <h1><?php esc_html_e('Điều kiện hiển thị nâng cao', 'extend-referrals'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields(AdvancedRulesPage::OPTION_GROUP); ?>

        <h2>
            <?php esc_html_e('Chapter (áp dụng cho plugin Extend Referrals)', 'extend-referrals'); ?>
        </h2>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Kích hoạt', 'extend-referrals'); ?></th>
                <td>
                    <input type="checkbox"
                           name="<?php echo esc_attr(AdvancedRulesPage::OPTION_KEY_CHAPTER) . '[enabled]'; ?>"
                           value="1" <?php checked($chapter['enabled']); ?>
                           aria-label="" />
                </td>
            </tr>

            <tr>
                <th><?php esc_html_e('Chế độ', 'extend-referrals'); ?></th>

                <td>
                    <select id="ar-chapter-mode" name="<?php echo esc_attr(AdvancedRulesPage::OPTION_KEY_CHAPTER) . '[mode]'; ?>" aria-label="">
                        <option value="odd" <?php selected($chapter['mode'], 'odd'); ?>>
                            <?php esc_html_e('Chương lẻ', 'extend-referrals'); ?>
                        </option>

                        <option value="even" <?php selected($chapter['mode'], 'even'); ?>>
                            <?php esc_html_e('Chương chẵn', 'extend-referrals'); ?>
                        </option>

                        <option value="from_number" <?php selected($chapter['mode'], 'from_number'); ?>>
                            <?php esc_html_e('Từ chương X', 'extend-referrals'); ?>
                        </option>

                        <option value="only_list" <?php selected($chapter['mode'], 'only_list'); ?>>
                            <?php esc_html_e('Danh sách chương', 'extend-referrals'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr class="ar-row-from">
                <th><?php esc_html_e('Từ chương', 'extend-referrals'); ?></th>

                <td>
                    <input type="number"
                           name="<?php echo esc_attr(AdvancedRulesPage::OPTION_KEY_CHAPTER) . '[from]'; ?>"
                           value="<?php echo esc_attr($chapter['from']); ?>"
                           min="1" aria-label="" />
                </td>
            </tr>

            <tr class="ar-row-only-list">
                <th><?php esc_html_e('Danh sách chương', 'extend-referrals'); ?></th>

                <td>
                    <input type="text"
                           name="<?php echo esc_attr(AdvancedRulesPage::OPTION_KEY_CHAPTER) . '[only_list]'; ?>"
                           value="<?php echo esc_attr($chapter['only_list']); ?>"
                           placeholder="Ví dụ: 5,10,20" aria-label="" />
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>