<?php
namespace ExtendSite\Core;

defined('ABSPATH') || exit;

final class Autoloader {
    public static function register(): void {
        $base = rtrim(EXTEND_SITE_PATH, '/\\') . '/includes/';
        spl_autoload_register(static function (string $class) use ($base) {
            $prefix = 'ExtendSite\\';
            if (!str_starts_with($class, $prefix)) return;
            $rel  = substr($class, strlen($prefix));
            $file = $base . str_replace('\\', '/', $rel) . '.php';

            if (is_file($file)) {
                require_once $file;
            } else {
                error_log("[Autoloader] Missing file for class: $class | Expected: $file");
            }
        });
    }
}