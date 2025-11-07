<?php
namespace ExtendSite\Services\Tools;

defined('ABSPATH') || exit;

class ToolManager {
    public static function run_tool(string $tool_class): ?array
    {
        if (!class_exists($tool_class)) {
            error_log('[TOOLMANAGER] class not found: ' . $tool_class);
            return ['message' => 'Tool class not found'];
        }

        if (!is_subclass_of($tool_class, ToolInterface::class)) {
            error_log('[TOOLMANAGER] not a valid tool: ' . $tool_class);
            return ['message' => 'Invalid tool'];
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TOOLMANAGER] running ' . $tool_class);
        }

        return $tool_class::run();
    }
}