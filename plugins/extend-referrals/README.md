By default, Extend Referrals displays ads only from Chapter 2 onward
(for compatibility with the ExtendSite Story plugin).

If your site uses a different post type (e.g., story_chapter, post, or product),
you can override the display condition via the filter
extend_referrals_should_display.

```php
/**
 * Example: Customize display rules for your site
 */
add_filter('extend_referrals_should_display', function ($show, $post) {

    // Example 1: A different chapter CPT using custom meta
    if ($post->post_type === 'story_chapter') {
        $chapter_no = (int) get_post_meta($post->ID, '_chap_no', true);
        return $chapter_no >= 3; // show ads from chapter 3 onward
    }

    // Example 2: Normal blog posts with tag "affiliate"
    if ($post->post_type === 'post' && has_tag('affiliate', $post)) {
        return true;
    }

    // Example 3: WooCommerce products on sale
    if ($post->post_type === 'product' && has_term('sale', 'product_cat', $post)) {
        return true;
    }

    // Otherwise, use default rule
    return $show;
}, 10, 2);
  
* Tip:
  If your post type doesn’t have _chapter_number or any special meta,
  you can always return true to display ads globally.

----------------------------------------------------------------------------

🧩 Integrating with View Counters

If your site or theme tracks post or chapter views,
make sure to only count views when the content is actually unlocked
(i.e. when the user has clicked an affiliate ad and the TTL timer is still valid).

This prevents “fake views” from being counted when the ad is displayed but the user hasn’t opened the content yet.

use ExtendReferrals\Core\TTLManager;

/**
 * Count view only if TTL (unlock timer) is active.
 * 
 * Example usage in your theme or custom plugin.
 */
function my_count_view($post_id) {
    // If content still locked → do NOT count view
    if (TTLManager::is_expired()) {
        return;
    }

    $count = (int) get_post_meta($post_id, '_view_count', true);
    update_post_meta($post_id, '_view_count', $count + 1);
}

