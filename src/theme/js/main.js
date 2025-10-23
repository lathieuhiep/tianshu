(function ($) {
    "use strict";

    $(document).ready(function () {
        const BREAKPOINT_LG = 992;
        const RESIZE_DELAY = 150;

        /**
         * Hàm Debounce: Giới hạn tần suất gọi hàm (chỉ gọi sau khi sự kiện dừng trong 1 khoảng thời gian)
         * @param {Function} func Hàm cần được Debounce
         * @param {number} delay Độ trễ tính bằng mili giây
         */
        const debounce = (func, delay) => {
            let timeoutId;
            return function(...args) {
                // Xóa timer trước (nếu có) để đặt lại thời gian chờ
                clearTimeout(timeoutId);
                // Thiết lập timer mới
                timeoutId = setTimeout(() => {
                    func.apply(this, args);
                }, delay);
            };
        };

        // mobile menu
        const windowWidth = $(window).width();
        const subMenuToggle = $('.sub-menu-toggle');

        if (subMenuToggle.length) {
            subMenuToggle.on('click', function () {
                if (windowWidth < 992) {
                    const $this = $(this);
                    const $parentMenuItem = $this.closest('.menu-item-has-children');

                    // Toggle class 'active'
                    $this.toggleClass('active');
                    $parentMenuItem.children('.sub-menu').slideToggle();

                    // Close all other submenus, including child levels
                    $parentMenuItem.siblings('.menu-item-has-children')
                        .find('.sub-menu-toggle').removeClass('active')
                        .end().find('.sub-menu').slideUp();

                    // Close all submenus within the current level
                    $parentMenuItem.find('.menu-item-has-children .sub-menu')
                        .slideUp()
                        .prev('.sub-menu-toggle').removeClass('active');
                }
            });
        }

        // close menu when click outside
        $(document).on('click', function (event) {
            const clickTarget = $(event.target);
            const primaryMenu = $("#primary-menu");

            if (!clickTarget.closest('#primary-menu, .sub-menu-toggle').length) {
                primaryMenu.collapse('hide');
                primaryMenu.find('.sub-menu').slideUp();
                primaryMenu.find('.sub-menu-toggle').removeClass('active');
            }
        });

        // loading
        $(window).on("load", function () {
            $('#site-loading').remove();
        });

        // scroll event
        let isScrolling;
        const SCROLL_THRESHOLD = 200;
        const $backToTop = $('#back-top');

        if ( $backToTop.length ) {
            // back to top
            $backToTop.on('click', function (e) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            $(window).on('scroll', function () {
                // Hủy yêu cầu rAF trước đó (kỹ thuật tương tự Debounce/Throttle nhưng tối ưu hơn)
                if (isScrolling) {
                    cancelAnimationFrame(isScrolling);
                }

                // Yêu cầu rAF mới
                isScrolling = requestAnimationFrame(function () {
                    // Lấy vị trí cuộn mới nhất
                    const currentScrollTop = $(window).scrollTop();

                    // Thực hiện logic cập nhật giao diện
                    if (currentScrollTop > SCROLL_THRESHOLD) {
                        $backToTop.addClass('active_top');
                    } else {
                        $backToTop.removeClass('active_top');
                    }

                    // Đặt lại isScrolling về null sau khi đã chạy xong
                    isScrolling = null;
                });
            });
        }

        if ($(window).scrollTop() > SCROLL_THRESHOLD) {
            $backToTop.addClass('active_top');
        }

        // close mobile menu on desktop resize
        const primaryMenuMobile = $('#primary-menu-mobile');
        const autoHideOffCanvas = () => {
            const currentWidth = $(window).width();

            if (primaryMenuMobile.length && primaryMenuMobile.hasClass('show') && currentWidth >= BREAKPOINT_LG) {
                primaryMenuMobile.offcanvas('hide');
                console.log('Offcanvas đã được tự động ẩn vì màn hình >= 992px');
            }
        };

        autoHideOffCanvas();
        $(window).on('resize', debounce(autoHideOffCanvas, RESIZE_DELAY));
    });
})(jQuery);