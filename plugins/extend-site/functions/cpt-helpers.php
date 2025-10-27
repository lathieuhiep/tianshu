<?php

use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;

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

/**
 * Lấy ngày cập nhật gần nhất của truyện dựa trên chapter mới nhất.
 * Ưu tiên tốc độ: SELECT MAX(post_modified_gmt) + cache.
 *
 * @param int    $story_id
 * @param string $format   Default 'd-m-Y'
 * @return string
 */
function es_site_get_story_last_update( int $story_id, string $format = 'd-m-Y' ): string {
    if ( $story_id <= 0 ) {
        return '';
    }

    $cache_key_runtime  = "es:story_last_update:$story_id";
    $cache_group        = 'es_story';
    $cached             = wp_cache_get( $cache_key_runtime, $cache_group );

    if ( false !== $cached ) {
        return $cached;
    }

    // fallback transient (phòng khi không có persistent object cache)
    $cached = get_transient( $cache_key_runtime );
    if ( false !== $cached ) {
        wp_cache_set( $cache_key_runtime, $cached, $cache_group, 10 * MINUTE_IN_SECONDS );
        return $cached;
    }

    global $wpdb;

    // Lấy mốc thời gian GMT của chapter mới nhất theo story
    $last_mod_gmt = $wpdb->get_var( $wpdb->prepare("
        SELECT MAX(p.post_modified_gmt)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm
            ON pm.post_id = p.ID
           AND pm.meta_key = %s
           AND pm.meta_value = %d
        WHERE p.post_type = 'chapter'
          AND p.post_status = 'publish'
    ", '_chapter_story_id', $story_id ) );

    if ( $last_mod_gmt ) {
        // Chuyển từ GMT sang local và format
        $timestamp = strtotime( get_date_from_gmt( $last_mod_gmt, 'Y-m-d H:i:s' ) );
        $date      = date_i18n( $format, $timestamp );
    } else {
        // Không có chapter → fallback ngày sửa của story
        $date = get_the_modified_date( $format, $story_id );
    }

    // Cache 10 phút (tuỳ chỉnh theo tần suất xuất bản)
    wp_cache_set( $cache_key_runtime, $date, $cache_group, 10 * MINUTE_IN_SECONDS );
    set_transient( $cache_key_runtime, $date, 10 * MINUTE_IN_SECONDS );

    return $date;
}

/**
 * Get formatted author links for a story.
 *
 * @param int $story_id
 * @return string HTML of author names separated by commas.
 */
function es_site_get_story_authors( int $story_id ): string {
    $author_ids = get_post_meta( $story_id, StoryPostType::META_AUTHOR_IDS, true );
    $author_ids = is_array( $author_ids ) ? $author_ids : [];

    if ( empty( $author_ids ) ) {
        return esc_html__( 'Chưa rõ tác giả', 'extend-site' );
    }

    $authors = [];

    foreach ( $author_ids as $aid ) {
        if ( get_post_type( $aid ) !== AuthorPostType::SLUG ) {
            continue;
        }

        $authors[] = sprintf(
            '<a href="%s" class="author-name">%s</a>',
            esc_url( get_permalink( $aid ) ),
            esc_html( get_the_title( $aid ) )
        );
    }

    if ( empty( $authors ) ) {
        return esc_html__( 'Chưa rõ tác giả', 'extend-site' );
    }

    return implode( ', ', $authors );
}