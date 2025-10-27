<?php
/**
 * Part: Chapters list for a Story
 *
 * @package extend-site
 */

use ExtendSite\PostType\ChapterPostType;

defined('ABSPATH') || exit;

$story_id = isset($args['story_id']) ? absint($args['story_id']) : 0;

// Lấy hằng từ ChapterPostType nếu có, fallback về meta key chuỗi.
$chapter_type  = class_exists('\ExtendSite\PostType\ChapterPostType') ? ChapterPostType::SLUG : 'chapter';
$meta_story_id = class_exists('\ExtendSite\PostType\ChapterPostType') ? ChapterPostType::META_STORY_ID : '_chapter_story_id';
$meta_number   = class_exists('\ExtendSite\PostType\ChapterPostType') ? ChapterPostType::META_NUMBER   : '_chapter_number';

$query_args = [
    'post_type'      => $chapter_type,
    'posts_per_page' => -1,
    'post_status'    => ['publish'],
    'meta_key'       => $meta_number,
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'     => $meta_story_id,
            'value'   => $story_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
    ],
    'no_found_rows'  => true,
];

$chapters = new WP_Query($query_args);

if ($chapters->have_posts()) :
?>
    <ul class="list-group es-list-style-none">
        <?php
        while ($chapters->have_posts()) :
            $chapters->the_post();
            $number = get_post_meta(get_the_ID(), $meta_number, true);
            $title  = get_the_title();
        ?>
            <li class="list-group__item">
                <a class="chapter-link" href="<?php echo esc_url(get_permalink()); ?>">
                    <?php
                    if ($number !== '' && $number !== null) {
                        /* translators: 1: chapter number, 2: chapter title */
                        printf(
                            esc_html__('Chương %1$s: %2$s', 'extend-site'),
                            esc_html((string) $number),
                            esc_html($title)
                        );
                    } else {
                        echo esc_html($title);
                    }
                    ?>
                </a>

                <span class="chapter-date"><?php echo esc_html( get_the_date('d-m-Y') ); ?></span>
            </li>
        <?php
        endwhile;
        ?>
    </ul>
<?php
    wp_reset_postdata();
else :
?>
    <p class="es-text-error"><?php esc_html_e('Chưa có chương nào được đăng.', 'extend-site'); ?></p>
<?php
endif;