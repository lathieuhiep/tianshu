(function ($) {
    'use strict';

    /**
     * Gửi TTL bằng navigator.sendBeacon
     */
    function sendAffiliateTTL() {

        // Endpoint AJAX
        const url = ExtendReferrals.ajax_url;

        // FormData để gửi kèm action & nonce
        const data = new FormData();
        data.append('action', 'extend_referrals_set_ttl');
        data.append('nonce', ExtendReferrals.nonce);

        // Gửi ngay lập tức trước khi rời trang
        navigator.sendBeacon(url, data);
    }


    /**
     * Xử lý khi click quảng cáo
     */
    $(document).on('click', '[data-affiliate-click]', function () {

        // Chống double click
        if ($(this).data('aff-opening')) return;
        $(this).data('aff-opening', true);

        // Lưu trạng thái click ở client
        localStorage.setItem('er_aff_clicked', '1');

        // Gửi TTL bằng sendBeacon (an toàn khi unload trang)
        sendAffiliateTTL();

        // Xóa banner quảng cáo
        $('.er-partner-info').remove();

        // Mở khóa nội dung
        $('#er-partner-content-wrapper').removeClass('er-locked');
    });

})(jQuery);