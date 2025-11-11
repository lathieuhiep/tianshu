(function ($) {
    'use strict';

    /**
     * Gửi AJAX set TTL cookie khi người dùng click quảng cáo.
     * Thêm log chi tiết để debug.
     */
    function setAffiliateTTL() {
        console.log('[DEBUG] Sending AJAX to:', ExtendReferrals.ajax_url);

        return $.ajax({
            url: ExtendReferrals.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'extend_referrals_set_ttl',
                nonce: ExtendReferrals.nonce
            },
            success: function (res) {
                console.log('[DEBUG] AJAX success response:', res);
            },
            error: function (xhr, status, error) {
                console.error('[DEBUG] AJAX error:', status, error);
                console.error('[DEBUG] Response text:', xhr.responseText);
            }
        });
    }

    /**
     * Mở link affiliate
     */
    function openAffiliateLink(link) {
        console.log('[DEBUG] Opening link:', link);
        window.open(link, '_blank');
    }

    /**
     * Khi click quảng cáo
     */
    function handleAffiliateClick(e, link) {
        e.preventDefault();
        console.log('[DEBUG] Clicked link:', link);
        setAffiliateTTL()
            .done((res) => console.log('[DEBUG] TTL set done', res))
            .fail((xhr, status, error) => console.warn('[DEBUG] TTL set fail', status, error));
        openAffiliateLink(link);
    }

    $(document).ready(function () {
        $(document).on('click', '[data-affiliate-click]', function (e) {
            const link = $(this).attr('href') || '';
            handleAffiliateClick(e, link);
        });
    });

})(jQuery);