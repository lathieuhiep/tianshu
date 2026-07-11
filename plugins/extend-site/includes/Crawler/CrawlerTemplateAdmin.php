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
            esc_html__('Mẫu crawler', 'extend-site'),
            esc_html__('Mẫu crawler', 'extend-site'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'extend-site'));
        }
        $templates = CrawlerTemplateTable::all();
        ?>
        <div class="wrap es-crawler-template-page">
            <h1><?php esc_html_e('Mẫu crawler', 'extend-site'); ?></h1>

            <div class="es-template-layout">
                <section class="es-template-panel es-template-form-panel">
                    <h2><?php esc_html_e('Cấu hình selector', 'extend-site'); ?></h2>

                    <form id="es-crawler-template-form">
                        <input type="hidden" id="es-template-id" name="template_id" value="0" />

                        <div class="es-template-step es-template-manage">
                            <h3><span>0</span><?php esc_html_e('Quản lý mẫu', 'extend-site'); ?></h3>

                            <div class="es-template-manage-row">
                                <div class="es-template-field">
                                    <label for="es-template-existing"><?php esc_html_e('Mẫu đã lưu', 'extend-site'); ?></label>
                                    <select id="es-template-existing">
                                        <option value=""><?php esc_html_e('Chọn mẫu để sửa', 'extend-site'); ?></option>
                                        <?php foreach ($templates as $template) : ?>
                                            <option value="<?php echo esc_attr((string) $template['id']); ?>" data-domain="<?php echo esc_attr($template['domain']); ?>">
                                                <?php echo esc_html($template['name'] . ' - ' . $template['domain']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="es-template-manage-actions">
                                    <button type="button" class="button" id="es-template-new"><?php esc_html_e('Tạo mới', 'extend-site'); ?></button>
                                    <button type="button" class="button button-primary" id="es-template-save"><?php esc_html_e('Lưu mẫu', 'extend-site'); ?></button>
                                    <button type="button" class="button button-link-delete" id="es-template-delete" disabled><?php esc_html_e('Xóa mẫu', 'extend-site'); ?></button>
                                </div>
                            </div>

                            <div id="es-template-save-status" class="es-template-save-status" aria-live="polite"></div>
                        </div>

                        <div class="es-template-step">
                            <h3><span>1</span><?php esc_html_e('Trang truyện / mục lục', 'extend-site'); ?></h3>
                            <p class="es-template-step-note"><?php esc_html_e('Các selector trong phần này chạy trên URL trang truyện mẫu: thông tin truyện và danh sách link chương.', 'extend-site'); ?></p>

                            <div class="es-template-subsection">
                                <h4><?php esc_html_e('Thông tin nguồn', 'extend-site'); ?></h4>
                            <div class="es-template-two-cols">
                                <?php self::render_text_field('es-template-name', 'name', __('Tên nguồn', 'extend-site')); ?>
                                <?php self::render_text_field('es-template-domain', 'domain', __('Domain', 'extend-site'), 'example.com'); ?>
                            </div>
                            <div class="es-template-field">
                                <label for="es-template-delay-between"><?php esc_html_e('Độ trễ giữa mỗi request', 'extend-site'); ?></label>
                                <input type="number" id="es-template-delay-between" name="delay_between" min="1" max="60" step="1" value="3" />
                                <p class="description"><?php esc_html_e('Tính bằng giây, dùng khi chạy crawler thật.', 'extend-site'); ?></p>
                            </div>
                            </div>

                            <div class="es-template-subsection">
                                <h4><?php esc_html_e('Thông tin truyện', 'extend-site'); ?></h4>
                            <?php self::render_extract_field('story_title', 'es-template-story-title-selector', 'story_title_selector', __('Tên truyện', 'extend-site'), 'node_text'); ?>
                            <?php self::render_extract_field('story_author', 'es-template-story-author-selector', 'story_author_selector', __('Tác giả', 'extend-site'), 'first_link_text', __('Tác giả', 'extend-site')); ?>
                            <?php self::render_extract_field('story_cats', 'es-template-story-cats-selector', 'story_cats_selector', __('Thể loại', 'extend-site'), 'all_link_texts', __('Thể loại', 'extend-site')); ?>
                            <?php self::render_extract_field('story_desc', 'es-template-story-desc-selector', 'story_desc_selector', __('Mô tả', 'extend-site'), 'node_text'); ?>
                            <?php self::render_extract_field('story_thumb', 'es-template-story-thumb-selector', 'story_thumb_selector', __('Ảnh bìa', 'extend-site'), 'first_image_src'); ?>
                            </div>

                            <div class="es-template-subsection">
                                <h4><?php esc_html_e('Danh sách chương trên trang truyện', 'extend-site'); ?></h4>
                                <p class="es-template-step-note"><?php esc_html_e('Các selector này chạy trên URL trang truyện mẫu để tạo queue link chương.', 'extend-site'); ?></p>

                                <div class="es-template-two-cols">
                                    <div class="es-template-field">
                                        <label for="es-template-toc-type"><?php esc_html_e('Kiểu mục lục', 'extend-site'); ?></label>
                                        <select id="es-template-toc-type" name="toc_type">
                                            <option value="selector"><?php esc_html_e('Selector', 'extend-site'); ?></option>
                                            <option value="pattern"><?php esc_html_e('Mẫu URL', 'extend-site'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Nên dùng Selector nếu trang truyện có danh sách chương.', 'extend-site'); ?></p>
                                    </div>
                                </div>

                                <div class="es-template-two-cols">
                                    <?php self::render_selector_field(
                                        'es-template-chapter-link-selector',
                                        'chapter_link_selector',
                                        __('Khối/link danh sách chương', 'extend-site'),
                                        __('Có thể nhập selector thẻ a hoặc khối chứa danh sách chương. Hỗ trợ #id, .class, tag.class và selector con như .chapter-list a.', 'extend-site')
                                    ); ?>
                                    <?php self::render_selector_field('es-template-toc-page-link-selector', 'toc_page_link_selector', __('Link phân trang mục lục', 'extend-site')); ?>
                                </div>
                                <?php self::render_text_field('es-template-chapter-url-pattern', 'chapter_url_pattern', __('Mẫu URL chương', 'extend-site'), 'https://example.com/story/chapter-{chapter_number}/'); ?>
                            </div>
                        </div>

                        <div class="es-template-step es-template-step-chapter" id="es-template-step-chapter">
                            <h3><span>2</span><?php esc_html_e('Trang chi tiết chương', 'extend-site'); ?></h3>

                            <p class="es-template-step-note"><?php esc_html_e('Khi test phần này, nhập URL chương vào ô URL xem trước. Crawler khoanh vùng chi tiết chương trước, rồi mới lấy tên và nội dung bên trong vùng đó.', 'extend-site'); ?></p>

                            <?php self::render_selector_field(
                                'es-template-chapter-content-scope-selector',
                                'chapter_content_scope_selector',
                                __('Vùng chi tiết chương *', 'extend-site'),
                                __('Khối lớn chứa tiêu đề và nội dung chương. Crawler chỉ tìm các selector chương bên trong vùng này.', 'extend-site')
                            ); ?>
                            <?php self::render_selector_field(
                                'es-template-chapter-title-selector',
                                'chapter_title_selector',
                                __('Tên chương', 'extend-site'),
                                __('Tùy chọn. Lấy tiêu đề chương bên trong vùng chi tiết chương.', 'extend-site')
                            ); ?>
                            <?php self::render_selector_field(
                                'es-template-chapter-content-selector',
                                'chapter_content_selector',
                                __('Nội dung truyện', 'extend-site'),
                                __('Tùy chọn. Nếu bỏ trống, crawler sẽ lấy nội dung từ toàn bộ Vùng chi tiết chương.', 'extend-site')
                            ); ?>

                            <div class="es-template-field es-template-cleanup-field">
                                <label><?php esc_html_e('Quy tắc tìm/thay thế', 'extend-site'); ?></label>
                                <div class="es-template-two-cols">
                                    <div>
                                        <label for="es-template-find-replace-find"><?php esc_html_e('Tìm nội dung', 'extend-site'); ?></label>
                                        <textarea id="es-template-find-replace-find" rows="5" placeholder="<?php echo esc_attr__('Mỗi dòng là một nội dung cần tìm', 'extend-site'); ?>"></textarea>
                                    </div>
                                    <div>
                                        <label for="es-template-find-replace-replace"><?php esc_html_e('Thay bằng', 'extend-site'); ?></label>
                                        <textarea id="es-template-find-replace-replace" rows="5" placeholder="<?php echo esc_attr__('Mỗi dòng tương ứng với dòng cần tìm bên trái', 'extend-site'); ?>"></textarea>
                                    </div>
                                </div>
                                <label class="es-template-checkbox">
                                    <input type="checkbox" id="es-template-find-replace-remove-container" />
                                    <?php esc_html_e('Xóa cả dòng/đoạn chứa nội dung cần tìm', 'extend-site'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Bên phải để trống thì crawler sẽ xóa nội dung bên trái. Mỗi dòng bên trái tương ứng với một dòng bên phải.', 'extend-site'); ?></p>
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
                    <h2><?php esc_html_e('Xem trước selector', 'extend-site'); ?></h2>

                    <div class="es-template-field">
                        <label for="es-template-target-url"><?php esc_html_e('URL xem trước / test selector', 'extend-site'); ?></label>
                        <input type="url" id="es-template-target-url" class="regular-text" placeholder="https://example.com/story/" />
                        <p class="description"><?php esc_html_e('Nhập URL trang truyện để test thông tin/mục lục, hoặc URL chương để test vùng chi tiết chương.', 'extend-site'); ?></p>
                    </div>

                    <button type="button" class="button button-primary" id="es-template-load-preview">
                        <?php esc_html_e('Tải xem trước', 'extend-site'); ?>
                    </button>

                    <div id="es-template-preview-status" class="es-template-preview-status" aria-live="polite"></div>
                    <iframe id="es-template-preview-frame" title="<?php echo esc_attr__('Xem trước mẫu crawler', 'extend-site'); ?>"></iframe>
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

    private static function render_selector_field(string $id, string $name, string $label, string $description = ''): void
    {
        ?>
        <div class="es-template-field es-template-selector-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <div class="es-template-selector-control">
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="regular-text es-template-selector-input" placeholder=".selector" />
                <button type="button" class="button es-template-reset-field" data-target="#<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr__('Đặt lại trường', 'extend-site'); ?>">
                    <?php esc_html_e('Đặt lại', 'extend-site'); ?>
                </button>
            </div>
            <?php if ($description !== '') : ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
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
                    <label for="<?php echo esc_attr($field); ?>-label"><?php esc_html_e('Text nhãn', 'extend-site'); ?></label>
                    <input type="text" id="<?php echo esc_attr($field); ?>-label" name="<?php echo esc_attr($field); ?>_label" class="regular-text" placeholder="<?php echo esc_attr($default_label ?: __('Bỏ trống nếu lấy trực tiếp', 'extend-site')); ?>" />
                </div>

                <div class="es-template-extract-value-control">
                    <label for="<?php echo esc_attr($field); ?>-value-mode"><?php esc_html_e('Kiểu lấy', 'extend-site'); ?></label>
                    <select id="<?php echo esc_attr($field); ?>-value-mode" name="<?php echo esc_attr($field); ?>_value_mode">
                        <?php self::render_value_mode_options($default_value_mode); ?>
                    </select>
                </div>

                <div class="es-template-extract-reset-control">
                    <button type="button" class="button es-template-reset-field" data-target="#<?php echo esc_attr($selector_id); ?>" aria-label="<?php echo esc_attr__('Đặt lại trường', 'extend-site'); ?>">
                        <?php esc_html_e('Đặt lại', 'extend-site'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_value_mode_options(string $selected): void
    {
        $options = [
            'next_text' => __('Text sau chữ nhận diện', 'extend-site'),
            'first_link_text' => __('Text link đầu tiên', 'extend-site'),
            'all_link_texts' => __('Text tất cả link', 'extend-site'),
            'first_link_href' => __('URL link đầu tiên', 'extend-site'),
            'first_image_src' => __('Src ảnh đầu tiên', 'extend-site'),
            'node_text' => __('Text cả khối', 'extend-site'),
            'node_html' => __('HTML cả khối', 'extend-site'),
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
