<?php

namespace ExtendSite\Crawler;

defined('ABSPATH') || exit;

class CrawlerAdmin
{
    public const PAGE_SLUG = 'extend-site-crawler';
    public const PARENT_SLUG = 'extend-site-main';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 20);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Trình crawler', 'extend-site'),
            esc_html__('Crawler truyện', 'extend-site'),
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
        ?>
        <div class="wrap es-crawler-page">
            <h1><?php esc_html_e('Crawler truyện', 'extend-site'); ?></h1>

            <p>
                <button type="button" class="button es-crawler-help-open" id="es-crawler-help-open">
                    <?php esc_html_e('Hướng dẫn sử dụng', 'extend-site'); ?>
                </button>
            </p>

            <div class="es-crawler-grid">
                <section class="es-crawler-card es-crawler-form-card">
                    <h2><?php esc_html_e('Thiết lập batch', 'extend-site'); ?></h2>

                    <div class="es-crawler-step">
                        <h3><span>1</span><?php esc_html_e('Truyện & URL nguồn', 'extend-site'); ?></h3>

                        <div class="es-crawler-field">
                            <label for="es-crawler-story"><?php esc_html_e('Truyện', 'extend-site'); ?></label>
                            <select id="es-crawler-story" class="es-crawler-story-select" data-placeholder="<?php echo esc_attr__('Tìm truyện...', 'extend-site'); ?>"></select>
                            <p class="description"><?php esc_html_e('Chọn truyện đã có trong website. Crawler sẽ thêm chương vào truyện này; nhập ít nhất 2 ký tự để tìm.', 'extend-site'); ?></p>
                        </div>

                        <div class="es-crawler-field">
                            <label for="es-crawler-url-pattern"><?php esc_html_e('Mẫu URL', 'extend-site'); ?></label>
                            <input type="url" id="es-crawler-url-pattern" class="regular-text" placeholder="https://example.com/story/chuong-{n}/" />
                            <p class="description"><?php esc_html_e('Thay số chương trong URL thật bằng {n}. Crawler sẽ dùng {n} để tự tạo URL cho từng chương.', 'extend-site'); ?></p>
                            <p class="description"><strong><?php esc_html_e('Ví dụ URL thật:', 'extend-site'); ?></strong> <code>https://truyenfull.today/toi-cuong-he-thong/chuong-1/</code></p>
                            <p class="description"><strong><?php esc_html_e('Mẫu URL cần nhập:', 'extend-site'); ?></strong> <code>https://truyenfull.today/toi-cuong-he-thong/chuong-{n}/</code></p>
                        </div>

                        <div class="es-crawler-three-cols">
                            <div class="es-crawler-field">
                                <label for="es-crawler-range-from"><?php esc_html_e('Từ', 'extend-site'); ?></label>
                                <input type="number" id="es-crawler-range-from" min="1" step="1" value="1" />
                                <p class="description"><?php esc_html_e('Số chương bắt đầu crawl.', 'extend-site'); ?></p>
                            </div>
                            <div class="es-crawler-field">
                                <label for="es-crawler-range-to"><?php esc_html_e('Đến', 'extend-site'); ?></label>
                                <input type="number" id="es-crawler-range-to" min="1" step="1" value="1" />
                                <p class="description"><?php esc_html_e('Số chương kết thúc crawl.', 'extend-site'); ?></p>
                            </div>
                            <div class="es-crawler-field">
                                <label for="es-crawler-padding"><?php esc_html_e('Đệm số', 'extend-site'); ?></label>
                                <select id="es-crawler-padding">
                                    <option value="0"><?php esc_html_e('Không đệm', 'extend-site'); ?></option>
                                    <option value="2"><?php esc_html_e('2 chữ số', 'extend-site'); ?></option>
                                    <option value="3"><?php esc_html_e('3 chữ số', 'extend-site'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Dùng khi URL có dạng 01, 002. Nếu URL là chuong-1 thì chọn Không đệm.', 'extend-site'); ?></p>
                            </div>
                        </div>
                        <p class="description"><?php esc_html_e('Ví dụ: Từ 1 đến 10 sẽ tạo URL cho chương 1, 2, 3... đến chương 10.', 'extend-site'); ?></p>
                    </div>

                    <div class="es-crawler-step">
                        <h3><span>2</span><?php esc_html_e('Cách lưu chương', 'extend-site'); ?></h3>

                        <div class="es-crawler-two-cols">
                            <div class="es-crawler-field">
                                <label for="es-crawler-preview-number"><?php esc_html_e('Xem thử chương', 'extend-site'); ?></label>
                                <input type="number" id="es-crawler-preview-number" min="1" step="1" value="1" />
                                <p class="description"><?php esc_html_e('Đây là số thứ tự chương sẽ được dùng để tạo URL xem thử. Mặc định tự lấy theo ô Từ.', 'extend-site'); ?></p>
                            </div>
                            <div class="es-crawler-field">
                                <label for="es-crawler-post-status"><?php esc_html_e('Trạng thái bài viết', 'extend-site'); ?></label>
                                <select id="es-crawler-post-status">
                                    <option value="publish" selected><?php esc_html_e('Xuất bản', 'extend-site'); ?></option>
                                    <option value="draft"><?php esc_html_e('Bản nháp', 'extend-site'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Nên dùng Bản nháp khi test để kiểm tra nội dung trước khi xuất bản.', 'extend-site'); ?></p>
                            </div>
                        </div>

                        <div class="es-crawler-two-cols">
                            <div class="es-crawler-field">
                                <label for="es-crawler-title-mode"><?php esc_html_e('Kiểu tiêu đề chương', 'extend-site'); ?></label>
                                <select id="es-crawler-title-mode">
                                    <option value="number" selected><?php esc_html_e('Chỉ dùng Chương {n}', 'extend-site'); ?></option>
                                    <option value="story_number"><?php esc_html_e('{story} - Chương {n}', 'extend-site'); ?></option>
                                    <option value="source_prefixed"><?php esc_html_e('Chương {n}: {source_title}', 'extend-site'); ?></option>
                                    <option value="custom"><?php esc_html_e('Custom template', 'extend-site'); ?></option>
                                </select>
                            </div>
                            <div class="es-crawler-field es-crawler-title-template-field is-hidden">
                                <label for="es-crawler-title-template"><?php esc_html_e('Mẫu tiêu đề custom', 'extend-site'); ?></label>
                                <input type="text" id="es-crawler-title-template" class="regular-text" value="{story} - Chương {n}: {source_title}" />
                                <p class="description"><?php esc_html_e('Token dùng được: {story} = tên truyện, {n} = số chương, {source_title} = tiêu đề lấy từ website nguồn.', 'extend-site'); ?></p>
                                <p class="description"><strong><?php esc_html_e('Ví dụ:', 'extend-site'); ?></strong> <code>{story} - Chương {n}: {source_title}</code></p>
                            </div>
                        </div>

                        <div class="es-crawler-two-cols">
                            <div class="es-crawler-field">
                                <label for="es-crawler-delay"><?php esc_html_e('Thời gian nghỉ giữa mỗi URL', 'extend-site'); ?></label>
                                <input type="number" id="es-crawler-delay" min="1" max="60" step="1" value="5" />
                                <p class="description"><?php esc_html_e('Đơn vị giây. Có thể tăng nếu site nguồn chậm hoặc dễ chặn request.', 'extend-site'); ?></p>
                            </div>
                            <div class="es-crawler-field">
                                <label for="es-crawler-preview-url"><?php esc_html_e('URL xem thử riêng', 'extend-site'); ?></label>
                                <input type="url" id="es-crawler-preview-url" class="regular-text" placeholder="<?php echo esc_attr__('Không bắt buộc: ghi đè URL xem thử tự động', 'extend-site'); ?>" />
                                <p class="description"><?php esc_html_e('Nếu điền, nút Xem thử sẽ dùng URL này thay vì URL sinh từ mẫu URL và số chương xem thử.', 'extend-site'); ?></p>
                            </div>
                        </div>
                    </div>

                    <details class="es-crawler-step es-crawler-advanced">
                        <summary>
                            <span>3</span>
                            <strong><?php esc_html_e('Làm sạch nội dung', 'extend-site'); ?></strong>
                            <em><?php esc_html_e('Tuỳ chọn', 'extend-site'); ?></em>
                        </summary>

                        <div class="es-crawler-two-cols">
                            <div class="es-crawler-field">
                                <label for="es-crawler-find"><?php esc_html_e('Tìm nội dung', 'extend-site'); ?></label>
                                <textarea id="es-crawler-find" rows="5" placeholder="<?php echo esc_attr__('Mỗi dòng là một nội dung cần tìm', 'extend-site'); ?>"></textarea>
                                <p class="description"><?php esc_html_e('Dùng để xoá hoặc thay chữ trong nội dung sau khi scrape.', 'extend-site'); ?></p>
                            </div>
                            <div class="es-crawler-field">
                                <label for="es-crawler-replace"><?php esc_html_e('Thay bằng', 'extend-site'); ?></label>
                                <textarea id="es-crawler-replace" rows="5" placeholder="<?php echo esc_attr__('Mỗi dòng tương ứng với dòng cần tìm bên trái', 'extend-site'); ?>"></textarea>
                                <p class="description"><?php esc_html_e('Ví dụ: bên trái là "Nguồn: example.com", bên phải để trống thì crawler sẽ xoá đoạn đó.', 'extend-site'); ?></p>
                            </div>
                        </div>
                        <label class="es-crawler-checkbox">
                            <input type="checkbox" id="es-crawler-remove-container" />
                            <?php esc_html_e('Xoá cả dòng/đoạn chứa nội dung cần tìm', 'extend-site'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Nên bật khi muốn xoá credit, quảng cáo, dòng nguồn hoặc nút điều hướng như Chương trước/Chương sau.', 'extend-site'); ?></p>
                    </details>
                </section>

                <aside class="es-crawler-card es-crawler-status-card">
                    <div class="es-crawler-sidebar-actions">
                        <h2><?php esc_html_e('Thao tác', 'extend-site'); ?></h2>
                        <div class="es-crawler-actions">
                            <button type="button" class="button" id="es-crawler-preview-btn"><?php esc_html_e('Xem thử', 'extend-site'); ?></button>
                            <button type="button" class="button" id="es-crawler-generate-btn"><?php esc_html_e('Tạo danh sách URL', 'extend-site'); ?></button>
                            <button type="button" class="button button-primary" id="es-crawler-start-btn"><?php esc_html_e('Bắt đầu', 'extend-site'); ?></button>
                            <button type="button" class="button" id="es-crawler-pause-btn" disabled><?php esc_html_e('Tạm dừng', 'extend-site'); ?></button>
                            <button type="button" class="button" id="es-crawler-stop-btn" disabled><?php esc_html_e('Dừng', 'extend-site'); ?></button>
                            <button type="button" class="button button-secondary is-hidden" id="es-crawler-finalize-btn"><?php esc_html_e('Hoàn tất lại', 'extend-site'); ?></button>
                        </div>
                        <ul class="es-crawler-action-help">
                            <li><strong><?php esc_html_e('Xem thử:', 'extend-site'); ?></strong> <?php esc_html_e('Kiểm tra 1 URL, không tạo chương.', 'extend-site'); ?></li>
                            <li><strong><?php esc_html_e('Tạo URL:', 'extend-site'); ?></strong> <?php esc_html_e('Sinh danh sách trước khi crawl.', 'extend-site'); ?></li>
                            <li><strong><?php esc_html_e('Bắt đầu:', 'extend-site'); ?></strong> <?php esc_html_e('Chạy tuần tự từng URL.', 'extend-site'); ?></li>
                        </ul>
                    </div>

                    <div class="es-crawler-sidebar-progress">
                        <h2><?php esc_html_e('Tiến độ', 'extend-site'); ?></h2>
                        <div class="es-crawler-progress" aria-label="<?php echo esc_attr__('Tiến độ crawler', 'extend-site'); ?>">
                            <div class="es-crawler-progress-bar" style="width:0%"></div>
                        </div>
                        <div class="es-crawler-progress-text">0 / 0 (0%)</div>
                        <div class="es-crawler-current-url"><?php esc_html_e('Chưa xử lý URL nào.', 'extend-site'); ?></div>
                        <div id="es-crawler-lock-notice" class="es-crawler-lock-notice is-hidden"></div>
                        <div id="es-crawler-finalize-status" class="es-crawler-finalize-status"></div>
                    </div>
                </aside>
            </div>

            <div class="es-crawler-panels">
                <section class="es-crawler-card">
                    <h2><?php esc_html_e('Danh sách URL đã tạo', 'extend-site'); ?></h2>
                    <div id="es-crawler-url-summary" class="es-crawler-url-summary"><?php esc_html_e('Chưa tạo URL nào.', 'extend-site'); ?></div>
                    <textarea id="es-crawler-url-list" readonly rows="8"></textarea>
                </section>

                <section class="es-crawler-card">
                    <h2><?php esc_html_e('Kết quả xem thử', 'extend-site'); ?></h2>
                    <div id="es-crawler-preview-result" class="es-crawler-preview-result"><?php esc_html_e('Chưa có kết quả xem thử.', 'extend-site'); ?></div>
                </section>

                <section class="es-crawler-card es-crawler-log-card">
                    <div class="es-crawler-log-header">
                        <h2><?php esc_html_e('Nhật ký', 'extend-site'); ?></h2>
                        <button type="button" class="button" id="es-crawler-copy-log-btn"><?php esc_html_e('Sao chép nhật ký', 'extend-site'); ?></button>
                    </div>
                    <textarea id="es-crawler-log-export" readonly rows="10"></textarea>
                    <table class="widefat striped es-crawler-log-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Chương', 'extend-site'); ?></th>
                                <th><?php esc_html_e('Trạng thái', 'extend-site'); ?></th>
                                <th><?php esc_html_e('Thử lại', 'extend-site'); ?></th>
                                <th><?php esc_html_e('Thông báo', 'extend-site'); ?></th>
                                <th><?php esc_html_e('URL', 'extend-site'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="es-crawler-log-body"></tbody>
                    </table>
                </section>
            </div>

            <div class="es-crawler-help-modal is-hidden" id="es-crawler-help-modal" aria-hidden="true">
                <div class="es-crawler-help-backdrop" data-es-crawler-help-close></div>
                <div class="es-crawler-help-dialog" role="dialog" aria-modal="true" aria-labelledby="es-crawler-help-title">
                    <button type="button" class="es-crawler-help-close" data-es-crawler-help-close aria-label="<?php echo esc_attr__('Đóng hướng dẫn', 'extend-site'); ?>">×</button>
                    <h2 id="es-crawler-help-title"><?php esc_html_e('Hướng dẫn crawler truyện', 'extend-site'); ?></h2>

                    <ol class="es-crawler-help-steps">
                        <li><strong><?php esc_html_e('Chuẩn bị truyện trước', 'extend-site'); ?></strong><p><?php esc_html_e('Crawler hiện chỉ thêm chương vào truyện đã có. Hãy tạo Story, tác giả, ảnh bìa và mô tả trước nếu cần.', 'extend-site'); ?></p></li>
                        <li><strong><?php esc_html_e('Nhập mẫu URL có {n}', 'extend-site'); ?></strong><p><?php esc_html_e('Thay số chương thật bằng {n}. Ví dụ URL thật là /chuong-1/ thì mẫu URL là /chuong-{n}/.', 'extend-site'); ?></p></li>
                        <li><strong><?php esc_html_e('Xem thử trước khi chạy', 'extend-site'); ?></strong><p><?php esc_html_e('Nên xem thử chương đầu, giữa và cuối. Kiểm tra kỹ Tiêu đề sẽ lưu, độ dài nội dung và phần preview.', 'extend-site'); ?></p></li>
                        <li><strong><?php esc_html_e('Tạo danh sách URL rồi mới bắt đầu', 'extend-site'); ?></strong><p><?php esc_html_e('Nếu sửa mẫu URL, Từ, Đến hoặc Đệm số, hãy tạo lại danh sách URL trước khi bấm Bắt đầu.', 'extend-site'); ?></p></li>
                        <li><strong><?php esc_html_e('Chạy thử bằng bản nháp', 'extend-site'); ?></strong><p><?php esc_html_e('Khi test nguồn mới, chọn Bản nháp và chạy ít chương trước. Nếu nội dung ổn mới chạy batch lớn hoặc xuất bản.', 'extend-site'); ?></p></li>
                    </ol>

                    <div class="es-crawler-help-notes">
                        <h3><?php esc_html_e('Lưu ý nhanh', 'extend-site'); ?></h3>
                        <ul>
                            <li><?php esc_html_e('Xem thử chương là số thứ tự chương dùng để tạo URL preview, không phải số lượng chương.', 'extend-site'); ?></li>
                            <li><?php esc_html_e('Nếu trùng URL hoặc trùng số chương, crawler báo duplicate và bỏ qua, không tạo chương trùng.', 'extend-site'); ?></li>
                            <li><?php esc_html_e('Nếu gặp 404, captcha hoặc trang chặn truy cập, crawler sẽ báo lỗi và không tạo chương.', 'extend-site'); ?></li>
                            <li><?php esc_html_e('Crawler chỉ cho chạy một batch tại một thời điểm. Khi đang chạy, hệ thống gửi heartbeat mỗi 30 giây, nếu tab bị đóng hoặc request chết, lock sẽ tự hết hiệu lực sau khoảng 5 phút.', 'extend-site'); ?></li>
                            <li><?php esc_html_e('Batch sẽ tự dừng nếu có 3 URL lỗi liên tiếp để tránh crawl quá xa số chương thật.', 'extend-site'); ?></li>
                            <li><?php esc_html_e('Dùng Làm sạch nội dung khi cần xoá credit, quảng cáo hoặc nút Chương trước/Chương sau.', 'extend-site'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
