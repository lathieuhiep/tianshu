<?php
/**
 * @var string $fb_url url facebook
 */
?>

<div class="wrap">
    <h2>
        <?php echo esc_html($title ?? esc_html__('Cài đặt chung', 'extend-site')); ?>
    </h2>

    <p>
        <?php esc_html_e('Chào mừng bạn đến với khu vực quản lý hệ thống truyện, chương, tác giả.', 'extend-site'); ?>
    </p>

    <p>
        <strong><?php esc_html_e('Tip: Bạn có thể vào mục "Công cụ" để đồng bộ tổng chương.', 'extend-site'); ?></strong>
    </p>

    <form method="post">
        <?php wp_nonce_field('extend_site_save_options', 'extend_site_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="extend_site_last_chapter_facebook_url">
                        URL Facebook khi hết truyện
                    </label>
                </th>
                <td>
                    <input type="text"
                           id="extend_site_last_chapter_facebook_url"
                           name="extend_site_last_chapter_facebook_url"
                           value="<?php echo esc_attr($fb_url); ?>"
                           class="regular-text"
                           placeholder="https://facebook.com/..." />
                    <p class="description">
                        <?php esc_html_e('Khi truyện đến chương cuối, nút “Chương sau” sẽ trỏ tới URL này.', 'extend-site'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button('Lưu cài đặt'); ?>
    </form>
</div>