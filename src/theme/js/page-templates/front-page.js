(function ($) {
    "use strict";

    $(document).ready(function () {
        $('[data-home-featured-slider], [data-home-hot-slider]').each(function () {
            if (typeof window.Swiper === 'undefined') {
                return;
            }

            const slider = this;
            const $slider = $(slider);
            const options = $slider.data('settings-swiper') || {};
            const config = $.extend({
                loop: true,
                speed: 800,
                autoplay: false,
                navigation: false,
                pagination: false,
                observer: true,
                observeParents: true,
                watchOverflow: true
            }, options);

            if (config.autoplay === true) {
                config.autoplay = {delay: 4000, disableOnInteraction: false};
            }

            if (config.pagination && $slider.find('.swiper-pagination').length) {
                config.pagination = {
                    el: $slider.find('.swiper-pagination')[0],
                    clickable: true
                };
            }

            if (config.navigation) {
                config.navigation = {
                    nextEl: $slider.find('.swiper-button-next')[0],
                    prevEl: $slider.find('.swiper-button-prev')[0]
                };
            }

            new window.Swiper(slider, config);
        });

        $('[data-home-latest-stories]').each(function () {
            if (typeof window.tianshuFrontPage === 'undefined') {
                return;
            }

            const $container = $(this);
            const $grid = $container.find('.home-latest-stories__grid');
            const $button = $container.find('.home-latest-stories__load-more');
            const $loading = $container.find('.home-latest-stories__loading');
            const config = $container.data('config') || {};
            let page = 1;
            let isLoading = false;

            const createStoryCard = function (item) {
                const title = item.title || '';
                const $article = $('<article>', {class: 'home-latest-story'});
                const $media = $('<div>', {class: 'home-latest-story__media'});
                const $thumbnail = $('<a>', {
                    class: 'home-latest-story__thumbnail',
                    href: item.url || '#',
                    title: title
                });

                $('<img>', {
                    src: item.image_url || config.fallback_image || '',
                    alt: item.image_alt || title
                }).appendTo($thumbnail);

                const $views = $('<div>', {class: 'home-latest-story__views'});
                $('<i>', {
                    class: 'es-ic-mask es-ic-mask-eye',
                    'aria-hidden': 'true'
                }).appendTo($views);
                $('<span>').text(item.views || '0').appendTo($views);
                $media.append($thumbnail, $views);

                const $detail = $('<div>', {class: 'home-latest-story__detail'});
                const $title = $('<h3>', {class: 'home-latest-story__title'});
                $('<a>', {href: item.url || '#'}).text(title).appendTo($title);

                const $info = $('<div>', {class: 'home-latest-story__info'});
                if (item.chapter && item.chapter.url) {
                    const chapterLabel = window.tianshuFrontPage.chapterPrefix
                        + ' ' + (parseInt(item.chapter.number, 10) || 0)
                        + ': ' + (item.chapter.title || '');

                    $('<a>', {
                        class: 'home-latest-story__chapter',
                        href: item.chapter.url
                    }).text(chapterLabel).appendTo($info);
                }

                if (item.modified_human) {
                    $('<time>', {datetime: item.modified_iso || ''})
                        .text(item.modified_human)
                        .appendTo($info);
                }

                $detail.append($title, $info);
                return $article.append($media, $detail);
            };

            $button.on('click', function () {
                if (isLoading) {
                    return;
                }

                isLoading = true;
                $loading.prop('hidden', false);
                $button.prop('disabled', true);

                $.ajax({
                    url: window.tianshuFrontPage.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: window.tianshuFrontPage.latestStoriesAction,
                        security: window.tianshuFrontPage.nonce,
                        page: page + 1,
                        limit: config.limit || 12,
                        image_size: config.image_size || 'large'
                    }
                }).done(function (response) {
                    const items = response && response.success && response.data
                        ? response.data.items || []
                        : [];

                    items.forEach(function (item) {
                        $grid.append(createStoryCard(item));
                    });

                    if (items.length) {
                        page += 1;
                    }

                    if (!items.length || !response.data.next) {
                        $button.hide();
                    }
                }).fail(function () {
                    window.alert(window.tianshuFrontPage.errorMessage);
                }).always(function () {
                    $loading.prop('hidden', true);
                    $button.prop('disabled', false);
                    isLoading = false;
                });
            });
        });

        $('[data-home-taxonomy-list]').each(function () {
            const $section = $(this);
            const $toggle = $section.find('.home-story-taxonomies__toggle');
            const $list = $section.find('.home-story-taxonomies__list');

            $toggle.on('click', function () {
                const isExpanded = $toggle.attr('aria-expanded') === 'true';
                $toggle.attr('aria-expanded', isExpanded ? 'false' : 'true');
                $list.stop(true, true).slideToggle(200);
            });
        });
    });
})(jQuery);
