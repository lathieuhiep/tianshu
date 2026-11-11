<?php
/**
 * Story taxonomy list on the front page.
 *
 * @package tianshu
 */

use ExtendSite\Repositories\StoryRepository;

defined('ABSPATH') || exit;

if (!class_exists(StoryRepository::class)) {
    return;
}

$allowed_taxonomies = ['story_genre', 'story_status', 'story_tag'];
$settings = apply_filters('tianshu_home_story_taxonomy_list_settings', [
    'title' => __('Danh sách thể loại', 'tianshu'),
    'taxonomy' => tianshu_get_option('opt_home_taxonomy_list_taxonomy', 'story_genre'),
]);

$taxonomy = sanitize_key((string) $settings['taxonomy']);
if (!in_array($taxonomy, $allowed_taxonomies, true)) {
    $taxonomy = 'story_genre';
}

$terms = StoryRepository::get_taxonomy_terms($taxonomy);
if (!$terms) {
    return;
}
?>
<section class="home-story-taxonomies container" data-home-taxonomy-list>
    <div class="home-story-taxonomies__heading">
        <h2 class="home-story-taxonomies__title"><?php echo esc_html($settings['title']); ?></h2>
        <button
            class="home-story-taxonomies__toggle"
            type="button"
            aria-expanded="true"
            aria-controls="home-story-taxonomies-list"
            aria-label="<?php esc_attr_e('Thu gọn danh sách thể loại', 'tianshu'); ?>"
        >
            <i class="es-ic-mask es-ic-mask-angle-up" aria-hidden="true"></i>
        </button>
    </div>

    <div id="home-story-taxonomies-list" class="home-story-taxonomies__list">
        <?php foreach ($terms as $term) : ?>
            <?php $term_url = get_term_link($term); ?>
            <?php if (!is_wp_error($term_url)) : ?>
                <a class="home-story-taxonomies__item" href="<?php echo esc_url($term_url); ?>">
                    <i class="es-ic-mask es-ic-mask-angle-right" aria-hidden="true"></i>
                    <span><?php echo esc_html($term->name); ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
