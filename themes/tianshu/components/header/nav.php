<div id="primary-menu" class="primary-menu collapse navbar-collapse d-lg-block h-100">
    <?php
    if ( has_nav_menu( 'primary' ) ) :
        wp_nav_menu( array(
            'theme_location' => 'primary',
            'menu_class' => 'd-lg-flex',
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