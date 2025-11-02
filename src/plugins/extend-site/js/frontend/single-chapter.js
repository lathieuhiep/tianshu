(function ($) {
    'use strict';

    // Modal danh sách chương
    const modalChapterList = () => {
        $(document).on('click', '.es-btn-chapter-list', function (e) {
            e.preventDefault();

            const $modal = $('#es-chapter-modal');
            $modal.addClass('active').attr('aria-hidden', 'false');
            $('body').addClass('es-modal-open');
        });

        $(document).on('click', '[data-close]', function (e) {
            e.preventDefault();
            const $modal = $('#es-chapter-modal');
            if (document.activeElement) document.activeElement.blur();
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
    };

    // Theo dõi lượt xem chương
    const viewTracker = async () => {
        const $chapter = $('.es-post');
        const chapterID = parseInt($chapter.data('chapter-id'), 10);
        if (!chapterID) return;

        /**
         * Giới hạn thời gian giữa 2 view hợp lệ (ms)
         * Mặc định: 1 giờ = 3600000 ms
         * Khi test có thể giảm xuống 10000 (10 giây)
         */
        const TIME_LIMIT_MS = 60 * 60 * 1000; // 1h

        // ----- Tạo fingerprint duy nhất -----
        const getFingerprint = async () => {
            const msg = [
                navigator.userAgent,
                navigator.language,
                screen.width,
                screen.height,
                new Date().getTimezoneOffset(),
            ].join('|');
            const buffer = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(msg));
            return Array.from(new Uint8Array(buffer))
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');
        };

        // ----- UID cố định cho trình duyệt -----
        const uidKey = 'es_uid';
        let uid = localStorage.getItem(uidKey);
        if (!uid) {
            uid = crypto.randomUUID();
            localStorage.setItem(uidKey, uid);
        }

        // ----- Kiểm tra thời gian xem gần nhất -----
        const key = `es_viewed_${chapterID}`;
        const lastViewTime = Number(localStorage.getItem(key)) || 0;
        const now = Date.now();
        if (lastViewTime && now - lastViewTime < TIME_LIMIT_MS) {
            const remain = ((TIME_LIMIT_MS - (now - lastViewTime)) / 1000).toFixed(0);
            console.info(`Skipped: viewed ${(now - lastViewTime) / 1000}s ago. Wait ${remain}s.`);
            return;
        }

        // ----- Delay ngẫu nhiên để chống spam bot -----
        const delay = 5000 + Math.random() * 5000; // 5–10s
        console.info(`[VIEW] Chapter ${chapterID}: sending after ${delay.toFixed(0)}ms`);

        // ----- Gửi view sau delay -----
        setTimeout(async () => {
            const fingerprint = await getFingerprint();

            $.ajax({
                url: esSingleChapterAjax.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'es_increment_view',
                    chapter_id: chapterID,
                    fingerprint,
                    uid,
                    security: esSingleChapterAjax.nonce
                },
                success: function (res) {
                    console.log(res);
                    if (res.success) {
                        console.info(`View counted: ${res.data.message}`);
                        // Ghi thời gian sau khi gửi thành công
                        localStorage.setItem(key, Date.now().toString());
                    } else {
                        console.warn(`Skipped (server): ${res.data.message}`);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.log('🧾 Response text:', xhr.responseText);
                },
            });
        }, delay);
    };

    // Khởi tạo tất cả
    $(function () {
        modalChapterList();
        viewTracker();
    });

})(jQuery);