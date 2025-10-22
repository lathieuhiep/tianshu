<?php
// get the footer sidebar
$opt_number_columns = tianshu_get_option('opt_footer_columns', '4');

// check sidebar active
$has_footer_sidebar = false;
for ( $i = 1; $i <= 4; $i++ ) {
    if ( is_active_sidebar( BASICTHEME_PREFIX_SIDEBAR_FOOTER_COLUMN . $i ) ) {
        $has_footer_sidebar = true;
        break;
    }
}

if ( $has_footer_sidebar ) :
?>
    <div class="footer__column">
        <div class="container">
            <div class="row row-gap-6">
	            <?php
	            for ( $i = 0; $i < $opt_number_columns; $i++ ) :
		            $j = $i + 1;
		            $cols = tianshu_get_option( 'opt_footer_column_width_' .  $j );

                    if ( empty( $cols ) ) {
                        $cols = [
                            'sm' => 12,
                            'md' => 6,
                            'lg' => 3,
                            'xl' => 3
                        ];
                    }

                    $classes = sprintf(
                        'col-12 col-sm-%s col-md-%s col-lg-%s col-xl-%s',
                        $cols['sm'],
                        $cols['md'],
                        $cols['lg'],
                        $cols['xl']
                    );

		            if ( is_active_sidebar( BASICTHEME_PREFIX_SIDEBAR_FOOTER_COLUMN . $j ) ):
                ?>
                    <div class="<?php echo esc_attr( $classes ); ?>">
                        <?php dynamic_sidebar( BASICTHEME_PREFIX_SIDEBAR_FOOTER_COLUMN . $j ); ?>
                    </div>
                <?php
		            endif;
	            endfor;
	            ?>
            </div>
        </div>
    </div>
<?php
endif;