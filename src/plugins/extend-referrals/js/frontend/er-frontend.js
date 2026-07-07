(function ($) {
    'use strict';

    const TTL_COOKIE = "extend_affiliate_ads_ttl";
    const LEGACY_TTL_COOKIE = "extend_referrals_ttl";

    function getTTL() {
        const match = document.cookie.match(new RegExp(TTL_COOKIE + '=(\\d+)'));
        if (match) {
            return parseInt(match[1], 10);
        }

        const legacyMatch = document.cookie.match(new RegExp(LEGACY_TTL_COOKIE + '=(\\d+)'));
        return legacyMatch ? parseInt(legacyMatch[1], 10) : 0;
    }

    function setTTL(ttlMinutes) {
        const ttlSeconds = ttlMinutes * 60;
        const expire = Math.floor(Date.now() / 1000) + ttlSeconds;

        document.cookie =
            TTL_COOKIE + "=" + expire +
            "; path=/; max-age=" + ttlSeconds +
            "; samesite=Lax";
    }

    function isTTLExpired(ttlMinutes) {
        const saved = getTTL();
        if (!saved) return true;

        return saved < Math.floor(Date.now() / 1000);
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

        $(document).trigger('extend_referrals_unlocked');
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
