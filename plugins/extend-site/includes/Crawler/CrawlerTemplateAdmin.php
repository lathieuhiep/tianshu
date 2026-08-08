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

    private static function current_notice(): array
    {
        $notice = isset($_GET['crawler_template_notice']) ? sanitize_key((string) wp_unslash($_GET['crawler_template_notice'])) : '';
        $messages = [
            'trashed' => __('Đã bỏ mẫu crawler vào thùng rác.', 'extend-site'),
            'restored' => __('Đã khôi phục mẫu crawler.', 'extend-site'),
            'deleted' => __('Đã xóa vĩnh viễn mẫu crawler.', 'extend-site'),
            'error' => __('Không thể xử lý mẫu crawler.', 'extend-site'),
        ];

        if (!isset($messages[$notice])) {
            return [];
        }

        return [
            'message' => $messages[$notice],
            'is_error' => $notice === 'error',
        ];
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

        self::render_view('template-list', [
            'search' => $search,
            'status' => $status,
            'paged' => $paged,
            'total_pages' => $total_pages,
            'total_items' => $total_items,
            'all_items' => $all_items,
            'trash_items' => $trash_items,
            'templates' => $templates,
            'page_slug' => self::PAGE_SLUG,
            'notice' => self::current_notice(),
            'import_export_url' => CrawlerTemplateImportExportAdmin::page_url(),
            'page_url_callback' => static fn(array $args = []): string => self::page_url($args),
            'template_action_url_callback' => static fn(string $action, int $template_id): string => self::template_action_url($action, $template_id),
            'export_url_callback' => static fn(int $template_id): string => CrawlerTemplateImportExportAdmin::export_url($template_id),
            'format_datetime_callback' => static fn(string $datetime): string => self::format_datetime($datetime),
        ]);
    }

    private static function render_view(string $view, array $view_data = []): void
    {
        $path = EXTEND_SITE_PATH . 'includes/Crawler/views/' . $view . '.php';
        if (!is_file($path)) {
            wp_die(esc_html__('Không tìm thấy view admin.', 'extend-site'));
        }

        include $path;
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
            wp_die(esc_html__('ID máº«u crawler khÃ´ng há»£p lá»‡.', 'extend-site'));
        }

        $selected_template = $template_id > 0 ? CrawlerTemplateTable::find($template_id) : null;

        self::render_view('template-form', [
            'template_id' => $template_id,
            'selected_template' => $selected_template,
            'list_url' => self::page_url(),
            'render_text_field_callback' => static function (string $id, string $name, string $label, string $placeholder = '', string $description = ''): void {
                self::render_text_field($id, $name, $label, $placeholder, $description);
            },
            'render_selector_field_callback' => static function (string $id, string $name, string $label, string $description = '', string $placeholder = '.selector'): void {
                self::render_selector_field($id, $name, $label, $description, $placeholder);
            },
            'render_extract_field_callback' => static function (string $field, string $selector_id, string $selector_name, string $label, string $default_value_mode, string $default_label = ''): void {
                self::render_extract_field($field, $selector_id, $selector_name, $label, $default_value_mode, $default_label);
            },
        ]);
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
