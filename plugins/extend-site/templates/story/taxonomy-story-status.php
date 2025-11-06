<?php

use ExtendSite\PostType\TemplateLoader;

get_header();
?>
    <div class="es-archive-warp es-pt-10 es-pb-10" data-plugin="extend-site" itemscope itemtype="https://schema.org/CollectionPage">
        <div class="es-container">
            <h1 class="page-archive-title es-fs-lg">
                <?php single_term_title(); ?>
            </h1>

            <?php TemplateLoader::part('story/partials/story-term'); ?>
        </div>
    </div>
<?php
get_footer();