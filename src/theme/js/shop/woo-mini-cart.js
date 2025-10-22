(function ($) {
    "use strict";

    // event remove product mini cart
    $(document).on('click', '.remove-custom-mini-cart', function (e) {
        const btn = $(this);

        if (btn.closest('.item').find('.block-ui-spinner').length === 0) {
            // Thêm spinner phủ lên sản phẩm đang xóa
            btn.closest('.item').append('<div class="block-ui-spinner"></div>');
        }

        btn.addClass('is-loading').prop('disabled', true);
    });

})(jQuery);