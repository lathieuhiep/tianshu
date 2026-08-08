<?php
/**
 * @var string $option_name
 * @var string $value
 */

defined('ABSPATH') || exit;
?>
<code>/%story%/</code>
<input
    name="<?php echo esc_attr((string) $option_name); ?>"
    id="<?php echo esc_attr((string) $option_name); ?>"
    type="text"
    class="regular-text code"
    value="<?php echo esc_attr((string) $value); ?>"
/>
<p class="description">
    <?php esc_html_e('Nhập phần URL sau slug truyện. Ví dụ: chuong/%postname%/ sẽ tạo URL /slug-truyen/chuong/slug-chuong/. Thẻ hỗ trợ: %postname%, %post_id%.', 'extend-site'); ?>
</p>
