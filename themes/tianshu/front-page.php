<?php
/**
 * Front page template.
 *
 * Migrated home sections are rendered before the remaining page-builder content.
 *
 * @package tianshu
 */

defined('ABSPATH') || exit;

get_header();
?>
    <div class="front-page">
        <?php get_template_part('template-parts/home/featured-stories'); ?>
        <?php get_template_part('template-parts/home/hot-stories'); ?>
        <?php get_template_part('template-parts/home/story-collections'); ?>
        <?php get_template_part('template-parts/home/story-taxonomy-list'); ?>
    </div>
<?php
get_footer();
