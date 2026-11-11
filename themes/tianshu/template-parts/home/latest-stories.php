<?php
/**
 * Latest stories grid and widget sidebar on the front page.
 *
 * @package tianshu
 */

use ExtendSite\Repositories\StoryRepository;
use ExtendSite\Views\ViewTracker;

defined('ABSPATH') || exit;

if (!class_exists(StoryRepository::class) || !class_exists(ViewTracker::class)) {
    return;
}

$settings = apply_filters('tianshu_home_latest_story_settings', [
    'title' => __('Truyện mới nhất', 'tianshu'),
    'limit' => tianshu_get_option('opt_home_latest_limit', 12),
    'image_size' => 'large',
]);

$settings['limit'] = max(1, min(36, absint($settings['limit'])));
$query = StoryRepository::get_latest($settings['limit']);

if (!$query->have_posts()) {
    return;
}

$story_ids = wp_list_pluck($query->posts, 'ID');
$latest_chapters = StoryRepository::get_latest_chapters($story_ids);
?>
<section class="home-latest-stories" aria-labelledby="home-latest-stories-title">
    <div class="home-latest-stories__layout">
        <div
            class="home-latest-stories__main"
            data-home-latest-stories
            data-config="<?php echo esc_attr(wp_json_encode([
                'limit' => $settings['limit'],
                'image_size' => $settings['image_size'],
                'fallback_image' => get_theme_file_uri('/assets/images/no-image.png'),
            ])); ?>"
        >
            <?php
            get_template_part(
                'components/home/section-heading',
                null,
                [
                    'title' => $settings['title'],
                    'title_id' => 'home-latest-stories-title',
                ]
            );
            ?>

            <div class="home-latest-stories__grid">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php
                    $story_id = get_the_ID();
                    $story_title = get_the_title();
                    $latest_chapter = $latest_chapters[$story_id] ?? null;
                    $story_views = ViewTracker::format_short(ViewTracker::get_story_views($story_id));
                    $modified_time = get_post_modified_time('U', true, $story_id);
                    ?>
                    <article class="home-latest-story">
                        <div class="home-latest-story__media">
                            <a
                                class="home-latest-story__thumbnail"
                                href="<?php the_permalink(); ?>"
                                title="<?php echo esc_attr($story_title); ?>"
                            >
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail($settings['image_size']); ?>
                                <?php else : ?>
                                    <img
                                        src="<?php echo esc_url(get_theme_file_uri('/assets/images/no-image.png')); ?>"
                                        alt="<?php echo esc_attr($story_title); ?>"
                                    >
                                <?php endif; ?>
                            </a>

                            <div class="home-latest-story__views">
                                <i class="es-ic-mask es-ic-mask-eye" aria-hidden="true"></i>
                                <span><?php echo esc_html($story_views); ?></span>
                            </div>
                        </div>

                        <div class="home-latest-story__detail">
                            <h3 class="home-latest-story__title">
                                <a href="<?php the_permalink(); ?>"><?php echo esc_html($story_title); ?></a>
                            </h3>

                            <div class="home-latest-story__info">
                                <?php if ($latest_chapter) : ?>
                                    <a
                                        class="home-latest-story__chapter"
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
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            </div>

            <div class="home-latest-stories__actions">
                <div class="home-latest-stories__loading" hidden>
                    <span class="home-latest-stories__spinner" aria-hidden="true"></span>
                    <span><?php esc_html_e('Đang tải...', 'tianshu'); ?></span>
                </div>
                <button class="home-latest-stories__load-more home-section-more" type="button">
                    <?php esc_html_e('Xem thêm', 'tianshu'); ?>
                </button>
            </div>
        </div>

    </div>
</section>
