<?php

use ExtendSite\Search\SearchController;
use ExtendSite\Search\SearchHelper;

defined('ABSPATH') || exit;

// get active filters
$filters = SearchHelper::get_active_filters();
?>

<?php if (!empty($filters)) : ?>
    <div class="es-active-filters es-mb-4">
        <?php foreach ($filters as $filter) : ?>
            <span class="es-active-filter">
                <?php echo wp_kses_post($filter['label']); ?>
                <a href="<?php echo esc_url($filter['url']); ?>"
                   class="es-remove-filter"
                   aria-label="<?php esc_attr_e('Xóa bộ lọc này', 'extend-site'); ?>">✕</a>
            </span>
        <?php endforeach; ?>

        <a href="<?php echo esc_url(home_url('/' . SearchController::SLUG . '/')); ?>"
           class="es-reset-filters">
            <?php esc_html_e('Xóa tất cả', 'extend-site'); ?>
        </a>
    </div>
<?php endif; ?>