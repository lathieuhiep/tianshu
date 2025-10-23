<?php
$sticky_menu = tianshu_get_option( 'opt_menu_sticky', '1' );
?>
<header class="main-header <?php echo esc_attr( $sticky_menu == '1' ? 'active-sticky-nav' : '' ); ?>">
    <div class="main-header__top">
        <div class="container">
            <div class="d-flex gap-4">
                <div class="canvas-action d-flex align-items-center justify-content-center d-lg-none">
                    <button class="navbar-toggler d-flex align-items-center justify-content-center"
                            type="button"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#primary-menu-mobile"
                            aria-controls="primary-menu-mobile"
                            aria-label="Open navigation"
                    >
                        <i class="ic-mask ic-mask-bars"></i>
                    </button>
                </div>

                <?php get_template_part('components/header/logo'); ?>

                <div class="search-bar d-flex align-items-center justify-content-end justify-content-lg-start flex-fill">
                    <div class="d-none d-lg-block">
                        <?php get_template_part('components/inc', 'search-form-story'); ?>
                    </div>

                    <div class="d-lg-none">
                        <button class="btn btn-modal-search p-0 border-0 d-flex align-items-center me-2"
                                data-bs-toggle="modal"
                                data-bs-target="#searchModal">
                            <i class="ic-mask ic-mask-magnifying-glass"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex gap-3 align-items-center">
                    <a href="#" class="btn btn-primary btn-login rounded-2"><?php esc_html_e('Đăng nhập', 'tianshu'); ?></a>
                    <a href="#" class="btn btn-primary btn-register rounded-2"><?php esc_html_e('Đăng ký', 'tianshu'); ?></a>
                </div>
            </div>
        </div>
    </div>

    <nav class="main-header__warp d-none d-lg-flex">
        <div class="container">
            <?php get_template_part('components/header/nav'); ?>
        </div>
    </nav>
</header>

<!--off canvas menu mobile-->
<?php get_template_part('components/header/menu-mobile'); ?>

<!--modal search mobile-->
<div class="modal modal-search-mobile fade" id="searchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header mb-8 pb-4">
                <h5 class="modal-title">
                    <?php esc_html_e('Tìm kiếm truyện', 'tianshu'); ?>
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <?php get_template_part('components/inc', 'search-form-story'); ?>
            </div>
        </div>
    </div>
</div>
