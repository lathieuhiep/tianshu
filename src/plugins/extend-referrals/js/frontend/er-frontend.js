(function ($) {
    'use strict';

    /**
     * AJAX: Set TTL (count click)
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
     * OPEN LINK (Android / iOS / Desktop)
     */
    function openAffiliateLink(link) {
        const ua = navigator.userAgent.toLowerCase();

        // ANDROID
        if (ua.includes('android')) {
            // Intent mở Chrome → thoát WebView Messenger, Zalo, TikTok
            const intentLink =
                `intent://${link.replace(/^https?:\/\//, '')}` +
                `#Intent;scheme=https;package=com.android.chrome;end`;

            window.location.href = intentLink;
            return;
        }

        // IOS
        if (/iphone|ipad|ipod/.test(ua)) {
            // Mở Chrome iOS
            const chromeURL = `googlechrome://${link.replace(/^https?:\/\//, '')}`;
            window.location.href = chromeURL;

            // Fallback Safari
            setTimeout(() => {
                window.location.href = link;
            }, 300);

            return;
        }

        // DESKTOP
        window.open(link, '_blank', 'noopener,noreferrer');
    }

    /**
     * MOBILE POPUP + AUTO OPEN
     */
    function showAffiliatePopup(link) {
        const $popup = $('#er-aff-popup');

        $popup.fadeIn(150);

        // Auto-open Shopee
        const openTimer = setTimeout(() => {
            openAffiliateLink(link);
        }, 200);

        // Auto-close popup
        const closeTimer = setTimeout(() => {
            $popup.fadeOut(250);
        }, 1000);

        // Manual close
        $popup.find('.er-aff-popup__close')
            .off('click')
            .on('click', function () {
                clearTimeout(openTimer);
                clearTimeout(closeTimer);
                $popup.fadeOut(250);
            });
    }

    /**
     * CLICK AFFILIATE FINAL FLOW
     */
    $(document).on('click', '[data-affiliate-click]', function (e) {
        e.preventDefault();

        const link = $(this).attr('href');
        const ua = navigator.userAgent.toLowerCase();
        const isMobile = /android|iphone|ipad|ipod/.test(ua);

        // Chống double click mở 2 lần
        if ($(this).data('aff-opening')) return;
        $(this).data('aff-opening', true);

        // 1) Gửi TTL (count click)
        setAffiliateTTL().always(() => {

            // LocalStorage recording
            localStorage.setItem('er_aff_clicked', '1');

            // Remove ad/banner
            $('.er-partner-info').remove();

            // Unlock content
            $('#er-partner-content-wrapper').removeClass('er-locked');

            // 2) DESKTOP → mở thẳng tab mới (không popup)
            if (!isMobile) {
                window.open(link, '_blank', 'noopener,noreferrer');
                return;
            }

            // 3) MOBILE → popup + auto-open
            showAffiliatePopup(link);
        });
    });

})(jQuery);
