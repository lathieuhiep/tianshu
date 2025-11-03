<?php

namespace ExtendSite\Widgets;

use ExtendSite\Ajax\LoadRanking;
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;
use ExtendSite\Repositories\StoryRankingRepository;
use WP_Widget;

defined('ABSPATH') || exit;

class RankingWidget extends WP_Widget
{
    public const RANKING_TYPE_STORY = StoryPostType::SLUG;
    public const RANKING_TYPE_AUTHOR = AuthorPostType::SLUG;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(
            'es_ranking',
            esc_html__('Plugin Extend Site: Bảng Xếp Hạng Truyện', 'extend-site'),
            [
                'description' => esc_html__('Hiển thị top truyện được xem nhiều nhất theo ngày, tuần, tháng, năm.', 'extend-site'),
            ]
        );
    }

    public static function get_tabs(): array {
        return [
            'day'   => esc_html__('Ngày', 'extend-site'),
            'week'  => esc_html__('Tuần', 'extend-site'),
            'month' => esc_html__('Tháng', 'extend-site'),
            'year'  => esc_html__('Năm', 'extend-site'),
        ];
    }

    /**
     * Output the widget content.
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance): void
    {
        // Enqueue script chỉ khi ở frontend
        if ( ! is_admin() ) {
            wp_enqueue_script('es-widget');
        }

        $tabs = self::get_tabs();

        echo $args['before_widget'];

        // parse instance settings
        $config = [
            'limit' => (int)($instance['limit'] ?? 10),
            'default_tab' => $instance['default_tab'] ?? 'day',
            'ranking_type' => $instance['ranking_type'] ?? self::RANKING_TYPE_STORY
        ];

        // get title
        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );

        // display title
        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        // Lấy dữ liệu xếp hạng ban đầu
        $ranking_type = $config['ranking_type'] ?? self::RANKING_TYPE_STORY;
        $period = $config['default_tab'] ?? 'day';

        $items = StoryRankingRepository::get_ranking_items($ranking_type, $period, $config['limit']);
    ?>
        <div class="es-ranking-widget" data-config='<?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE); ?>'>
            <div class="es-ranking-tabs es-mb-6 es-text-center">
                <?php foreach ($tabs as $key => $label): ?>
                    <button type="button" data-period="<?php echo esc_attr($key); ?>" class="btn-ranking <?php echo $period === $key ? 'active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="es-ranking-tabs-content">
                <div class="es-loading es-flex es-flex-column es-flex-align-center es-row-gap-2" hidden>
                    <span class="es-spinner"></span>
                    <span class="text-load"><?php esc_html_e('Đang tải...', 'extend-site'); ?></span>
                </div>

                <?php foreach ($tabs as $key => $label): ?>
                    <div class="ranking-list tab-<?php echo esc_attr($key); echo $period === $key ? ' active loaded' : ''; ?>"
                         data-period="<?php echo esc_attr($key); ?>" <?php echo $period === $key ? '' : 'hidden'; ?>>
                        <?php
                        if ($period === $key) :
                            echo LoadRanking::render_view($items, $ranking_type);
                        endif;
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
        echo $args['after_widget'];
    }

    /**
     * Output the widget settings form.
     *
     * @param array $instance
     * @return void
     */
    public function form($instance): void
    {
        $title = $instance['title'] ?? esc_html__('Bảng Xếp Hạng', 'extend-site');
        $limit = $instance['limit'] ?? 10;
        $default_tab = $instance['default_tab'] ?? 'day';
        $ranking_type = $instance['ranking_type'] ?? self::RANKING_TYPE_STORY;
        $tabs = self::get_tabs();
    ?>
        <!--Title field-->
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?= esc_html__('Title:', 'extend-site'); ?>
            </label>

            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <!--Limit field-->
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('limit')); ?>">
                <?php esc_html_e('Số lượng hiển thị:', 'extend-site'); ?>
            </label>

            <input id="<?php echo esc_attr($this->get_field_id('limit')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('limit')); ?>"
                   type="number"
                   min="1"
                   max="50"
                   value="<?php echo esc_attr($limit); ?>"
                   style="width: 80px;">
        </p>

        <!--Default Tab field-->
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('default_tab')); ?>">
                <?php esc_html_e('Tab mặc định:', 'extend-site'); ?>
            </label>

            <select id="<?php echo esc_attr($this->get_field_id('default_tab')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('default_tab')); ?>">
                <?php foreach ($tabs as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($default_tab, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <!-- Ranking Type -->
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('ranking_type')); ?>">
                <?php esc_html_e('Kiểu xếp hạng:', 'extend-site'); ?>
            </label>
            <select id="<?php echo esc_attr($this->get_field_id('ranking_type')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('ranking_type')); ?>">
                <option value="<?php echo esc_attr(self::RANKING_TYPE_STORY); ?>" <?php selected($ranking_type, self::RANKING_TYPE_STORY); ?>>
                    <?php esc_html_e('Truyện', 'extend-site'); ?>
                </option>
                <option value="<?php echo esc_attr(self::RANKING_TYPE_AUTHOR); ?>" <?php selected($ranking_type, self::RANKING_TYPE_AUTHOR); ?>>
                    <?php esc_html_e('Tác giả', 'extend-site'); ?>
                </option>
            </select>
        </p>
    <?php
    }

    /**
     * Process widget options to be saved.
     *
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    public function update($new_instance, $old_instance): array
    {
        $instance = [];

        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = max(1, (int)($new_instance['limit'] ?? 10));
        $instance['default_tab'] = sanitize_key($new_instance['default_tab'] ?? 'day');
        $instance['ranking_type'] = sanitize_key($new_instance['ranking_type'] ?? self::RANKING_TYPE_STORY);

        return $instance;
    }
}

// Register the widget
add_action('widgets_init', function() {
    register_widget(RankingWidget::class);
});