<?php
namespace ExtendReferrals\Core;

defined('ABSPATH') || exit;

class Helpers
{
    /**
     * Get all public post types with their singular labels.
     *
     * @return array<string, string> Associative array of post type names and their singular labels.
     */
    public static function get_all_post_types(): array
    {
        $args = [
            'public'   => true,
        ];

        $post_types = get_post_types($args, 'objects');

        // Exclude specific post types
        $excluded = ['attachment', 'elementor_library', 'e-floating-buttons'];
        $post_types = array_diff_key($post_types, array_flip($excluded));

        $result = [];
        foreach ($post_types as $key => $post_type) {
            $result[$post_type->name] = $post_type->labels->singular_name;
        }

        return $result;
    }
}