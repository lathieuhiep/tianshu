<?php
/**
 * @var array $notice
 */

defined('ABSPATH') || exit;

$type = isset($notice['type']) && $notice['type'] === 'error' ? 'error' : 'success';
$title = isset($notice['title']) ? (string) $notice['title'] : '';
$message = isset($notice['message']) ? (string) $notice['message'] : '';
$action_url = isset($notice['action_url']) ? (string) $notice['action_url'] : '';
$action_label = isset($notice['action_label']) ? (string) $notice['action_label'] : '';
?>
<div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
    <?php if ($title !== '') : ?>
        <p><strong><?php echo esc_html($title); ?></strong></p>
    <?php endif; ?>

    <?php if ($message !== '') : ?>
        <p><?php echo esc_html($message); ?></p>
    <?php endif; ?>

    <?php if ($action_url !== '' && $action_label !== '') : ?>
        <p>
            <a class="button button-primary" href="<?php echo esc_url($action_url); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($action_label); ?>
            </a>
        </p>
    <?php endif; ?>
</div>