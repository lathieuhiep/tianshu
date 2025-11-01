(function ($) {
    'use strict';

    // chapter pagination
    $(document).on('click', '.chapter-pagination a.page-numbers', function (e) {
        e.preventDefault();

        const $link = $(this);
        const $chapters = $link.closest('.es-chapters');
        const storyID = $chapters.data('story-id');
        const perPage = $chapters.data('per-page');
        const currentPage = parseInt($chapters.data('current-page'), 10) || 1;

        const parseBool = val => String(val).toLowerCase() === 'true';
        const showTitle = parseBool($chapters.data('show-title') ?? 'true');
        const showDate  = parseBool($chapters.data('show-date') ?? 'true');

        let pageNum = 1;

        // Ưu tiên lấy từ href (?chap_page=2)
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

        $.ajax({
            url: esLoadChapters.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'load_chapters',
                story_id: storyID,
                page: pageNum,
                per_page: perPage,
                show_title: showTitle,
                show_date: showDate,
                security: esLoadChapters.nonce,
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
                }
            },
            error: (xhr, status, err) => {
                console.error('AJAX error:', status, err);
                console.log('Response:', xhr.responseText);
            },
            complete: () => {
                $chapters.removeClass('is-loading');
            }
        });
    });
})(jQuery);