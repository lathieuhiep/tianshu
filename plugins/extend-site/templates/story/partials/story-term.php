<?php
use ExtendSite\PostType\TemplateLoader;
?>

<div class="es-archive-warp es-pt-10 es-pb-10" data-plugin="extend-site" itemscope itemtype="https://schema.org/CollectionPage">
    <div class="es-container">
        <h1 class="page-archive-title es-fs-lg">
            <?php single_term_title(); ?>
        </h1>

        <?php TemplateLoader::part('story/parts/content-archive'); ?>
    </div>
</div>