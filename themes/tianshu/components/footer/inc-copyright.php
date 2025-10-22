<?php
$show_copyright = tianshu_get_option('opt_footer_copyright_show', '1');
$copyright = tianshu_get_option('opt_footer_copyright_content', 'Copyright &copy; DiepLK');

if ( $show_copyright == '1' ) :
?>
    <div class="footer__bottom text-center">
        <div class="container">
            <div class="copyright">
                <?php echo wpautop( $copyright ); ?>
            </div>
        </div>
    </div>
<?php endif; ?>