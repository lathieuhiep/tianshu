jQuery(function ($) {
    const list = $('#affiliate-ads-list');
    const addBtn = $('.add-ad');
    const template = $('#affiliate-ad-template').html();

    addBtn.on('click', function () {
        const index = list.find('.ad-item').length;
        const newItem = template.replace(/__INDEX__/g, index);
        list.append(newItem);
    });

    list.on('click', '.remove-ad', function () {
        const ad = $(this).closest('.ad-item');
        ad.fadeOut(200, function () { ad.remove(); });
    });
});
