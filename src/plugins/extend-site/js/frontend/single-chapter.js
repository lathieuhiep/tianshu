(function ($, window) {
    'use strict';

    const ESingleChapter = {
        hasLoaded: false,
        $select: null,
        $parent: null,
        storyId: 0,
        current: 0,
        security: '',

        init() {
            this.$select = $('#chapter-selector');
            if (!this.$select.length) return;

            this.$parent  = $('.chapter-selector-box');
            this.storyId  = this.$select.data('story-id');
            this.current  = this.$select.data('current');
            this.security = esSingleChapterAjax.nonce;

            this.appendCurrentPlaceholder();
            this.initSelect2();
            this.bindEvents();
        },

        /**
         * Hiển thị tạm chương hiện tại (placeholder)
         */
        appendCurrentPlaceholder() {
            const title = $('h1.title').first().text().trim() || 'Chương hiện tại';
            const text = `Chương ${this.current} – ${title} (Đang đọc)`;
            const option = new Option(text, this.current, true, true);
            this.$select.append(option).trigger('change');
        },

        /**
         * Khởi tạo Select2 AJAX mode với hook transport
         */
        initSelect2() {
            const self = this;

            this.$select.select2({
                width: '100%',
                dropdownParent: this.$parent,
                placeholder: 'Chọn hoặc tìm chương...',
                language: {
                    searching: () => 'Đang tải chương...',
                    noResults: () => 'Không tìm thấy chương phù hợp',
                },
                ajax: {
                    url: esSingleChapterAjax.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: function () {
                        return {
                            action: 'load_chapter_neighbors',
                            security: self.security,
                            story_id: self.storyId,
                            current_number: self.current,
                        };
                    },

                    /**
                     * ✅ Hook Select2 transport — ta chèn logic custom ở đây
                     */
                    transport: function (params, success, failure) {
                        // Chỉ gọi 1 lần duy nhất
                        if (self.hasLoaded) {
                            // Lần sau trả luôn cache
                            if (self.cachedData) {
                                success(self.cachedData);
                            }
                            return null;
                        }
                        self.hasLoaded = true;

                        // Hiển thị tạm dòng "Đang tải chương..."
                        const $results = $('.select2-results__options');
                        $results.html('<li class="select2-results__option">Đang tải chương...</li>');

                        const request = $.ajax(params);
                        request.then(function (data) {
                            console.log('✅ AJAX success:', data);

                            self.cachedData = data; // cache để không gọi lại

                            // Gọi callback gốc
                            success(data);

                            // Mở lại dropdown khi data trả về
                            setTimeout(() => self.$select.select2('open'), 10);
                        }).fail(function (xhr) {
                            console.error('❌ AJAX error:', xhr.status, xhr.statusText);
                            failure();
                        });

                        return request;
                    },

                    processResults: function (data) {
                        return data; // dạng { results: [...] }
                    },
                    cache: true,
                }
            });
        },

        /**
         * Sự kiện chọn chương
         */
        bindEvents() {
            this.$select.on('select2:select', function (e) {
                const selected = e.params.data;
                if (selected && selected.url) {
                    window.location.href = selected.url;
                }
            });
        },
    };

    $(document).ready(() => ESingleChapter.init());

})(jQuery, window);
