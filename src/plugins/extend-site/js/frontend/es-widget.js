(function ($) {
    'use strict';

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

                if ( currentTab.hasClass('loaded') ) {
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

    // call on document ready
    $(document).ready(() => handleTabRanking());

})(jQuery);