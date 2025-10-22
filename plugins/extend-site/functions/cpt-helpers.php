<?php
/**
 * Thêm dropdown filter taxonomy cho màn danh sách của một CPT.
 *
 * @param string $post_type  Slug CPT, vd: 'portfolio'
 * @param string $taxonomy   Slug taxonomy, vd: 'portfolio_cat'
 * @param string $all_label  Nhãn "Tất cả ..." (tuỳ chọn)
 */
function es_add_custom_taxonomy_filter_to_cpt(string $post_type, string $taxonomy, string $all_label = ''): void
{
    // Đảm bảo chạy sau khi taxonomy đã có
    add_action('admin_init', function () use ($post_type, $taxonomy, $all_label) {
        // 1) Dropdown trên list table
        add_action('restrict_manage_posts', function () use ($post_type, $taxonomy, $all_label) {
            $screen = get_current_screen();
            if (!$screen || $screen->post_type !== $post_type) {
                return;
            }

            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]);
            if (empty($terms) || is_wp_error($terms)) {
                return;
            }

            $selected = isset($_GET[$taxonomy]) ? sanitize_text_field(wp_unslash($_GET[$taxonomy])) : '';
            $label    = $all_label !== '' ? $all_label : esc_html__('Tất cả danh mục', 'extend-site');

            echo '<select name="' . esc_attr($taxonomy) . '" id="' . esc_attr($taxonomy) . '" class="postform">';
            echo '<option value="">' . esc_html($label) . '</option>';
            foreach ($terms as $term) {
                printf(
                    '<option value="%1$s" %2$s>%3$s (%4$d)</option>',
                    esc_attr($term->slug),
                    selected($selected, $term->slug, false),
                    esc_html($term->name),
                    (int) $term->count
                );
            }
            echo '</select>';
        });

        // 2) Áp filter vào query
        add_action('parse_query', function ($query) use ($post_type, $taxonomy) {
            if (!is_admin() || !$query->is_main_query()) {
                return;
            }
            // chỉ áp trên màn list của CPT này
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $pt     = $query->get('post_type');
            if (($screen && $screen->post_type !== $post_type) && $pt !== $post_type) {
                return;
            }

            if (!empty($_GET[$taxonomy])) {
                $term  = sanitize_text_field(wp_unslash($_GET[$taxonomy]));
                $tax_q = (array) $query->get('tax_query');

                $tax_q[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $term,
                ];
                $query->set('tax_query', $tax_q);
            }
        });
    });
}