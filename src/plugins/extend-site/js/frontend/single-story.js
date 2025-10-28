(function ($) {
    'use strict';

    // tabs single story
    function activateTab($container, $btn) {
        var target = $btn.data('tab-target');
        if (!target || target.charAt(0) !== '#') return;

        var $panel = $container.find(target);
        if (!$panel.length) return;

        // Toggle active buttons
        $container.find('.story-tab-btn').removeClass('is-active').attr('aria-selected', 'false');
        $btn.addClass('is-active').attr('aria-selected', 'true');

        // Toggle panels
        $container.find('.story-tab-panel').removeClass('is-active').attr('hidden', 'hidden');
        $panel.addClass('is-active').removeAttr('hidden');

        // Save session per story
        var storyId = $container.data('story-id');
        if (storyId) {
            try {
                sessionStorage.setItem('storyTabs:' + storyId, target);
            } catch (e) {}
        }
    }

    function initTabs($container) {
        // init state
        $container.find('.story-tab-panel').each(function () {
            var $p = $(this);
            if ($p.hasClass('is-active')) $p.removeAttr('hidden');
            else $p.attr('hidden', 'hidden');
        });

        // click
        $container.on('click', '.story-tab-btn', function (e) {
            e.preventDefault();
            activateTab($container, $(this));
        });

        // restore last tab
        var storyId = $container.data('story-id');
        var stored = null;
        if (storyId) {
            try {
                stored = sessionStorage.getItem('storyTabs:' + storyId);
            } catch (e) {}
        }
        if (stored) {
            var $btn = $container.find('.story-tab-btn[data-tab-target="' + stored + '"]');
            if ($btn.length) activateTab($container, $btn.first());
        }
    }

    $('.story-tabs').each(function () {
        initTabs($(this));
    });

    // chapter pagination
    $(document).on('click', '.chapter-pagination a.page-numbers', function (e) {
        e.preventDefault();

        const $link = $(this);
        const $chapters = $link.closest('.es-chapters');
        const storyID = $chapters.data('story-id');
        const perPage = $chapters.data('per-page');
        const currentPage = parseInt($chapters.data('current-page'), 10) || 1;

        let pageNum = 1;

        // 🔹 Ưu tiên lấy từ href (?chap_page=2)
        const href = $link.attr('href');
        const match = href && href.match(/chap_page=(\d+)/);
        if (match && match[1]) {
            pageNum = parseInt(match[1], 10);
        } else if ($link.hasClass('next')) {
            pageNum = currentPage + 1;
        } else if ($link.hasClass('prev')) {
            pageNum = currentPage - 1;
        }

        if (pageNum < 1 || isNaN(pageNum)) pageNum = 1;

        console.log('➡️ Loading page:', pageNum);

        $.ajax({
            url: extendSite.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'load_chapters',
                story_id: storyID,
                page: pageNum,
                per_page: perPage,
                security: extendSite.nonce,
            },
            beforeSend: () => {
                $chapters.addClass('is-loading');
                $chapters.find('.chapter-pagination .page-numbers.current').removeClass('current');
                $link.addClass('current');
            },
            success: (res) => {
                if (res.success && res.data.html) {
                    const $new = $(res.data.html);
                    $chapters.find('.chapter-list, .chapter-pagination').remove();
                    $chapters.append($new);
                    $chapters.attr('data-current-page', pageNum);

                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('chap_page', pageNum);
                    window.history.pushState({}, '', newUrl);
                }
            },
            error: (xhr, status, err) => {
                console.error('❌ AJAX error:', status, err);
                console.log('Response:', xhr.responseText);
            },
            complete: () => {
                $chapters.removeClass('is-loading');
            }
        });
    });
})(jQuery);