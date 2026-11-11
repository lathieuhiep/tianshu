<?php
/**
 * Shared front-page layout for story grids and the widget sidebar.
 *
 * @package tianshu
 */

defined('ABSPATH') || exit;

$has_sidebar = is_active_sidebar('es-sidebar');
?>
<div class="home-story-collections container">
    <div class="home-story-collections__layout<?php echo $has_sidebar ? ' has-sidebar' : ''; ?>">
        <main class="home-story-collections__content">
            <?php get_template_part('template-parts/home/latest-stories'); ?>
            <?php get_template_part('template-parts/home/completed-stories'); ?>
        </main>

        <?php if ($has_sidebar) : ?>
            <aside class="home-story-collections__sidebar" aria-label="<?php esc_attr_e('Sidebar trang chủ', 'tianshu'); ?>">
                <?php dynamic_sidebar('es-sidebar'); ?>
            </aside>
        <?php endif; ?>
    </div>
</div>
