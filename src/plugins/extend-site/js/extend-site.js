/**
 * ExtendSite Admin Scripts
 * - Xử lý chung backend (story, chapter, select2, ...).
 */
(function ($) {
    'use strict';

    /**
     * ============================
     * MODULE 1: Select2 AJAX Loader
     * ============================
     */
    const initEsSelect2Ajax = () => {
        if (typeof esAdminStoryChapter === 'undefined' || !esAdminStoryChapter.ajax_url) {
            console.warn('esAdminStoryChapter config missing or invalid');
            return;
        }

        $('[data-es-ajax-select]').each(function () {
            const $el = $(this);
            const type = $el.data('es-type') || 'generic';
            const placeholder = $el.data('placeholder') || 'Nhập để tìm...';

            // Tránh khởi tạo lại nếu Select2 đã có
            if ($el.hasClass('select2-hidden-accessible')) return;

            $el.select2({
                ajax: {
                    url: esAdminStoryChapter.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'es_search_' + type,
                            q: params.term || '',
                            nonce: esAdminStoryChapter.nonce
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function (item) {
                                return { id: item.id, text: item.text };
                            })
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: placeholder,
                width: '100%',
                language: {
                    inputTooShort: function () {
                        return 'Nhập ít nhất 2 ký tự...';
                    },
                    searching: function () {
                        return 'Đang tìm...';
                    },
                    noResults: function () {
                        return 'Không tìm thấy kết quả';
                    }
                }
            });

            // Gửi event nội bộ nếu cần hook ngoài
            $el.on('select2:select select2:unselect', function () {
                $el.trigger('es:select2:change', [type]);
            });
        });
    }

    /**
     * ============================
     * KHỞI CHẠY KHI SẴN SÀNG
     * ============================
     */
    $(document).ready(function () {
        try {
            initEsSelect2Ajax();
        } catch (e) {
            console.error('ExtendSite Select2 init failed:', e);
        }

        // Gọi lại nếu metabox thay đổi động
        $(document).on('postbox-toggled', initEsSelect2Ajax);
    });

    // Expose nếu cần gọi thủ công từ nơi khác
    window.ExtendSiteAdmin = {
        initSelect2: initEsSelect2Ajax
    };

})(jQuery);