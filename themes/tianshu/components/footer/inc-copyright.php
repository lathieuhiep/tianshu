<?php
$show_copyright = tianshu_get_option('opt_footer_copyright_show', '1');
$copyright = tianshu_get_option('opt_footer_copyright_content', 'Copyright &copy; DiepLK');

if ( has_nav_menu('footer') || $show_copyright == '1' ) :
?>
    <div class="footer__bottom text-center">
        <div class="container d-flex flex-column gap-1">
            <?php if ( has_nav_menu('footer') ) : ?>
            <div class="menu-footer">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'footer',
                    'menu_class' => 'd-flex list-style-none align-items-center justify-content-center flex-wrap gap-3',
                    'container' => false,
                ) );
                ?>
            </div>
            <?php endif; ?>

            <div class="copyright">
                <?php echo wpautop( $copyright ); ?>
            </div>
        </div>
    </div>
<?php endif; ?>