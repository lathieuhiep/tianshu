(function ($) {
    "use strict";

    // event qty
    const updateQty = (input, delta) => {
        let current = parseFloat(input.val());
        const min = parseFloat(input.attr('min')) || 1;
        const max = parseFloat(input.attr('max')) || Infinity;
        const step = parseFloat(input.attr('step')) || 1;

        let newVal;

        if (isNaN(current)) {
            newVal = 1;
        } else {
            newVal = current + (delta * step);
        }

        if (newVal < min) newVal = min;
        if (newVal > max) newVal = max;

        input.val(newVal).trigger('change');
    };

    $(document).on('click', '.qty-minus', function() {
        const input = $(this).siblings('input.qty');
        updateQty(input, -1);
    });

    $(document).on('click', '.qty-plus', function() {
        const input = $(this).siblings('input.qty');
        updateQty(input, 1);
    });

})(jQuery);