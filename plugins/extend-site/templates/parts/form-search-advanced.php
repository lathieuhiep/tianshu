<?php

use ExtendSite\PostType\StoryPostType;
use ExtendSite\Search\SearchController;
use ExtendSite\Search\SearchHelper;

defined('ABSPATH') || exit;

// keywords search form
$keywords = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

// get tax genres
$selected_genres = isset($_GET['genre']) ? array_map('intval', (array) $_GET['genre']) : [];
$genres = get_terms([
    'taxonomy'   => StoryPostType::TAX_SLUG,
    'hide_empty' => false,
]);

// get tax statuses
$statuses = get_terms([
    'taxonomy'   => StoryPostType::STATUS_TAX,
    'hide_empty' => false,
]);
$selected_status = isset($_GET['status']) ? (int) $_GET['status'] : 0;

// get chapter ranges
$chapter_ranges = SearchHelper::get_chapter_ranges();
$selected_range = $_GET['chapters'] ?? '';

// get sort options
$sorts = SearchHelper::get_sort_options();
$selected_sort = $_GET['sort'] ?? 'latest';
?>

<form class="es-advanced-search" method="get" action="<?php echo esc_url(home_url('/'. SearchController::SLUG .'/')); ?>">
    <!-- 1. Keyword -->
    <div class="filter-group keyword">
        <label class="filter-label"><?php esc_html_e('Từ khóa', 'extend-site'); ?></label>

        <input type="text"
               class="es-input"
               name="q"
               value="<?php echo esc_attr( $keywords ); ?>"
               placeholder="<?php esc_attr_e('Từ khóa cần tìm', 'extend-site'); ?>"
               aria-label="<?php esc_attr_e('Từ khóa', 'extend-site'); ?>">
    </div>

    <!-- 2. Genre -->
    <?php if ($genres && !is_wp_error($genres)) : ?>
        <div class="filter-group genre">
            <label class="filter-label"><?php esc_html_e('Thể loại', 'extend-site'); ?></label>

            <ul class="filter-list genre-list es-list-style-none es-custom-scrollbar">
                <?php
                foreach ($genres as $term) :
                    $checked = in_array($term->term_id, $selected_genres, true) ? 'checked' : '';
                ?>
                    <li>
                        <label>
                            <input type="checkbox"
                                   name="genre[]"
                                   value="<?php echo esc_attr($term->term_id); ?>"
                                <?php echo $checked; ?>>
                            <?php echo esc_html($term->name); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- 3. Status -->
    <?php if ($statuses && !is_wp_error($statuses)) : ?>
        <div class="filter-group status">
            <label class="filter-label"><?php esc_html_e('Tình trạng', 'extend-site'); ?></label>

            <ul class="filter-list status-list es-list-style-none es-custom-scrollbar">
                <li>
                    <label>
                        <input type="radio"
                               name="status"
                               value=""
                            <?php checked(empty($_GET['status'])); ?>>
                        <?php esc_html_e('Tất cả', 'extend-site'); ?>
                    </label>
                </li>

                <?php foreach ($statuses as $term) : ?>
                    <li>
                        <label>
                            <input type="radio"
                                   name="status"
                                   value="<?php echo esc_attr($term->term_id); ?>"
                                <?php checked($selected_status, $term->term_id); ?>>
                            <?php echo esc_html($term->name); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- 4. Chapter Range -->
    <div class="filter-group chapters">
        <label class="filter-label"><?php esc_html_e('Số chương', 'extend-site'); ?></label>

        <ul class="filter-list chapter-list es-list-style-none">
            <li>
                <label>
                    <input type="radio"
                           name="chapters"
                           value=""
                        <?php checked(empty($selected_range)); ?>>
                    <?php esc_html_e('Tất cả', 'extend-site'); ?>
                </label>
            </li>

            <?php foreach ($chapter_ranges as $value => $label) : ?>
                <li>
                    <label>
                        <input type="radio"
                               name="chapters"
                               value="<?php echo esc_attr($value); ?>"
                            <?php checked($selected_range, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- 5. Sort -->
    <div class="filter-group sort">
        <label class="filter-label"><?php esc_html_e('Sắp xếp theo', 'extend-site'); ?></label>

        <ul class="filter-list sort-list es-list-style-none">
            <?php foreach ($sorts as $val => $label) : ?>
                <li>
                    <label>
                        <input type="radio"
                               name="sort"
                               value="<?php echo esc_attr($val); ?>"
                            <?php checked($selected_sort, $val); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Submit -->
    <div class="submit-wrap es-mt-3">
        <button type="submit" class="btn btn-primary btn-search-advanced">
            <?php esc_html_e('Tìm kiếm', 'extend-site'); ?>
        </button>
    </div>
</form>