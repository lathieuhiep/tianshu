<?php
use ExtendSite\PostType\AuthorPostType;
use ExtendSite\PostType\StoryPostType;

/**
 * Xoá cache khi lưu/publish chapter.
 */
add_action( 'save_post_chapter', function($post_id, $post, $update ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }
    $story_id = (int) get_post_meta( $post_id, '_chapter_story_id', true );
    if ( $story_id > 0 ) {
        $key = "es:story_last_update:$story_id";
        wp_cache_delete( $key, 'es_story' );
        delete_transient( $key );
    }
}, 10, 3 );

/**
 * Xoá cache khi xoá chapter.
 */
add_action( 'before_delete_post', function( $post_id ) {
    if ( get_post_type( $post_id ) !== 'chapter' ) {
        return;
    }
    $story_id = (int) get_post_meta( $post_id, '_chapter_story_id', true );
    if ( $story_id > 0 ) {
        $key = "es:story_last_update:$story_id";
        wp_cache_delete( $key, 'es_story' );
        delete_transient( $key );
    }
}, 10 );

/**
 * Thiết lập số bài viết hiển thị trên trang lưu trữ cho CPT Story và Author.
 */
add_action( 'pre_get_posts', function( WP_Query $query ) {
    // Chỉ chạy ở frontend và query chính
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    // Áp dụng cho CPT story + các taxonomy liên quan
    if ( is_post_type_archive( StoryPostType::SLUG )
        || is_tax( [ StoryPostType::TAX_SLUG, StoryPostType::TAG_SLUG, StoryPostType::STATUS_TAX ] )
        || is_post_type_archive( AuthorPostType::SLUG )
    ) {
        $query->set( 'posts_per_page', 12 );
    }
});
