<?php
/**
 * Part: Comments box for a Story
 *
 * @package extend-site
 */

defined('ABSPATH') || exit;

$post_id = isset($args['story_id']) ? absint($args['story_id']) : get_the_ID();


global $post;
$original_post = $post;
$post = get_post($post_id);
setup_postdata($post);

if ( post_type_supports(get_post_type($post), 'comments') && (comments_open($post) || get_comments_number($post))) :
?>
    <div class="story-comments">
        <?php comments_template(); ?>
    </div>
<?php
else :
?>
    <p><?php esc_html_e( 'Chức năng bình luận hiện không khả dụng.', 'extend-site' ); ?></p>
<?php
endif;

wp_reset_postdata();
$post = $original_post;