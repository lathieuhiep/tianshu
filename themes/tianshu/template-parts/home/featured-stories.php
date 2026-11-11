<?php
/**
 * Featured stories section on the front page.
 *
 * The defaults below are temporary configuration values. They can be replaced
 * with theme options later without changing the section markup.
 *
 * @package tianshu
 */

use ExtendSite\Repositories\StoryRepository;

defined('ABSPATH') || exit;

if (!class_exists(StoryRepository::class)) {
    return;
}

$settings = apply_filters('tianshu_home_featured_story_settings', [
    'title' => __('Đề cử hôm nay', 'tianshu'),
    'query_source' => tianshu_get_option('opt_home_featured_query_source', 'custom'),
    'taxonomy' => tianshu_get_option('opt_home_featured_taxonomy', []),
    'tag' => tianshu_get_option('opt_home_featured_tag', []),
    'limit' => tianshu_get_option('opt_home_featured_limit', 18),
    'order_by' => tianshu_get_option('opt_home_featured_order_by', 'ID'),
    'order' => tianshu_get_option('opt_home_featured_order', 'DESC'),
    'excerpt_words' => 25,
    'image_size' => 'large',
    'loop' => true,
    'autoplay' => true,
    'speed' => 800,
    'navigation' => false,
    'pagination' => true,
]);

$settings = tianshu_normalize_home_story_query_settings(
    $settings,
    ['custom', 'top_week'],
    'custom'
);

$query = StoryRepository::get_featured($settings);
if (!$query->have_posts()) {
    return;
}

$story_authors = StoryRepository::get_authors_by_story_ids(
    wp_list_pluck($query->posts, 'ID')
);

$swiper_options = [
    'loop' => (bool) $settings['loop'],
    'autoplay' => (bool) $settings['autoplay'],
    'speed' => absint($settings['speed']),
    'navigation' => (bool) $settings['navigation'],
    'pagination' => (bool) $settings['pagination'],
    'spaceBetween' => 20,
    'breakpoints' => [
        0 => ['slidesPerView' => 1],
        768 => ['slidesPerView' => 2],
    ],
];
?>
<section class="home-featured-stories container" aria-labelledby="home-featured-stories-title">
    <?php
    get_template_part(
        'components/home/section-heading',
        null,
        [
            'title' => $settings['title'],
            'title_id' => 'home-featured-stories-title',
        ]
    );
    ?>

    <div
        class="home-featured-stories__slider swiper"
        data-home-featured-slider
        data-settings-swiper="<?php echo esc_attr(wp_json_encode($swiper_options)); ?>"
    >
        <div class="swiper-wrapper">
            <?php
            $index = 0;
            while ($query->have_posts()) :
                $query->the_post();
                $story_id = get_the_ID();
                $authors = $story_authors[$story_id] ?? [];
                $excerpt_raw = get_post_field('post_excerpt', $story_id);
                $excerpt_raw = $excerpt_raw ?: get_post_field('post_content', $story_id);
                $excerpt = wp_trim_words(
                    wp_strip_all_tags($excerpt_raw),
                    max(5, absint($settings['excerpt_words'])),
                    '…'
                );

                if (0 === $index % 3) :
                    if ($index > 0) :
                        ?>
                            </div>
                        </div>
                        <?php
                    endif;
                    ?>
                    <div class="home-featured-stories__slide swiper-slide">
                        <div class="home-featured-stories__list">
                    <?php
                endif;
                ?>
                <article class="home-featured-story">
                    <a class="home-featured-story__thumbnail" href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail($settings['image_size']); ?>
                        <?php else : ?>
                            <img
                                src="<?php echo esc_url(get_theme_file_uri('/assets/images/no-image.png')); ?>"
                                alt="<?php echo esc_attr(get_the_title()); ?>"
                            >
                        <?php endif; ?>
                    </a>

                    <div class="home-featured-story__detail">
                        <h3 class="home-featured-story__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>

                        <div class="home-featured-story__description">
                            <?php echo esc_html($excerpt); ?>
                        </div>

                        <?php if ($authors) : ?>
                            <div class="home-featured-story__authors">
                                <?php foreach ($authors as $author) : ?>
                                    <a class="home-featured-story__author" href="<?php echo esc_url($author['url']); ?>">
                                        <i class="es-ic-mask es-ic-mask-user" aria-hidden="true"></i>
                                        <span><?php echo esc_html($author['name']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php
                $index++;
            endwhile;

            if ($index > 0) :
                ?>
                        </div>
                    </div>
                <?php
            endif;
            wp_reset_postdata();
            ?>
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
