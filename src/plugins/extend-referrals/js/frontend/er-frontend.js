(function ($) {
    'use strict';

    let ttlChecked = false;
    let leftPage = false;

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

                // Mở khóa nội dung
                $('#er-partner-content-wrapper').removeClass('er-locked');

                // Xóa flag click
                localStorage.removeItem('er_aff_clicked');
            }
        });
    }

    /** Toast Notification */
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
        }, 4000);
    }

    /**
     * Click affiliate
     */
    $(document).on('click', '[data-affiliate-click]', function (e) {
        e.preventDefault();

        leftPage = false;

        const link = $(this).attr('href') || '';
        if (link) window.open(link, '_blank', 'noopener,noreferrer');

        setAffiliateTTL().always(() => {
            localStorage.setItem('er_aff_clicked', '1');
        });

        // Fallback: user cancel popup → nhắc nhẹ
        setTimeout(function () {
            if (!leftPage) {
                showToast('Vui lòng MỞ Quảng Cáo để tiếp tục đọc chương ❤️');
            }
        }, 1500);
    });

    /** User rời trang */
    window.addEventListener('blur', function () {
        leftPage = true;
    });

    /** Quay lại tab (desktop) */
    window.addEventListener('focus', function () {
        if (localStorage.getItem('er_aff_clicked') === '1') {
            leftPage = true;
            checkTTL();
        }
    });

    /** Quay lại sau khi mở Shopee (mobile webview) */
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