<?php
$opt_back_to_top = tianshu_get_option( 'opt_general_back_to_top', '1' );

if ( $opt_back_to_top != '1' ) return;
?>

<div id="back-top">
    <a href="#">
        <i class="ic-mask ic-mask-chevron-up"></i>
    </a>
</div>