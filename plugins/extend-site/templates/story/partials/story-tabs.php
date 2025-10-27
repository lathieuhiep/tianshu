<?php
/**
 * Partial: Story Tabs (Chapters + Comments)
 *
 * @package extend-site
 */

use ExtendSite\PostType\TemplateLoader;

defined('ABSPATH') || exit;

$story_id = isset($args['story_id']) ? absint($args['story_id']) : get_the_ID();
?>
<div class="story-tabs" data-story-id="<?php echo esc_attr($story_id); ?>">
    <ul class="story-tab-nav es-list-style-none" role="tablist">
        <li>
            <button class="story-tab-btn is-active"
                    role="tab"
                    data-tab-target="#story-tab-chapters"
                    aria-selected="true">
                <?php echo esc_html__('Danh sách chương', 'extend-site'); ?>
            </button>
        </li>
        <li>
            <button class="story-tab-btn"
                    role="tab"
                    data-tab-target="#story-tab-comments"
                    aria-selected="false">
                <?php echo esc_html__('Bình luận', 'extend-site'); ?>
            </button>
        </li>
    </ul>

    <div class="story-tab-panels">
        <div id="story-tab-chapters" class="story-tab-panel story-chapters is-active" role="tabpanel">
            <?php TemplateLoader::part('story/parts/tab-chapters', ['story_id' => $story_id]); ?>
        </div>
        <div id="story-tab-comments" class="story-tab-panel story-comments" role="tabpanel" hidden>
            <?php TemplateLoader::part('story/parts/tab-comments', ['story_id' => $story_id]); ?>
        </div>
    </div>
</div>