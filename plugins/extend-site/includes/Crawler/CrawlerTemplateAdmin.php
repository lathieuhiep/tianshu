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
            esc_html__('Mau crawler', 'extend-site'),
            esc_html__('Mau crawler', 'extend-site'),
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
        $templates = CrawlerTemplateTable::all();
        ?>
        <div class="wrap es-crawler-template-page">
            <h1><?php esc_html_e('Mau crawler', 'extend-site'); ?></h1>

            <div class="es-template-layout">
                <section class="es-template-panel es-template-form-panel">
                    <h2><?php esc_html_e('Cau hinh selector', 'extend-site'); ?></h2>

                    <form id="es-crawler-template-form">
                        <input type="hidden" id="es-template-id" name="template_id" value="0" />

                        <div class="es-template-step es-template-manage">
                            <h3><span>0</span><?php esc_html_e('Quan ly mau', 'extend-site'); ?></h3>

                            <div class="es-template-manage-row">
                                <div class="es-template-field">
                                    <label for="es-template-existing"><?php esc_html_e('Mau da luu', 'extend-site'); ?></label>
                                    <select id="es-template-existing">
                                        <option value=""><?php esc_html_e('Chon mau de sua', 'extend-site'); ?></option>
                                        <?php foreach ($templates as $template) : ?>
                                            <option value="<?php echo esc_attr((string) $template['id']); ?>" data-domain="<?php echo esc_attr($template['domain']); ?>">
                                                <?php echo esc_html($template['name'] . ' - ' . $template['domain']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="es-template-manage-actions">
                                    <button type="button" class="button" id="es-template-new"><?php esc_html_e('Tao moi', 'extend-site'); ?></button>
                                    <button type="button" class="button button-primary" id="es-template-save"><?php esc_html_e('Luu mau', 'extend-site'); ?></button>
                                    <button type="button" class="button" id="es-template-list-toggle"><?php esc_html_e('Danh sach mau', 'extend-site'); ?></button>
                                    <button type="button" class="button button-link-delete" id="es-template-delete" disabled><?php esc_html_e('Xoa mau', 'extend-site'); ?></button>
                                </div>
                            </div>

                            <div id="es-template-save-status" class="es-template-save-status" aria-live="polite"></div>

                            <div class="es-template-list-panel is-hidden" id="es-template-list-panel">
                                <label for="es-template-list-search"><?php esc_html_e('Tim mau', 'extend-site'); ?></label>
                                <input type="search" id="es-template-list-search" class="regular-text" placeholder="<?php echo esc_attr__('Nhap ten hoac domain', 'extend-site'); ?>" />

                                <table class="widefat striped es-template-list-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Ten mau', 'extend-site'); ?></th>
                                            <th><?php esc_html_e('Domain', 'extend-site'); ?></th>
                                            <th><?php esc_html_e('Hanh dong', 'extend-site'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="es-template-list-body">
                                        <?php foreach ($templates as $template) : ?>
                                            <tr data-template-id="<?php echo esc_attr((string) $template['id']); ?>" data-search="<?php echo esc_attr(strtolower($template['name'] . ' ' . $template['domain'])); ?>">
                                                <td><?php echo esc_html($template['name']); ?></td>
                                                <td><code><?php echo esc_html($template['domain']); ?></code></td>
                                                <td>
                                                    <button type="button" class="button button-small es-template-list-edit" data-template-id="<?php echo esc_attr((string) $template['id']); ?>"><?php esc_html_e('Sua', 'extend-site'); ?></button>
                                                    <button type="button" class="button button-small button-link-delete es-template-list-delete" data-template-id="<?php echo esc_attr((string) $template['id']); ?>"><?php esc_html_e('Xoa', 'extend-site'); ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="es-template-step">
                            <h3><span>1</span><?php esc_html_e('Thong tin truyen', 'extend-site'); ?></h3>

                            <div class="es-template-two-cols">
                                <?php self::render_text_field('es-template-name', 'name', __('Ten nguon', 'extend-site')); ?>
                                <?php self::render_text_field('es-template-domain', 'domain', __('Domain', 'extend-site'), 'example.com'); ?>
                            </div>

                            <?php self::render_extract_field('story_title', 'es-template-story-title-selector', 'story_title_selector', __('Ten truyen', 'extend-site'), 'node_text'); ?>
                            <?php self::render_selector_field('es-template-chapter-content-selector', 'chapter_content_selector', __('Selector noi dung chuong', 'extend-site')); ?>
                            <?php self::render_extract_field('story_author', 'es-template-story-author-selector', 'story_author_selector', __('Tac gia', 'extend-site'), 'first_link_text', __('Tac gia', 'extend-site')); ?>
                            <?php self::render_extract_field('story_cats', 'es-template-story-cats-selector', 'story_cats_selector', __('The loai', 'extend-site'), 'all_link_texts', __('The loai', 'extend-site')); ?>
                            <?php self::render_extract_field('story_desc', 'es-template-story-desc-selector', 'story_desc_selector', __('Mo ta', 'extend-site'), 'node_text'); ?>
                            <?php self::render_extract_field('story_thumb', 'es-template-story-thumb-selector', 'story_thumb_selector', __('Anh bia', 'extend-site'), 'first_image_src'); ?>
                        </div>

                        <div class="es-template-step es-template-step-chapter" id="es-template-step-chapter">
                            <h3><span>2</span><?php esc_html_e('Thong tin chuong', 'extend-site'); ?></h3>

                            <div class="es-template-two-cols">
                                <div class="es-template-field">
                                    <label for="es-template-toc-type"><?php esc_html_e('Kieu muc luc', 'extend-site'); ?></label>
                                    <select id="es-template-toc-type" name="toc_type">
                                        <option value="selector"><?php esc_html_e('Selector', 'extend-site'); ?></option>
                                        <option value="pattern"><?php esc_html_e('Mau URL', 'extend-site'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Nen dung Selector neu trang truyen co danh sach chuong.', 'extend-site'); ?></p>
                                </div>

                                <div class="es-template-field">
                                    <label for="es-template-delay-between"><?php esc_html_e('Do tre giua moi request', 'extend-site'); ?></label>
                                    <input type="number" id="es-template-delay-between" name="delay_between" min="1" max="60" step="1" value="1" />
                                    <p class="description"><?php esc_html_e('Tinh bang giay, dung khi chay crawler that.', 'extend-site'); ?></p>
                                </div>
                            </div>

                            <div class="es-template-two-cols">
                                <?php self::render_selector_field('es-template-chapter-link-selector', 'chapter_link_selector', __('Selector link chuong', 'extend-site')); ?>
                                <?php self::render_selector_field('es-template-chapter-title-selector', 'chapter_title_selector', __('Selector ten chuong', 'extend-site')); ?>
                            </div>
                            <?php self::render_selector_field('es-template-toc-page-link-selector', 'toc_page_link_selector', __('Selector link phan trang muc luc', 'extend-site')); ?>
                            <?php self::render_text_field('es-template-chapter-url-pattern', 'chapter_url_pattern', __('Mau URL chuong', 'extend-site'), 'https://example.com/story/chapter-{chapter_number}/'); ?>

                            <div class="es-template-field es-template-cleanup-field">
                                <label><?php esc_html_e('Quy tac tim/thay the', 'extend-site'); ?></label>
                                <div class="es-template-two-cols">
                                    <div>
                                        <label for="es-template-find-replace-find"><?php esc_html_e('Tim noi dung', 'extend-site'); ?></label>
                                        <textarea id="es-template-find-replace-find" rows="5" placeholder="<?php echo esc_attr__('Moi dong la mot noi dung can tim', 'extend-site'); ?>"></textarea>
                                    </div>
                                    <div>
                                        <label for="es-template-find-replace-replace"><?php esc_html_e('Thay bang', 'extend-site'); ?></label>
                                        <textarea id="es-template-find-replace-replace" rows="5" placeholder="<?php echo esc_attr__('Moi dong tuong ung voi dong can tim ben trai', 'extend-site'); ?>"></textarea>
                                    </div>
                                </div>
                                <label class="es-template-checkbox">
                                    <input type="checkbox" id="es-template-find-replace-remove-container" />
                                    <?php esc_html_e('Xoa ca dong/doan chua noi dung can tim', 'extend-site'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Ben phai de trong thi crawler se xoa noi dung ben trai. Moi dong ben trai tuong ung voi mot dong ben phai.', 'extend-site'); ?></p>
                                <input type="hidden" id="es-template-find-replace-rules" name="find_replace_rules" value="[]" />
                            </div>
                        </div>

                        <div class="es-template-actions">
                            <button type="button" class="button button-secondary" id="es-template-test-parse">
                                <?php esc_html_e('Test selector', 'extend-site'); ?>
                            </button>
                        </div>

                        <div id="es-template-test-result" class="es-template-test-result" aria-live="polite"></div>
                    </form>
                </section>

                <aside class="es-template-panel es-template-preview-panel">
                    <h2><?php esc_html_e('Xem truoc selector', 'extend-site'); ?></h2>

                    <div class="es-template-field">
                        <label for="es-template-target-url"><?php esc_html_e('URL truyen mau', 'extend-site'); ?></label>
                        <input type="url" id="es-template-target-url" class="regular-text" placeholder="https://example.com/story/" />
                    </div>

                    <button type="button" class="button button-primary" id="es-template-load-preview">
                        <?php esc_html_e('Tai xem truoc', 'extend-site'); ?>
                    </button>

                    <div id="es-template-preview-status" class="es-template-preview-status" aria-live="polite"></div>
                    <iframe id="es-template-preview-frame" title="<?php echo esc_attr__('Xem truoc mau crawler', 'extend-site'); ?>"></iframe>
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
                <button type="button" class="button es-template-reset-field" data-target="#<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr__('Dat lai truong', 'extend-site'); ?>">
                    <?php esc_html_e('Dat lai', 'extend-site'); ?>
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

            <div class="es-template-extract-controls">
                <div class="es-template-extract-selector-control">
                    <label for="<?php echo esc_attr($selector_id); ?>"><?php esc_html_e('Class / selector', 'extend-site'); ?></label>
                    <input type="text" id="<?php echo esc_attr($selector_id); ?>" name="<?php echo esc_attr($selector_name); ?>" class="regular-text es-template-selector-input es-template-extract-selector-input" placeholder=".selector" />
                </div>

                <div class="es-template-extract-label-control">
                    <label for="<?php echo esc_attr($field); ?>-label"><?php esc_html_e('Text nhan', 'extend-site'); ?></label>
                    <input type="text" id="<?php echo esc_attr($field); ?>-label" name="<?php echo esc_attr($field); ?>_label" class="regular-text" placeholder="<?php echo esc_attr($default_label ?: __('Bo trong neu lay truc tiep', 'extend-site')); ?>" />
                </div>

                <div class="es-template-extract-value-control">
                    <label for="<?php echo esc_attr($field); ?>-value-mode"><?php esc_html_e('Kieu lay', 'extend-site'); ?></label>
                    <select id="<?php echo esc_attr($field); ?>-value-mode" name="<?php echo esc_attr($field); ?>_value_mode">
                        <?php self::render_value_mode_options($default_value_mode); ?>
                    </select>
                </div>

                <div class="es-template-extract-reset-control">
                    <button type="button" class="button es-template-reset-field" data-target="#<?php echo esc_attr($selector_id); ?>" aria-label="<?php echo esc_attr__('Dat lai truong', 'extend-site'); ?>">
                        <?php esc_html_e('Dat lai', 'extend-site'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_value_mode_options(string $selected): void
    {
        $options = [
            'next_text' => __('Text sau chu nhan dien', 'extend-site'),
            'first_link_text' => __('Text link dau tien', 'extend-site'),
            'all_link_texts' => __('Text tat ca link', 'extend-site'),
            'first_link_href' => __('URL link dau tien', 'extend-site'),
            'first_image_src' => __('Src anh dau tien', 'extend-site'),
            'node_text' => __('Text ca khoi', 'extend-site'),
            'node_html' => __('HTML ca khoi', 'extend-site'),
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
