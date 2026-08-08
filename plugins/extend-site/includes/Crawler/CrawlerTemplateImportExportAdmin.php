<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class CrawlerTemplateImportExportAdmin
{
    public const PAGE_SLUG = 'extend-site-crawler-template-import-export';
    public const PARENT_SLUG = CrawlerAdmin::PARENT_SLUG;

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_init', [self::class, 'handle_admin_action']);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Import/Export mẫu crawler', 'extend-site'),
            esc_html__('Import/Export mẫu', 'extend-site'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function export_url(int $template_id): string
    {
        return wp_nonce_url(
            self::page_url([
                'action' => 'export_template',
                'id' => $template_id,
            ]),
            'es_crawler_template_export_template_' . $template_id
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'extend-site'));
        }

        self::render_view('template-import-export', [
            'templates' => CrawlerTemplateTable::all(),
            'page_url' => self::page_url(),
            'page_slug' => self::PAGE_SLUG,
            'notice' => self::current_notice(),
            'export_url_callback' => [self::class, 'export_url'],
            'format_datetime_callback' => [self::class, 'format_datetime'],
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

    public static function handle_admin_action(): void
    {
        if (self::current_request_page() !== self::PAGE_SLUG) {
            return;
        }

        if (isset($_GET['action']) && sanitize_key((string) wp_unslash($_GET['action'])) === 'export_template') {
            self::handle_export_template();
            return;
        }

        if (isset($_POST['es_crawler_template_export_selected']) || isset($_POST['es_crawler_template_export_all'])) {
            self::handle_export_bulk();
            return;
        }

        if (isset($_POST['es_crawler_template_import'])) {
            self::handle_import_template();
        }
    }

    private static function current_request_page(): string
    {
        if (isset($_POST['page'])) {
            return sanitize_key((string) wp_unslash($_POST['page']));
        }

        return isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    }

    private static function handle_export_template(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền export mẫu crawler.', 'extend-site'));
        }

        $template_id = absint($_GET['id'] ?? 0);
        if ($template_id <= 0) {
            wp_die(esc_html__('ID mẫu crawler không hợp lệ.', 'extend-site'));
        }

        check_admin_referer('es_crawler_template_export_template_' . $template_id);

        $template = CrawlerTemplateTable::find($template_id);
        if (!$template) {
            wp_die(esc_html__('Không tìm thấy mẫu crawler.', 'extend-site'));
        }

        self::download_json(
            CrawlerTemplateSerializer::export_payload($template),
            self::export_filename($template)
        );
    }

    private static function handle_export_bulk(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền export mẫu crawler.', 'extend-site'));
        }

        check_admin_referer('es_crawler_template_export_bulk', 'es_crawler_template_export_nonce');

        if (isset($_POST['es_crawler_template_export_all'])) {
            $templates = CrawlerTemplateTable::all();
        } else {
            $template_ids = isset($_POST['template_ids']) && is_array($_POST['template_ids']) ? array_map('absint', $_POST['template_ids']) : [];
            $template_ids = array_filter(array_unique($template_ids));
            $templates = [];
            foreach ($template_ids as $template_id) {
                $template = CrawlerTemplateTable::find((int) $template_id);
                if ($template) {
                    $templates[] = $template;
                }
            }
        }

        if (!$templates) {
            self::redirect_with_notice('export_empty');
        }

        self::download_json(
            CrawlerTemplateSerializer::export_collection_payload($templates),
            'crawler-templates-' . gmdate('Ymd-His') . '.json'
        );
    }

    private static function handle_import_template(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền import mẫu crawler.', 'extend-site'));
        }

        check_admin_referer('es_crawler_template_import', 'es_crawler_template_import_nonce');

        $payload = self::read_import_payload();
        if (is_wp_error($payload)) {
            self::redirect_with_notice('import_error');
        }

        $items = CrawlerTemplateSerializer::import_items($payload);
        if (is_wp_error($items)) {
            self::redirect_with_notice('import_error');
        }

        $imported = 0;
        foreach ($items as $item) {
            if (CrawlerTemplateTable::save($item)) {
                $imported++;
            }
        }

        self::redirect_with_notice($imported > 0 ? 'imported' : 'import_error', ['imported' => $imported]);
    }

    /**
     * @return array|\WP_Error
     */
    private static function read_import_payload()
    {
        if (
            empty($_FILES['es_crawler_template_import_file'])
            || !is_array($_FILES['es_crawler_template_import_file'])
        ) {
            return new \WP_Error('missing_file', __('Chưa chọn file JSON để import.', 'extend-site'));
        }

        $file = $_FILES['es_crawler_template_import_file'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', __('Không thể đọc file import.', 'extend-site'));
        }

        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            return new \WP_Error('invalid_upload', __('File import không hợp lệ.', 'extend-site'));
        }

        $size = isset($file['size']) ? absint($file['size']) : 0;
        if ($size <= 0 || $size > 1048576) {
            return new \WP_Error('invalid_size', __('File import phải nhỏ hơn 1MB.', 'extend-site'));
        }

        $name = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
            return new \WP_Error('invalid_extension', __('File import phải có đuôi .json.', 'extend-site'));
        }

        $contents = file_get_contents($tmp_name);
        if (!is_string($contents) || trim($contents) === '') {
            return new \WP_Error('empty_file', __('File import đang trống.', 'extend-site'));
        }

        $payload = json_decode($contents, true);
        if (!is_array($payload)) {
            return new \WP_Error('invalid_json', __('File import không phải JSON hợp lệ.', 'extend-site'));
        }

        return $payload;
    }

    private static function download_json(array $payload, string $filename): void
    {
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            wp_die(esc_html__('Không thể tạo file export mẫu crawler.', 'extend-site'));
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $json;
        exit;
    }

    private static function redirect_with_notice(string $notice, array $args = []): void
    {
        wp_safe_redirect(self::page_url(array_merge(['crawler_template_notice' => $notice], $args)));
        exit;
    }

    private static function current_notice(): array
    {
        $notice = isset($_GET['crawler_template_notice']) ? sanitize_key((string) wp_unslash($_GET['crawler_template_notice'])) : '';
        $imported = absint($_GET['imported'] ?? 0);
        $messages = [
            'imported' => $imported > 0
                ? sprintf(__('Đã import %s mẫu crawler.', 'extend-site'), number_format_i18n($imported))
                : __('Đã import mẫu crawler.', 'extend-site'),
            'import_error' => __('Không thể import mẫu crawler.', 'extend-site'),
            'export_empty' => __('Chưa chọn mẫu crawler nào để export.', 'extend-site'),
        ];

        if (!isset($messages[$notice])) {
            return [];
        }

        return [
            'message' => $messages[$notice],
            'is_error' => in_array($notice, ['import_error', 'export_empty'], true),
        ];
    }

    private static function export_filename(array $template): string
    {
        $parts = array_filter([
            (string) ($template['name'] ?? ''),
            (string) ($template['domain'] ?? ''),
            'crawler-template',
        ]);

        return sanitize_file_name(implode('-', $parts)) . '.json';
    }

    private static function format_datetime(string $datetime): string
    {
        if ($datetime === '') {
            return '';
        }

        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $datetime);
    }

    public static function page_url(array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::PAGE_SLUG], $args),
            admin_url('admin.php')
        );
    }
}