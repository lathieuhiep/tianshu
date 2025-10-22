(function ($) {
    // callback function to chain multiple event handlers
    const chainCallbacks = (existing, added) => {
        if (!existing) return added;

        return function (...args) {
            existing.apply(this, args);
            added.apply(this, args);
        };
    }

    // merge the default options with the user defined options
    const swiperOptions = (options, $slider) => {
        let defaults = {
            loop: true,
            speed: 800,
            autoplay: false,
            navigation: false,
            pagination: false,
            breakpoints: {},
            observer: true,
            observeParents: true,
            watchOverflow: true,
            on: {}
        };

        // Merge options
        let config = $.extend({}, defaults, options);

        // check pagination
        if (config.pagination) {
            if ($slider.find('.swiper-pagination').length) {
                config.pagination = {
                    el: $slider.find('.swiper-pagination')[0],
                    clickable: true
                };
            } else {
                config.pagination = false;
            }
        }

        // check navigation
        if (config.navigation) {
            if ($slider.find('.swiper-button-next').length && $slider.find('.swiper-button-prev').length) {
                config.navigation = {
                    nextEl: $slider.find('.swiper-button-next')[0],
                    prevEl: $slider.find('.swiper-button-prev')[0]
                };
            } else {
                config.navigation = false;
            }
        }

        // check autoplay
        if (config.autoplay === true) {
            config.autoplay = { delay: 4000, disableOnInteraction: false };
        }

        return config;
    }

    // Initialize Swiper sliders
    const InitSwiperSliders = ($scope) => {
        let $slider = $scope.find('.es-custom-swiper-slider');

        if ( $slider.length && !$slider.hasClass('swiper-initialized') ) {
            // Check if Swiper is loaded
            $slider.addClass('is-initializing');

            // Get options from data attribute
            let options = $slider.data('settings-swiper');
            let config = swiperOptions(options, $slider);

            // check equal height
            if ($slider.hasClass('es-equal-height')) {
                const equalize = () => {
                    const $slides = $slider.find('.swiper-slide');
                    $slides.css({ height: '', minHeight: '' });

                    let maxHeight = 0;
                    $slides.each(function () {
                        maxHeight = Math.max(maxHeight, $(this).outerHeight());
                    });

                    $slides.css('min-height', maxHeight);
                };

                config.on = config.on || {};
                config.on.imagesReady = chainCallbacks(config.on.imagesReady, equalize);
                config.on.resize = chainCallbacks(config.on.resize, equalize);
            }

            // remove class to mark as initializing
            config.on = config.on || {};
            config.on.init = chainCallbacks(config.on.init, () => {
                $slider.removeClass('is-initializing');
            });

            // create new Swiper instance
            new Swiper($slider.get(0), config);

            // Add class to mark as initialized
            $slider.addClass('swiper-initialized');
        }
    }

    $(window).on('elementor/frontend/init', function () {
        /* Element slider */
        elementorFrontend.hooks.addAction('frontend/element_ready/es-slides.default', InitSwiperSliders);

        // post carousel slider
        elementorFrontend.hooks.addAction('frontend/element_ready/es-post-carousel.default', InitSwiperSliders);

        // testimonial slider
        elementorFrontend.hooks.addAction('frontend/element_ready/es-testimonial-slider.default', InitSwiperSliders);

        // image carousel slider
        elementorFrontend.hooks.addAction('frontend/element_ready/es-carousel-images.default', InitSwiperSliders);
    });

})(jQuery);