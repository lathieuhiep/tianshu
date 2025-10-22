<?php
namespace ExtendSite\PostType;

defined('ABSPATH') || exit;

class TemplateLoader
{
    /**
     * Initialize the template loader by hooking into the 'template_include' filter.
     */
    public static function boot(): void {
        add_filter('template_include', [__CLASS__, 'pick'], 99);
    }

    /**
     * Generate a list of template candidates based on the basename.
     *
     * @param string $basename The base name of the template file.
     * @return array An array of candidate template paths.
     */
    private static function candidates(string $basename): array {
        return [
            'extend-site/' . $basename,
            $basename,
        ];
    }

    /**
     * Locate the template in the active theme or child theme.
     *
     * @param string $basename The base name of the template file.
     * @return string The path to the located template file, or an empty string if not found.
     */
    private static function locate_in_theme(string $basename): string {
        $t = locate_template(self::candidates($basename));
        return $t ?: '';
    }

    /**
     * Get the path to the plugin template file.
     *
     * @param string $basename The base name of the template file.
     * @return string The full path to the plugin template file.
     */
    private static function plugin_template(string $basename): string {
        return EXTEND_SITE_PATH . 'templates/' . $basename;
    }

    /**
     * Pick the appropriate template based on the current context.
     *
     * @param string $template The default template to use if no specific template is found.
     * @return string The path to the selected template file.
     */
    public static function pick(string $template): string {
        $templates = BasePostType::get_templates();

        // Kiểm tra post type và template tương ứng
        foreach ($templates as $slug => $template_files) {
            // single post type
            if (is_singular($slug) && isset($template_files['single'])) {
                return self::locate_in_theme($template_files['single'])
                    ?: self::plugin_template($template_files['single']);
            }

            // archive post type
            if (is_post_type_archive($slug) && isset($template_files['archive'])) {
                return self::locate_in_theme($template_files['archive'])
                    ?: self::plugin_template($template_files['archive']);
            }

            // taxonomy
            if (is_tax($slug) && isset($template_files['taxonomy'])) {
                return self::locate_in_theme($template_files['taxonomy'])
                    ?: self::plugin_template($template_files['taxonomy']);
            }
        }

        return $template;
    }
}