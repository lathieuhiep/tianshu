<?php
$sticky_menu = tianshu_get_option( 'opt_menu_sticky', '1' );
?>
<header class="main-header <?php echo esc_attr( $sticky_menu == '1' ? 'active-sticky-nav' : '' ); ?>">
    <nav class="main-header__warp container">
        <!-- main logo -->
        <?php get_template_part('components/header/logo'); ?>

        <!-- Main menu -->
        <?php get_template_part('components/header/nav'); ?>

        <!-- Main shopping cart -->
        <?php get_template_part('components/header/inc', 'shopping-cart'); ?>
    </nav>
</header>