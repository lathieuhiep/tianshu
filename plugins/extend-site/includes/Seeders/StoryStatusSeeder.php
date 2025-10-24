<?php
namespace ExtendSite\Seeders;

class StoryStatusSeeder {
    public static function run(): void {
        $tax = 'story_status';
        if ( ! taxonomy_exists($tax) ) return;

        $defaults = [
            'ongoing'   => ['name' => esc_html__('Đang ra', 'extend-site'),   'color' => '#22c55e'],
            'completed' => ['name' => esc_html__('Hoàn thành', 'extend-site'), 'color' => '#8b5cf6'],
            'hiatus'    => ['name' => esc_html__('Tạm dừng', 'extend-site'),   'color' => '#f59e0b'],
            'dropped'   => ['name' => esc_html__('Đã drop', 'extend-site'),    'color' => '#ef4444'],
            'oneshot'   => ['name' => esc_html__('One-shot', 'extend-site'),   'color' => '#06b6d4'],
        ];

        foreach ($defaults as $slug => $item) {
            $term = term_exists($slug, $tax);
            if ( ! $term ) {
                $term = wp_insert_term($item['name'], $tax, ['slug' => $slug]);
            }
            if ( is_wp_error($term) ) continue;

            $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
            if ( ! get_term_meta($term_id, 'color', true) ) {
                update_term_meta($term_id, 'color', sanitize_hex_color($item['color']));
            }
        }
    }
}