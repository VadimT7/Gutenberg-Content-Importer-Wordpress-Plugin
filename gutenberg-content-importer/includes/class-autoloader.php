<?php
/**
 * PSR-4 Autoloader for Gutenberg Content Importer
 *
 * @package GCI
 */

namespace GCI;

class Autoloader {
    /**
     * Plugin namespace prefix
     */
    const PREFIX = 'GCI\\';

    /**
     * Register the autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload classes
     *
     * @param string $class Class name
     */
    public static function autoload($class) {
        // Check if class uses our namespace
        if (strpos($class, self::PREFIX) !== 0) {
            return;
        }

        // Remove namespace prefix
        $class = str_replace(self::PREFIX, '', $class);

        // Convert to file path
        $path = strtolower(str_replace('\\', '/', $class));
        $path = str_replace('_', '-', $path);

        // Build file path
        $file = GCI_PLUGIN_DIR . 'includes/' . $path . '.php';

        // Require file if it exists
        if (file_exists($file)) {
            require_once $file;
        }
    }
} 