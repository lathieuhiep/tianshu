<?php

use ExtendSite\Search\SearchController;
use ExtendSite\Search\SearchHelper;
use ExtendSite\PostType\StoryPostType;

// --- Helper: tạo URL khi bỏ 1 filter ---
function es_remove_filter_arg($key, $value = null): string {
    $args = $_GET;

    // Nếu không có key trong URL thì trả về như cũ
    if (!isset($args[$key])) {
        return add_query_arg([], home_url('/' . SearchController::SLUG . '/'));
    }

    // Xử lý loại bỏ filter
    if ($value === null) {
        unset($args[$key]);
    } else {
        if (is_array($args[$key])) {
            $args[$key] = array_diff($args[$key], [$value]);
            if (empty($args[$key])) unset($args[$key]);
        } elseif ((string)$args[$key] === (string)$value) {
            unset($args[$key]);
        }
    }

    return add_query_arg($args, home_url('/' . SearchController::SLUG . '/'));
}

$filters = [];

// --- Keyword ---
if (!empty($_GET['q'])) {
    $filters[] = [
        'label' => sprintf(
            '%s <span class="es-filter-value">“%s”</span>',
            esc_html__('Từ khóa:', 'extend-site'),
            esc_html($_GET['q'])
        ),
        'url' => es_remove_filter_arg('q'),
    ];
}

// --- Genres ---
if (!empty($_GET['genre']) && is_array($_GET['genre'])) {
    $genres = get_terms([
        'taxonomy'   => StoryPostType::TAX_SLUG,
        'include'    => array_map('intval', $_GET['genre']),
        'hide_empty' => false,
    ]);
    if ($genres && !is_wp_error($genres)) {
        foreach ($genres as $term) {
            $filters[] = [
                'label' => sprintf(
                    '%s <span class="es-filter-value">%s</span>',
                    esc_html__('Thể loại:', 'extend-site'),
                    esc_html($term->name)
                ),
                'url' => es_remove_filter_arg('genre', (string)$term->term_id),
            ];
        }
    }
}

// --- Status ---
if (!empty($_GET['status'])) {
    $term = get_term((int) $_GET['status'], StoryPostType::STATUS_TAX);
    if ($term && !is_wp_error($term)) {
        $filters[] = [
            'label' => sprintf(
                '%s <span class="es-filter-value">%s</span>',
                esc_html__('Tình trạng:', 'extend-site'),
                esc_html($term->name)
            ),
            'url' => es_remove_filter_arg('status'),
        ];
    }
}

// --- Chapters ---
if (!empty($_GET['chapters'])) {
    $ranges = SearchHelper::get_chapter_ranges();
    $label  = $ranges[$_GET['chapters']] ?? '';
    if ($label) {
        $filters[] = [
            'label' => sprintf(
                '%s <span class="es-filter-value">%s</span>',
                esc_html__('Số chương:', 'extend-site'),
                esc_html($label)
            ),
            'url' => es_remove_filter_arg('chapters'),
        ];
    }
}

// --- Sort ---
$sort_key = $_GET['sort'] ?? 'latest';
$sorts    = SearchHelper::get_sort_options();

if (!empty($_GET['sort']) && $sort_key !== 'latest') {
    $label = $sorts[$sort_key] ?? '';
    if ($label) {
        $filters[] = [
            'label' => sprintf(
                '%s <span class="es-filter-value">%s</span>',
                esc_html__('Sắp xếp:', 'extend-site'),
                esc_html($label)
            ),
            'url' => es_remove_filter_arg('sort'),
        ];
    }
}
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