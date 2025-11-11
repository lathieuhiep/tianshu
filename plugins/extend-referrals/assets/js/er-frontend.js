(function ($) {
    'use strict';

    /**
     * Gửi AJAX set TTL cookie khi người dùng click quảng cáo.
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
     * Khi click quảng cáo
     */
    function handleAffiliateClick(link) {
        // Gửi TTL request song song
        setAffiliateTTL()
            .done((res) => {
                smoothReload();
            })
            .fail((xhr, status, error) => {
                smoothReload();
            });
    }

    /**
     * Reload mượt mà (giữ nguyên vị trí cuộn)
     */
    function smoothReload() {
        const scrollY = window.scrollY; // Lưu lại vị trí hiện tại

        // Reload nội dung (mềm hơn hard reload)
        window.location.replace(window.location.href);

        // Sau khi reload, browser tự giữ scroll (do dùng replace thay vì reload)
        // Tuy nhiên để chắc chắn hơn (Safari, Firefox cũ):
        setTimeout(() => window.scrollTo(0, scrollY), 300);
    }

    $(document).ready(function () {
        $(document).on('click', '[data-affiliate-click]', function () {
            const link = $(this).attr('href') || '';
            handleAffiliateClick(link);
        });
    });

})(jQuery);