<?php $tianshu_unique_id = esc_attr(uniqid('search-form-')); ?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <input type="search"
           id="<?php echo $tianshu_unique_id; ?>"
           class="search-field"
           placeholder="<?php esc_attr_e('Tìm kiếm...', 'tianshu'); ?>"
           value="<?php echo get_search_query(); ?>" name="s" aria-label="" />

    <button type="submit" class="btn search-submit">
        <i class="ic-mask ic-mask-magnifying-glass"></i>
    </button>
</form>