<?php

defined('ABSPATH') || exit;

$template_id = isset($view_data['template_id']) ? (int)$view_data['template_id'] : 0;
$selected_template = isset($view_data['selected_template']) && is_array($view_data['selected_template']) ? $view_data['selected_template'] : null;
$list_url = isset($view_data['list_url']) ? (string)$view_data['list_url'] : admin_url('admin.php');
$render_text_field = is_callable($view_data['render_text_field_callback'] ?? null) ? $view_data['render_text_field_callback'] : static function (): void {
};
$render_selector_field = is_callable($view_data['render_selector_field_callback'] ?? null) ? $view_data['render_selector_field_callback'] : static function (): void {
};
$render_extract_field = is_callable($view_data['render_extract_field_callback'] ?? null) ? $view_data['render_extract_field_callback'] : static function (): void {
};
?>
<div class="wrap es-crawler-template-page">
    <h1><?php esc_html_e('Mẫu crawler', 'extend-site'); ?></h1>

    <p>
        <a class="button" href="<?php echo esc_url($list_url); ?>">
            <?php esc_html_e('Quay lại danh sách', 'extend-site'); ?>
        </a>
    </p>

    <div class="es-template-layout">
        <section class="es-template-panel es-template-form-panel">
            <h2><?php esc_html_e('Cấu hình selector', 'extend-site'); ?></h2>

            <form id="es-crawler-template-form">
                <input type="hidden" id="es-template-id" name="template_id"
                       value="<?php echo esc_attr((string)$template_id); ?>"/>

                <div class="es-template-step es-template-manage">
                    <h3><span>0</span><?php esc_html_e('Quản lý mẫu', 'extend-site'); ?></h3>

                    <div class="es-template-manage-row">
                        <div class="es-template-field">
                            <label for="es-template-existing"><?php esc_html_e('Mẫu đã lưu', 'extend-site'); ?></label>
                            <select id="es-template-existing">
                                <option value=""><?php esc_html_e('Chọn mẫu để sửa', 'extend-site'); ?></option>
                                <?php if ($selected_template) : ?>
                                    <option value="<?php echo esc_attr((string)$selected_template['id']); ?>"
                                            data-domain="<?php echo esc_attr($selected_template['domain']); ?>"
                                            selected>
                                        <?php echo esc_html($selected_template['name'] . ' - ' . $selected_template['domain']); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="es-template-manage-actions">
                            <button type="button" class="button button-primary"
                                    id="es-template-save"><?php esc_html_e('Lưu mẫu', 'extend-site'); ?></button>
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
                            <?php $render_text_field('es-template-name', 'name', __('Tên nguồn', 'extend-site')); ?>
                            <?php $render_text_field('es-template-domain', 'domain', __('Domain', 'extend-site'), 'example.com'); ?>
                        </div>
                        <div class="es-template-field">
                            <label for="es-template-delay-between"><?php esc_html_e('Độ trễ giữa mỗi request', 'extend-site'); ?></label>
                            <input type="number" id="es-template-delay-between" name="delay_between" min="1" max="60"
                                   step="1" value="3"/>
                            <p class="description"><?php esc_html_e('Tính bằng giây, dùng khi chạy crawler thật.', 'extend-site'); ?></p>
                        </div>
                    </div>

                    <div class="es-template-subsection">
                        <h4><?php esc_html_e('Thông tin truyện', 'extend-site'); ?></h4>
                        <?php $render_extract_field('story_title', 'es-template-story-title-selector', 'story_title_selector', __('Tên truyện', 'extend-site'), 'node_text'); ?>
                        <?php $render_extract_field('story_author', 'es-template-story-author-selector', 'story_author_selector', __('Tác giả', 'extend-site'), 'first_link_text', __('Tác giả', 'extend-site')); ?>
                        <?php $render_extract_field('story_cats', 'es-template-story-cats-selector', 'story_cats_selector', __('Thể loại', 'extend-site'), 'all_link_texts', __('Thể loại', 'extend-site')); ?>
                        <?php $render_extract_field('story_desc', 'es-template-story-desc-selector', 'story_desc_selector', __('Mô tả', 'extend-site'), 'node_text'); ?>
                        <?php $render_extract_field('story_thumb', 'es-template-story-thumb-selector', 'story_thumb_selector', __('Ảnh bìa', 'extend-site'), 'first_image_src'); ?>
                    </div>

                    <div class="es-template-subsection">
                        <h4><?php esc_html_e('Danh sách chương trên trang truyện', 'extend-site'); ?></h4>
                        <p class="es-template-step-note"><?php esc_html_e('Các selector này chạy trên URL trang truyện mẫu để tạo queue link chương.', 'extend-site'); ?></p>


                        <div class="es-template-two-cols">
                            <?php $render_selector_field(
                                    'es-template-chapter-link-selector',
                                    'chapter_link_selector',
                                    __('Khối/link danh sách chương', 'extend-site'),
                                    __('Có thể nhập selector thẻ a hoặc khối chứa danh sách chương. Hỗ trợ #id, .class, tag.class và selector con như .chapter-list a.', 'extend-site')
                            ); ?>
                            <?php $render_selector_field('es-template-toc-page-link-selector', 'toc_page_link_selector', __('Link phân trang mục lục', 'extend-site')); ?>
                        </div>
                        <?php $render_text_field(
                                'es-template-chapter-url-pattern',
                                'chapter_url_pattern',
                                __('Mẫu URL chương', 'extend-site'),
                                '{story_url}/chuong-{chapter_number}/',
                                __('Dung {story_url} de lay URL truyen dang cao, roi thay vi tri so chuong bang {chapter_number}. Vi du: {story_url}/chuong-{chapter_number}/. Neu site dung slug ngay sau domain, co the dung https://domain.com/{story_slug}/chuong-{chapter_number}/. Khong nen nhap cung slug cua truyen mau.', 'extend-site')
                        ); ?>
                    </div>
                </div>

                <div class="es-template-step es-template-step-chapter" id="es-template-step-chapter">
                    <h3><span>2</span><?php esc_html_e('Trang chi tiết chương', 'extend-site'); ?></h3>

                    <p class="es-template-step-note"><?php esc_html_e('Khi test phần này, nhập URL chương vào ô URL xem trước. Crawler khoanh vùng chi tiết chương trước, rồi mới lấy tên và nội dung bên trong vùng đó.', 'extend-site'); ?></p>

                    <?php $render_selector_field(
                            'es-template-chapter-content-scope-selector',
                            'chapter_content_scope_selector',
                            __('Vùng chi tiết chương *', 'extend-site'),
                            __('Khối lớn chứa tiêu đề và nội dung chương. Crawler chỉ tìm các selector chương bên trong vùng này.', 'extend-site'),
                            '.chapter-detail'
                    ); ?>
                    <?php $render_selector_field(
                            'es-template-chapter-title-selector',
                            'chapter_title_selector',
                            __('Tên chương', 'extend-site'),
                            __('Tùy chọn. Lấy tiêu đề chương bên trong vùng chi tiết chương.', 'extend-site'),
                            'h1'
                    ); ?>
                    <?php $render_selector_field(
                            'es-template-chapter-content-selector',
                            'chapter_content_selector',
                            __('Nội dung truyện', 'extend-site'),
                            __('Tùy chọn. Nếu bỏ trống, crawler sẽ lấy nội dung từ toàn bộ Vùng chi tiết chương.', 'extend-site'),
                            'div:nth-of-type(2)'
                    ); ?>

                    <div class="es-template-field es-template-cleanup-field">
                        <label><?php esc_html_e('Quy tắc tìm/thay thế', 'extend-site'); ?></label>
                        <div class="es-template-two-cols">
                            <div>
                                <label for="es-template-find-replace-find"><?php esc_html_e('Tìm nội dung', 'extend-site'); ?></label>
                                <textarea id="es-template-find-replace-find" rows="5"
                                          placeholder="<?php echo esc_attr__('Mỗi dòng là một nội dung cần tìm', 'extend-site'); ?>"></textarea>
                            </div>
                            <div>
                                <label for="es-template-find-replace-replace"><?php esc_html_e('Thay bằng', 'extend-site'); ?></label>
                                <textarea id="es-template-find-replace-replace" rows="5"
                                          placeholder="<?php echo esc_attr__('Mỗi dòng tương ứng với dòng cần tìm bên trái', 'extend-site'); ?>"></textarea>
                            </div>
                        </div>
                        <label class="es-template-checkbox">
                            <input type="checkbox" id="es-template-find-replace-remove-container"/>
                            <?php esc_html_e('Xóa cả dòng/đoạn chứa nội dung cần tìm', 'extend-site'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Bên phải để trống thì crawler sẽ xóa nội dung bên trái. Mỗi dòng bên trái tương ứng với một dòng bên phải.', 'extend-site'); ?></p>
                        <input type="hidden" id="es-template-find-replace-rules" name="find_replace_rules" value="[]"/>
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

            <div class="es-template-preview-url-grid">
                <div class="es-template-field">
                    <label for="es-template-target-url"><?php esc_html_e('URL trang truyện / mục lục', 'extend-site'); ?></label>
                    <input type="url" id="es-template-target-url" class="regular-text"
                           placeholder="https://example.com/story/"/>
                    <p class="description"><?php esc_html_e('Chỉ dùng để test selector khi sửa template và sẽ được lưu theo template. Không ảnh hưởng URL truyện khi cào thật.', 'extend-site'); ?></p>
                    <button type="button" class="button button-primary" id="es-template-load-preview">
                        <?php esc_html_e('Xem trước trang truyện', 'extend-site'); ?>
                    </button>
                </div>

                <div class="es-template-field">
                    <label for="es-template-chapter-url"><?php esc_html_e('URL chương mẫu', 'extend-site'); ?></label>
                    <input type="url" id="es-template-chapter-url" class="regular-text"
                           placeholder="https://example.com/story/chapter-1/"/>
                    <p class="description"><?php esc_html_e('Chỉ dùng để test vùng chi tiết chương, tên chương và nội dung truyện; sẽ được lưu theo template để lần sau mở lại dùng tiếp.', 'extend-site'); ?></p>
                    <button type="button" class="button button-secondary" id="es-template-load-chapter-preview">
                        <?php esc_html_e('Xem trước chương mẫu', 'extend-site'); ?>
                    </button>
                </div>
            </div>

            <div id="es-template-preview-status" class="es-template-preview-status" aria-live="polite"></div>
            <iframe id="es-template-preview-frame"
                    title="<?php echo esc_attr__('Xem trước mẫu crawler', 'extend-site'); ?>"></iframe>
        </aside>
    </div>
</div>
