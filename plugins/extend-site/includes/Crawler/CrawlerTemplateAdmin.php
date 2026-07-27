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
        add_action('admin_init', [self::class, 'handle_admin_action']);
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

        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Thêm mẫu crawler', 'extend-site'),
            esc_html__('Thêm mẫu crawler', 'extend-site'),
            'manage_options',
            self::PAGE_SLUG . '-new',
            [self::class, 'render_new_page']
        );
    }

    public static function render_new_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'extend-site'));
        }

        self::render_form_page('new');
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'extend-site'));
        }
        $action = self::current_action();
        if ($action === 'new' || $action === 'edit') {
            self::render_form_page($action);
            return;
        }

        self::render_list_page();
    }

    private static function current_action(): string
    {
        $action = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : 'list';

        return in_array($action, ['list', 'new', 'edit', 'trash'], true) ? $action : 'list';
    }

    public static function handle_admin_action(): void
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page !== self::PAGE_SLUG) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';
        if (!in_array($action, ['trash_template', 'restore_template', 'force_delete_template'], true)) {
            return;
        }

        $template_id = absint($_GET['id'] ?? 0);
        if ($template_id <= 0) {
            wp_die(esc_html__('ID mẫu crawler không hợp lệ.', 'extend-site'));
        }

        check_admin_referer('es_crawler_template_' . $action . '_' . $template_id);

        $notice = 'error';
        if ($action === 'trash_template' && CrawlerTemplateTable::trash($template_id)) {
            $notice = 'trashed';
        } elseif ($action === 'restore_template' && CrawlerTemplateTable::restore($template_id)) {
            $notice = 'restored';
        } elseif ($action === 'force_delete_template' && CrawlerTemplateTable::force_delete($template_id)) {
            $notice = 'deleted';
        }

        $redirect_args = [
            'crawler_template_notice' => $notice,
        ];
        if ($action !== 'trash_template') {
            $redirect_args['action'] = 'trash';
        }

        wp_safe_redirect(self::page_url($redirect_args));
        exit;
    }

    private static function page_url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::PAGE_SLUG], $args),
            admin_url('admin.php')
        );
    }

    private static function template_action_url(string $action, int $template_id): string
    {
        return wp_nonce_url(
            self::page_url([
                'action' => $action,
                'id' => $template_id,
            ]),
            'es_crawler_template_' . $action . '_' . $template_id
        );
    }

    private static function render_notice(): void
    {
        $notice = isset($_GET['crawler_template_notice']) ? sanitize_key((string) wp_unslash($_GET['crawler_template_notice'])) : '';
        $messages = [
            'trashed' => __('Đã bỏ mẫu crawler vào thùng rác.', 'extend-site'),
            'restored' => __('Đã khôi phục mẫu crawler.', 'extend-site'),
            'deleted' => __('Đã xóa vĩnh viễn mẫu crawler.', 'extend-site'),
            'error' => __('Không thể xử lý mẫu crawler.', 'extend-site'),
        ];

        if (!isset($messages[$notice])) {
            return;
        }
        ?>
        <div class="notice <?php echo $notice === 'error' ? 'notice-error' : 'notice-success'; ?> is-dismissible">
            <p><?php echo esc_html($messages[$notice]); ?></p>
        </div>
        <?php
    }

    private static function render_list_page(): void
    {
        $search = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
        $status = self::current_action() === 'trash' ? 'trash' : 'active';
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 20;
        $all_items = CrawlerTemplateTable::count(['status' => 'active']);
        $trash_items = CrawlerTemplateTable::count(['status' => 'trash']);
        $total_items = CrawlerTemplateTable::count(['search' => $search, 'status' => $status]);
        $total_pages = max(1, (int) ceil($total_items / $per_page));
        $paged = min($paged, $total_pages);
        $templates = CrawlerTemplateTable::query([
            'search' => $search,
            'status' => $status,
            'paged' => $paged,
            'per_page' => $per_page,
        ]);
        ?>
        <div class="wrap es-crawler-template-page">
            <div class="es-template-list-heading">
                <div>
                    <h1><?php esc_html_e('Mẫu crawler', 'extend-site'); ?></h1>
                    <p><?php esc_html_e('Quản lý các mẫu bóc dữ liệu truyện, mục lục và nội dung chương.', 'extend-site'); ?></p>
                </div>
                <a class="button button-primary" href="<?php echo esc_url(self::page_url(['action' => 'new'])); ?>">
                    <?php esc_html_e('Thêm mẫu mới', 'extend-site'); ?>
                </a>
            </div>
            <hr class="wp-header-end">
            <?php self::render_notice(); ?>

            <div class="es-template-list-card">
                <div class="es-template-list-toolbar">
                    <ul class="subsubsub es-template-list-views">
                        <li>
                            <a href="<?php echo esc_url(self::page_url($search !== '' ? ['s' => $search] : [])); ?>" class="<?php echo $status === 'active' ? 'current' : ''; ?>" <?php echo $status === 'active' ? 'aria-current="page"' : ''; ?>>
                                <?php
                                printf(
                                    '%s <span class="count">(%s)</span>',
                                    esc_html__('Tất cả', 'extend-site'),
                                    esc_html(number_format_i18n($all_items))
                                );
                                ?>
                            </a>
                        </li>
                        <li>
                            | <a href="<?php echo esc_url(self::page_url(array_merge(['action' => 'trash'], $search !== '' ? ['s' => $search] : []))); ?>" class="<?php echo $status === 'trash' ? 'current' : ''; ?>" <?php echo $status === 'trash' ? 'aria-current="page"' : ''; ?>>
                                <?php
                                printf(
                                    '%s <span class="count">(%s)</span>',
                                    esc_html__('Thùng rác', 'extend-site'),
                                    esc_html(number_format_i18n($trash_items))
                                );
                                ?>
                            </a>
                        </li>
                        <?php if ($search !== '') : ?>
                            <li>
                                | <span class="current">
                                    <?php
                                    printf(
                                        '%s <span class="count">(%s)</span>',
                                        esc_html__('Kết quả tìm kiếm', 'extend-site'),
                                        esc_html(number_format_i18n($total_items))
                                    );
                                    ?>
                                </span>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="es-template-list-count">
                        <?php
                        printf(
                            esc_html__('%s mẫu crawler', 'extend-site'),
                            esc_html(number_format_i18n($total_items))
                        );
                        ?>
                    </div>

                    <form method="get" class="es-template-search-form">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
                        <label class="screen-reader-text" for="es-crawler-template-search-input"><?php esc_html_e('Tìm mẫu crawler', 'extend-site'); ?></label>
                        <input type="search" id="es-crawler-template-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Tìm theo tên hoặc domain', 'extend-site'); ?>" />
                        <input type="submit" class="button" value="<?php echo esc_attr__('Tìm', 'extend-site'); ?>" />
                        <?php if ($search !== '') : ?>
                            <a class="button" href="<?php echo esc_url(self::page_url($status === 'trash' ? ['action' => 'trash'] : [])); ?>"><?php esc_html_e('Xóa lọc', 'extend-site'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <table class="widefat striped es-template-list-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Tên mẫu', 'extend-site'); ?></th>
                            <th scope="col"><?php esc_html_e('Domain', 'extend-site'); ?></th>
                            <th scope="col"><?php esc_html_e('Cập nhật', 'extend-site'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($templates) : ?>
                            <?php foreach ($templates as $template) : ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php if ($status === 'trash') : ?>
                                                <?php echo esc_html((string) $template['name']); ?>
                                            <?php else : ?>
                                                <a href="<?php echo esc_url(self::page_url(['action' => 'edit', 'id' => (int) $template['id']])); ?>">
                                                    <?php echo esc_html((string) $template['name']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </strong>
                                        <div class="row-actions es-template-row-actions <?php echo $status === 'trash' ? 'is-trash' : 'is-active'; ?>">
                                            <span class="edit">
                                                <a href="<?php echo esc_url(self::page_url(['action' => 'edit', 'id' => (int) $template['id']])); ?>">
                                                    <?php esc_html_e('Sửa', 'extend-site'); ?>
                                                </a>
                                            </span>
                                            <?php if ($status === 'trash') : ?>
                                                <span class="restore">
                                                    <a href="<?php echo esc_url(self::template_action_url('restore_template', (int) $template['id'])); ?>">
                                                        <?php esc_html_e('Khôi phục', 'extend-site'); ?>
                                                    </a>
                                                </span>
                                                <span class="delete">
                                                    | <a class="submitdelete" href="<?php echo esc_url(self::template_action_url('force_delete_template', (int) $template['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('Xóa vĩnh viễn mẫu crawler này?', 'extend-site')); ?>');">
                                                        <?php esc_html_e('Xóa vĩnh viễn', 'extend-site'); ?>
                                                    </a>
                                                </span>
                                            <?php else : ?>
                                                <span class="trash">
                                                    | <a class="submitdelete" href="<?php echo esc_url(self::template_action_url('trash_template', (int) $template['id'])); ?>" onclick="return confirm('<?php echo esc_js(__('Bỏ mẫu crawler này vào thùng rác?', 'extend-site')); ?>');">
                                                        <?php esc_html_e('Bỏ vào thùng rác', 'extend-site'); ?>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><code><?php echo esc_html((string) $template['domain']); ?></code></td>
                                    <td><?php echo esc_html(self::format_datetime((string) ($template['updated_at'] ?? ''))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="3">
                                    <?php echo $search !== '' ? esc_html__('Không tìm thấy mẫu crawler phù hợp.', 'extend-site') : esc_html($status === 'trash' ? __('Thùng rác đang trống.', 'extend-site') : __('Chưa có mẫu crawler nào.', 'extend-site')); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php self::render_pagination($paged, $total_pages, $total_items, $search, $status, 'bottom'); ?>
            </div>
        </div>
        <?php
    }

    private static function render_pagination(int $paged, int $total_pages, int $total_items, string $search, string $status, string $position): void
    {
        if ($total_pages <= 1) {
            return;
        }
        ?>
        <div class="tablenav <?php echo esc_attr($position); ?>">
            <div class="tablenav-pages">
                <?php
                echo wp_kses_post(
                    paginate_links([
                                'base' => add_query_arg('paged', '%#%', self::page_url(array_merge($status === 'trash' ? ['action' => 'trash'] : [], $search !== '' ? ['s' => $search] : []))),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => '&lsaquo;',
                        'next_text' => '&rsaquo;',
                    ])
                );
                ?>
            </div>
        </div>
        <?php
    }

    private static function format_datetime(string $datetime): string
    {
        if ($datetime === '') {
            return '';
        }

        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $datetime);
    }

    private static function render_form_page(string $action): void
    {
        $template_id = $action === 'edit' ? absint($_GET['id'] ?? 0) : 0;
        if ($action === 'edit' && $template_id <= 0) {
            wp_die(esc_html__('ID mẫu crawler không hợp lệ.', 'extend-site'));
        }

        $selected_template = $template_id > 0 ? CrawlerTemplateTable::find($template_id) : null;
        ?>
        <div class="wrap es-crawler-template-page">
            <h1><?php esc_html_e('Mẫu crawler', 'extend-site'); ?></h1>

            <p>
                <a class="button" href="<?php echo esc_url(self::page_url()); ?>">
                    <?php esc_html_e('Quay lại danh sách', 'extend-site'); ?>
                </a>
            </p>

            <div class="es-template-layout">
                <section class="es-template-panel es-template-form-panel">
                    <h2><?php esc_html_e('Cấu hình selector', 'extend-site'); ?></h2>

                    <form id="es-crawler-template-form">
                        <input type="hidden" id="es-template-id" name="template_id" value="<?php echo esc_attr((string) $template_id); ?>" />

                        <div class="es-template-step es-template-manage">
                            <h3><span>0</span><?php esc_html_e('Quản lý mẫu', 'extend-site'); ?></h3>

                            <div class="es-template-manage-row">
                                <div class="es-template-field">
                                    <label for="es-template-existing"><?php esc_html_e('Mẫu đã lưu', 'extend-site'); ?></label>
                                    <select id="es-template-existing">
                                        <option value=""><?php esc_html_e('Chọn mẫu để sửa', 'extend-site'); ?></option>
                                        <?php if ($selected_template) : ?>
                                            <option value="<?php echo esc_attr((string) $selected_template['id']); ?>" data-domain="<?php echo esc_attr($selected_template['domain']); ?>" selected>
                                                <?php echo esc_html($selected_template['name'] . ' - ' . $selected_template['domain']); ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="es-template-manage-actions">
                                    <button type="button" class="button button-primary" id="es-template-save"><?php esc_html_e('Lưu mẫu', 'extend-site'); ?></button>
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
                                    <?php self::render_selector_field(
                                        'es-template-chapter-link-selector',
                                        'chapter_link_selector',
                                        __('Khối/link danh sách chương', 'extend-site'),
                                        __('Có thể nhập selector thẻ a hoặc khối chứa danh sách chương. Hỗ trợ #id, .class, tag.class và selector con như .chapter-list a.', 'extend-site')
                                    ); ?>
                                    <?php self::render_selector_field('es-template-toc-page-link-selector', 'toc_page_link_selector', __('Link phân trang mục lục', 'extend-site')); ?>
                                </div>
                                <?php self::render_text_field(
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

                            <?php self::render_selector_field(
                                'es-template-chapter-content-scope-selector',
                                'chapter_content_scope_selector',
                                __('Vùng chi tiết chương *', 'extend-site'),
                                __('Khối lớn chứa tiêu đề và nội dung chương. Crawler chỉ tìm các selector chương bên trong vùng này.', 'extend-site'),
                                '.chapter-detail'
                            ); ?>
                            <?php self::render_selector_field(
                                'es-template-chapter-title-selector',
                                'chapter_title_selector',
                                __('Tên chương', 'extend-site'),
                                __('Tùy chọn. Lấy tiêu đề chương bên trong vùng chi tiết chương.', 'extend-site'),
                                'h1'
                            ); ?>
                            <?php self::render_selector_field(
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

                    <div class="es-template-preview-url-grid">
                        <div class="es-template-field">
                            <label for="es-template-target-url"><?php esc_html_e('URL trang truyện / mục lục', 'extend-site'); ?></label>
                            <input type="url" id="es-template-target-url" class="regular-text" placeholder="https://example.com/story/" />
                            <p class="description"><?php esc_html_e('Chỉ dùng để test selector khi sửa template và sẽ được lưu theo template. Không ảnh hưởng URL truyện khi cào thật.', 'extend-site'); ?></p>
                            <button type="button" class="button button-primary" id="es-template-load-preview">
                                <?php esc_html_e('Xem trước trang truyện', 'extend-site'); ?>
                            </button>
                        </div>

                        <div class="es-template-field">
                            <label for="es-template-chapter-url"><?php esc_html_e('URL chương mẫu', 'extend-site'); ?></label>
                            <input type="url" id="es-template-chapter-url" class="regular-text" placeholder="https://example.com/story/chapter-1/" />
                            <p class="description"><?php esc_html_e('Chỉ dùng để test vùng chi tiết chương, tên chương và nội dung truyện; sẽ được lưu theo template để lần sau mở lại dùng tiếp.', 'extend-site'); ?></p>
                            <button type="button" class="button button-secondary" id="es-template-load-chapter-preview">
                                <?php esc_html_e('Xem trước chương mẫu', 'extend-site'); ?>
                            </button>
                        </div>
                    </div>

                    <div id="es-template-preview-status" class="es-template-preview-status" aria-live="polite"></div>
                    <iframe id="es-template-preview-frame" title="<?php echo esc_attr__('Xem trước mẫu crawler', 'extend-site'); ?>"></iframe>
                </aside>
            </div>
        </div>
        <?php
    }

    private static function render_text_field(string $id, string $name, string $label, string $placeholder = '', string $description = ''): void
    {
        ?>
        <div class="es-template-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <?php if ($id === 'es-template-chapter-url-pattern') : ?>
                <div class="es-template-url-pattern-control">
                    <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php echo esc_attr($placeholder); ?>" value="{story_url}/chuong-{chapter_number}/" />
                    <button type="button" class="button" id="es-template-build-pattern-from-preview"><?php esc_html_e('Tạo từ URL chương mẫu', 'extend-site'); ?></button>
                </div>
            <?php else : ?>
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="<?php echo esc_attr($placeholder); ?>" />
            <?php endif; ?>
            <?php if ($description !== '') : ?>
                <p class="description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
            <?php if ($id === 'es-template-chapter-url-pattern') : ?>
                <div id="es-template-chapter-url-pattern-check" class="es-template-pattern-check" aria-live="polite"></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_selector_field(string $id, string $name, string $label, string $description = '', string $placeholder = '.selector'): void
    {
        ?>
        <div class="es-template-field es-template-selector-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <div class="es-template-selector-control">
                <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" class="regular-text es-template-selector-input" placeholder="<?php echo esc_attr($placeholder); ?>" />
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
