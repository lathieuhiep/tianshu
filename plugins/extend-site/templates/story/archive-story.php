<?php

use ExtendSite\PostType\TemplateLoader;

get_header(); ?>

<div class="es-archive-warp es-pt-10 es-pb-10" data-plugin="extend-site" itemscope itemtype="https://schema.org/CollectionPage">
    <div class="es-container">
        <h1 class="page-archive-title es-fs-lg">
            <?php if ($menu_label = es_get_current_menu_label()) : ?>
                <?php echo esc_html($menu_label); ?>
            <?php else : ?>
                <?php post_type_archive_title(); ?>
            <?php endif; ?>
        </h1>

        <?php TemplateLoader::part('story/partials/story-term'); ?>
    </div>
</div>

<?php
get_footer();