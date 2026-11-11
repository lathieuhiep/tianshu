<?php
/**
 * Completed stories grid on the front page.
 *
 * @package tianshu
 */

use ExtendSite\Repositories\StoryRepository;

defined('ABSPATH') || exit;

if (!class_exists(StoryRepository::class)) {
    return;
}

$completed_term = get_term_by('slug', 'hoan-thanh', 'story_status');
$default_status = $completed_term && !is_wp_error($completed_term) ? (int) $completed_term->term_id : 0;

$settings = apply_filters('tianshu_home_completed_story_settings', [
    'title' => __('Truyện đã hoàn thành', 'tianshu'),
    'query_source' => 'custom',
    'taxonomy' => tianshu_get_option('opt_home_completed_taxonomy', []),
    'status' => tianshu_get_option('opt_home_completed_status', $default_status),
    'tag' => tianshu_get_option('opt_home_completed_tag', []),
    'limit' => tianshu_get_option('opt_home_completed_limit', 6),
    'order_by' => tianshu_get_option('opt_home_completed_order_by', 'ID'),
    'order' => tianshu_get_option('opt_home_completed_order', 'DESC'),
    'image_size' => 'large',
    'status_label' => __('Full', 'tianshu'),
]);

$settings = tianshu_normalize_home_story_query_settings(
    $settings,
    ['custom'],
    'custom'
);

$query = StoryRepository::get_grid($settings);
if (!$query->have_posts()) {
    return;
}

$story_ids = wp_list_pluck($query->posts, 'ID');
$latest_chapters = StoryRepository::get_latest_chapters($story_ids);
$section_url = get_post_type_archive_link('story');

if ($settings['status']) {
    $selected_status = get_term((int) reset($settings['status']), 'story_status');
    if ($selected_status && !is_wp_error($selected_status)) {
        $status_url = get_term_link($selected_status);
        if (!is_wp_error($status_url)) {
            $section_url = $status_url;
        }
    }
}
?>
<section class="home-completed-stories" aria-labelledby="home-completed-stories-title">
    <?php
    get_template_part(
        'components/home/section-heading',
        null,
        [
            'title' => $settings['title'],
            'title_id' => 'home-completed-stories-title',
            'url' => $section_url,
            'link_label' => __('Xem thêm', 'tianshu'),
        ]
    );
    ?>

    <div class="home-completed-stories__grid">
        <?php while ($query->have_posts()) : $query->the_post(); ?>
            <?php
            $story_id = get_the_ID();
            get_template_part(
                'components/home/story-grid-card',
                null,
                [
                    'story_id' => $story_id,
                    'latest_chapter' => $latest_chapters[$story_id] ?? null,
                    'image_size' => $settings['image_size'],
                    'status_label' => $settings['status_label'],
                ]
            );
            ?>
        <?php endwhile; ?>
        <?php wp_reset_postdata(); ?>
    </div>

    <?php if ($section_url) : ?>
        <div class="home-completed-stories__actions">
            <a class="home-completed-stories__more home-section-more" href="<?php echo esc_url($section_url); ?>">
                <?php esc_html_e('Xem thêm', 'tianshu'); ?>
            </a>
        </div>
    <?php endif; ?>
</section>
