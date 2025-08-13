<?php
/**
 * Plugin Name: Gutenberg Content Importer
 * Plugin URI: https://github.com/automattic/gutenberg-content-importer
 * Description: Universal content importer that converts content from Medium, Notion, and Google Docs into perfectly structured Gutenberg blocks.
 * Version: 1.0.0
 * Author: Automattic
 * Author URI: https://automattic.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gutenberg-content-importer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GCI_VERSION', '1.0.0');
define('GCI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GCI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GCI_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
require_once GCI_PLUGIN_DIR . 'includes/class-autoloader.php';
GCI\Autoloader::register();

// Initialize the plugin
add_action('plugins_loaded', function() {
    $plugin = new GCI\Core\Plugin();
    $plugin->init();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    GCI\Core\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    GCI\Core\Deactivator::deactivate();
});

// Register background import action
add_action('gci_process_import_background', ['GCI\Core\Plugin', 'process_import_background'], 10, 4); 