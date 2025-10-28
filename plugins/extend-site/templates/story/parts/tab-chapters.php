<?php
use ExtendSite\Ajax\LoadChapters;

$story_id = get_the_ID();
$paged    = isset($_GET['chap_page']) ? max(1, (int) $_GET['chap_page']) : 1;
$per_page = 10;
?>

<div class="es-chapters"
     data-story-id="<?php echo esc_attr($story_id); ?>"
     data-per-page="<?php echo esc_attr($per_page); ?>"
     data-current-page="<?php echo esc_attr($paged); ?>">

    <?php echo LoadChapters::render($story_id, $paged, $per_page); ?>
</div>