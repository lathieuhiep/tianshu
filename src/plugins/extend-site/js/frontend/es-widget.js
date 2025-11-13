(function ($) {
    'use strict';

    // Ranking Tab Handler
    const handleTabRanking = () => {
        const widgets = $('.es-ranking-widget');

        if (!widgets.length) return;

        widgets.each(function () {
            const widget = $(this);
            const btnRanking = widget.find('.es-ranking-tabs .btn-ranking');
            const loadingIndicator = widget.find('.es-loading');
            const config = JSON.parse(widget.attr('data-config') || '{}');

            btnRanking.on('click', function (e) {
                e.preventDefault();

                const btn = $(this);
                const period = btn.data('period');

                if (btn.hasClass('active')) return;

                // toggle active button
                btnRanking.removeClass('active');
                btn.addClass('active');

                // toggle tab content
                const allTabs = widget.find('.ranking-list');
                const currentTab = allTabs.filter(`[data-period="${period}"]`);

                if (currentTab.hasClass('loaded')) {
                    allTabs.attr('hidden', true).removeClass('active');
                    currentTab.removeAttr('hidden').hide().fadeIn(400).addClass('active');

                    return;
                }

                $.ajax({
                    url: esWidget.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'es_get_ranking',
                        period: period,
                        limit: config.limit || 10,
                        ranking_type: config.ranking_type,
                        security: esWidget.nonce,
                    },
                    beforeSend: () => {
                        allTabs.attr('hidden', true).removeClass('active');
                        currentTab.removeAttr('hidden').addClass('active').addClass('is-loading');
                        loadingIndicator.attr('hidden', false);
                    },
                    success: (res) => {
                        if (res.success && res.data.html) {
                            currentTab.hide().html(res.data.html).fadeIn(400);
                            currentTab.addClass('loaded');
                        } else {
                            currentTab.html('<p class="es-no-data">' + (res.data?.message || esWidget.i18n.error_message) + '</p>');
                        }
                    },
                    error: (xhr, status, err) => {
                        console.error('AJAX error:', status, err);
                        currentTab.html('<p class="es-no-data">' + esWidget.i18n.error_message + '</p>');
                    },
                    complete: () => {
                        currentTab.removeClass('is-loading');
                        loadingIndicator.attr('hidden', true);
                    }
                })
            });
        });
    }

    $(document).ready(() => handleTabRanking());

    /**
     * Initialize autocomplete for a single search form.
     * @param {jQuery} $form - The search form element (.es-search-form)
     */
    const initSearchForm = ($form) => {
        const $input   = $form.find('.es-search-input');
        const $wrapper = $form.closest('.es-search-autocomplete-wrapper');
        const $list    = $wrapper.find('.results-autocomplete');
        const $loading = $list.find('.es-loading'); // spinner sẵn có trong HTML

        if (!$input.length || !$list.length) return;

        let timer;
        let xhr;
        let lastSearch = 0; // throttle timestamp

        $input.on('input', function () {
            const keyword = $(this).val().trim();
            clearTimeout(timer);

            // Reset nếu gõ ít hơn 2 ký tự
            if (keyword.length < 2) {
                $list.find('ul').remove();
                $loading.attr('hidden', true);
                $list.removeClass('active');
                return;
            }

            timer = setTimeout(() => {
                // Ngăn gửi quá thường xuyên (throttle)
                const now = Date.now();
                if (now - lastSearch < 600) return;
                lastSearch = now;

                // Hủy request cũ nếu còn chạy
                if (xhr && xhr.readyState !== 4) xhr.abort();

                // Đảm bảo biến esWidget có tồn tại
                if (typeof esWidget === 'undefined') {
                    console.warn('esWidget not defined.');
                    return;
                }

                xhr = $.ajax({
                    url: esWidget.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'es_story_search',
                        security: esWidget.nonce,
                        keyword: keyword,
                    },
                    beforeSend: () => {
                        // Clear kết quả cũ, show spinner
                        $list.find('ul').remove();
                        $loading.removeAttr('hidden');
                        $list.addClass('active');
                    },
                    success: (res) => {
                        $loading.attr('hidden', true); // ẩn spinner
                        $list.find('ul').remove();

                        if (res.success && res.data.html) {
                            $list.append(res.data.html);
                        }
                    },
                    error: () => {
                        $loading.attr('hidden', true);
                        $list.find('ul').remove();
                        $list.removeClass('active');
                    },
                });
            }, 600); // debounce 600ms: chỉ gửi khi dừng gõ
        });

        // Ẩn khi click ra ngoài
        $(document).on('click', function (e) {
            if (!$wrapper.is(e.target) && !$wrapper.has(e.target).length) {
                $list.find('ul').remove();
                $loading.attr('hidden', true);
                $list.removeClass('active');
            }
        });
    };

    /**
     * Initialize all search forms on page
     */
    const initAllSearchForms = () => {
        $('.es-search-form').each(function () {
            initSearchForm($(this));
        });
    };

    // Init khi DOM sẵn sàng
    $(initAllSearchForms);

    // Expose cho widget load động hoặc Elementor
    window.ExtendSiteSearchForm = {
        init: initSearchForm,
        initAll: initAllSearchForms,
    };

})(jQuery);