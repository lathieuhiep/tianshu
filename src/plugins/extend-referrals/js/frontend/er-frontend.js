(function ($) {
    'use strict';

    const TTL_COOKIE = "extend_referrals_ttl";

    function getTTL() {
        const match = document.cookie.match(/extend_referrals_ttl=(\d+)/);
        return match ? parseInt(match[1], 10) : 0;
    }

    function setTTL(ttlMinutes) {
        const ttlSeconds = ttlMinutes * 60;
        const now = Date.now();

        document.cookie =
            TTL_COOKIE + "=" + now +
            "; path=/; max-age=" + ttlSeconds +
            "; samesite=Lax";
    }

    function isTTLExpired(ttlMinutes) {
        const saved = getTTL();
        if (!saved) return true;

        const ttlMs = ttlMinutes * 60 * 1000;
        return (Date.now() - saved) > ttlMs;
    }

    /**
     * CLICK: set TTL + switch UI
     */
    $(document).on('click', '[data-affiliate-click]', function () {

        if ($(this).data('aff-opening')) return;
        $(this).data('aff-opening', true);

        const $box = $('.er-partner-info');
        const $content = $('#er-partner-content-wrapper');
        const ttl = parseInt($box.data('ttl'), 10) || 10;

        setTTL(ttl);

        // Ẩn quảng cáo bằng hidden
        $box.prop('hidden', true);

        // Hiện nội dung
        $content.prop('hidden', false);
    });

    /**
     * LOAD TRANG → kiểm tra TTL và ẩn/hiện
     */
    $(document).ready(function () {

        const $box = $('.er-partner-info');
        const $content = $('#er-partner-content-wrapper');

        if (!$box.length || !$content.length) return;

        const ttl = parseInt($box.data('ttl'), 10) || 10;

        if (isTTLExpired(ttl)) {
            // TTL hết → show quảng cáo, hide content
            $box.prop('hidden', false);
            $content.prop('hidden', true);
        } else {
            // TTL còn → ẩn quảng cáo, hiện content
            $box.prop('hidden', true);
            $content.prop('hidden', false);
        }
    });

})(jQuery);