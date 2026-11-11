<?php
/**
 * Reusable story card for front-page grids.
 *
 * @var array<string,mixed> $args Component arguments.
 *
 * @package tianshu
 */

use ExtendSite\Views\ViewTracker;

defined('ABSPATH') || exit;

$story_id = absint($args['story_id'] ?? 0);
if (!$story_id || !class_exists(ViewTracker::class)) {
    return;
}

$story_title = get_the_title($story_id);
$story_url = get_permalink($story_id);
$latest_chapter = isset($args['latest_chapter']) && is_array($args['latest_chapter'])
    ? $args['latest_chapter']
    : null;
$image_size = sanitize_key((string) ($args['image_size'] ?? 'large'));
$status_label = sanitize_text_field((string) ($args['status_label'] ?? ''));
$story_views = ViewTracker::format_short(ViewTracker::get_story_views($story_id));
$modified_time = get_post_modified_time('U', true, $story_id);
?>
<article class="home-story-card">
    <div class="home-story-card__media">
        <?php if ('' !== $status_label) : ?>
            <span class="home-story-card__status"><?php echo esc_html($status_label); ?></span>
        <?php endif; ?>

        <a
            class="home-story-card__thumbnail"
            href="<?php echo esc_url($story_url); ?>"
            title="<?php echo esc_attr($story_title); ?>"
        >
            <?php if (has_post_thumbnail($story_id)) : ?>
                <?php echo get_the_post_thumbnail($story_id, $image_size); ?>
            <?php else : ?>
                <img
                    src="<?php echo esc_url(get_theme_file_uri('/assets/images/no-image.png')); ?>"
                    alt="<?php echo esc_attr($story_title); ?>"
                >
            <?php endif; ?>
        </a>

        <div class="home-story-card__views">
            <i class="es-ic-mask es-ic-mask-eye" aria-hidden="true"></i>
            <span><?php echo esc_html($story_views); ?></span>
        </div>
    </div>

    <div class="home-story-card__detail">
        <h3 class="home-story-card__title">
            <a href="<?php echo esc_url($story_url); ?>"><?php echo esc_html($story_title); ?></a>
        </h3>

        <div class="home-story-card__info">
            <?php if ($latest_chapter) : ?>
                <a
                    class="home-story-card__chapter"
                    href="<?php echo esc_url($latest_chapter['url']); ?>"
                >
                    <?php
                    printf(
                        esc_html__('Chương %1$d: %2$s', 'tianshu'),
                        absint($latest_chapter['number']),
                        esc_html($latest_chapter['title'])
                    );
                    ?>
                </a>
            <?php endif; ?>

            <?php if ($modified_time) : ?>
                <time datetime="<?php echo esc_attr(get_post_modified_time('c', true, $story_id)); ?>">
                    <?php
                    printf(
                        esc_html__('%s trước', 'tianshu'),
                        esc_html(human_time_diff($modified_time, current_time('timestamp', true)))
                    );
                    ?>
                </time>
            <?php endif; ?>
        </div>
    </div>
</article>
