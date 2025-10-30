(function ($) {
    'use strict';

    // Mở modal danh sách chương
    const modalChapterList = () => {
        $(document).on('click', '.es-btn-chapter-list', function (e) {
            e.preventDefault();

            const storyID = $(this).data('story-id');
            const currentPage = $(this).data('current-page') || 1;

            const $modal = $('#es-chapter-modal');
            const $body = $('#es-chapter-modal-body');

            $modal.addClass('active').attr('aria-hidden', 'false');
            $('body').addClass('es-modal-open');
        });

        // Đóng modal
        $(document).on('click', '[data-close]', function (e) {
            e.preventDefault();
            const $modal = $('#es-chapter-modal');

            if (document.activeElement) {
                document.activeElement.blur();
            }

            $modal.removeClass('active').attr('aria-hidden', 'true');
            $('body').removeClass('es-modal-open');
        });

        $(document).on('keyup', function (e) {
            if (e.key === 'Escape') {
                const $modal = $('#es-chapter-modal');
                if ($modal.hasClass('active')) {
                    if (document.activeElement) document.activeElement.blur();

                    $modal.removeClass('active').attr('aria-hidden', 'true');
                    $('body').removeClass('es-modal-open');
                }
            }
        });
    }

    $(document).ready(function () {
        modalChapterList();
    });
})(jQuery);