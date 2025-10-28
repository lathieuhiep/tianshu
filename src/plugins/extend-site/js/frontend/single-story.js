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

    $(function () {
        $('.story-tabs').each(function () {
            initTabs($(this));
        });
    });

    // chapter pagination
    $(document).on('click', '.chapter-pagination a.page-numbers', function (e) {
        e.preventDefault();

        const $link = $(this);
        const $chapters = $link.closest('.es-chapters');
        const storyID = $chapters.data('story-id');
        const perPage = $chapters.data('per-page');
        const currentPage = parseInt($chapters.data('current-page')) || 1;

        // 🧠 Lấy số trang
        let pageNum = parseInt($link.text().match(/\d+/)?.[0]);
        if ($link.hasClass('next')) pageNum = currentPage + 1;
        if ($link.hasClass('prev')) pageNum = currentPage - 1;
        if (!pageNum || isNaN(pageNum)) pageNum = 1;

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
                // Xóa trạng thái active cũ (tạm thời)
                $chapters.find('.chapter-pagination .page-numbers.current').removeClass('current');
                // Highlight trang đang click ngay lập tức (cho cảm giác phản hồi nhanh)
                $link.addClass('current');
            },
            success: (res) => {
                console.log('✅ AJAX success:', res);
                if (res.success && res.data.html) {
                    // Tạo node HTML mới từ response
                    const $newContent = $(res.data.html);

                    // Tìm container
                    const $chapters = $link.closest('.es-chapters');

                    // Xóa nội dung cũ
                    $chapters.find('.chapter-list').remove();
                    $chapters.find('.chapter-pagination').remove();

                    // Thêm nội dung mới
                    $chapters.append($newContent);

                    // Cập nhật current-page cho data attribute
                    $chapters.attr('data-current-page', pageNum);

                    // Cập nhật URL
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('chap_page', pageNum);
                    window.history.pushState({}, '', newUrl);

                    // Scroll nhẹ
                    $chapters.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    console.error(res.data?.message || 'Error loading chapters.');
                }
            },
            complete: () => $chapters.removeClass('is-loading'),
        });
    });
})(jQuery);