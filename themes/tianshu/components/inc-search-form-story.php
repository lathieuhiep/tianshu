<form role="search" method="get" class="search-form-story" action="<?php echo esc_url(home_url('/')); ?>">
    <input type="hidden" name="post_type" value="story">

    <input type="search"
           name="s"
           class="search-field"
           placeholder="<?php esc_attr_e('Tìm truyện', 'tianshu'); ?>"
           aria-label=""
    >

    <button type="submit" class="btn btn-search p-0 border-0 d-flex align-items-center">
        <i class="ic-mask ic-mask-magnifying-glass"></i>
    </button>
</form>