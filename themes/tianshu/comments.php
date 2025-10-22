<?php
defined('ABSPATH') || exit;

// If the post is password protected, don't load the comments.
if ( post_password_required() ) {
    return;
}
?>

<div id="comments" class="comments-area mt-6">
    <?php if ( have_comments() ) : ?>
        <div class="comments-area__warp">
            <h2 class="comments-area__title mb-5">
                <?php
                printf(
                    _nx(
                        'Một bình luận trên &ldquo;%2$s&rdquo;',
                        '%1$s bình luận trên &ldquo;%2$s&rdquo;',
                        get_comments_number(),
                        'comments title',
                        'tianshu'
                    ),
                    number_format_i18n( get_comments_number() ),
                    get_the_title()
                );
                ?>
            </h2>

            <?php tianshu_comment_nav(); ?>

            <ul class="comments-area__list">
                <?php
                wp_list_comments(array(
                    'style' => 'ul',
                    'avatar_size' => 60,
                    'short_ping' => true,
                    'callback' => 'tianshu_comment_item',
                ));
                ?>
            </ul>

            <?php tianshu_comment_nav(); ?>
        </div>
    <?php endif; ?>

    <?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
        <p class="no-comments">
            <?php esc_html_e('Bình luận đã đóng.', 'tianshu'); ?>
        </p>
    <?php endif; ?>

    <?php
    // custom comment form

    $tianshu_commenter = wp_get_current_commenter();
    $tianshu_req = get_option('require_name_email');
    $tianshu_comments_args = ($tianshu_req ? " aria-required='true'" : '');

    $tianshu_comments_args = array(
        'title_reply' => '<span>' . esc_html__('Để lại bình luận', 'tianshu') . '</span>',
        'fields' => apply_filters('comment_form_default_fields',
            array(
                'comment_notes_before' => '<div class="comment-fields-row"><div class="row">',
                'author' => '<div class="col-12 col-sm-6 col-md-6"><div class="form-comment-item"><input id="author" placeholder="' . esc_html__('Họ và tên *', 'tianshu') . '" class="form-control w-100 p-3" name="author" type="text" value="' . esc_attr($tianshu_commenter['comment_author']) . '" size="30" ' . $tianshu_comments_args . ' /></div></div>',
                'email' => '<div class="col-12 col-sm-6 col-md-6"><div class="form-comment-item"><input id="email" placeholder="' . esc_html__('Email *', 'tianshu') . '" class="form-control w-100 p-3" name="email" type="text" value="' . esc_attr($tianshu_commenter['comment_author_email']) . '" size="30" ' . $tianshu_comments_args . ' /></div></div>',
                'comment_notes_after' => '</div></div>',
            )
        ),
        'comment_field' => '<div class="form-comment-item form-comment-field"><textarea rows="5" id="comment" placeholder="' . esc_html__('Bình luận *', 'tianshu') . '" name="comment" class="form-control w-100 p-3"></textarea></div>',
    );

    comment_form($tianshu_comments_args);
    ?>
</div>
