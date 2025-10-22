<?php if( is_active_sidebar( 'sidebar-main' ) ): ?>

    <aside class="<?php echo esc_attr( tianshu_col_sidebar() ); ?> site-sidebar order-1">
        <?php dynamic_sidebar( 'sidebar-main' ); ?>
    </aside>

<?php endif; ?>