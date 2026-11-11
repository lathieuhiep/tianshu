<?php
/**
 * Reusable heading for home-page sections.
 *
 * @var array<string,mixed> $args Component arguments.
 *
 * @package tianshu
 */

defined('ABSPATH') || exit;

$title = isset($args['title']) ? (string) $args['title'] : '';
$title_id = isset($args['title_id']) ? sanitize_html_class((string) $args['title_id']) : '';
$url = isset($args['url']) ? (string) $args['url'] : '';
$link_label = isset($args['link_label']) ? (string) $args['link_label'] : '';

if ('' === $title) {
    return;
}
?>
<div class="home-section-heading">
    <h2
        class="home-section-heading__title"
        <?php if ('' !== $title_id) : ?>id="<?php echo esc_attr($title_id); ?>"<?php endif; ?>
    ><?php echo esc_html($title); ?></h2>

    <?php if ('' !== $url && '' !== $link_label) : ?>
        <a class="home-section-heading__link home-section-more" href="<?php echo esc_url($url); ?>">
            <?php echo esc_html($link_label); ?>
        </a>
    <?php endif; ?>
</div>
