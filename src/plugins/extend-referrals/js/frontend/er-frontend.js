(function ($) {
    'use strict';

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

    function getDevicePlatform() {
        const ua = navigator.userAgent.toLowerCase();
        if (/android/.test(ua)) return 'android';
        if (/iphone|ipad|ipod/.test(ua)) return 'ios';
        return 'desktop';
    }

    function smoothReload() {
        const scrollY = window.scrollY;
        window.location.replace(window.location.href);
        setTimeout(() => window.scrollTo(0, scrollY), 300);
    }

    function handleAffiliateClick(link) {
        const platform = getDevicePlatform();

        // Mở link ngay
        if (link) {
            window.open(link, '_blank', 'noopener,noreferrer');
        }

        // Gửi TTL song song + đánh dấu localStorage
        setAffiliateTTL().always(() => {
            localStorage.setItem('er_aff_clicked', '1');
            if (platform === 'desktop') {
                setTimeout(() => smoothReload(), 800);
            }
        });
    }

    // Khi người dùng quay lại tab (từ app/store)
    window.addEventListener('focus', function () {
        if (localStorage.getItem('er_aff_clicked') === '1') {
            localStorage.removeItem('er_aff_clicked');
            smoothReload();
        }
    });

    $(document).ready(function () {
        $(document).on('click', '[data-affiliate-click]', function () {
            const link = $(this).attr('href') || '';
            handleAffiliateClick(link);
        });
    });

})(jQuery);