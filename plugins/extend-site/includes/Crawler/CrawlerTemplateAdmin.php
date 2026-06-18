<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class CrawlerTemplateAdmin
{
    public const PAGE_SLUG = 'extend-site-crawler-templates';
    public const PARENT_SLUG = CrawlerAdmin::PARENT_SLUG;

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 19);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Crawler Templates', 'extend-site'),
            esc_html__('Crawler Templates', 'extend-site'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'extend-site'));
        }
        ?>
        <div class="wrap es-crawler-template-page">
            <h1><?php esc_html_e('Crawler Template', 'extend-site'); ?></h1>

            <div class="es-template-layout">
                <section class="es-template-panel es-template-form-panel">
                    <h2><?php esc_html_e('Template selectors', 'extend-site'); ?></h2>

                    <form id="es-crawler-template-form">
                        <div class="es-template-step">
                            <h3><span>1</span><?php esc_html_e('Story information', 'extend-site'); ?></h3>

                            <div class="es-template-two-cols">
                                <?php self::render_text_field('es-template-name', 'name', __('Source name', 'extend-site')); ?>
                                <?php self::render_text_field('es-template-domain', 'domain', __('Domain', 'extend-site'), 'example.com'); ?>
                            </div>

                            <?php self::render_extract_field('story_title', 'es-template-story-title-selector', 'story_title_selector', __('Title', 'extend-site'), 'node_text'); ?>
                            <?php self::render_extract_field('story_author', 'es-template-story-author-selector', 'story_author_selector', __('Author', 'extend-site'), 'first_link_text', __('Tac gia', 'extend-site')); ?>
                            <?php self::render_extract_field('story_desc', 'es-template-story-desc-selector', 'story_desc_selector', __('Description', 'extend-site'), 'node_text'); ?>
                            <?php self::render_extract_field('story_thumb', 'es-template-story-thumb-selector', 'story_thumb_selector', __('Thumbnail', 'extend-site'), 'first_image_src'); ?>
                            <?php self::render_extract_field('story_cats', 'es-template-story-cats-selector', 'story_cats_selector', __('Categories', 'extend-site'), 'all_link_texts', __('The loai', 'extend-site')); ?>

                            <p>
                                <button type="button" class="button button-primary" id="es-template-unlock-chapter">
                                    <?php esc_html_e('Continue to chapter setup', 'extend-site'); ?>
                                </button>
                            </p>
                        </div>

                        <fieldset class="es-template-step es-template-step-chapter is-locked" id="es-template-step-chapter" disabled>
                            <h3><span>2</span><?php esc_html_e('Chapter information', 'extend-site'); ?></h3>

                            <div class="es-template-two-cols">
                                <div class="es-template-field">
                                    <label for="es-template-toc-type"><?php esc_html_e('TOC type', 'extend-site'); ?></label>
                                    <select id="es-template-toc-type" name="toc_type">
                                        <option value="selector"><?php esc_html_e('Selector', 'extend-site'); ?></option>
                                        <option value="pattern"><?php esc_html_e('URL pattern', 'extend-site'); ?></option>
                                    </select>
                                </div>

                                <div class="es-template-field">
                                    <label for="es-template-delay-between"><?php esc_html_e('Delay between requests', 'extend-site'); ?></label>
                                    <input type="number" id="es-template-delay-between" name="delay_between" min="1" max="60" step="1" value="1" />
                                </div>
                            </div>

                            <?php self::render_text_field('es-template-chapter-url-pattern', 'chapter_url_pattern', __('Chapter URL pattern', 'extend-site'), 'https://example.com/story/chapter-{chapter_number}/'); ?>
                            <?php self::render_selector_field('es-template-chapter-link-selector', 'chapter_link_selector', __('Chapter link selector', 'extend-site')); ?>
                            <?php self::render_selector_field('es-template-chapter-title-selector', 'chapter_title_selector', __('Chapter title selector', 'extend-site')); ?>
                            <?php self::render_selector_field('es-template-chapter-content-selector', 'chapter_content_selector', __('Chapter content selector', 'extend-site')); ?>

                            <div class="es-template-field">
                                <label for="es-template-find-replace-rules"><?php esc_html_e('Find/replace rules JSON', 'extend-site'); ?></label>
                                <textarea id="es-template-find-replace-rules" name="find_replace_rules" rows="5" placeholder='[{"find":"Source: example.com","replace":"","regex":false,"remove_container":true}]'></textarea>
                            </div>
                        </fieldset>

                        <div class="es-template-actions">
                            <button type="button" class="button button-secondary" id="es-template-test-parse">
                                <?php esc_html_e('Test selector', 'extend-site'); ?>
                            </button>
                        </div>

                        <div id="es-template-test-result" class="es-template-test-result" aria-live="polite"></div>
                    </form>
                </section>

                <aside class="es-template-panel es-template-preview-panel">
                    <h2><?php esc_html_e('Selector preview', 'extend-site'); ?></h2>

                    <div class="es-template-field">
                        <label for="es-template-target-url"><?php esc_html_e('Sample story URL', 'extend-site'); ?></label>
                        <input type="url" id="es-template-target-url" class="regular-text" placeholder="https://example.com/story/" />
                    </div>

                    <button type="button" class="button button-primary" id="es-template-load-preview">
                        <?php esc_html_e('Load preview', 'extend-site'); ?>
                    </button>

                    <div id="es-template-preview-status" class="es-template-preview-status" aria-live="polite"></div>
                    <iframe id="es-template-preview-frame" title="<?php echo esc_attr__('Crawler template preview', 'extend-site'); ?>"></iframe>
                </aside>
            </div>
        </div>
        <?php
    }

    private static function render_text_field(string $id, string $name, string $label, string $placeholder = ''): void
    {
        ?>
        <div class="es-template-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php echo esc_attr($placeholder); ?>" />
        </div>
        <?php
    }

    private static function render_selector_field(string $id, string $name, string $label): void
    {
        ?>
        <div class="es-template-field es-template-selector-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <div class="es-template-selector-control">
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="regular-text es-template-selector-input" placeholder=".selector" />
                <button type="button" class="button es-template-reset-field" data-target="#<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr__('Reset field', 'extend-site'); ?>">
                    <?php esc_html_e('Reset', 'extend-site'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    private static function render_extract_field(string $field, string $selector_id, string $selector_name, string $label, string $default_value_mode, string $default_label = ''): void
    {
        ?>
        <div class="es-template-field es-template-extract-field" data-extract-field="<?php echo esc_attr($field); ?>">
            <label for="<?php echo esc_attr($selector_id); ?>"><?php echo esc_html($label); ?></label>

            <div class="es-template-extract-mode-row">
                <select name="<?php echo esc_attr($field); ?>_extract_mode" class="es-template-extract-mode" aria-label="<?php echo esc_attr__('Extraction mode', 'extend-site'); ?>">
                    <option value="selector"><?php esc_html_e('Direct selector', 'extend-site'); ?></option>
                    <option value="label"><?php esc_html_e('Label in area', 'extend-site'); ?></option>
                </select>
            </div>

            <div class="es-template-selector-control es-template-direct-controls">
                <input type="text" id="<?php echo esc_attr($selector_id); ?>" name="<?php echo esc_attr($selector_name); ?>" class="regular-text es-template-selector-input" placeholder=".selector" />
                <button type="button" class="button es-template-reset-field" data-target="#<?php echo esc_attr($selector_id); ?>" aria-label="<?php echo esc_attr__('Reset field', 'extend-site'); ?>">
                    <?php esc_html_e('Reset', 'extend-site'); ?>
                </button>
            </div>

            <div class="es-template-label-controls is-hidden">
                <div class="es-template-three-cols">
                    <div>
                        <label for="<?php echo esc_attr($field); ?>-area-selector"><?php esc_html_e('Info area', 'extend-site'); ?></label>
                        <input type="text" id="<?php echo esc_attr($field); ?>-area-selector" name="<?php echo esc_attr($field); ?>_area_selector" class="regular-text es-template-selector-input" placeholder=".story-info" />
                    </div>
                    <div>
                        <label for="<?php echo esc_attr($field); ?>-label"><?php esc_html_e('Label text', 'extend-site'); ?></label>
                        <input type="text" id="<?php echo esc_attr($field); ?>-label" name="<?php echo esc_attr($field); ?>_label" class="regular-text" value="<?php echo esc_attr($default_label); ?>" placeholder="<?php echo esc_attr__('Author', 'extend-site'); ?>" />
                    </div>
                    <div>
                        <label for="<?php echo esc_attr($field); ?>-value-mode"><?php esc_html_e('Value mode', 'extend-site'); ?></label>
                        <select id="<?php echo esc_attr($field); ?>-value-mode" name="<?php echo esc_attr($field); ?>_value_mode">
                            <?php self::render_value_mode_options($default_value_mode); ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_value_mode_options(string $selected): void
    {
        $options = [
            'next_text' => __('Text after label', 'extend-site'),
            'first_link_text' => __('First link text', 'extend-site'),
            'all_link_texts' => __('All link texts', 'extend-site'),
            'first_link_href' => __('First link href', 'extend-site'),
            'first_image_src' => __('First image src', 'extend-site'),
            'node_text' => __('Whole block text', 'extend-site'),
            'node_html' => __('Whole block HTML', 'extend-site'),
        ];

        foreach ($options as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($selected, $value, false),
                esc_html($label)
            );
        }
    }
}
