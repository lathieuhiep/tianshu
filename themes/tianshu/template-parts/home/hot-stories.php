<?php
/**
 * Hot stories carousel on the front page.
 *
 * @package tianshu
 */

use ExtendSite\Repositories\StoryRepository;
use ExtendSite\Views\ViewTracker;

defined('ABSPATH') || exit;

if (!class_exists(StoryRepository::class) || !class_exists(ViewTracker::class)) {
    return;
}

$settings = apply_filters('tianshu_home_hot_story_settings', [
    'title' => __('Truyện hot tháng này', 'tianshu'),
    'query_source' => tianshu_get_option('opt_home_hot_query_source', 'top_month'),
    'taxonomy' => tianshu_get_option('opt_home_hot_taxonomy', []),
    'tag' => tianshu_get_option('opt_home_hot_tag', []),
    'limit' => tianshu_get_option('opt_home_hot_limit', 12),
    'order_by' => tianshu_get_option('opt_home_hot_order_by', 'ID'),
    'order' => tianshu_get_option('opt_home_hot_order', 'DESC'),
    'image_size' => 'large',
    'loop' => true,
    'autoplay' => true,
    'speed' => 800,
    'navigation' => false,
    'pagination' => false,
]);

$settings = tianshu_normalize_home_story_query_settings(
    $settings,
    ['custom', 'top_month'],
    'custom'
);

$query = StoryRepository::get_carousel($settings);
if (!$query->have_posts()) {
    return;
}

$swiper_options = [
    'loop' => (bool) $settings['loop'],
    'autoplay' => (bool) $settings['autoplay'],
    'speed' => absint($settings['speed']),
    'navigation' => (bool) $settings['navigation'],
    'pagination' => (bool) $settings['pagination'],
    'breakpoints' => [
        0 => ['slidesPerView' => 1, 'spaceBetween' => 12],
        480 => ['slidesPerView' => 2, 'spaceBetween' => 12],
        768 => ['slidesPerView' => 3, 'spaceBetween' => 16],
        992 => ['slidesPerView' => 4, 'spaceBetween' => 20],
        1200 => ['slidesPerView' => 5, 'spaceBetween' => 20],
    ],
];
?>
<section class="home-hot-stories container" aria-labelledby="home-hot-stories-title">
    <?php
    get_template_part(
        'components/home/section-heading',
        null,
        [
            'title' => $settings['title'],
            'title_id' => 'home-hot-stories-title',
        ]
    );
    ?>

    <div
        class="home-hot-stories__slider swiper"
        data-home-hot-slider
        data-settings-swiper="<?php echo esc_attr(wp_json_encode($swiper_options)); ?>"
    >
        <div class="swiper-wrapper">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <?php
                $story_id = get_the_ID();
                $story_title = get_the_title();
                $story_views = ViewTracker::format_full(ViewTracker::get_story_views($story_id));
                ?>
                <article class="home-hot-story swiper-slide">
                    <div class="home-hot-story__media">
                        <a
                            class="home-hot-story__thumbnail"
                            href="<?php the_permalink(); ?>"
                            title="<?php echo esc_attr($story_title); ?>"
                        >
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail($settings['image_size']); ?>
                            <?php else : ?>
                                <img src="<?php echo esc_url(get_theme_file_uri('/assets/images/no-image.png')); ?>" alt="<?php echo esc_attr($story_title); ?>">
                            <?php endif; ?>
                        </a>

                        <div class="home-hot-story__views">
                            <i class="es-ic-mask es-ic-mask-eye" aria-hidden="true"></i>
                            <span><?php echo esc_html($story_views); ?></span>
                        </div>
                    </div>

                    <h3 class="home-hot-story__title">
                        <a href="<?php the_permalink(); ?>"><?php echo esc_html($story_title); ?></a>
                    </h3>
                </article>
            <?php endwhile; ?>
            <?php wp_reset_postdata(); ?>
        </div>

        <?php if ($settings['pagination']) : ?>
            <div class="swiper-pagination"></div>
        <?php endif; ?>

        <?php if ($settings['navigation']) : ?>
            <button class="swiper-button-prev" type="button" aria-label="<?php esc_attr_e('Truyện trước', 'tianshu'); ?>"></button>
            <button class="swiper-button-next" type="button" aria-label="<?php esc_attr_e('Truyện tiếp theo', 'tianshu'); ?>"></button>
        <?php endif; ?>
    </div>
</section>
