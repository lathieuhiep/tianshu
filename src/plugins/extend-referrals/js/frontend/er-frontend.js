(function ($) {
    'use strict';

    let ttlChecked = false;
    let leftPage = false;

    /**
     * AJAX: Set TTL
     */
    function setAffiliateTTL() {
        return $.ajax({
            url: ExtendReferrals.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'extend_referrals_set_ttl',
                nonce: ExtendReferrals.nonce
            }
        });
    }

    /**
     * AJAX: Check TTL
     */
    function checkTTL() {
        if (ttlChecked) return;
        ttlChecked = true;

        $.ajax({
            url: ExtendReferrals.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'extend_referrals_check_ttl',
                nonce: ExtendReferrals.nonce
            }
        }).done(res => {
            if (res && res.expired === false) {
                // Xóa quảng cáo
                $('.er-partner-info').remove();

                // Mở khóa content
                $('#er-partner-content-wrapper').removeClass('er-locked');

                // Xóa flag click
                localStorage.removeItem('er_aff_clicked');
            }
        });
    }

    /**
     * Toast notification
     */
    function showToast(message) {
        let $toast = $('#er-toast');

        if ($toast.length === 0) {
            $('body').append('<div id="er-toast" class="er-toast"></div>');
            $toast = $('#er-toast');
        }

        $toast.text(message);
        $toast.addClass('er-toast--show');

        setTimeout(() => {
            $toast.removeClass('er-toast--show');
        }, 4000); // Hiển thị 4 giây
    }

    /**
     * CLICK AFFILIATE
     */
    $(document).on('click', '[data-affiliate-click]', function (e) {
        e.preventDefault();

        leftPage = false;

        const link = $(this).attr('href') || '';
        if (link) window.open(link, '_blank', 'noopener,noreferrer');

        // --- AUTO-DELAY PRO MAX ---
        const start = performance.now(); // bắt đầu đo thời gian AJAX

        setAffiliateTTL().always(() => {
            localStorage.setItem('er_aff_clicked', '1');

            const elapsed = performance.now() - start; // AJAX mất bao lâu
            const delay = Math.max(150, elapsed + 100); // buffer + minimum delay

            setTimeout(() => {
                checkTTL(); // Auto-unlock không chờ quay lại tab
            }, delay);
        });

        // fallback nếu user cancel popup
        setTimeout(function () {
            if (!leftPage) {
                showToast('Vui lòng MỞ SHOPEE để tiếp tục đọc chương ❤️');
            }
        }, 1500);
    });

    /**
     * User rời tab
     */
    window.addEventListener('blur', function () {
        leftPage = true;
    });

    /**
     * Desktop quay lại tab
     */
    window.addEventListener('focus', function () {
        if (localStorage.getItem('er_aff_clicked') === '1') {
            leftPage = true;
            checkTTL();
        }
    });

    /**
     * Mobile quay lại (WebView)
     */
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            leftPage = true;
        }

        if (document.visibilityState === 'visible') {
            if (localStorage.getItem('er_aff_clicked') === '1') {
                checkTTL();
            }
        }
    });

})(jQuery);
