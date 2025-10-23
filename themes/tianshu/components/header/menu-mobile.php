<!-- OffCanvas mobile: chỉ hiện -->
<div class="offcanvas offcanvas-start d-lg-none primary-menu-mobile" tabindex="-1" id="primary-menu-mobile" aria-labelledby="primary-menu-mobile-label">
    <div class="offcanvas-header">
        <?php get_template_part('components/header/logo'); ?>

        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        <?php
        if ( has_nav_menu( 'primary' ) ) :
            wp_nav_menu( array(
                'theme_location' => 'primary',
                'menu_class' => 'nav flex-column',
                'container' => false,
            ) );
        else:
            ?>
            <ul class="main-menu">
                <li>
                    <a href="<?php echo get_admin_url() . '/nav-menus.php'; ?>">
                        <?php esc_html_e( 'Thêm Menu', 'tianshu' ); ?>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </div>
</div>