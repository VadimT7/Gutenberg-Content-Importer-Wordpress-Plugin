<?php
/**
 * Plugin Deactivation Handler
 *
 * @package GCI\Core
 */

namespace GCI\Core;

class Deactivator {
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook('gci_process_import_queue');
        wp_clear_scheduled_hook('gci_cleanup_old_imports');
    }
} 