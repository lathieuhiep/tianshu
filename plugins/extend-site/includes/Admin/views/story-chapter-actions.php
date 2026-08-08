<?php
/**
 * @var string $add_url
 * @var string $list_url
 * @var string $wrapper_class
 * @var string $add_label
 * @var string $list_label
 * @var string $button_size_class
 */

defined('ABSPATH') || exit;

$wrapper_class = isset($wrapper_class) ? (string) $wrapper_class : 'story-chapter-actions';
$button_size_class = isset($button_size_class) ? trim((string) $button_size_class) : '';
$button_class = trim('button ' . $button_size_class);
$primary_button_class = trim('button button-primary ' . $button_size_class);
?>
<div class="<?php echo esc_attr($wrapper_class); ?>">
    <a href="<?php echo esc_url((string) $add_url); ?>" class="<?php echo esc_attr($primary_button_class); ?>">
        <?php echo esc_html((string) $add_label); ?>
    </a>
    <a href="<?php echo esc_url((string) $list_url); ?>" class="<?php echo esc_attr($button_class); ?>">
        <?php echo esc_html((string) $list_label); ?>
    </a>
</div>
